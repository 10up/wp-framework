<?php
/**
 * Tests for the tenup-framework-generate-class-cache build command.
 *
 * The command runs without bootstrapping WordPress by design, so these belong in the unit
 * suite even though they shell out to a real subprocess.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

beforeEach(
	function (): void {
		$this->temp_dirs = [];
	}
);

afterEach(
	function (): void {
		foreach ( $this->temp_dirs as $dir ) {
			tenup_remove_dir( $dir );
		}
	}
);

it(
	'generates a readable cache of the classes in a directory',
	function (): void {
		$dir               = tenup_example_copy( 'plugin-inc' );
		$this->temp_dirs[] = $dir;

		$result = tenup_run_cache_bin( [ $dir ] );

		expect( $result['exit'] )->toBe( 0, $result['stderr'] );
		expect( $result['stdout'] )->toContain( 'Cached' );

		$cache_file = tenup_cache_file_path( $dir );
		expect( file_exists( $cache_file ) )->toBeTrue();

		$cached = require $cache_file;
		expect( $cached )
		->toContain( 'TenupFrameworkExamples\\Modules\\GreetingModule' )
		->toContain( 'TenupFrameworkExamples\\Support\\Formatter' );
	}
);

it(
	'reports usage and fails when given no arguments',
	function (): void {
		$result = tenup_run_cache_bin( [] );

		expect( $result['exit'] )->toBe( 1 );
		expect( $result['stderr'] )->toContain( 'Usage:' );
	}
);

it(
	'still caches the valid directories when one is missing',
	function (): void {
		$good              = tenup_example_copy( 'plugin-inc' );
		$this->temp_dirs[] = $good;
		$missing           = sys_get_temp_dir() . '/tenup_bin_missing_' . uniqid( '', true );

		// The missing directory is passed first, to prove a failure does not abort the run.
		$result = tenup_run_cache_bin( [ $missing, $good ] );

		expect( $result['exit'] )->toBe( 1 );
		expect( $result['stderr'] )
		->toContain( $missing )
		->toContain( 'Failed to generate cache' );

		expect( file_exists( tenup_cache_file_path( $good ) ) )->toBeTrue();
	}
);

it(
	'caches several directories in a single invocation',
	function (): void {
		$first             = tenup_example_copy( 'plugin-inc' );
		$second            = tenup_example_copy( 'second-inc' );
		$this->temp_dirs[] = $first;
		$this->temp_dirs[] = $second;

		$result = tenup_run_cache_bin( [ $first, $second ] );

		expect( $result['exit'] )->toBe( 0, $result['stderr'] );
		expect( file_exists( tenup_cache_file_path( $first ) ) )->toBeTrue();
		expect( file_exists( tenup_cache_file_path( $second ) ) )->toBeTrue();

		$cached = require tenup_cache_file_path( $second );
		expect( $cached )->toContain( 'TenupFrameworkExamples\\Widgets\\Card' );
	}
);
