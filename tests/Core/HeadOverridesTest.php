<?php
/**
 * Test Class for HeadOverrides
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFrameworkTests\Core;

use PHPUnit\Framework\TestCase;
use TenupFramework\Core\HeadOverrides;
use TenupFrameworkTests\FrameworkTestSetup;

/**
 * Test Class for HeadOverrides
 *
 * @package TenupFramework
 */
class HeadOverridesTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test that HeadOverrides implements ModuleInterface.
	 *
	 * @return void
	 */
	public function test_implements_module_interface() {
		$head_overrides = new HeadOverrides();

		$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, $head_overrides );
	}

	/**
	 * Test that HeadOverrides can be registered.
	 *
	 * @return void
	 */
	public function test_can_register() {
		$head_overrides = new HeadOverrides();

		$this->assertTrue( $head_overrides->can_register() );
	}

	/**
	 * Test that HeadOverrides has correct load order.
	 *
	 * @return void
	 */
	public function test_load_order() {
		$head_overrides = new HeadOverrides();

		$this->assertEquals( 5, $head_overrides->load_order() );
	}

	/**
	 * Test that register method can be called without errors.
	 *
	 * @return void
	 */
	public function test_register_can_be_called() {
		$head_overrides = new HeadOverrides();

		// Mock remove_action to prevent actual WordPress function calls
		\Brain\Monkey\Functions\when( 'remove_action' )->justReturn( true );

		// This should not throw any exceptions
		$head_overrides->register();

		// If we get here, the method executed successfully
		$this->assertTrue( true );
	}

	/**
	 * Test that register method exists and is callable.
	 *
	 * @return void
	 */
	public function test_register_method_exists() {
		$head_overrides = new HeadOverrides();

		$this->assertTrue( method_exists( $head_overrides, 'register' ) );
		$this->assertTrue( is_callable( [ $head_overrides, 'register' ] ) );
	}

	/**
	 * Test that HeadOverrides has the expected WordPress function calls in register method.
	 *
	 * @return void
	 */
	public function test_register_method_contains_expected_calls() {
		$reflection = new \ReflectionClass( HeadOverrides::class );
		$method     = $reflection->getMethod( 'register' );
		$filename   = $method->getFileName();
		$start_line = $method->getStartLine();
		$end_line   = $method->getEndLine();

		// Read the method source code
		$lines         = file( $filename );
		$method_source = implode( '', array_slice( $lines, $start_line - 1, $end_line - $start_line + 1 ) );

		// Verify the method contains the expected remove_action calls
		$this->assertStringContainsString( "remove_action( 'wp_head', 'wp_generator' )", $method_source );
		$this->assertStringContainsString( "remove_action( 'wp_head', 'wlwmanifest_link' )", $method_source );
		$this->assertStringContainsString( "remove_action( 'wp_head', 'rsd_link' )", $method_source );
	}

	/**
	 * Test that HeadOverrides can be instantiated multiple times.
	 *
	 * @return void
	 */
	public function test_multiple_instances() {
		$head_overrides_1 = new HeadOverrides();
		$head_overrides_2 = new HeadOverrides();

		$this->assertInstanceOf( HeadOverrides::class, $head_overrides_1 );
		$this->assertInstanceOf( HeadOverrides::class, $head_overrides_2 );
		$this->assertNotSame( $head_overrides_1, $head_overrides_2 );
	}

	/**
	 * Test that HeadOverrides uses the Module trait.
	 *
	 * @return void
	 */
	public function test_uses_module_trait() {
		$head_overrides = new HeadOverrides();

		// Check that the class has the methods from the Module trait
		$this->assertTrue( method_exists( $head_overrides, 'load_order' ) );
		$this->assertTrue( method_exists( $head_overrides, 'can_register' ) );
		$this->assertTrue( method_exists( $head_overrides, 'register' ) );
	}
}
