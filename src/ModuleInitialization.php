<?php
/**
 * Auto-initialize all Module based clases in the plugin.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFramework;

use ReflectionClass;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;

/**
 * ModuleInitialization class.
 *
 * @package TenupFramework
 */
class ModuleInitialization {

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

		// Get all classes from this directory and its subdirectories.
		$class_finder = Discover::in( $dir );
		// Only fetch classes.
		$class_finder->classes();

		// If we are in production or staging, cache the class loader to improve performance.
		if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) && in_array( wp_get_environment_type(), [ 'production', 'staging' ], true ) ) {
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
