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
}
