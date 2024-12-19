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

			// Create a new reflection of the class.
			// @phpstan-ignore argument.type
			$reflection_class = new ReflectionClass( $class );

			// Using reflection, check if the class can be initialized.
			// If not, skip.
			if ( ! $reflection_class->isInstantiable() ) {
				continue;
			}

			// Initialize the class.
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var ModuleInterface $instantiated_class */
			$instantiated_class = new $class();

			// Make sure the class is a subclass of Module, so we can initialize it.
			if ( ! in_array( 'TenupFramework\Module', $this->class_uses_recursive( $instantiated_class ), true ) ) {
				unset( $instantiated_class );
				continue;
			}

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
	 * Returns all traits used by a class and its traits.
	 *
	 * @param  object|string $class_to_search The class to check for traits.
	 * @return array<string>
	 */
	protected function class_uses_recursive( $class_to_search ) {
		if ( is_object( $class_to_search ) ) {
			$class_to_search = get_class( $class_to_search );
		}

		$results = [];

		foreach ( array_reverse( class_parents( $class_to_search ) ? class_parents( $class_to_search ) : [] ) + [ $class_to_search => $class_to_search ] as $class ) {
			$results = array_merge( $results, $this->trait_uses_recursive( $class ) );
		}

		return array_unique( $results );
	}

	/**
	 * Returns all traits used by a trait and its traits.
	 *
	 * @param  object|string $trait_to_search The trait to check for traits.
	 * @return array<string>
	 */
	protected function trait_uses_recursive( $trait_to_search ) {
		$traits_to_search = class_uses( $trait_to_search ) ? class_uses( $trait_to_search ) : [];

		foreach ( $traits_to_search as $trait ) {
			$traits_to_search = array_merge( $traits_to_search, $this->trait_uses_recursive( $trait ) );
		}

		return (array) $traits_to_search;
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
