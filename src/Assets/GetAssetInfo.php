<?php
/**
 * Get asset info from extracted asset files
 *
 * @package TenUpPlugin\Traits
 */

declare( strict_types = 1 );

namespace TenupFramework\Assets;

use RuntimeException;

/**
 * Trait GetAssetInfo
 *
 * @package TenUpPlugin\Traits
 */
trait GetAssetInfo {

	/**
	 * Path to the dist directory
	 *
	 * @var ?string
	 */
	public $dist_path = null;

	/**
	 * Fallback version to use if asset file is not found
	 *
	 * @var ?string
	 */
	public $fallback_version = null;

	/**
	 * Setup asset variables
	 *
	 * @param string $dist_path        Path to the dist directory
	 * @param string $fallback_version Fallback version to use if asset file is not found
	 *
	 * @return void
	 */
	public function setup_asset_vars( string $dist_path, string $fallback_version ) {
		$this->dist_path        = trailingslashit( $dist_path );
		$this->fallback_version = $fallback_version;
	}

	/**
	 * Get asset info from extracted asset files
	 *
	 * @param string  $slug      Asset slug as defined in build/webpack configuration
	 * @param ?string $attribute Optional attribute to get. Can be version or dependencies
	 *
	 * @throws RuntimeException If asset variables are not set
	 *
	 * @return string|($attribute is null ? array{version: string, dependencies: array<string>} : $attribute is'dependencies' ? array<string> : string)
	 */
	public function get_asset_info( string $slug, ?string $attribute = null ) {

		if ( is_null( $this->dist_path ) || is_null( $this->fallback_version ) ) {
			throw new RuntimeException( 'Asset variables not set. Please run setup_asset_vars() before calling get_asset_info().' );
		}

		if ( file_exists( $this->dist_path . 'js/' . $slug . '.asset.php' ) ) {
			$asset = require $this->dist_path . 'js/' . $slug . '.asset.php';
		} elseif ( file_exists( $this->dist_path . 'css/' . $slug . '.asset.php' ) ) {
			$asset = require $this->dist_path . 'css/' . $slug . '.asset.php';
		} elseif ( file_exists( $this->dist_path . 'blocks/' . $slug . '.asset.php' ) ) {
			$asset = require $this->dist_path . 'blocks/' . $slug . '.asset.php';
		} else {
			$asset = [
				'version'      => $this->fallback_version,
				'dependencies' => [],
			];
		}

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var array{version: string, dependencies: array<string>} $asset */

		if ( empty( $attribute ) ) {
			return $asset;
		}

		if ( isset( $asset[ $attribute ] ) ) {
			return $asset[ $attribute ];
		}

		return '';
	}
}
