<?php
/**
 * Confirms the unit suite runs without WordPress loaded.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

it(
	'runs the unit suite without WordPress loaded',
	function (): void {
		expect( class_exists( \TenupFramework\ModuleInitialization::class ) )->toBeTrue();
	}
);
