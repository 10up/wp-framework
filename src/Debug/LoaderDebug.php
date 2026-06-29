<?php
/**
 * LoaderDebug
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFramework\Debug;

use TenupFramework\ModuleInitialization;

/**
 * Admin-only diagnostics for the class-loader caches.
 *
 * A site can run 1..n framework copies (one per plugin/theme that requires the package), each
 * with its own discovery directory and cache file. This class aggregates every loader recorded
 * across all of those copies and renders them on a hidden admin page, so an engineer can see
 * exactly what each cache is loading and spot a stale one (e.g. a failed build that left an old
 * cache file on production).
 *
 * Aggregation happens over the fixed-string `tenup_framework_debug_loaders` filter rather than
 * via class references, so it works even when copies are php-scoped (prefixed) or differently
 * versioned: string literal hook names survive scoping, class names do not.
 *
 * This class is only ever referenced from an is_admin() branch in ModuleInitialization, so it
 * never loads on front-end requests. The page is read-only — it never writes to the filesystem.
 *
 * @package TenupFramework
 */
class LoaderDebug {

	/**
	 * The admin page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'tenup-framework-loaders';

	/**
	 * The shared, scope-independent aggregation filter.
	 *
	 * @var string
	 */
	public const FILTER = 'tenup_framework_debug_loaders';

	/**
	 * The capability required to view the page.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The nonce action for the on-demand staleness check.
	 *
	 * @var string
	 */
	public const CHECK_NONCE = 'tenup_framework_loader_check';

	/**
	 * Loader records collected for this framework copy.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected static $loaders = [];

	/**
	 * Whether this copy has wired its WordPress hooks yet.
	 *
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Record a loader and ensure the admin hooks are wired.
	 *
	 * Called from ModuleInitialization::init_classes() (admin requests only). Returns early
	 * without storing anything or adding hooks when the tooling is disabled.
	 *
	 * @param array<string, mixed> $record The loader record.
	 *
	 * @return void
	 */
	public static function record( array $record ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		self::$loaders[] = $record;

		self::boot();
	}

	/**
	 * The loader records collected for this framework copy (not the cross-copy aggregate —
	 * use the `tenup_framework_debug_loaders` filter for that).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_loaders(): array {
		return self::$loaders;
	}

	/**
	 * Whether the debug tooling is enabled.
	 *
	 * Off when WordPress isn't loaded, when the disable constant is set, or when a filter turns
	 * it off. On by default (in the admin, which is the only place record() is called).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( ! function_exists( 'add_action' ) ) {
			return false;
		}

		if ( defined( 'TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG' ) && true === constant( 'TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG' ) ) {
			return false;
		}

		return (bool) apply_filters( 'tenup_framework_enable_loader_debug', true );
	}

	/**
	 * Wire the WordPress hooks once per copy: contribute this copy's records to the shared
	 * filter, and register the admin page a single time across every loaded copy.
	 *
	 * @return void
	 */
	protected static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_filter(
			self::FILTER,
			static function ( $loaders ) {
				return array_merge( (array) $loaders, self::$loaders );
			}
		);

