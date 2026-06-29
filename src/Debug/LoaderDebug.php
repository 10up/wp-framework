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

		echo '<div class="wrap tenup-loaders">';
		self::render_styles();
		echo '<h1>' . esc_html__( 'WP Framework Loaders', 'tenup-framework' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Each card is a directory passed to ModuleInitialization::init_classes(), with the state of its class-loader cache. Caches are built at deploy time and read — never written — at runtime.', 'tenup-framework' ) . '</p>';

		if ( empty( $loaders ) ) {
			echo '<div class="tenup-notice tenup-notice--info"><strong>' . esc_html__( 'No class loaders were recorded for this request.', 'tenup-framework' ) . '</strong></div>';
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

		$state  = self::cache_state( $loader );
		$legacy = self::legacy_files( $cache_file );

		echo '<div class="tenup-loader">';

		echo '<div class="tenup-loader__head">';
		echo '<h2>' . esc_html( self::owner_label( $directory ) ) . '</h2>';
		echo '<span class="tenup-badge tenup-badge--' . esc_attr( $state['severity'] ) . '">' . esc_html( $state['badge'] ) . '</span>';
		echo '</div>';

		if ( '' !== $state['note'] ) {
			echo '<div class="tenup-notice tenup-notice--' . esc_attr( $state['severity'] ) . '">' . esc_html( $state['note'] ) . '</div>';
		}

		if ( ! empty( $legacy ) ) {
			echo '<div class="tenup-notice tenup-notice--error">' . esc_html(
				sprintf(
					/* translators: %s: comma-separated list of unexpected filenames. */
					__( 'Unexpected files in the cache directory, likely left by an older version: %s. Delete them or redeploy.', 'tenup-framework' ),
					implode( ', ', $legacy )
				)
			) . '</div>';
		}

		echo '<table class="widefat striped tenup-loader__meta"><tbody>';
		self::render_row( __( 'Directory', 'tenup-framework' ), $directory );
		self::render_row( __( 'Framework version', 'tenup-framework' ), self::version_label( $loader ) );
		self::render_row( __( 'Cache file', 'tenup-framework' ), '' !== $cache_file ? $cache_file : '—' );
		self::render_row( __( 'Cache detail', 'tenup-framework' ), self::cache_detail( $loader ) );
		echo '</tbody></table>';

		echo '<details class="tenup-loader__classes">';
		echo '<summary>' . esc_html(
			sprintf(
				/* translators: %d: number of classes. */
				_n( '%d class loaded', '%d classes loaded', count( $classes ), 'tenup-framework' ),
				count( $classes )
			)
		) . '</summary>';
		self::render_classes( $classes );
		echo '</details>';

		self::render_staleness( $directory, $classes, $check );

		echo '</div>';
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

		echo '<table class="widefat striped tenup-loader__class-table">';
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

			echo '<p class="tenup-loader__actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Check this cache for staleness', 'tenup-framework' ) . '</a></p>';
			return;
		}

		$live    = ModuleInitialization::instance()->discover_live( $directory );
		$loaded  = array_values( $classes );
		$removed = array_diff( $loaded, $live ); // In cache but no longer on disk.
		$added   = array_diff( $live, $loaded ); // On disk but missing from the cache.

		if ( empty( $removed ) && empty( $added ) ) {
			echo '<div class="tenup-notice tenup-notice--ok"><strong>' . esc_html__( 'Up to date — the cache matches a live scan.', 'tenup-framework' ) . '</strong></div>';
			return;
		}

		echo '<div class="tenup-notice tenup-notice--error">';
		echo '<strong>' . esc_html__( 'Stale — the cache differs from a live scan.', 'tenup-framework' ) . '</strong>';

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
		echo '</div>';
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
	 * Resolve the headline cache state for a loader: a severity, a short badge label, and an
	 * optional explanatory note.
	 *
	 * Severity maps to the badge/notice colour. Note that running uncached is a valid default
	 * (caching is opt-in), so it is surfaced as a warning to be noticeable, not as an error.
	 *
	 * @param array $loader The loader record.
	 *
	 * @return array{severity: string, badge: string, note: string}
	 */
	protected static function cache_state( array $loader ): array {
		if ( ! empty( $loader['cache_disabled'] ) ) {
			return [
				'severity' => 'warn',
				'badge'    => __( 'Caching disabled', 'tenup-framework' ),
				'note'     => __( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE is set, so any shipped cache is ignored and classes are discovered live on every request.', 'tenup-framework' ),
			];
		}

		if ( empty( $loader['cache_exists'] ) ) {
			return [
				'severity' => 'warn',
				'badge'    => __( 'Uncached — live discovery', 'tenup-framework' ),
				'note'     => __( 'No cache file is present, so classes are discovered live on every request. That is the correct default for small projects; for large codebases, build a cache in your pipeline (see Build and Deployment).', 'tenup-framework' ),
			];
		}

		if ( empty( $loader['cache_used'] ) ) {
			return [
				'severity' => 'error',
				'badge'    => __( 'Cache present but not used', 'tenup-framework' ),
				'note'     => __( 'A cache file exists but is not being used. This is unexpected — check TENUP_FRAMEWORK_DISABLE_CLASS_CACHE.', 'tenup-framework' ),
			];
		}

		return [
			'severity' => 'ok',
			'badge'    => __( 'Cache in use', 'tenup-framework' ),
			'note'     => '',
		];
	}

	/**
	 * A short description of the cache file on disk (age and size), or a placeholder when none.
	 *
	 * @param array $loader The loader record.
	 *
	 * @return string
	 */
	protected static function cache_detail( array $loader ): string {
		$cache_file = self::to_string( $loader['cache_file'] ?? '' );

		if ( '' === $cache_file || ! file_exists( $cache_file ) ) {
			return __( 'No cache file on disk.', 'tenup-framework' );
		}

		$mtime = (int) filemtime( $cache_file );
		$size  = (int) filesize( $cache_file );

		return sprintf(
			/* translators: 1: relative age, 2: file size. */
			__( 'Built %1$s ago · %2$s', 'tenup-framework' ),
			$mtime ? human_time_diff( $mtime ) : __( 'unknown time', 'tenup-framework' ),
			size_format( $size )
		);
	}

	/**
	 * Output the page's scoped styles once.
	 *
	 * @return void
	 */
	protected static function render_styles() {
		echo '<style>
			.tenup-loaders .tenup-loader { max-width: 60em; margin: 1.25em 0; padding: .5em 1.25em 1.25em; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
			.tenup-loaders .tenup-loader__head { display: flex; align-items: center; gap: .75em; flex-wrap: wrap; }
			.tenup-loaders .tenup-loader__head h2 { margin: .5em 0; }
			.tenup-loaders .tenup-badge { display: inline-block; padding: .15em .7em; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid; }
			.tenup-loaders .tenup-badge--ok { background: #edfaef; color: #00450c; border-color: #00a32a; }
			.tenup-loaders .tenup-badge--info { background: #f0f6fc; color: #0a4b78; border-color: #72aee6; }
			.tenup-loaders .tenup-badge--warn { background: #fcf9e8; color: #614b00; border-color: #dba617; }
			.tenup-loaders .tenup-badge--error { background: #fcf0f1; color: #8a1f11; border-color: #d63638; }
			.tenup-loaders .tenup-notice { margin: .75em 0; padding: .6em .9em; border-left: 4px solid #72aee6; background: #f6f7f7; }
			.tenup-loaders .tenup-notice--ok { border-left-color: #00a32a; }
			.tenup-loaders .tenup-notice--info { border-left-color: #72aee6; }
			.tenup-loaders .tenup-notice--warn { border-left-color: #dba617; }
			.tenup-loaders .tenup-notice--error { border-left-color: #d63638; }
			.tenup-loaders .tenup-loader__meta { margin: .75em 0; }
			.tenup-loaders .tenup-loader__meta th { width: 14em; }
			.tenup-loaders .tenup-loader__classes { margin: .5em 0; }
			.tenup-loaders .tenup-loader__classes summary { cursor: pointer; font-weight: 600; padding: .4em 0; }
			.tenup-loaders .tenup-loader__class-table { margin: .5em 0 1em; }
			.tenup-loaders .tenup-loader__actions { margin: .75em 0 0; }
		</style>';
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
