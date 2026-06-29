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
			'directory'      => $directory,
			'cache_file'     => $directory . '/class-loader-cache/class-loader-cache-v2.php',
			'cache_exists'   => false,
			'cache_used'     => false,
			'cache_disabled' => false,
			'classes'        => [ 'TenupTmp\\Widget' ],
			'version'        => '1.3.0',
			'reference'      => 'abcdef1234567890',
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