		// Register the page only once, even when several framework copies are loaded.
		if ( empty( $GLOBALS['tenup_framework_debug_page_registered'] ) ) {
			$GLOBALS['tenup_framework_debug_page_registered'] = true;
			add_action( 'admin_menu', [ self::class, 'register_page' ] );
		}
	}

	/**
	 * Register the hidden admin page.
	 *
	 * An empty parent slug keeps the page out of every menu while leaving it reachable at
	 * admin.php?page=tenup-framework-loaders.
	 *
	 * @return void
	 */
	public static function register_page() {
		$title = __( 'WP Framework Loaders', 'tenup-framework' );

		add_submenu_page(
			'',
			$title,
			$title,
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tenup-framework' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostic; the value is nonce-verified below before use.
		$requested_check = ( isset( $_GET['check'] ) && is_string( $_GET['check'] ) ) ? sanitize_text_field( wp_unslash( $_GET['check'] ) ) : '';
		$check           = self::check_is_valid( $requested_check ) ? $requested_check : '';

		$loaders = apply_filters( self::FILTER, [] );
		if ( ! is_array( $loaders ) ) {
			$loaders = [];
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Framework Loaders', 'tenup-framework' ) . '</h1>';
		echo '<p>' . esc_html__( 'Each block is a directory passed to ModuleInitialization::init_classes(), with the state of its class-loader cache. Caches are built at deploy time and read (never written) at runtime.', 'tenup-framework' ) . '</p>';

		if ( empty( $loaders ) ) {
			echo '<p><strong>' . esc_html__( 'No class loaders were recorded for this request.', 'tenup-framework' ) . '</strong></p>';
			echo '</div>';
			return;
		}

		foreach ( $loaders as $loader ) {
			if ( is_array( $loader ) ) {
				self::render_loader( $loader, $check );
			}
		}

		echo '</div>';
	}

	/**
	 * Render a single loader block.
	 *
	 * @param array  $loader The loader record.
	 * @param string $check  The validated staleness-check token, if any.
	 *
	 * @return void
	 */
	protected static function render_loader( array $loader, string $check ) {
		$directory  = self::to_string( $loader['directory'] ?? '' );
		$cache_file = self::to_string( $loader['cache_file'] ?? '' );
		$classes    = isset( $loader['classes'] ) && is_array( $loader['classes'] )
			? array_values( array_map( [ self::class, 'to_string' ], $loader['classes'] ) )
			: [];

		echo '<h2>' . esc_html( self::owner_label( $directory ) ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:60em;margin-bottom:1em;"><tbody>';

		self::render_row( __( 'Directory', 'tenup-framework' ), $directory );
		self::render_row( __( 'Framework version', 'tenup-framework' ), self::version_label( $loader ) );
		self::render_row( __( 'Cache file', 'tenup-framework' ), $cache_file );
		self::render_row( __( 'Cache status', 'tenup-framework' ), self::cache_status_label( $loader ) );
		self::render_row( __( 'Classes loaded', 'tenup-framework' ), (string) count( $classes ) );

		$legacy = self::legacy_files( $cache_file );
		if ( ! empty( $legacy ) ) {
			self::render_row(
				__( 'Stale cache files', 'tenup-framework' ),
				sprintf(
					/* translators: %s: comma-separated list of unexpected filenames. */
					__( 'Unexpected files alongside the current cache (likely left by an older version): %s', 'tenup-framework' ),
					implode( ', ', $legacy )
				)
			);
		}

		echo '</tbody></table>';

		self::render_classes( $classes );
		self::render_staleness( $directory, $classes, $check );
	}

	/**
	 * Render a label/value row, escaping both.
	 *
	 * @param string $label The row label.
	 * @param string $value The row value.
	 *
	 * @return void
	 */
	protected static function render_row( string $label, string $value ) {
		echo '<tr><th scope="row" style="width:14em;">' . esc_html( $label ) . '</th><td><code>' . esc_html( $value ) . '</code></td></tr>';
	}

	/**
	 * Render the list of loaded classes with the file each resolves to.
	 *
	 * @param array<int, string> $classes The class names.
	 *
	 * @return void
	 */
	protected static function render_classes( array $classes ) {
		if ( empty( $classes ) ) {
			return;
		}

		echo '<table class="widefat striped" style="max-width:60em;margin-bottom:1.5em;">';
		echo '<thead><tr><th>' . esc_html__( 'Class', 'tenup-framework' ) . '</th><th>' . esc_html__( 'File', 'tenup-framework' ) . '</th></tr></thead><tbody>';

		$module_init = ModuleInitialization::instance();

		foreach ( $classes as $class ) {
			$reflection = $module_init->get_fully_loadable_class( $class );
			$file       = $reflection ? (string) $reflection->getFileName() : __( 'Does not resolve — likely a stale cache entry.', 'tenup-framework' );

			echo '<tr><td><code>' . esc_html( $class ) . '</code></td><td><code>' . esc_html( $file ) . '</code></td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the on-demand staleness check: a trigger link, and the diff when requested.
	 *
	 * @param string             $directory The loader directory.
	 * @param array<int, string> $classes   The currently loaded class list.
	 * @param string             $check     The validated staleness-check token, if any.
	 *
	 * @return void
	 */
	protected static function render_staleness( string $directory, array $classes, string $check ) {
		if ( '' === $directory ) {
			return;
		}

		if ( self::token_for( $directory ) !== $check ) {
			$url = wp_nonce_url(
				add_query_arg(
					[
						'page'  => self::PAGE_SLUG,
						'check' => self::token_for( $directory ),
					],
					admin_url( 'admin.php' )
				),
				self::CHECK_NONCE
			);

			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Check this cache for staleness', 'tenup-framework' ) . '</a></p>';
			return;
		}

		$live    = ModuleInitialization::instance()->discover_live( $directory );
		$loaded  = array_values( $classes );
		$removed = array_diff( $loaded, $live ); // In cache but no longer on disk.
		$added   = array_diff( $live, $loaded ); // On disk but missing from the cache.

		if ( empty( $removed ) && empty( $added ) ) {
			echo '<p><strong>' . esc_html__( 'Up to date — the cache matches a live scan.', 'tenup-framework' ) . '</strong></p>';
			return;
		}

		echo '<p><strong>' . esc_html__( 'Stale — the cache differs from a live scan:', 'tenup-framework' ) . '</strong></p>';

		if ( ! empty( $added ) ) {
			echo '<p>' . esc_html__( 'On disk but missing from the cache:', 'tenup-framework' ) . '</p><ul>';
			foreach ( $added as $class ) {
				echo '<li><code>' . esc_html( $class ) . '</code></li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $removed ) ) {
			echo '<p>' . esc_html__( 'In the cache but no longer on disk:', 'tenup-framework' ) . '</p><ul>';
			foreach ( $removed as $class ) {
				echo '<li><code>' . esc_html( $class ) . '</code></li>';
			}
			echo '</ul>';
		}

		echo '<p>' . esc_html__( 'Regenerate the cache in your build (composer generate-class-cache) or remove the file and redeploy.', 'tenup-framework' ) . '</p>';
	}

	/**
	 * Coerce a mixed value (loader records arrive through a filter as mixed) to a string,
	 * returning an empty string for anything non-scalar.
	 *
	 * @param mixed $value The value to coerce.
	 *
	 * @return string
	 */
	protected static function to_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A stable, opaque token identifying a loader directory in the check link.
	 *
	 * @param string $directory The loader directory.
	 *
	 * @return string
	 */
	protected static function token_for( string $directory ): string {
		return md5( $directory );
	}

	/**
	 * Whether a requested check token is well-formed and the request is nonce-verified.
	 *
	 * @param string $token The requested token.
	 *
	 * @return bool
	 */
	protected static function check_is_valid( string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		$nonce = ( isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, self::CHECK_NONCE );
	}

	/**
	 * A human-friendly owner label for a directory (plugin/theme name where derivable).
	 *
	 * @param string $directory The loader directory.
	 *
	 * @return string
	 */
	protected static function owner_label( string $directory ): string {
		if ( '' === $directory ) {
			return __( 'Unknown loader', 'tenup-framework' );
		}

		$roots = [];
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots[] = [ self::to_string( constant( 'WP_PLUGIN_DIR' ) ), __( 'Plugin', 'tenup-framework' ) ];
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots[] = [ self::to_string( constant( 'WPMU_PLUGIN_DIR' ) ), __( 'Must-use plugin', 'tenup-framework' ) ];
		}
		if ( function_exists( 'get_theme_root' ) ) {
			$roots[] = [ self::to_string( get_theme_root() ), __( 'Theme', 'tenup-framework' ) ];
		}

		foreach ( $roots as $candidate ) {
			$root = rtrim( $candidate[0], '/' );
			$type = $candidate[1];
			if ( '' !== $root && str_starts_with( $directory, $root . '/' ) ) {
				$relative = ltrim( substr( $directory, strlen( $root ) ), '/' );
				$segment  = explode( '/', $relative )[0];

				/* translators: 1: owner type (Plugin/Theme), 2: plugin or theme folder name. */
				return sprintf( __( '%1$s: %2$s', 'tenup-framework' ), $type, $segment );
			}
		}

		return $directory;
	}

	/**
	 * A label describing the framework version that recorded a loader.
	 *
	 * @param array $loader The loader record.
	 *
	 * @return string
	 */
	protected static function version_label( array $loader ): string {
		$version   = self::to_string( $loader['version'] ?? '' );
		$reference = self::to_string( $loader['reference'] ?? '' );

		if ( '' === $version ) {
			$version = __( 'unknown', 'tenup-framework' );
		}

		if ( '' !== $reference ) {
			$version .= ' (' . substr( $reference, 0, 8 ) . ')';
		}

		return $version;
	}

	/**
	 * A label describing the cache status, including mtime, size and whether it is in use.
	 *
	 * @param array $loader The loader record.
	 *
	 * @return string
	 */
	protected static function cache_status_label( array $loader ): string {
		if ( ! empty( $loader['cache_disabled'] ) ) {
			return __( 'Disabled — discovering live (TENUP_FRAMEWORK_DISABLE_CLASS_CACHE).', 'tenup-framework' );
		}

		if ( empty( $loader['cache_exists'] ) ) {
			return __( 'No cache file — discovering live on every request.', 'tenup-framework' );
		}

		$cache_file = self::to_string( $loader['cache_file'] ?? '' );
		$mtime      = file_exists( $cache_file ) ? (int) filemtime( $cache_file ) : 0;
		$size       = file_exists( $cache_file ) ? (int) filesize( $cache_file ) : 0;

		$used = empty( $loader['cache_used'] )
			? __( 'present but not used', 'tenup-framework' )
			: __( 'in use', 'tenup-framework' );

		return sprintf(
			/* translators: 1: in-use status, 2: relative age, 3: file size. */
			__( 'Cache %1$s — built %2$s ago, %3$s.', 'tenup-framework' ),
			$used,
			$mtime ? human_time_diff( $mtime ) : __( 'unknown time', 'tenup-framework' ),
			size_format( $size )
		);
	}

	/**
	 * Find files in the cache directory that are not the current cache file — usually stale
	 * leftovers from an older framework version.
	 *
	 * @param string $cache_file The current cache file path.
	 *
	 * @return array<int, string> The unexpected filenames.
	 */
	protected static function legacy_files( string $cache_file ): array {
		if ( '' === $cache_file ) {
			return [];
		}

		$dir = dirname( $cache_file );
		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$expected = basename( $cache_file );
		$found    = [];

		foreach ( new \DirectoryIterator( $dir ) as $item ) {
			if ( $item->isDot() || $item->isDir() ) {
				continue;
			}
			$name = $item->getFilename();
			if ( $name !== $expected ) {
				$found[] = $name;
			}
		}

		return $found;
	}
}
