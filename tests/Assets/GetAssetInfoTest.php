<?php
/**
 * Test Class
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace Assets;

use PHPUnit\Framework\TestCase;
use TenupFramework\Assets\GetAssetInfo;
use TenupFrameworkTests\FrameworkTestSetup;

/**
 * Test Class
 *
 * @package TenupFramework
 */
class GetAssetInfoTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test setup_asset_vars works as expected.
	 *
	 * @return void
	 */
	public function test_setup_asset_vars() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		$asset_info->setup_asset_vars(
			dist_path: 'dist',
			fallback_version: '1.0.0'
		);

		$this->assertEquals( 'dist/', $asset_info->dist_path );
		$this->assertEquals( '1.0.0', $asset_info->fallback_version );
	}

	/**
	 * Test get_asset_info returns an array with version and dependencies.
	 *
	 * @return void
	 */
	public function test_get_asset_info_returns_array_with_version_and_dependencies() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		$asset_info->setup_asset_vars(
			dist_path: dirname( __DIR__, 2 ) . '/fixtures/assets/dist',
			fallback_version: '1.0.0'
		);

		$asset = $asset_info->get_asset_info(
			slug: 'test-script'
		);
		$this->assertIsArray( $asset );
		$this->assertArrayHasKey( 'version', $asset );
		$this->assertArrayHasKey( 'dependencies', $asset );
		$vars = require dirname( __DIR__, 2 ) . '/fixtures/assets/dist/js/test-script.asset.php';
		$this->assertEquals( $vars, $asset );

		$asset = $asset_info->get_asset_info(
			slug: 'test-style'
		);
		$this->assertArrayHasKey( 'version', $asset );
		$this->assertArrayHasKey( 'dependencies', $asset );
		$vars = require dirname( __DIR__, 2 ) . '/fixtures/assets/dist/css/test-style.asset.php';
		$this->assertEquals( $vars, $asset );

		$asset = $asset_info->get_asset_info(
			slug: 'non-existent'
		);

		$this->assertArrayHasKey( 'version', $asset );
		$this->assertArrayHasKey( 'dependencies', $asset );
	}

	/**
	 * Test get_asset_info returns a string when passed a specific dependency.
	 *
	 * @return void
	 */
	public function test_get_asset_info_returns_string_when_passed_specific_dependency() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		$asset_info->setup_asset_vars(
			dist_path: dirname( __DIR__, 2 ) . '/fixtures/assets/dist',
			fallback_version: '1.0.0'
		);

		$vars = require dirname( __DIR__, 2 ) . '/fixtures/assets/dist/js/test-script.asset.php';

		$version = $asset_info->get_asset_info(
			slug: 'test-script',
			attribute: 'version'
		);

		$this->assertEquals( $vars['version'], $version );

		$version = $asset_info->get_asset_info(
			slug: 'test-script',
			attribute: 'dependencies'
		);

		$this->assertEquals( $vars['dependencies'], $version );
	}

	/**
	 * Test get_asset_info throws and exception when get_asset_info is called without setting up the asset vars.
	 *
	 * @return void
	 */
	public function test_get_asset_info_throws_exception_when_called_without_setting_up_asset_vars() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Asset variables not set. Please run setup_asset_vars() before calling get_asset_info().' );

		$asset_info->get_asset_info(
			slug: 'test-script'
		);
	}

	/**
	 * Test get_asset_info with prefix-based slug handling (css/, js/, blocks/).
	 *
	 * @return void
	 */
	public function test_get_asset_info_with_prefix_based_slug() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		// Initialize WP_Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create a temporary test directory structure
		$test_dir   = get_temp_dir() . 'wp-framework-test-' . uniqid();
		$css_dir    = $test_dir . '/css';
		$js_dir     = $test_dir . '/js';
		$blocks_dir = $test_dir . '/blocks';

		// Create directories using WP_Filesystem
		$wp_filesystem->mkdir( $test_dir, 0755 );
		$wp_filesystem->mkdir( $css_dir, 0755 );
		$wp_filesystem->mkdir( $js_dir, 0755 );
		$wp_filesystem->mkdir( $blocks_dir, 0755 );

		// Create asset files
		$css_asset   = [
			'version'      => '1.0.0',
			'dependencies' => [ 'css-dep' ],
		];
		$css_content = '<?php return ' . wp_json_encode( $css_asset ) . ';';
		$wp_filesystem->put_contents( $css_dir . '/file.asset.php', $css_content );

		$js_asset   = [
			'version'      => '2.0.0',
			'dependencies' => [ 'js-dep' ],
		];
		$js_content = '<?php return ' . wp_json_encode( $js_asset ) . ';';
		$wp_filesystem->put_contents( $js_dir . '/file.asset.php', $js_content );

		$blocks_asset   = [
			'version'      => '3.0.0',
			'dependencies' => [ 'blocks-dep' ],
		];
		$blocks_content = '<?php return ' . wp_json_encode( $blocks_asset ) . ';';
		$wp_filesystem->put_contents( $blocks_dir . '/file.asset.php', $blocks_content );

		$asset_info->setup_asset_vars(
			dist_path: $test_dir,
			fallback_version: '1.0.0'
		);

		// Test CSS prefix
		$asset = $asset_info->get_asset_info( slug: 'css/file' );
		$this->assertEquals( $css_asset, $asset );

		// Test JS prefix
		$asset = $asset_info->get_asset_info( slug: 'js/file' );
		$this->assertEquals( $js_asset, $asset );

		// Test blocks prefix
		$asset = $asset_info->get_asset_info( slug: 'blocks/file' );
		$this->assertEquals( $blocks_asset, $asset );

		// Clean up using WP_Filesystem
		$wp_filesystem->delete( $css_dir . '/file.asset.php' );
		$wp_filesystem->delete( $js_dir . '/file.asset.php' );
		$wp_filesystem->delete( $blocks_dir . '/file.asset.php' );
		$wp_filesystem->rmdir( $css_dir );
		$wp_filesystem->rmdir( $js_dir );
		$wp_filesystem->rmdir( $blocks_dir );
		$wp_filesystem->rmdir( $test_dir );
	}

	/**
	 * Test get_asset_info priority order: prefix-based slugs take priority over fallback.
	 *
	 * @return void
	 */
	public function test_get_asset_info_priority_order_prefix_vs_fallback() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		// Initialize WP_Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create a temporary test directory structure
		$test_dir = get_temp_dir() . 'wp-framework-test-' . uniqid();
		$css_dir  = $test_dir . '/css';
		$js_dir   = $test_dir . '/js';

		// Create directories using WP_Filesystem
		$wp_filesystem->mkdir( $test_dir, 0755 );
		$wp_filesystem->mkdir( $css_dir, 0755 );
		$wp_filesystem->mkdir( $js_dir, 0755 );

		// Create asset files
		$css_prefix_asset = [
			'version'      => '2.0.0',
			'dependencies' => [ 'css-prefix-dep' ],
		];
		$css_content      = '<?php return ' . wp_json_encode( $css_prefix_asset ) . ';';
		$wp_filesystem->put_contents( $css_dir . '/file.asset.php', $css_content );

		$js_fallback_asset = [
			'version'      => '1.0.0',
			'dependencies' => [ 'js-fallback-dep' ],
		];
		$js_content        = '<?php return ' . wp_json_encode( $js_fallback_asset ) . ';';
		$wp_filesystem->put_contents( $js_dir . '/file.asset.php', $js_content );

		$asset_info->setup_asset_vars(
			dist_path: $test_dir,
			fallback_version: '1.0.0'
		);

		// Test that prefix-based slug takes priority
		$asset = $asset_info->get_asset_info( slug: 'css/file' );
		$this->assertEquals( $css_prefix_asset, $asset );

		// Test that fallback still works for non-prefixed slugs
		$asset = $asset_info->get_asset_info( slug: 'file' );
		$this->assertEquals( $js_fallback_asset, $asset );

		// Clean up using WP_Filesystem
		$wp_filesystem->delete( $css_dir . '/file.asset.php' );
		$wp_filesystem->delete( $js_dir . '/file.asset.php' );
		$wp_filesystem->rmdir( $css_dir );
		$wp_filesystem->rmdir( $js_dir );
		$wp_filesystem->rmdir( $test_dir );
	}

	/**
	 * Test get_asset_info fallback behavior when direct file doesn't exist.
	 *
	 * @return void
	 */
	public function test_get_asset_info_fallback_when_direct_file_missing() {
		$asset_info = new class() {
			use GetAssetInfo;
		};

		// Initialize WP_Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create a temporary test directory structure
		$test_dir = get_temp_dir() . 'wp-framework-test-' . uniqid();
		$js_dir   = $test_dir . '/js';
		$css_dir  = $test_dir . '/css';

		// Create directories using WP_Filesystem
		$wp_filesystem->mkdir( $test_dir, 0755 );
		$wp_filesystem->mkdir( $js_dir, 0755 );
		$wp_filesystem->mkdir( $css_dir, 0755 );

		// Create asset files in subdirectories only (no direct file)
		$js_asset   = [
			'version'      => '1.0.0',
			'dependencies' => [ 'js-dep' ],
		];
		$js_content = '<?php return ' . wp_json_encode( $js_asset ) . ';';
		$wp_filesystem->put_contents( $js_dir . '/fallback-asset.asset.php', $js_content );

		$css_asset   = [
			'version'      => '1.5.0',
			'dependencies' => [ 'css-dep' ],
		];
		$css_content = '<?php return ' . wp_json_encode( $css_asset ) . ';';
		$wp_filesystem->put_contents( $css_dir . '/fallback-asset.asset.php', $css_content );

		$asset_info->setup_asset_vars(
			dist_path: $test_dir,
			fallback_version: '1.0.0'
		);

		// Test that it falls back to JS directory first (priority order: js -> css -> blocks)
		$asset = $asset_info->get_asset_info( slug: 'fallback-asset' );
		$this->assertEquals( $js_asset, $asset );

		// Clean up using WP_Filesystem
		$wp_filesystem->delete( $js_dir . '/fallback-asset.asset.php' );
		$wp_filesystem->delete( $css_dir . '/fallback-asset.asset.php' );
		$wp_filesystem->rmdir( $js_dir );
		$wp_filesystem->rmdir( $css_dir );
		$wp_filesystem->rmdir( $test_dir );
	}
}
