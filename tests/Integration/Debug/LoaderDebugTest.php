<?php
/**
 * Tests the loader debug page against real WordPress hooks, users and nonces.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use Mantle\Testing\Concerns\Admin_Screen;
use TenupFramework\Debug\LoaderDebug;

uses( Admin_Screen::class );

beforeEach(
	function (): void {
		tenup_reset_framework_state();
	}
);

afterEach(
	function (): void {
		tenup_reset_framework_state();
		unset( $_GET['check'], $_GET['_wpnonce'] );
	}
);

describe(
	'enablement',
	function (): void {
		it(
			'is enabled by default in the admin',
			function (): void {
				expect( LoaderDebug::is_enabled() )->toBeTrue();
			}
		);

		it(
			'is disabled when the filter returns false',
			function (): void {
				add_filter( 'tenup_framework_enable_loader_debug', '__return_false' );

				expect( LoaderDebug::is_enabled() )->toBeFalse();

				remove_filter( 'tenup_framework_enable_loader_debug', '__return_false' );
			}
		);

		it(
			'stores nothing when disabled',
			function (): void {
				add_filter( 'tenup_framework_enable_loader_debug', '__return_false' );

				LoaderDebug::record( tenup_sample_loader_record() );

				expect( LoaderDebug::get_loaders() )->toBe( [] );

				remove_filter( 'tenup_framework_enable_loader_debug', '__return_false' );
			}
		);
	}
);

describe(
	'recording',
	function (): void {
		it(
			'stores a record when enabled',
			function (): void {
				LoaderDebug::record( tenup_sample_loader_record() );

				$loaders = LoaderDebug::get_loaders();

				expect( $loaders )->toHaveCount( 1 );
				expect( $loaders[0]['directory'] )->toBe( '/srv/site/wp-content/plugins/demo/inc' );
			}
		);

		it(
			'accumulates records for different directories',
			function (): void {
				LoaderDebug::record( tenup_sample_loader_record( '/a/inc' ) );
				LoaderDebug::record( tenup_sample_loader_record( '/b/inc' ) );

				expect( LoaderDebug::get_loaders() )->toHaveCount( 2 );
			}
		);

		it(
			'replaces rather than duplicates a repeat record for one directory',
			function (): void {
				LoaderDebug::record( tenup_sample_loader_record( '/a/inc' ) );
				LoaderDebug::record( tenup_sample_loader_record( '/a/inc' ) );

				expect( LoaderDebug::get_loaders() )->toHaveCount( 1 );
			}
		);

		it(
			'contributes its records to the cross-copy aggregation filter',
			function (): void {
				LoaderDebug::record( tenup_sample_loader_record( '/b/inc' ) );

				// Records from another framework copy must survive alongside ours, which is the whole
				// reason aggregation happens over a fixed-string filter instead of class references.
				$merged = apply_filters( LoaderDebug::FILTER, [ [ 'directory' => '/a/inc' ] ] );

				expect( $merged )->toHaveCount( 2 );
				expect( $merged[0]['directory'] )->toBe( '/a/inc' );
				expect( $merged[1]['directory'] )->toBe( '/b/inc' );
			}
		);
	}
);

describe(
	'page rendering',
	function (): void {
		beforeEach(
			function (): void {
				tenup_acting_as_admin();
			}
		);

		it(
			'lists each loader, its classes and its timings',
			function (): void {
				LoaderDebug::record( tenup_sample_loader_record() );

				ob_start();
				LoaderDebug::render_page();
				$output = (string) ob_get_clean();

				expect( $output )
				->toContain( 'WP Framework Loaders' )
				->toContain( '/srv/site/wp-content/plugins/demo/inc' )
				->toContain( 'TenupTmp\\Widget' )
				->toContain( 'Check this cache for staleness' )
				->toContain( 'Discovery time' )
				->toContain( 'Class lookup time' )
				->toContain( '12.30 ms' )
				->toContain( '45.60 ms' );
			}
		);

		it(
			'says so when no loaders were recorded',
			function (): void {
				ob_start();
				LoaderDebug::render_page();
				$output = (string) ob_get_clean();

				expect( $output )->toContain( 'No class loaders were recorded' );
			}
		);

		it(
			'reports an up-to-date cache when the live scan matches',
			function (): void {
				$dir = tenup_temp_class_dir();

				$record            = tenup_sample_loader_record( $dir );
				$record['classes'] = [ 'TenupTmp\\Widget' ];
				LoaderDebug::record( $record );

				$_GET['check']    = tenup_invoke_loader_debug( 'token_for', [ $dir ] );
				$_GET['_wpnonce'] = wp_create_nonce( LoaderDebug::CHECK_NONCE );

				ob_start();
				LoaderDebug::render_page();
				$output = (string) ob_get_clean();

				expect( $output )->toContain( 'Up to date' );
				expect( $output )->toContain( 'Live discovery took' );

				tenup_remove_dir( $dir );
			}
		);

		it(
			'reports drift when the recorded classes differ from a live scan',
			function (): void {
				$dir = tenup_temp_class_dir();

				// A class the cache claims but which is not on disk, and a missing real one.
				$record            = tenup_sample_loader_record( $dir );
				$record['classes'] = [ 'TenupTmp\\Ghost' ];
				LoaderDebug::record( $record );

				$_GET['check']    = tenup_invoke_loader_debug( 'token_for', [ $dir ] );
				$_GET['_wpnonce'] = wp_create_nonce( LoaderDebug::CHECK_NONCE );

				ob_start();
				LoaderDebug::render_page();
				$output = (string) ob_get_clean();

				expect( $output )->toContain( 'Stale' );
				expect( $output )->toContain( 'TenupTmp\\Ghost' );
				expect( $output )->toContain( 'TenupTmp\\Widget' );

				tenup_remove_dir( $dir );
			}
		);

		it(
			'ignores a staleness check without a valid nonce',
			function (): void {
				$dir = tenup_temp_class_dir();

				LoaderDebug::record( tenup_sample_loader_record( $dir ) );

				$_GET['check'] = tenup_invoke_loader_debug( 'token_for', [ $dir ] );
				// No _wpnonce, so check_is_valid() must reject it.

				ob_start();
				LoaderDebug::render_page();
				$output = (string) ob_get_clean();

				expect( $output )->toContain( 'Check this cache for staleness' );
				expect( $output )->not->toContain( 'Up to date' );

				tenup_remove_dir( $dir );
			}
		);
	}
);

describe(
	'formatting helpers',
	function (): void {
		it(
			'derives a plugin name for a directory under the plugins root',
			function (): void {
				expect( tenup_invoke_loader_debug( 'owner_label', [ WP_PLUGIN_DIR . '/demo/inc' ] ) )
				->toBe( 'Plugin: demo' );
			}
		);

		it(
			'falls back to the raw directory when it is outside any known root',
			function (): void {
				expect( tenup_invoke_loader_debug( 'owner_label', [ '/somewhere/else/inc' ] ) )
				->toBe( '/somewhere/else/inc' );
			}
		);

		it(
			'resolves the headline cache state',
			function ( array $flags, string $severity, string $snippet ): void {
				$state = tenup_invoke_loader_debug(
					'cache_state',
					[ array_merge( tenup_sample_loader_record(), $flags ) ]
				);

				expect( $state['severity'] )->toBe( $severity );
				expect( $state['badge'] )->toContain( $snippet );
			}
		)->with(
			[
				'disabled'           => [ [ 'cache_disabled' => true ], 'warn', 'disabled' ],
				'uncached'           => [
					[
						'cache_exists' => false,
						'cache_used'   => false,
					],
					'warn',
					'Uncached',
				],
				'present but unused' => [
					[
						'cache_exists' => true,
						'cache_used'   => false,
					],
					'error',
					'not used',
				],
				'failed to load'     => [
					[
						'cache_exists' => true,
						'cache_used'   => false,
						'cache_failed' => true,
					],
					'error',
					'failed to load',
				],
				'in use'             => [
					[
						'cache_exists' => true,
						'cache_used'   => true,
					],
					'ok',
					'in use',
				],
			]
		);

		it(
			'formats a duration, rejecting anything not a positive finite number',
			function ( mixed $seconds, string $expected ): void {
				expect( tenup_invoke_loader_debug( 'format_duration', [ $seconds ] ) )->toBe( $expected );
			}
		)->with(
			[
				'zero'         => [ 0.0, '—' ],
				'negative'     => [ -0.005, '—' ],
				'non-numeric'  => [ 'nope', '—' ],
				'not-a-number' => [ NAN, '—' ],
				'infinite'     => [ INF, '—' ],
				'sub-milli'    => [ 0.0004, '0.400 ms' ],
				'milliseconds' => [ 0.0123, '12.30 ms' ],
				'seconds'      => [ 1.5, '1.50 s' ],
			]
		);

		it(
			'reports unexpected files left in the cache directory',
			function (): void {
				$dir       = tenup_temp_class_dir();
				$cache_dir = $dir . '/class-loader-cache';
				mkdir( $cache_dir );

				$current = $cache_dir . '/class-loader-cache-v2.php';
				file_put_contents( $current, '<?php return array();' );
				file_put_contents( $cache_dir . '/discoverer-cache-TenupFramework', 'stale' );

				$legacy = tenup_invoke_loader_debug( 'legacy_files', [ $current ] );

				expect( $legacy )->toBe( [ 'discoverer-cache-TenupFramework' ] );

				tenup_remove_dir( $dir );
			}
		);

		it(
			'reports no unexpected files when the cache directory is absent',
			function (): void {
				expect( tenup_invoke_loader_debug( 'legacy_files', [ '/nope/class-loader-cache/x.php' ] ) )
				->toBe( [] );
			}
		);

		it(
			'describes the cache file with its size and UTC build time',
			function (): void {
				$dir       = tenup_temp_class_dir();
				$cache_dir = $dir . '/class-loader-cache';
				mkdir( $cache_dir );
				$cache_file = $cache_dir . '/class-loader-cache-v2.php';
				file_put_contents( $cache_file, '<?php return array();' );

				$detail = tenup_invoke_loader_debug( 'cache_detail', [ [ 'cache_file' => $cache_file ] ] );

				expect( $detail )->toContain( 'Built' );
				// The absolute build time is the file mtime, rendered in UTC as the trailing segment.
				expect( $detail )->toContain( '· ' . gmdate( 'Y-m-d H:i:s', (int) filemtime( $cache_file ) ) . ' UTC' );
				expect( $detail )->toMatch( '/·\s*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC$/' );

				tenup_remove_dir( $dir );
			}
		);

		it(
			'says there is no cache file when none is on disk',
			function (): void {
				expect( tenup_invoke_loader_debug( 'cache_detail', [ [ 'cache_file' => '' ] ] ) )
				->toContain( 'No cache file on disk' );
			}
		);
	}
);
