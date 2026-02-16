<?php
/**
 * Test Class for Emoji
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFrameworkTests\Core;

use PHPUnit\Framework\TestCase;
use TenupFramework\Core\Emoji;
use TenupFrameworkTests\FrameworkTestSetup;

/**
 * Test Class for Emoji
 *
 * @package TenupFramework
 */
class EmojiTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test that Emoji implements ModuleInterface.
	 *
	 * @return void
	 */
	public function test_implements_module_interface() {
		$emoji = new Emoji();

		$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, $emoji );
	}

	/**
	 * Test that Emoji can be registered.
	 *
	 * @return void
	 */
	public function test_can_register() {
		$emoji = new Emoji();

		$this->assertTrue( $emoji->can_register() );
	}

	/**
	 * Test that Emoji has correct load order.
	 *
	 * @return void
	 */
	public function test_load_order() {
		$emoji = new Emoji();

		$this->assertEquals( 5, $emoji->load_order() );
	}

	/**
	 * Test that register method can be called without errors.
	 *
	 * @return void
	 */
	public function test_register_can_be_called() {
		$emoji = new Emoji();

		// Mock WordPress functions to prevent actual function calls
		\Brain\Monkey\Functions\when( 'remove_action' )->justReturn( true );
		\Brain\Monkey\Functions\when( 'remove_filter' )->justReturn( true );
		\Brain\Monkey\Functions\when( 'add_filter' )->justReturn( true );

		// This should not throw any exceptions
		$emoji->register();

		// If we get here, the method executed successfully
		$this->assertTrue( true );
	}

	/**
	 * Test that register method exists and is callable.
	 *
	 * @return void
	 */
	public function test_register_method_exists() {
		$emoji = new Emoji();

		$this->assertTrue( method_exists( $emoji, 'register' ) );
		$this->assertTrue( is_callable( [ $emoji, 'register' ] ) );
	}

	/**
	 * Test that Emoji has the expected WordPress function calls in register method.
	 *
	 * @return void
	 */
	public function test_register_method_contains_expected_calls() {
		$reflection = new \ReflectionClass( Emoji::class );
		$method     = $reflection->getMethod( 'register' );
		$filename   = $method->getFileName();
		$start_line = $method->getStartLine();
		$end_line   = $method->getEndLine();

		// Read the method source code
		$lines         = file( $filename );
		$method_source = implode( '', array_slice( $lines, $start_line - 1, $end_line - $start_line + 1 ) );

		// Verify the method contains the expected remove_action calls
		$this->assertStringContainsString( "remove_action( 'wp_head', 'print_emoji_detection_script', 7 )", $method_source );
		$this->assertStringContainsString( "remove_action( 'admin_print_scripts', 'print_emoji_detection_script' )", $method_source );
		$this->assertStringContainsString( "remove_action( 'wp_print_styles', 'print_emoji_styles' )", $method_source );
		$this->assertStringContainsString( "remove_action( 'admin_print_styles', 'print_emoji_styles' )", $method_source );

		// Verify the method contains the expected remove_filter calls
		$this->assertStringContainsString( "remove_filter( 'the_content_feed', 'wp_staticize_emoji' )", $method_source );
		$this->assertStringContainsString( "remove_filter( 'comment_text_rss', 'wp_staticize_emoji' )", $method_source );
		$this->assertStringContainsString( "remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' )", $method_source );

		// Verify the method contains the expected add_filter calls
		$this->assertStringContainsString( "add_filter( 'tiny_mce_plugins', [ \$this, 'disable_emojis_tinymce' ] )", $method_source );
		$this->assertStringContainsString( "add_filter( 'wp_resource_hints', [ \$this, 'disable_emoji_dns_prefetch' ], 10, 2 )", $method_source );
	}

	/**
	 * Test that disable_emojis_tinymce method exists and is callable.
	 *
	 * @return void
	 */
	public function test_disable_emojis_tinymce_method_exists() {
		$emoji = new Emoji();

		$this->assertTrue( method_exists( $emoji, 'disable_emojis_tinymce' ) );
		$this->assertTrue( is_callable( [ $emoji, 'disable_emojis_tinymce' ] ) );
	}

	/**
	 * Test that disable_emoji_dns_prefetch method exists and is callable.
	 *
	 * @return void
	 */
	public function test_disable_emoji_dns_prefetch_method_exists() {
		$emoji = new Emoji();

		$this->assertTrue( method_exists( $emoji, 'disable_emoji_dns_prefetch' ) );
		$this->assertTrue( is_callable( [ $emoji, 'disable_emoji_dns_prefetch' ] ) );
	}

	/**
	 * Test disable_emojis_tinymce method functionality.
	 *
	 * @return void
	 */
	public function test_disable_emojis_tinymce_functionality() {
		$emoji = new Emoji();

		// Test with wpemoji plugin present
		$plugins_with_emoji = [ 'wordpress', 'wpemoji', 'media' ];
		$result             = $emoji->disable_emojis_tinymce( $plugins_with_emoji );

		$this->assertNotContains( 'wpemoji', $result );
		$this->assertContains( 'wordpress', $result );
		$this->assertContains( 'media', $result );

		// Test with wpemoji plugin not present
		$plugins_without_emoji = [ 'wordpress', 'media' ];
		$result                = $emoji->disable_emojis_tinymce( $plugins_without_emoji );

		$this->assertEquals( $plugins_without_emoji, $result );
	}

	/**
	 * Test disable_emoji_dns_prefetch method functionality.
	 *
	 * @return void
	 */
	public function test_disable_emoji_dns_prefetch_functionality() {
		$emoji = new Emoji();

		// Mock apply_filters for emoji_svg_url
		\Brain\Monkey\Filters\expectApplied( 'emoji_svg_url' )
			->once()
			->andReturn( 'https://s.w.org/images/core/emoji/2/svg/' );

		$urls = [
			'https://fonts.googleapis.com',
			'https://s.w.org/images/core/emoji/2/svg/',
			'https://example.com',
		];

		$result = $emoji->disable_emoji_dns_prefetch( $urls, 'dns-prefetch' );

		$this->assertNotContains( 'https://s.w.org/images/core/emoji/2/svg/', $result );
		$this->assertContains( 'https://fonts.googleapis.com', $result );
		$this->assertContains( 'https://example.com', $result );

		// Test with different relation type
		$result = $emoji->disable_emoji_dns_prefetch( $urls, 'preconnect' );
		$this->assertEquals( $urls, $result );
	}

	/**
	 * Test that Emoji can be instantiated multiple times.
	 *
	 * @return void
	 */
	public function test_multiple_instances() {
		$emoji_1 = new Emoji();
		$emoji_2 = new Emoji();

		$this->assertInstanceOf( Emoji::class, $emoji_1 );
		$this->assertInstanceOf( Emoji::class, $emoji_2 );
		$this->assertNotSame( $emoji_1, $emoji_2 );
	}

	/**
	 * Test that Emoji uses the Module trait.
	 *
	 * @return void
	 */
	public function test_uses_module_trait() {
		$emoji = new Emoji();

		// Check that the class has the methods from the Module trait
		$this->assertTrue( method_exists( $emoji, 'load_order' ) );
		$this->assertTrue( method_exists( $emoji, 'can_register' ) );
		$this->assertTrue( method_exists( $emoji, 'register' ) );
	}
}
