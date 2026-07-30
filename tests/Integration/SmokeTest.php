<?php
/**
 * Confirms the integration suite boots a real WordPress.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

it(
	'boots a real WordPress',
	function (): void {
		expect( defined( 'ABSPATH' ) )->toBeTrue();
		expect( function_exists( 'register_post_type' ) )->toBeTrue();
		expect( did_action( 'init' ) )->toBeGreaterThan( 0 );
	}
);

it(
	'has a usable database',
	function (): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Proving the test database is reachable is the point.
		expect( $wpdb->get_var( 'SELECT 1' ) )->toEqual( 1 );
	}
);
