<?php
/**
 * Tests for the GetAssetInfo trait.
 *
 * Reads *.asset.php sidecar files off disk only, so it runs without WordPress.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\Assets\GetAssetInfo;

/**
 * A bare consumer of the trait, which is all the trait needs to be exercised.
 *
 * @return object
 */
function tenup_asset_info_consumer(): object {
	return new class() {
		use GetAssetInfo;
	};
}

/**
 * Absolute path to the built asset fixtures.
 *
 * @return string
 */
function tenup_asset_fixture_path(): string {
	return dirname( __DIR__, 3 ) . '/fixtures/assets/dist';
}

beforeEach(
	function (): void {
		$this->asset_info = tenup_asset_info_consumer();
		$this->asset_info->setup_asset_vars(
			dist_path: tenup_asset_fixture_path(),
			fallback_version: '1.0.0'
		);
	}
);

it(
	'normalises the dist path and stores the fallback version',
	function (): void {
		$asset_info = tenup_asset_info_consumer();

		$asset_info->setup_asset_vars( dist_path: 'dist', fallback_version: '1.0.0' );

		expect( $asset_info->dist_path )->toBe( 'dist/' );
		expect( $asset_info->fallback_version )->toBe( '1.0.0' );
	}
);

it(
	'throws when get_asset_info() is called before setup_asset_vars()',
	function (): void {
		tenup_asset_info_consumer()->get_asset_info( slug: 'test-script' );
	}
)->throws(
	RuntimeException::class,
	'Asset variables not set. Please run setup_asset_vars() before calling get_asset_info().'
);

it(
	'resolves a bare slug by searching js/, then css/, then blocks/',
	function ( string $slug, string $subdirectory ): void {
		$expected = require tenup_asset_fixture_path() . "/{$subdirectory}/{$slug}.asset.php";

		expect( $this->asset_info->get_asset_info( slug: $slug ) )->toBe( $expected );
	}
)->with(
	[
		'js sidecar'     => [ 'test-script', 'js' ],
		'css sidecar'    => [ 'test-style', 'css' ],
		'blocks sidecar' => [ 'test-block', 'blocks' ],
	]
);

it(
	'resolves an explicitly prefixed slug',
	function ( string $slug, string $subdirectory, string $file ): void {
		$expected = require tenup_asset_fixture_path() . "/{$subdirectory}/{$file}.asset.php";

		expect( $this->asset_info->get_asset_info( slug: $slug ) )->toBe( $expected );
	}
)->with(
	[
		'css prefix'    => [ 'css/test-style', 'css', 'test-style' ],
		'js prefix'     => [ 'js/test-script', 'js', 'test-script' ],
		'blocks prefix' => [ 'blocks/test-block', 'blocks', 'test-block' ],
	]
);

it(
	'returns a single attribute when one is requested',
	function (): void {
		expect( $this->asset_info->get_asset_info( slug: 'test-script', attribute: 'version' ) )
		->toBe( 'test-script-version' );

		expect( $this->asset_info->get_asset_info( slug: 'test-script', attribute: 'dependencies' ) )
		->toBe( [ 'test-script-deps' ] );
	}
);

it(
	'falls back to the supplied version and no dependencies when no sidecar exists',
	function (): void {
		expect( $this->asset_info->get_asset_info( slug: 'non-existent' ) )
		->toBe(
			[
				'version'      => '1.0.0',
				'dependencies' => [],
			]
		);
	}
);
