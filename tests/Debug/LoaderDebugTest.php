<?php
/**
 * Tests for the LoaderDebug admin diagnostics.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests\Debug;

use PHPUnit\Framework\TestCase;
use TenupFramework\Debug\LoaderDebug;
use TenupFrameworkTests\FrameworkTestSetup;
use function Brain\Monkey\Functions\when;

/**
 * LoaderDebugTest class.
 */
class LoaderDebugTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * A representative loader record.
	 *
	 * @param string $directory The loader directory.
	 *
	 * @return array<string, mixed>
	 */
	private function sample_record( string $directory = '/srv/site/wp-content/plugins/demo/inc' ): array {
		return [
			'directory'         => $directory,
			'cache_file'        => $directory . '/class-loader-cache/class-loader-cache-v2.php',
			'cache_exists'      => false,
			'cache_used'        => false,
			'cache_disabled'    => false,
			'classes'           => [ 'TenupTmp\\Widget' ],
			'version'           => '1.3.0',
			'reference'         => 'abcdef1234567890',
			'discovery_seconds' => 0.0123,
			'lookup_seconds'    => 0.0456,
		];
	}

	/**
	 * Stub the functions record() needs, with the tooling enabled.
	 *
	 * @return void
	 */
	private function stub_enabled() {
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );
	}

	/**
	 * is_enabled() is false when the enable filter returns false.
	 *
	 * @return void
	 */
	public function test_is_enabled_false_when_filter_disables() {
		when( 'add_action' )->justReturn( true );
		when( 'apply_filters' )->justReturn( false );

		$this->assertFalse( LoaderDebug::is_enabled() );
	}

	/**
	 * is_enabled() is false when the disable constant is set.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_is_enabled_false_when_constant_defined() {
		when( 'add_action' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );

		define( 'TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG', true );

		$this->assertFalse( LoaderDebug::is_enabled() );
	}

	/**
	 * record() stores the record when enabled.
	 *
	 * @return void
	 */
	public function test_record_stores_when_enabled() {
		$this->stub_enabled();

		LoaderDebug::record( $this->sample_record() );

		$this->assertCount( 1, LoaderDebug::get_loaders() );
	}

	/**
	 * record() accumulates multiple records.
	 *
	 * @return void
	 */
	public function test_records_accumulate() {
		$this->stub_enabled();

		LoaderDebug::record( $this->sample_record( '/a/inc' ) );
		LoaderDebug::record( $this->sample_record( '/b/inc' ) );

		$this->assertCount( 2, LoaderDebug::get_loaders() );
	}

	/**
	 * record() stores nothing when disabled.
	 *
	 * @return void
	 */
	public function test_record_skips_when_disabled() {
		when( 'add_action' )->justReturn( true );
		when( 'apply_filters' )->justReturn( false );

		LoaderDebug::record( $this->sample_record() );

		$this->assertSame( [], LoaderDebug::get_loaders() );
	}

	/**
	 * The callback registered on the aggregation filter merges this copy's records into
	 * whatever other copies have already contributed.
	 *
	 * @return void
	 */
	public function test_aggregation_filter_merges_records() {
		$captured = null;

		when( 'add_action' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );
		when( 'add_filter' )->alias(
			static function ( $hook, $callback ) use ( &$captured ) {
				if ( LoaderDebug::FILTER === $hook ) {
					$captured = $callback;
				}
				return true;
			}
		);

		LoaderDebug::record( $this->sample_record( '/b/inc' ) );

		$this->assertIsCallable( $captured );

		// A record contributed by another copy should be preserved alongside ours.
		$existing = [ [ 'directory' => '/a/inc' ] ];
		$merged   = $captured( $existing );

		$this->assertCount( 2, $merged );
		$this->assertSame( '/a/inc', $merged[0]['directory'] );
		$this->assertSame( '/b/inc', $merged[1]['directory'] );
	}

	/**
	 * render_page() lists each loader and the classes it loaded.
	 *
	 * @return void
	 */
	public function test_render_page_lists_loaders_and_classes() {
		$this->stub_render_environment();

		LoaderDebug::record( $this->sample_record() );

		$output = $this->capture_render();

		$this->assertStringContainsString( 'WP Framework Loaders', $output );
		$this->assertStringContainsString( '/srv/site/wp-content/plugins/demo/inc', $output );
		$this->assertStringContainsString( 'TenupTmp\\Widget', $output );
		$this->assertStringContainsString( 'Check this cache for staleness', $output );

		// The recorded discovery/lookup timings are surfaced on the page.
		$this->assertStringContainsString( 'Discovery time', $output );
		$this->assertStringContainsString( 'Class lookup time', $output );
		$this->assertStringContainsString( '12.30 ms', $output ); // 0.0123s discovery.
		$this->assertStringContainsString( '45.60 ms', $output ); // 0.0456s lookup.
	}

	/**
	 * owner_label() derives a plugin name when the directory sits under the plugins root.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_owner_label_derives_plugin_name() {
		define( 'WP_PLUGIN_DIR', '/srv/site/wp-content/plugins' );

		$method = ( new \ReflectionClass( LoaderDebug::class ) )->getMethod( 'owner_label' );
		$method->setAccessible( true );

		$this->assertSame(
			'Plugin: demo',
			$method->invoke( null, '/srv/site/wp-content/plugins/demo/inc' )
		);
	}

	/**
	 * render_page() reports drift when a staleness check is requested with a valid nonce.
	 *
	 * @return void
	 */
	public function test_render_page_reports_staleness_drift() {
		$this->stub_render_environment();
		when( 'wp_verify_nonce' )->justReturn( true );

		$dir = $this->make_temp_class_dir();

		// The loaded list (in the record) is deliberately out of date versus what is on disk.
		$record            = $this->sample_record( $dir );
		$record['classes'] = [ 'TenupTmp\\Old' ];
		LoaderDebug::record( $record );

		$_GET['check']    = md5( $dir );
		$_GET['_wpnonce'] = 'test';

		$output = $this->capture_render();

		unset( $_GET['check'], $_GET['_wpnonce'] );
		$this->remove_temp_dir( $dir );

		$this->assertStringContainsString( 'Stale', $output );
		$this->assertStringContainsString( 'TenupTmp\\Widget', $output ); // On disk, missing from cache.
		$this->assertStringContainsString( 'TenupTmp\\Old', $output );    // In cache, gone from disk.
		// The drift notice also reports a real, positive live-discovery duration.
		$this->assertMatchesRegularExpression( '/Live discovery took \d[\d.,]* (ms|s)\./', $output );
	}

	/**
	 * render_page() confirms an up-to-date cache and reports how long the live scan took when a
	 * staleness check is requested and the loaded list matches disk.
	 *
	 * @return void
	 */
	public function test_render_page_reports_up_to_date_and_timing() {
		$this->stub_render_environment();
		when( 'wp_verify_nonce' )->justReturn( true );

		$dir = $this->make_temp_class_dir();

		// The loaded list matches what is actually on disk (the single Widget class).
		$record            = $this->sample_record( $dir );
		$record['classes'] = [ 'TenupTmp\\Widget' ];
		LoaderDebug::record( $record );

		$_GET['check']    = md5( $dir );
		$_GET['_wpnonce'] = 'test';

		$output = $this->capture_render();

		unset( $_GET['check'], $_GET['_wpnonce'] );
		$this->remove_temp_dir( $dir );

		$this->assertStringContainsString( 'Up to date', $output );
		// Require a real, positive duration — this must NOT match the "Live discovery took —."
		// placeholder that format_duration() emits for a non-positive/absent value.
		$this->assertMatchesRegularExpression( '/Live discovery took \d[\d.,]* (ms|s)\./', $output );
	}

	/**
	 * cache_state() maps each combination of the record flags to the expected severity and badge.
	 *
	 * @dataProvider cache_state_provider
	 *
	 * @param array<string, bool> $flags            The cache_* flags to set on the record.
	 * @param string              $expected_sev     The expected severity.
	 * @param string              $expected_snippet A substring expected in the badge.
	 *
	 * @return void
	 */
	public function test_cache_state_resolves_expected_states( array $flags, string $expected_sev, string $expected_snippet ) {
		$state = $this->invoke_protected( 'cache_state', [ array_merge( $this->sample_record(), $flags ) ] );

		$this->assertSame( $expected_sev, $state['severity'] );
		$this->assertStringContainsString( $expected_snippet, $state['badge'] );
	}

	/**
	 * Data for test_cache_state_resolves_expected_states.
	 *
	 * @return array<string, array{0: array<string, bool>, 1: string, 2: string}>
	 */
	public function cache_state_provider(): array {
		return [
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
		];
	}

	/**
	 * legacy_files() reports files in the cache directory that are not the current cache file,
	 * and nothing when the directory is clean or absent.
	 *
	 * @return void
	 */
	public function test_legacy_files_detects_unexpected_files() {
		$dir       = $this->make_temp_class_dir();
		$cache_dir = $dir . '/class-loader-cache';
		mkdir( $cache_dir );

		$current = $cache_dir . '/class-loader-cache-v2.php';
		file_put_contents( $current, '<?php return array();' );

		// A clean directory (only the current file) reports nothing.
		$this->assertSame( [], $this->invoke_protected( 'legacy_files', [ $current ] ) );

		// A leftover file from an older version is reported.
		file_put_contents( $cache_dir . '/discoverer-cache-TenupFramework', 'x' );
		$found = $this->invoke_protected( 'legacy_files', [ $current ] );
		$this->assertContains( 'discoverer-cache-TenupFramework', $found );
		$this->assertNotContains( 'class-loader-cache-v2.php', $found );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * legacy_files() is empty when the cache directory does not exist.
	 *
	 * @return void
	 */
	public function test_legacy_files_empty_when_directory_absent() {
		$missing = sys_get_temp_dir() . '/tenup_missing_' . uniqid( '', true ) . '/class-loader-cache-v2.php';

		$this->assertSame( [], $this->invoke_protected( 'legacy_files', [ $missing ] ) );
	}

	/**
	 * format_duration() picks a sensible unit and renders a placeholder for non-positive input.
	 *
	 * @dataProvider duration_provider
	 *
	 * @param mixed  $seconds  The duration in seconds.
	 * @param string $expected The expected rendered string.
	 *
	 * @return void
	 */
	public function test_format_duration( $seconds, string $expected ) {
		$this->assertSame( $expected, $this->invoke_protected( 'format_duration', [ $seconds ] ) );
	}

	/**
	 * Data for test_format_duration.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public function duration_provider(): array {
		return [
			'zero'         => [ 0.0, '—' ],
			'negative'     => [ -0.005, '—' ],
			'non-numeric'  => [ 'nope', '—' ],
			'not-a-number' => [ NAN, '—' ],
			'infinite'     => [ INF, '—' ],
			'sub-milli'    => [ 0.0004, '0.400 ms' ],
			'milliseconds' => [ 0.0123, '12.30 ms' ],
			'seconds'      => [ 1.5, '1.50 s' ],
		];
	}

	/**
	 * cache_detail() renders "Built <age> ago · <size> · <utc>" with the build time in UTC.
	 *
	 * @return void
	 */
	public function test_cache_detail_shows_size_and_utc_build_time() {
		when( 'human_time_diff' )->justReturn( '5 minutes' );
		when( 'size_format' )->alias( static fn( $bytes ) => $bytes . ' B' );

		$dir       = $this->make_temp_class_dir();
		$cache_dir = $dir . '/class-loader-cache';
		mkdir( $cache_dir );
		$cache_file = $cache_dir . '/class-loader-cache-v2.php';
		file_put_contents( $cache_file, '<?php return array();' );

		$detail = $this->invoke_protected( 'cache_detail', [ [ 'cache_file' => $cache_file ] ] );

		$this->assertStringContainsString( 'Built 5 minutes ago', $detail );
		// The absolute build time is the file mtime rendered in UTC as the trailing segment.
		$expected_utc = gmdate( 'Y-m-d H:i:s', (int) filemtime( $cache_file ) ) . ' UTC';
		$this->assertStringContainsString( '· ' . $expected_utc, $detail );
		$this->assertMatchesRegularExpression( '/·\s*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC$/', $detail );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * Invoke a protected static method on LoaderDebug via reflection.
	 *
	 * @param string       $method The method name.
	 * @param array<mixed> $args   The arguments.
	 *
	 * @return mixed
	 */
	private function invoke_protected( string $method, array $args ) {
		$reflection = ( new \ReflectionClass( LoaderDebug::class ) )->getMethod( $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	/**
	 * Stub everything render_page() touches, with the tooling enabled and the current user
	 * capable. apply_filters returns this copy's records for the aggregation filter.
	 *
	 * @return void
	 */
	private function stub_render_environment() {
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'current_user_can' )->justReturn( true );
		when( 'sanitize_text_field' )->returnArg( 1 );
		when( 'wp_unslash' )->returnArg( 1 );
		when( 'admin_url' )->alias(
			static function ( $path = '' ) {
				return 'http://example.test/wp-admin/' . $path;
			}
		);
		when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '?' . http_build_query( (array) $args );
			}
		);
		when( 'wp_nonce_url' )->returnArg( 1 );
		when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				if ( LoaderDebug::FILTER === $hook ) {
					return LoaderDebug::get_loaders();
				}
				return $value;
			}
		);
	}

	/**
	 * Capture the output of render_page().
	 *
	 * @return string
	 */
	private function capture_render(): string {
		ob_start();
		LoaderDebug::render_page();
		return (string) ob_get_clean();
	}

	/**
	 * Create a temporary directory containing a single discoverable class.
	 *
	 * @return string The created directory path.
	 */
	private function make_temp_class_dir(): string {
		$dir = sys_get_temp_dir() . '/tenup_loader_debug_' . uniqid( '', true );
		mkdir( $dir );
		file_put_contents( $dir . '/Widget.php', "<?php\nnamespace TenupTmp;\nclass Widget {}\n" );

		return $dir;
	}

	/**
	 * Recursively remove a temporary directory.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function remove_temp_dir( string $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}
