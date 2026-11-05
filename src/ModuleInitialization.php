<?php
/**
 * Auto-initialize all Module based classes in the plugin.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFramework;

use ReflectionClass;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;
use TenupFramework\Cache\ReadOnlyFileDiscoverCacheDriver;

/**
 * ModuleInitialization class.
 *
 * @package TenupFramework
 */
class ModuleInitialization {

	/**
	 * The directory name, within the discovery directory, that holds the class cache.
	 *
	 * @var string
	 */
	public const CACHE_DIR_NAME = 'class-loader-cache';

	/**
	 * The filename of the cache `tenup-framework-generate-class-cache` builds.
	 *
	 * Matches the name 2.0.0 reads, so a build step added for 1.x keeps working
	 * after the upgrade. It differs from the runtime cache's default filename, so
	 * the two never collide.
	 *
	 * @var string
	 */
	public const CACHE_FILENAME = 'class-loader-cache-v2.php';

	/**
	 * The Spatie cache identifier of the prebuilt cache.
	 *
	 * @var string
	 */
	public const CACHE_ID = 'TenupFramework';

	/**
	 * The class instance.
	 *
	 * @var null|ModuleInitialization
	 */
	private static $instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @return ModuleInitialization
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Override the constructor, we don't want to init it that way.
	 */
	private function __construct() {
		// no-op. This class is a singleton.
	}

	/**
	 * The list of initialized classes.
	 *
	 * @var array<ModuleInterface>
	 */
	protected $classes = [];

	/**
	 * Get all the TenupFramework plugin classes.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return array<string>
	 */
	public function get_classes( $dir ) {
		$this->directory_check( $dir );

		$prebuilt = $this->get_prebuilt_classes( $dir );

		if ( null !== $prebuilt ) {
			return $prebuilt;
		}

		$class_finder = $this->build_discoverer( $dir );

		// If we are in production or staging, cache the class loader to improve performance.
		if ( $this->should_use_cache() ) {
			$class_finder->withCache(
				__NAMESPACE__,
				new FileDiscoverCacheDriver( $dir . '/class-loader-cache' )
			);
		}

		$classes = array_filter( $class_finder->get(), fn( $cl ) => is_string( $cl ) );

		// Return the classes
		return $classes;
	}

	/**
	 * Read the class list from a cache built by `tenup-framework-generate-class-cache`.
	 *
	 * Read on any host, WordPress VIP included, which is where it matters most: the
	 * runtime cache can't be written there, so without a prebuilt one every request
	 * tokenizes every PHP file in the directory. The file is never written at
	 * runtime. A missing cache, or one that fails to load (a truncated deploy, say),
	 * returns null and discovery carries on exactly as it would without it.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return array<string>|null The cached class names, or null to discover instead.
	 */
	protected function get_prebuilt_classes( $dir ): ?array {
		if ( $this->cache_disabled() || ! file_exists( $this->get_cache_directory( $dir ) . '/' . self::CACHE_FILENAME ) ) {
			return null;
		}

		$class_finder = $this->build_discoverer( $dir );
		$class_finder->withCache(
			self::CACHE_ID,
			new ReadOnlyFileDiscoverCacheDriver( $this->get_cache_directory( $dir ), false, self::CACHE_FILENAME )
		);

		try {
			// Inside the try, so a file that parses but doesn't return an array falls back too.
			return array_filter( $class_finder->get(), fn( $cl ) => is_string( $cl ) );
		} catch ( \Throwable $e ) {
			if ( function_exists( 'do_action' ) ) {
				/**
				 * Fires when a prebuilt class cache could not be read and discovery ran instead.
				 *
				 * @param string     $dir The directory whose cache failed to load.
				 * @param \Throwable $e   The error raised while reading the cache.
				 */
				do_action( 'tenup_framework_cache_load_failed', $dir, $e );
			}

			return null;
		}
	}

	/**
	 * Generate the prebuilt class cache for a directory and write it to disk.
	 *
	 * The build-time counterpart to get_prebuilt_classes(), run by
	 * `tenup-framework-generate-class-cache`. It makes no WordPress calls, so it runs
	 * in CI without bootstrapping WordPress.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return array<string> The discovered class names that were cached.
	 */
	public function generate_cache( $dir = '' ) {
		$this->directory_check( $dir );

		$class_finder = $this->build_discoverer( $dir );
		$class_finder->withCache(
			self::CACHE_ID,
			new FileDiscoverCacheDriver( $this->get_cache_directory( $dir ), false, self::CACHE_FILENAME )
		);

		// cache() always discovers afresh and overwrites any existing file.
		return array_filter( $class_finder->cache(), fn( $cl ) => is_string( $cl ) );
	}

	/**
	 * A discoverer configured the same way for reading, generating and discovering,
	 * so the three can't drift apart.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return Discover
	 */
	protected function build_discoverer( $dir ): Discover {
		// Get all classes from this directory and its subdirectories.
		$class_finder = Discover::in( $dir );
		// Only fetch classes.
		$class_finder->classes();
		// Disable inheritance chain resolution.
		$class_finder->withoutChains();

		return $class_finder;
	}

