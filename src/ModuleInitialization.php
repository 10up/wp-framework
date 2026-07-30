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
	 * Whether the most recent get_classes() call fell back to a live scan because a shipped
	 * cache file failed to load (corrupt or truncated). Read by record_loader_debug() so the
	 * debug page flags the degraded state instead of reporting the cache as healthy.
	 *
	 * @var bool
	 */
	protected $cache_read_failed = false;

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

		$this->cache_read_failed = false;

		try {
			// array_filter is inside the try so that a cache which parses but returns a
			// non-array (not only a truncated one) also falls back rather than fataling here.
			return array_filter( $class_finder->get(), fn( $cl ) => is_string( $cl ) );
		} catch ( \Throwable $e ) {
			// A shipped cache file that is corrupt or truncated — a partial deploy, an
			// interrupted build, a half-written rsync — would otherwise fatal on every request
			// (the cache is executable PHP loaded with `require`). Fall back to a fresh live
			// discovery so the site keeps working, uncached, until the cache is rebuilt. This
			// is the same spirit as issue #30: a bad cache must never take the site down.
			$this->cache_read_failed = true;

			if ( function_exists( 'do_action' ) ) {
				/**
				 * Fires when a shipped class cache could not be read and the runtime fell back
				 * to a live scan. Lets a project log or alert on a degraded (uncached) deploy;
				 * the loader debug page flags the same state.
				 *
				 * @param string     $dir The directory whose cache failed to load.
				 * @param \Throwable $e   The error raised while reading the cache.
				 */
				do_action( 'tenup_framework_cache_load_failed', $dir, $e );
			}

			return array_filter( $this->build_discoverer( $dir )->get(), fn( $cl ) => is_string( $cl ) );
		}
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
	 * @param string        $dir               The directory that was discovered.
	 * @param array<string> $classes           The discovered class names.
	 * @param float         $discovery_seconds Seconds spent obtaining the class list (cache read or live scan).
	 * @param float         $lookup_seconds    Seconds spent reflecting, instantiating and registering the classes.
	 *
	 * @return void
	 */
	protected function record_loader_debug( $dir, array $classes, float $discovery_seconds = 0.0, float $lookup_seconds = 0.0 ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		// is_admin() is also true for admin-ajax.php. The debug page is a normal admin GET that
		// re-runs discovery and records afresh, so recording on ajax requests is pure waste
		// (often triggered from the front end). Skip them.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		$cache_file   = $this->get_cache_directory( $dir ) . '/' . self::CACHE_FILENAME;
		$cache_exists = file_exists( $cache_file );
		$disabled     = $this->cache_disabled();
		$failed       = $this->cache_read_failed;

		LoaderDebug::record(
			[
				'directory'         => $dir,
				'cache_file'        => $cache_file,
				'cache_exists'      => $cache_exists,
				// A present cache that failed to load was not actually used — the runtime fell
				// back to a live scan — so report it as such rather than "in use".
				'cache_used'        => $cache_exists && ! $disabled && ! $failed,
				'cache_disabled'    => $disabled,
				'cache_failed'      => $failed,
				'classes'           => $classes,
				'version'           => $this->framework_version(),
				'reference'         => $this->framework_reference(),
				'discovery_seconds' => $discovery_seconds,
				'lookup_seconds'    => $lookup_seconds,
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

		// Time discovery (a cache read when a cache is present, a live filesystem scan
		// otherwise) separately from the reflection/instantiation work below, so the debug
		// page can show where the request's time actually goes. hrtime() is monotonic, so an
		// NTP adjustment mid-request cannot produce a negative or wildly wrong delta.
		$discovery_start   = hrtime( true );
		$classes           = $this->get_classes( $dir );
		$discovery_seconds = ( hrtime( true ) - $discovery_start ) / 1e9;

		$lookup_start = hrtime( true );

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

		$lookup_seconds = ( hrtime( true ) - $lookup_start ) / 1e9;

		$this->record_loader_debug( $dir, $classes, $discovery_seconds, $lookup_seconds );
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
