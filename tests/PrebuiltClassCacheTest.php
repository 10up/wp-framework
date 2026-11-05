<?php
/**
 * Tests for the prebuilt class cache.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use Brain\Monkey\Actions;
use PHPUnit\Framework\TestCase;
use TenupFramework\Cache\ReadOnlyFileDiscoverCacheDriver;
use TenupFramework\ModuleInitialization;

/**
 * The cache `tenup-framework-generate-class-cache` builds, and get_classes() reading it.
 */
class PrebuiltClassCacheTest extends TestCase {

	use FrameworkTestSetup {
		setUp as framework_set_up;
		tearDown as framework_tear_down;
	}

	/**
	 * A scratch discovery directory holding one class, removed in tearDown().
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Create the scratch directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->framework_set_up();

		$this->dir = sys_get_temp_dir() . '/tenup-framework-cache-' . uniqid();
		mkdir( $this->dir );
		file_put_contents( $this->dir . '/Example.php', "<?php\nnamespace PrebuiltCacheFixture;\nclass Example {}\n" );
	}

	/**
	 * Remove the scratch directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->remove( $this->dir );
		$this->framework_tear_down();
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $path Directory to delete.
	 *
	 * @return void
	 */
	private function remove( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( (array) scandir( $path ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full = $path . '/' . $entry;
			is_dir( $full ) ? $this->remove( $full ) : unlink( $full );
		}

		rmdir( $path );
	}

	/**
	 * The path the prebuilt cache lives at.
	 *
	 * @return string
	 */
	private function cache_file(): string {
		return $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME . '/' . ModuleInitialization::CACHE_FILENAME;
	}

	/**
	 * Write a prebuilt cache by hand, listing a class that isn't in the directory,
	 * so a test can tell a cache read from a discovery.
	 *
	 * @param string $contents File contents.
	 *
	 * @return void
	 */
	private function write_cache( string $contents ): void {
		mkdir( dirname( $this->cache_file() ) );
		file_put_contents( $this->cache_file(), $contents );
	}

	/**
	 * generate_cache() writes the file get_classes() then reads.
	 *
	 * @return void
	 */
	public function test_generated_cache_round_trips() {
		$generated = ModuleInitialization::instance()->generate_cache( $this->dir );

		$this->assertFileExists( $this->cache_file() );
		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( $generated ) );
		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
	}

	/**
	 * A prebuilt cache is read on VIP, where the runtime cache is never used.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_prebuilt_cache_is_read_on_vip() {
		define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		$this->write_cache( "<?php return [ 'FromCache\\\\Only' ];" );

		$this->assertSame( [ 'FromCache\Only' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
	}

	/**
	 * Without a prebuilt cache, discovery runs as before and nothing is written on VIP.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_missing_cache_discovers_and_writes_nothing_on_vip() {
		define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
		$this->assertDirectoryDoesNotExist( $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME );
	}

	/**
	 * A cache that can't be loaded falls back to discovery and fires an action.
	 *
	 * @return void
	 */
	public function test_corrupt_cache_falls_back_to_discovery() {
		$this->write_cache( '<?php return [ "truncated' );

		Actions\expectDone( 'tenup_framework_cache_load_failed' )->once();

		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
	}

	/**
	 * A cache file that doesn't return an array falls back too.
	 *
	 * @return void
	 */
	public function test_non_array_cache_falls_back_to_discovery() {
		$this->write_cache( '<?php return 42;' );

		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
	}

	/**
	 * TENUP_FRAMEWORK_DISABLE_CLASS_CACHE ignores a prebuilt cache.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_disable_constant_ignores_prebuilt_cache() {
		define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true );
		$this->write_cache( "<?php return [ 'FromCache\\\\Only' ];" );

		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], array_values( ModuleInitialization::instance()->get_classes( $this->dir ) ) );
	}

	/**
	 * The command builds a cache for each directory it's given and fails without any.
	 *
	 * @return void
	 */
	public function test_command_generates_the_cache() {
		$bin = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/bin/tenup-framework-generate-class-cache' );

		exec( $bin . ' ' . escapeshellarg( $this->dir ) . ' 2>&1', $output, $code );
		$this->assertSame( 0, $code, implode( "\n", $output ) );
		$this->assertFileExists( $this->cache_file() );
		$this->assertSame( [ 'PrebuiltCacheFixture\Example' ], require $this->cache_file() );

		exec( $bin . ' 2>&1', $output, $code );
		$this->assertSame( 1, $code );
	}

	/**
	 * The read-only driver neither creates its directory nor writes.
	 *
	 * @return void
	 */
	public function test_read_only_driver_never_writes() {
		$cache_dir = $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME;
		$driver    = new ReadOnlyFileDiscoverCacheDriver( $cache_dir, false, ModuleInitialization::CACHE_FILENAME );

		$driver->put( ModuleInitialization::CACHE_ID, [ 'Anything' ] );

		$this->assertDirectoryDoesNotExist( $cache_dir );
	}
}
