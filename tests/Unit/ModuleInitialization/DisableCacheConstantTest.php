<?php
/**
 * Tests that TENUP_FRAMEWORK_DISABLE_CLASS_CACHE forces live discovery.
 *
 * Delegated to a subprocess (tests/scripts/disable-class-cache.php) because a constant cannot
 * be undefined. Defining it in-process leaks into every later test: with caching disabled,
 * unrelated tests then see cache_used === false. Testkit's Unit_Test_Case does carry
 * #[RunTestsInSeparateProcesses], but that attribute does not survive Pest's generated test
 * classes, so a real subprocess is the only reliable isolation here.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

it(
	'forces live discovery even when a cache file is present',
	function (): void {
		$dir = tenup_temp_class_dir();

		$result = tenup_run_script( 'disable-class-cache.php', [ $dir ] );

		expect( $result['exit'] )->toBe( 0, $result['stderr'] );

		$classes = json_decode( $result['stdout'], true, 512, JSON_THROW_ON_ERROR );

		// The tampered cache's sentinel proves whether the cache was read.
		expect( $classes )->not->toContain( 'TenupTmp\\Sentinel' );
		expect( $classes )->toContain( 'TenupTmp\\Widget' );

		tenup_remove_dir( $dir );
	}
);
