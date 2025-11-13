<?php
/**
 * Test Class
 *
 * @package TenupScaffold
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\stubs;

/**
 * Test Class
 */
class ModuleInitializationTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Ensure we can find the right classes.
	 *
	 * @return void
	 */
	public function test_it_can_find_classes() {
		$class   = \TenupFramework\ModuleInitialization::instance();
		$classes = $class->get_classes( dirname( __DIR__, 1 ) . '/src/' );

		// Check that we have the concrete classes we expect to see.
		$this->assertContains( 'TenupFramework\PostTypes\AbstractPostType', $classes );
		$this->assertContains( 'TenupFramework\PostTypes\AbstractCorePostType', $classes );
		$this->assertContains( 'TenupFramework\Taxonomies\AbstractTaxonomy', $classes );
		$this->assertContains( 'TenupFramework\ModuleInitialization', $classes );
	}

	/**
	 * Ensure we can find the right classes.
	 *
	 * @return void
	 */
	public function test_it_can_find_classes_to_register() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$class->init_classes( dirname( __DIR__, 1 ) . '/src/' );
		$classes = $class->get_all_classes();

		// Check that we have only classes that extend Module and more than 0.
		$this->assertGreaterThanOrEqual( 0, count( $classes ) );
	}

	/**
	 * Ensure an exception is thrown when a directory does not exist.
	 *
	 * @return void
	 */
	public function test_that_an_exception_is_thrown_when_a_directory_does_not_exist() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$this->expectException( \RuntimeException::class );
		$class->init_classes( dirname( __DIR__, 1 ) . '/src/does-not-exist-1234567/' );
	}

	/**
	 * Ensure an exception is thrown when a directory is not passed.
	 *
	 * @return void
	 */
	public function test_that_an_exception_is_thrown_when_a_directory_is_not_passed() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$this->expectException( \RuntimeException::class );
		$class->init_classes();
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_instance_returns_same_instance() {
		$instance1 = \TenupFramework\ModuleInitialization::instance();
		$instance2 = \TenupFramework\ModuleInitialization::instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_get_classes_returns_classes_from_directory() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$classes     = $module_init->get_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$this->assertIsArray( $classes );
		$this->assertNotEmpty( $classes );
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_init_classes_initializes_classes_in_correct_order() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$classes = $module_init->get_all_classes();
		$this->assertNotEmpty( $classes );
		$this->assertNotContains( 'TenupFramework\Taxonomies\AbstractTaxonomy', $classes );
		$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, reset( $classes ) );
	}

	/**
	 * Ensure that we can return an instantiated class vie get_module.
	 *
	 * @return void
	 */
	public function test_get_module_returns_instantiated_class() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$module = \TenupFramework\ModuleInitialization::get_module( 'TenupFrameworkTestClasses\PostTypes\Demo' );
		$this->assertInstanceOf( \TenupFrameworkTestClasses\PostTypes\Demo::class, $module );

		$module = \TenupFramework\ModuleInitialization::get_module( 'TenupFrameworkTestClasses\DoesntExist' );
		$this->assertFalse( $module );
	}

	/**
	 * Test that only classes implementing ModuleInterface are initialized.
	 *
	 * @return void
	 */
	public function test_only_classes_implementing_module_interface_are_initialized() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );

		$this->assertTrue( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-posttypes-demo' ) > 0, 'Demo was not initialized.' );
		$this->assertFalse( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-standalone-standalone' ) > 0, 'Standalone class was initialized.' );
	}

	/**
	 * Validate if the classes are fully loadable.
	 *
	 * @return void
	 */
	public function testIsClassFullyLoadable() {
		$module_init = \TenupFramework\ModuleInitialization::instance();

		$this->assertInstanceOf( 'ReflectionClass', $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\BaseClass' ) );
		$this->assertInstanceOf( 'ReflectionClass', $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\ChildClass' ) );
		$this->assertFalse( $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\InvalidChildClass' ) );
	}


	/**
	 * Ensure it returns false if VIP_GO_APP_ENVIRONMENT is defined.
	 *
	 * @return void
	 */
	public function test_should_use_cache_returns_false_when_vip_env_is_defined() {
		define( 'VIP_GO_APP_ENVIRONMENT', true );
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$reflection = new \ReflectionClass( $module_init );
		$method     = $reflection->getMethod( 'should_use_cache' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $module_init ) );
	}

	/**
	 * Ensure it returns false in non-production or staging environments.
	 *
	 * @return void
	 */
	public function test_should_use_cache_returns_false_in_non_production_or_staging_env() {
		stubs(
			[
				'wp_get_environment_type' => 'development'
			]
		);

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$reflection = new \ReflectionClass( $module_init );
		$method     = $reflection->getMethod( 'should_use_cache' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $module_init ) );
	}

	/**
	 * Ensure it returns false when TENUP_FRAMEWORK_DISABLE_CLASS_CACHE is defined.
	 *
	 * @return void
	 */
	public function test_should_use_cache_returns_false_when_disable_class_cache_is_defined() {
		define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true );
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$reflection = new \ReflectionClass( $module_init );
		$method     = $reflection->getMethod( 'should_use_cache' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $module_init ) );
	}

	/**
	 * Ensure it returns true under default conditions.
	 *
	 * @return void
	 */
	public function test_should_use_cache_returns_true_under_default_conditions() {
		stubs(
			[
				'wp_get_environment_type' => 'production'
			]
		);

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$reflection = new \ReflectionClass( $module_init );
		$method     = $reflection->getMethod( 'should_use_cache' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $module_init ) );
	}
}
