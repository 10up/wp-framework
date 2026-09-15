<?php
/**
 * Tests for the ReadOnlyFileDiscoverCacheDriver.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests\Cache;

use PHPUnit\Framework\TestCase;
use TenupFramework\Cache\ReadOnlyFileDiscoverCacheDriver;
use TenupFrameworkTests\FrameworkTestSetup;

/**
 * ReadOnlyFileDiscoverCacheDriverTest class.
 */
class ReadOnlyFileDiscoverCacheDriverTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * The temporary working directory for a test.
	 *
	 * @var string
	 */
	private $dir = '';

	/**
	 * Create a temporary directory to act as the (already existing) cache directory.
	 *
	 * @return string
	 */
	private function make_dir(): string {
		$this->dir = sys_get_temp_dir() . '/tenup_ro_driver_' . uniqid( '', true );
		mkdir( $this->dir );

		return $this->dir;
	}

	/**
	 * Remove the temporary directory after a test.
	 *
	 * @return void
	 */
	protected function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( '' !== $this->dir && is_dir( $this->dir ) ) {
			$files = glob( $this->dir . '/*' );
			if ( false !== $files ) {
				array_map( 'unlink', $files );
			}
			rmdir( $this->dir );
		}
		$this->dir = '';

		parent::tearDown();
	}

	/**
	 * The constructor must not create the cache directory: the runtime never writes.
	 *
	 * @return void
	 */
	public function test_constructor_does_not_create_directory() {
		$missing = sys_get_temp_dir() . '/tenup_ro_missing_' . uniqid( '', true );

		new ReadOnlyFileDiscoverCacheDriver( $missing, false, 'cache.php' );

		$this->assertDirectoryDoesNotExist( $missing );
	}

	/**
	 * put() is a no-op: nothing is written to disk.
	 *
	 * @return void
	 */
	public function test_put_writes_nothing() {
		$dir    = $this->make_dir();
		$driver = new ReadOnlyFileDiscoverCacheDriver( $dir, false, 'cache.php' );

		$driver->put( 'id', [ 'Foo\\Bar' ] );

		$this->assertFalse( $driver->has( 'id' ) );
		$this->assertFileDoesNotExist( $dir . '/cache.php' );
	}

	/**
	 * forget() is a no-op: an existing cache file is left untouched.
	 *
	 * @return void
	 */
	public function test_forget_deletes_nothing() {
		$dir = $this->make_dir();
		file_put_contents( $dir . '/cache.php', '<?php return array();' );

		$driver = new ReadOnlyFileDiscoverCacheDriver( $dir, false, 'cache.php' );
		$driver->forget( 'id' );

		$this->assertFileExists( $dir . '/cache.php' );
	}

	/**
	 * has()/get() read an existing cache file produced with the same settings the
	 * build-time generator uses (serialize = false, an explicit filename).
	 *
	 * @return void
	 */
	public function test_has_and_get_read_an_existing_file() {
		$dir = $this->make_dir();
		file_put_contents( $dir . '/cache.php', "<?php return array( 'Foo\\\\Bar' );" );

		$driver = new ReadOnlyFileDiscoverCacheDriver( $dir, false, 'cache.php' );

		$this->assertTrue( $driver->has( 'id' ) );
		$this->assertSame( [ 'Foo\\Bar' ], $driver->get( 'id' ) );
	}
}
