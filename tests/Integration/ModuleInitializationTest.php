<?php
/**
 * Tests module discovery and registration against a real WordPress.
 *
 * init_classes() calls sanitize_title() and do_action() unguarded, so unlike the cache tests
 * this half genuinely needs WordPress loaded.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\Debug\LoaderDebug;
use TenupFramework\ModuleInitialization;
use TenupFramework\ModuleInterface;
use TenupFrameworkTestClasses\PostTypes\Demo;

beforeEach(
	function (): void {
		tenup_reset_framework_state();

		$this->fixtures = dirname( __DIR__, 2 ) . '/fixtures/classes';
	}
);

afterEach(
	function (): void {
		tenup_reset_framework_state();
	}
);

it(
	'returns the same instance every time',
	function (): void {
		expect( ModuleInitialization::instance() )->toBe( ModuleInitialization::instance() );
	}
);

it(
	'discovers the framework classes in a directory',
	function (): void {
		$classes = ModuleInitialization::instance()->get_classes( dirname( __DIR__, 2 ) . '/src/' );

		expect( $classes )
		->toContain( 'TenupFramework\PostTypes\AbstractPostType' )
		->toContain( 'TenupFramework\PostTypes\AbstractCorePostType' )
		->toContain( 'TenupFramework\Taxonomies\AbstractTaxonomy' )
		->toContain( 'TenupFramework\ModuleInitialization' );
	}
);

it(
	'registers only classes implementing ModuleInterface',
	function (): void {
		$module_init = ModuleInitialization::instance();
		$module_init->init_classes( $this->fixtures );

		$registered = $module_init->get_all_classes();

		expect( $registered )->not->toBeEmpty();

		foreach ( $registered as $instance ) {
			expect( $instance )->toBeInstanceOf( ModuleInterface::class );
		}
	}
);

it(
	'fires the per-module init action and skips non-modules',
	function (): void {
		ModuleInitialization::instance()->init_classes( $this->fixtures );

		expect( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-posttypes-demo' ) )
		->toBeGreaterThan( 0 );

		// Standalone implements no interface, so it must never be instantiated or announced.
		expect( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-standalone-standalone' ) )
		->toBe( 0 );
	}
);

it(
	'exposes registered modules through get_module()',
	function (): void {
		ModuleInitialization::instance()->init_classes( $this->fixtures );

		expect( ModuleInitialization::get_module( Demo::class ) )->toBeInstanceOf( Demo::class );
		expect( ModuleInitialization::get_module( 'TenupFrameworkTestClasses\DoesntExist' ) )->toBeFalse();
	}
);

it(
	'reflects loadable classes and rejects un-loadable ones',
	function (): void {
		$module_init = ModuleInitialization::instance();

		expect( $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\BaseClass' ) )
		->toBeInstanceOf( ReflectionClass::class );
		expect( $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\ChildClass' ) )
		->toBeInstanceOf( ReflectionClass::class );
		// Extends a parent that does not exist, so reflection throws and false is returned.
		expect( $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\InvalidChildClass' ) )
		->toBeFalse();
	}
);

it(
	'throws when the directory does not exist',
	function (): void {
		ModuleInitialization::instance()->init_classes( dirname( __DIR__, 2 ) . '/src/does-not-exist-1234567/' );
	}
)->throws( RuntimeException::class );

it(
	'throws when no directory is passed',
	function (): void {
		ModuleInitialization::instance()->init_classes();
	}
)->throws( RuntimeException::class );

// The admin-only counterpart lives in ModuleInitialization/LoaderRecordingTest.php, which
// applies Mantle's Admin_Screen trait to make is_admin() true for the whole file.
it(
	'records no loader debug data on the front end',
	function (): void {
		$dir = tenup_temp_class_dir();

		ModuleInitialization::instance()->init_classes( $dir );

		expect( LoaderDebug::get_loaders() )->toBe( [] );

		tenup_remove_dir( $dir );
	}
);