	/**
	 * The absolute path to the cache directory for a discovery directory.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return string
	 */
	protected function get_cache_directory( $dir ): string {
		return rtrim( $dir, '/' ) . '/' . self::CACHE_DIR_NAME;
	}

	/**
	 * Whether class caching has been turned off with TENUP_FRAMEWORK_DISABLE_CLASS_CACHE.
	 *
	 * @return bool
	 */
	protected function cache_disabled(): bool {
		return defined( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE' ) && true === TENUP_FRAMEWORK_DISABLE_CLASS_CACHE;
	}

	/**
	 * Should we set up and use the class cache?
	 *
	 * @return bool
	 */
	protected function should_use_cache(): bool {
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return false;
		}

		if ( ! in_array( wp_get_environment_type(), [ 'production', 'staging' ], true ) ) {
			return false;
		}

		if ( defined( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE' ) && true === TENUP_FRAMEWORK_DISABLE_CLASS_CACHE ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the directory exists.
	 *
	 * @param string $dir The directory to check.
	 *
	 * @throws \RuntimeException If the directory does not exist.
	 *
	 * @return bool
	 */
	protected function directory_check( $dir ): bool {
		if ( empty( $dir ) ) {
			throw new \RuntimeException( 'Directory is required to initialize classes.' );
		}

		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \RuntimeException( 'Directory "' . $dir . '" does not exist.' );
		}

		return true;
	}

	/**
	 * Initialize all the TenupFramework plugin classes.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return void
	 */
	public function init_classes( $dir = '' ) {
		$this->directory_check( $dir );

		$load_class_order = [];
		foreach ( $this->get_classes( $dir ) as $class ) {
			// Create a slug for the class name.
			$slug = $this->slugify_class_name( $class );

			// If the class has already been initialized, skip it.
			if ( isset( $this->classes[ $slug ] ) ) {
				continue;
			}

			$reflection_class = $this->get_fully_loadable_class( $class );

			if ( ! $reflection_class ) {
				continue;
			}

			// Using reflection, check if the class can be initialized.
			// If not, skip.
			if ( ! $reflection_class->isInstantiable() ) {
				continue;
			}

			// Check if the class implements ModuleInterface before instantiating it
			if ( ! $reflection_class->implementsInterface( 'TenupFramework\ModuleInterface' ) ) {
				continue;
			}

			// Initialize the class.
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var ModuleInterface $instantiated_class */
			$instantiated_class = new $class();

			do_action( 'tenup_framework_module_init__' . $slug, $instantiated_class );

			// Assign the classes into the order they should be initialized.
			$load_class_order[ intval( $instantiated_class->load_order() ) ][] = [
				'slug'  => $slug,
				'class' => $instantiated_class,
			];
		}

		// Sort the initialized classes by load order.
		ksort( $load_class_order );

		// Loop through the classes and initialize them.
		foreach ( $load_class_order as $class_objects ) {
			foreach ( $class_objects as $class_object ) {
				$class = $class_object['class'];
				$slug  = $class_object['slug'];

				// If the class can be registered, register it.
				if ( $class->can_register() ) {
					// Call its register method.
					$class->register();
					// Store the class in the list of initialized classes.
					$this->classes[ $slug ] = $class;
				}
			}
		}
	}

	/**
	 * Retrieves a fully loadable class using reflection.
	 *
	 * @param string $class_name The name of the class to load.
	 *
	 * @return false|ReflectionClass Returns a ReflectionClass instance if the class is loadable, or false if it is not.
	 *
	 * @phpstan-ignore missingType.generics
	 */
	public function get_fully_loadable_class( string $class_name ): false|ReflectionClass {
		try {
			// Create a new reflection of the class.
			// @phpstan-ignore argument.type
			return new ReflectionClass( $class_name );
		} catch ( \Throwable $e ) {
			// This includes ReflectionException, Error due to missing parent, etc.
			return false;
		}
	}

	/**
	 * Slugify a class name.
	 *
	 * @param string $class_name The class name.
	 *
	 * @return string
	 */
	protected function slugify_class_name( $class_name ) {
		return sanitize_title( str_replace( '\\', '-', $class_name ) );
	}

	/**
	 * Get a class by its full class name, including namespace.
	 *
	 * @param string $class_name The class name & namespace.
	 *
	 * @return false|ModuleInterface
	 */
	public function get_class( $class_name ) {
		$class_name = $this->slugify_class_name( $class_name );

		if ( isset( $this->classes[ $class_name ] ) ) {
			return $this->classes[ $class_name ];
		}

		return false;
	}

	/**
	 * Get all the initialized classes.
	 *
	 * @return array<ModuleInterface>
	 */
	public function get_all_classes() {
		return $this->classes;
	}

	/**
	 * Get an initialized class by its full class name, including namespace.
	 *
	 * @param string $class_name The class name including the namespace.
	 *
	 * @return false|ModuleInterface
	 */
	public static function get_module( $class_name ) {
		return self::instance()->get_class( $class_name );
	}
}
