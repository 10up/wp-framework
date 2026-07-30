<?php
/**
 * Tests for the ReadOnlyFileDiscoverCacheDriver.
 *
 * Touches only the filesystem, so it runs in the unit suite without WordPress.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\Cache\ReadOnlyFileDiscoverCacheDriver;

beforeEach(
	function (): void {
		$this->dir = sys_get_temp_dir() . '/tenup_ro_driver_' . uniqid( '', true );
		mkdir( $this->dir );
	}
);

afterEach(
	function (): void {
		if ( ! is_dir( $this->dir ) ) {
			return;
		}

		$files = glob( $this->dir . '/*' );

		if ( false !== $files ) {
			array_map( 'unlink', $files );
		}

		rmdir( $this->dir );
	}
);

it(
	'does not create the cache directory in the constructor',
	function (): void {
		$missing = sys_get_temp_dir() . '/tenup_ro_missing_' . uniqid( '', true );

		new ReadOnlyFileDiscoverCacheDriver( $missing, false, 'cache.php' );

		expect( is_dir( $missing ) )->toBeFalse();
	}
);

it(
	'writes nothing when put() is called',
	function (): void {
		$driver = new ReadOnlyFileDiscoverCacheDriver( $this->dir, false, 'cache.php' );

		$driver->put( 'id', [ 'Foo\\Bar' ] );

		expect( $driver->has( 'id' ) )->toBeFalse();
		expect( file_exists( $this->dir . '/cache.php' ) )->toBeFalse();
	}
);

it(
	'deletes nothing when forget() is called',
	function (): void {
		file_put_contents( $this->dir . '/cache.php', '<?php return array();' );

		$driver = new ReadOnlyFileDiscoverCacheDriver( $this->dir, false, 'cache.php' );
		$driver->forget( 'id' );

		expect( file_exists( $this->dir . '/cache.php' ) )->toBeTrue();
	}
);

it(
	'reads an existing cache file written with the generator settings',
	function (): void {
		// serialize = false and an explicit filename, matching generate_cache().
		file_put_contents( $this->dir . '/cache.php', "<?php return array( 'Foo\\\\Bar' );" );

		$driver = new ReadOnlyFileDiscoverCacheDriver( $this->dir, false, 'cache.php' );

		expect( $driver->has( 'id' ) )->toBeTrue();
		expect( $driver->get( 'id' ) )->toBe( [ 'Foo\\Bar' ] );
	}
);
