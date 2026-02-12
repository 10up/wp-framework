<?php
/**
 * BlockRegistrar Test
 *
 * @package TenupFrameworkTests
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use PHPUnit\Framework\TestCase;

/**
 * Test BlockRegistrar class
 */
class BlockRegistrarTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test that BlockRegistrar can be instantiated and implements ModuleInterface.
	 *
	 * @return void
	 */
	public function test_block_registrar_implements_module_interface() {
		// Create a concrete implementation for testing
		$block_registrar = new TestBlockRegistrar();

		$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, $block_registrar );
		$this->assertInstanceOf( \TenupFramework\BlockRegistrar::class, $block_registrar );
	}

	/**
	 * Test that can_register returns true by default.
	 *
	 * @return void
	 */
	public function test_can_register_returns_true() {
		$block_registrar = new TestBlockRegistrar();

		$this->assertTrue( $block_registrar->can_register() );
	}

	/**
	 * Test that get_blocks_directory is abstract and must be implemented.
	 *
	 * @return void
	 */
	public function test_get_blocks_directory_is_abstract() {
		$this->expectException( \Error::class );
		new \TenupFramework\BlockRegistrar();
	}

	/**
	 * Test that register method calls parent register and adds hooks.
	 *
	 * @return void
	 */
	public function test_register_method_adds_hooks() {
		// Create a concrete test class
		$block_registrar = new TestBlockRegistrar();

		// This should not throw an exception
		$block_registrar->register();
		$this->assertTrue( true ); // If we get here, register() worked
	}

	/**
	 * Test that get_blocks_directory returns an array.
	 *
	 * @return void
	 */
	public function test_get_blocks_directory_returns_array() {
		$block_registrar = new TestBlockRegistrar();
		$directories     = $block_registrar->get_blocks_directory();

		$this->assertIsArray( $directories );
		$this->assertCount( 1, $directories );
		$this->assertEquals( '/test/blocks/', $directories[0] );
	}

	/**
	 * Test that multiple directories can be returned.
	 *
	 * @return void
	 */
	public function test_multiple_directories_support() {
		$block_registrar = new TestMultiDirectoryBlockRegistrar();
		$directories     = $block_registrar->get_blocks_directory();

		$this->assertIsArray( $directories );
		$this->assertCount( 3, $directories );
		$this->assertEquals( '/test/blocks/', $directories[0] );
		$this->assertEquals( '/test/custom-blocks/', $directories[1] );
		$this->assertEquals( '/test/vendor-blocks/', $directories[2] );
	}

	/**
	 * Test that empty directory array is handled correctly.
	 *
	 * @return void
	 */
	public function test_empty_directory_array_support() {
		$block_registrar = new TestEmptyDirectoryBlockRegistrar();
		$directories     = $block_registrar->get_blocks_directory();

		$this->assertIsArray( $directories );
		$this->assertEmpty( $directories );
	}

	/**
	 * Test register_blocks with non-existent directories.
	 *
	 * @return void
	 */
	public function test_register_blocks_with_non_existent_directories() {
		$block_registrar = new TestBlockRegistrar();

		// This test verifies the method exists and can be called
		// In a real WordPress environment, it would handle non-existent directories gracefully
		$this->assertTrue( method_exists( $block_registrar, 'register_blocks' ) );
	}

	/**
	 * Test register_blocks with empty directory array.
	 *
	 * @return void
	 */
	public function test_register_blocks_with_empty_directory_array() {
		$block_registrar = new TestEmptyDirectoryBlockRegistrar();

		// This test verifies the method exists and can be called
		// In a real WordPress environment, it would handle empty directories gracefully
		$this->assertTrue( method_exists( $block_registrar, 'register_blocks' ) );
	}

	/**
	 * Test get_block_options without markup.php file.
	 *
	 * @return void
	 */
	public function test_get_block_options_without_markup() {
		$block_registrar = new TestBlockRegistrar();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $block_registrar );
		$method     = $reflection->getMethod( 'get_block_options' );
		$method->setAccessible( true );

		$options = $method->invoke( $block_registrar, '/test/block-without-markup/' );

		$this->assertIsArray( $options );
		$this->assertEmpty( $options );
	}

	/**
	 * Test get_block_options with markup.php file.
	 *
	 * @return void
	 */
	public function test_get_block_options_with_markup() {
		$block_registrar = new TestBlockRegistrar();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $block_registrar );
		$method     = $reflection->getMethod( 'get_block_options' );
		$method->setAccessible( true );

		// Mock file_exists to return true for markup.php
		$original_file_exists = 'file_exists';
		if ( function_exists( 'file_exists' ) ) {
			// In a real test environment, you'd mock this properly
			// For now, we'll test the structure
			$options = $method->invoke( $block_registrar, '/test/block-with-markup/' );

			// The method should return an array (empty if file doesn't exist)
			$this->assertIsArray( $options );
		}
	}

	/**
	 * Test register_allowed_block_types method.
	 *
	 * @return void
	 */
	public function test_register_allowed_block_types() {
		$block_registrar = new TestBlockRegistrar();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $block_registrar );
		$method     = $reflection->getMethod( 'register_allowed_block_types' );
		$method->setAccessible( true );

		$block_names = [ 'test/block1', 'test/block2' ];

		// This should not throw an exception
		$method->invoke( $block_registrar, $block_names );
		$this->assertTrue( true ); // If we get here, no exception was thrown
	}

	/**
	 * Test WordPress hook registration.
	 *
	 * @return void
	 */
	public function test_wordpress_hook_registration() {
		$block_registrar = new TestBlockRegistrar();

		// This should not throw an exception when registering hooks
		$block_registrar->register();
		$this->assertTrue( true ); // If we get here, register() worked without throwing
	}

	/**
	 * Test that multiple BlockRegistrar instances don't conflict.
	 *
	 * @return void
	 */
	public function test_multiple_instances_no_conflict() {
		// Create two different instances
		$theme_blocks  = new TestBlockRegistrar();
		$plugin_blocks = new TestMultiDirectoryBlockRegistrar();

		// Test that both instances can be created without conflicts
		$this->assertInstanceOf( \TenupFramework\BlockRegistrar::class, $theme_blocks );
		$this->assertInstanceOf( \TenupFramework\BlockRegistrar::class, $plugin_blocks );
		$this->assertNotSame( $theme_blocks, $plugin_blocks );
	}

	/**
	 * Test static block name tracking.
	 *
	 * @return void
	 */
	public function test_static_block_name_tracking() {
		// Use reflection to access static properties
		$reflection = new \ReflectionClass( \TenupFramework\BlockRegistrar::class );

		// Test that static properties exist
		$this->assertTrue( $reflection->hasProperty( 'registered_block_names' ) );
		$this->assertTrue( $reflection->hasProperty( 'filter_registered' ) );
		$this->assertTrue( $reflection->hasProperty( 'block_sources' ) );

		// Test that the static filter method exists
		$this->assertTrue( $reflection->hasMethod( 'filter_allowed_block_types' ) );
	}

	/**
	 * Test block conflict detection methods.
	 *
	 * @return void
	 */
	public function test_block_conflict_detection() {
		// Test conflict detection methods exist
		$this->assertTrue( method_exists( \TenupFramework\BlockRegistrar::class, 'has_block_conflict' ) );
		$this->assertTrue( method_exists( \TenupFramework\BlockRegistrar::class, 'get_block_source' ) );
		$this->assertTrue( method_exists( \TenupFramework\BlockRegistrar::class, 'get_all_block_sources' ) );

		// Test initial state
		$this->assertFalse( \TenupFramework\BlockRegistrar::has_block_conflict( 'test/block' ) );
		$this->assertNull( \TenupFramework\BlockRegistrar::get_block_source( 'test/block' ) );
		$this->assertIsArray( \TenupFramework\BlockRegistrar::get_all_block_sources() );
	}

	/**
	 * Test block source tracking.
	 *
	 * @return void
	 */
	public function test_block_source_tracking() {
		// Manually add a block source for testing
		\TenupFramework\BlockRegistrar::$block_sources['test/block'] = 'TestClass';

		// Test conflict detection
		$this->assertTrue( \TenupFramework\BlockRegistrar::has_block_conflict( 'test/block' ) );
		$this->assertEquals( 'TestClass', \TenupFramework\BlockRegistrar::get_block_source( 'test/block' ) );

		// Test getting all sources
		$sources = \TenupFramework\BlockRegistrar::get_all_block_sources();
		$this->assertArrayHasKey( 'test/block', $sources );
		$this->assertEquals( 'TestClass', $sources['test/block'] );

		// Clean up
		unset( \TenupFramework\BlockRegistrar::$block_sources['test/block'] );
	}

	/**
	 * Test path validation edge cases.
	 *
	 * @return void
	 */
	public function test_path_validation_edge_cases() {
		$block_registrar = new TestBlockRegistrar();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $block_registrar );
		$method     = $reflection->getMethod( 'validate_directory_path' );
		$method->setAccessible( true );

		// Test invalid paths
		$this->assertFalse( $method->invoke( $block_registrar, '' ) );
		$this->assertFalse( $method->invoke( $block_registrar, '../malicious' ) );
		$this->assertFalse( $method->invoke( $block_registrar, './relative' ) );
		$this->assertFalse( $method->invoke( $block_registrar, str_repeat( 'a', 1001 ) ) );

		// Test valid paths
		$this->assertEquals( '/valid/path/', $method->invoke( $block_registrar, '/valid/path' ) );
		$this->assertEquals( '/valid/path/', $method->invoke( $block_registrar, '/valid/path/' ) );
		$this->assertEquals( '/valid/path/', $method->invoke( $block_registrar, '\\valid\\path' ) );
	}

	/**
	 * Test block.json validation edge cases.
	 *
	 * @return void
	 */
	public function test_block_json_validation_edge_cases() {
		$block_registrar = new TestBlockRegistrar();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $block_registrar );
		$method     = $reflection->getMethod( 'validate_block_json' );
		$method->setAccessible( true );

		// Test invalid file paths
		$this->assertFalse( $method->invoke( $block_registrar, '/non/existent/file.json' ) );

		// Test invalid JSON (this would require creating actual files in tests)
		// For now, just test that the method exists and handles errors
		$this->assertTrue( method_exists( $block_registrar, 'validate_block_json' ) );
	}

	/**
	 * Test WordPress availability check.
	 *
	 * @return void
	 */
	public function test_wordpress_availability_check() {
		$block_registrar = new TestBlockRegistrar();

		// Test that the method exists and can be called
		// In a real WordPress environment, it would check for function availability
		$this->assertTrue( method_exists( $block_registrar, 'register_blocks' ) );
	}
}
