<?php
/**
 * Auto-initialize all Module based classes in the plugin.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFramework;

use Composer\InstalledVersions;
use ReflectionClass;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;
use TenupFramework\Cache\ReadOnlyFileDiscoverCacheDriver;
use TenupFramework\Debug\LoaderDebug;

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
	 * The class cache filename.
	 *
	 * Bumping this value invalidates caches written by older framework versions: the
	 * runtime looks for a filename the previous build never produced, so a stale file
	 * is simply ignored until a fresh build regenerates it. The old file is harmless
	 * cruft that a clean deploy clears.
	 *
	 * @var string
	 */
	public const CACHE_FILENAME = 'class-loader-cache-v2.php';

	/**
	 * The Spatie cache identifier.
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

		$class_finder = $this->build_discoverer( $dir );

		// The runtime only ever reads a pre-built cache; it never writes one. Caching is
		// therefore opt-in: with no cache file present we discover live on every request,
		// which is the correct default. A cache is produced at build time via the
		// `tenup-framework-generate-class-cache` command and shipped as a build artefact.
		//
		// Define TENUP_FRAMEWORK_DISABLE_CLASS_CACHE to ignore any shipped cache and always
		// discover live (useful for debugging).
		if ( ! $this->cache_disabled() ) {
			$class_finder->withCache(
				self::CACHE_ID,
				new ReadOnlyFileDiscoverCacheDriver(
					$this->get_cache_directory( $dir ),
					false,
					self::CACHE_FILENAME
				)
			);
		}

		$classes = array_filter( $class_finder->get(), fn( $cl ) => is_string( $cl ) );

		// Return the classes
		return $classes;
	}

	/**
	 * Generate the class cache for a directory and write it to disk.
	 *
	 * This is the build-time counterpart to get_classes(): it is the only place the
	 * framework writes the cache, and it deliberately makes no WordPress calls so it can
	 * run from a plain CLI script during CI without bootstrapping WordPress. The resulting
	 * file is then deployed as a build artefact and read (never rewritten) at runtime.
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
			new FileDiscoverCacheDriver(
				$this->get_cache_directory( $dir ),
				false,
				self::CACHE_FILENAME
			)
		);

		// cache() forces a fresh discovery and overwrites any existing cache file, so a
		// regenerate always reflects the current code rather than a previous build.
		$classes = $class_finder->cache();

		return array_filter( $classes, fn( $cl ) => is_string( $cl ) );
	}

	/**
	 * Build a discoverer configured the same way for both reading and generating, so the
	 * two paths can never drift apart.
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
	 * Get the absolute path to the cache directory for a discovery directory.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return string
	 */
	protected function get_cache_directory( $dir ): string {
		return rtrim( $dir, '/' ) . '/' . self::CACHE_DIR_NAME;
	}

	/**
	 * Whether class caching has been explicitly disabled.
	 *
	 * When true, the runtime ignores any shipped cache and discovers classes live on every
	 * request. Useful for debugging a suspected stale or incorrect cache.
	 *
	 * @return bool
	 */
	protected function cache_disabled(): bool {
		return defined( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE' ) && true === TENUP_FRAMEWORK_DISABLE_CLASS_CACHE;
	}

	/**
	 * Discover the classes in a directory live, ignoring any cache.
	 *
	 * Used by the admin-only debug page's on-demand staleness check to compare what is actually
	 * on disk against what the cache loaded.
	 *
	 * @param string $dir The directory to search for classes.
	 *
	 * @return array<string>
	 */
	public function discover_live( $dir ) {
		$this->directory_check( $dir );

		return array_values( array_filter( $this->build_discoverer( $dir )->get(), fn( $cl ) => is_string( $cl ) ) );
	}

	/**
	 * Hand loader metadata to the admin-only debug tooling.
	 *
	 * Front-end requests do nothing here: the data is only viewable in the admin, so it is only
	 * gathered there. The is_admin() check happens before LoaderDebug is referenced, so that
	 * class never autoloads on the front end.
	 *
	 * @param string        $dir     The directory that was discovered.
	 * @param array<string> $classes The discovered class names.
	 *
	 * @return void
	 */
	protected function record_loader_debug( $dir, array $classes ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		$cache_file   = $this->get_cache_directory( $dir ) . '/' . self::CACHE_FILENAME;
		$cache_exists = file_exists( $cache_file );
		$disabled     = $this->cache_disabled();

		LoaderDebug::record(
			[
				'directory'      => $dir,
				'cache_file'     => $cache_file,
				'cache_exists'   => $cache_exists,
				'cache_used'     => $cache_exists && ! $disabled,
				'cache_disabled' => $disabled,
				'classes'        => $classes,
				'version'        => $this->framework_version(),
				'reference'      => $this->framework_reference(),
			]
		);
	}

	/**
	 * The installed framework version, or an empty string when it cannot be determined.
	 *
	 * @return string
	 */
	protected function framework_version(): string {
		if ( class_exists( InstalledVersions::class ) && InstalledVersions::isInstalled( '10up/wp-framework' ) ) {
			return (string) InstalledVersions::getPrettyVersion( '10up/wp-framework' );
		}

		return '';
	}

	/**
	 * The installed framework reference (git hash), or an empty string when unavailable.
	 *
	 * @return string
	 */
	protected function framework_reference(): string {
		if ( class_exists( InstalledVersions::class ) && InstalledVersions::isInstalled( '10up/wp-framework' ) ) {
			return (string) InstalledVersions::getReference( '10up/wp-framework' );
		}

		return '';
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

		$classes = $this->get_classes( $dir );

		$this->record_loader_debug( $dir, $classes );

		$load_class_order = [];
		foreach ( $classes as $class ) {
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
