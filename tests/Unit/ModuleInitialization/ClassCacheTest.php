<?php
/**
 * Tests the class-cache read and write behaviour of ModuleInitialization.
 *
 * These live in the unit suite deliberately: get_classes(), generate_cache() and
 * discover_live() make no unguarded WordPress calls, so they need no WordPress. Testkit's
 * Unit_Test_Case also runs every test in its own process, which keeps the singleton clean.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\ModuleInitialization;

beforeEach(
	function (): void {
		$this->dir = tenup_temp_class_dir();
	}
);

afterEach(
	function (): void {
		tenup_remove_dir( $this->dir );
	}
);

it(
	'writes a readable cache file and returns the discovered classes',
	function (): void {
		$cached = ModuleInitialization::instance()->generate_cache( $this->dir );

		expect( file_exists( tenup_cache_file_path( $this->dir ) ) )->toBeTrue();
		expect( $cached )->toContain( 'TenupTmp\\Widget' );
	}
);

it(
	'reads the cache file when one is present',
	function (): void {
		$module_init = ModuleInitialization::instance();
		$module_init->generate_cache( $this->dir );

		// Tamper with the cache so a sentinel proves the read path used it rather than rescanning.
		file_put_contents( tenup_cache_file_path( $this->dir ), "<?php return array( 'TenupTmp\\\\Sentinel' );" );

		expect( array_values( $module_init->get_classes( $this->dir ) ) )->toBe( [ 'TenupTmp\\Sentinel' ] );
	}
);

it(
	'writes no cache when none exists',
	function (): void {
		$classes = ModuleInitialization::instance()->get_classes( $this->dir );

		expect( $classes )->toContain( 'TenupTmp\\Widget' );
		// The whole point of the read-only runtime: a server can never create a cache it must later invalidate.
		expect( is_dir( $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME ) )->toBeFalse();
	}
);

it(
	'ignores a cache written by an older framework version',
	function (): void {
		$cache_dir = $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME;
		mkdir( $cache_dir );

		// 1.x wrote `discoverer-cache-{id}` as a serialized file under a different name.
		file_put_contents( $cache_dir . '/discoverer-cache-TenupFramework', serialize( [ 'TenupTmp\\Legacy' ] ) );

		$read = ModuleInitialization::instance()->get_classes( $this->dir );

		expect( $read )->not->toContain( 'TenupTmp\\Legacy' );
		expect( $read )->toContain( 'TenupTmp\\Widget' );
	}
);

it(
	'falls back to a live scan when the cache is corrupt',
	function ( string $contents ): void {
		$cache_dir = $this->dir . '/' . ModuleInitialization::CACHE_DIR_NAME;

		if ( ! is_dir( $cache_dir ) ) {
			mkdir( $cache_dir );
		}

		// require() on this raises a ParseError, which the read path must catch rather than fatal.
		file_put_contents( tenup_cache_file_path( $this->dir ), $contents );

		expect( ModuleInitialization::instance()->get_classes( $this->dir ) )->toContain( 'TenupTmp\\Widget' );
	}
)->with(
	[
		'unterminated array' => [ "<?php return array( 'TenupTmp\\\\Widget'" ],
		'truncated file'     => [ '<?php return array( ' ],
	]
);

it(
	'ignores the cache entirely in discover_live()',
	function (): void {
		$module_init = ModuleInitialization::instance();
		$module_init->generate_cache( $this->dir );

		file_put_contents( tenup_cache_file_path( $this->dir ), "<?php return array( 'TenupTmp\\\\Sentinel' );" );

		$live = $module_init->discover_live( $this->dir );

		expect( $live )->toContain( 'TenupTmp\\Widget' );
		expect( $live )->not->toContain( 'TenupTmp\\Sentinel' );
	}
);

it(
	'throws when the directory does not exist',
	function (): void {
		ModuleInitialization::instance()->get_classes( $this->dir . '/does-not-exist-1234567' );
	}
)->throws( RuntimeException::class );
