<?php
/**
 * Tests the loader debug records init_classes() hands to LoaderDebug in the admin.
 *
 * Mantle's Admin_Screen trait sets the current screen so is_admin() is true for every test in
 * this file, and restores it afterwards. That replaces the Brain Monkey is_admin() stub the
 * previous suite relied on.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use Mantle\Testing\Concerns\Admin_Screen;
use TenupFramework\Debug\LoaderDebug;
use TenupFramework\ModuleInitialization;

uses( Admin_Screen::class );

beforeEach(
	function (): void {
		tenup_reset_framework_state();

		$this->dir = tenup_temp_class_dir();
	}
);

afterEach(
	function (): void {
		tenup_remove_dir( $this->dir );
		tenup_reset_framework_state();
	}
);

it(
	'records a loader for the directory it discovered',
	function (): void {
		ModuleInitialization::instance()->init_classes( $this->dir );

		$loaders = LoaderDebug::get_loaders();

		expect( $loaders )->toHaveCount( 1 );
		expect( $loaders[0]['directory'] )->toBe( $this->dir );
		expect( $loaders[0]['classes'] )->toContain( 'TenupTmp\\Widget' );
	}
);

it(
	'records live discovery timing when no cache exists',
	function (): void {
		ModuleInitialization::instance()->init_classes( $this->dir );

		$loader = LoaderDebug::get_loaders()[0];

		expect( $loader['cache_used'] )->toBeFalse();
		// The never-wired default is 0.0, so strictly positive proves the instrumentation ran.
		expect( $loader['discovery_seconds'] )->toBeFloat()->toBeGreaterThan( 0.0 );
		expect( $loader['lookup_seconds'] )->toBeFloat()->toBeGreaterThan( 0.0 );
	}
);

it(
	'reports the cache as in use when one is present',
	function (): void {
		$module_init = ModuleInitialization::instance();
		$module_init->generate_cache( $this->dir );

		$module_init->init_classes( $this->dir );

		$loader = LoaderDebug::get_loaders()[0];

		expect( $loader['cache_used'] )->toBeTrue();
		expect( $loader['discovery_seconds'] )->toBeGreaterThan( 0.0 );
	}
);

it(
	'flags a corrupt cache as failed rather than in use',
	function (): void {
		$module_init = ModuleInitialization::instance();
		$module_init->generate_cache( $this->dir );
		file_put_contents( tenup_cache_file_path( $this->dir ), '<?php return array( ' );

		$module_init->init_classes( $this->dir );

		$loader = LoaderDebug::get_loaders()[0];

		expect( $loader['cache_failed'] )->toBeTrue();
		expect( $loader['cache_used'] )->toBeFalse();
		// Still found the real class on disk, because it fell back to a live scan.
		expect( $loader['classes'] )->toContain( 'TenupTmp\\Widget' );
	}
);

it(
	'keeps one record per directory when init_classes() runs twice',
	function (): void {
		$module_init = ModuleInitialization::instance();

		$module_init->init_classes( $this->dir );
		$module_init->init_classes( $this->dir );

		expect( LoaderDebug::get_loaders() )->toHaveCount( 1 );
	}
);
