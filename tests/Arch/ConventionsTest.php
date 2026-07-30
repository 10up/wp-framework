<?php
/**
 * Architecture expectations for the shipped code.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

arch( 'declares strict types everywhere' )
	->expect( 'TenupFramework' )
	->toUseStrictTypes();

arch( 'ships no debugging leftovers' )
	->expect( [ 'var_dump', 'var_export', 'print_r', 'dd', 'dump', 'error_log' ] )
	->not->toBeUsed()
	// BlockRegistrar::register_blocks() logs deliberately when the block editor is missing,
	// which is a real diagnostic rather than a leftover. Scoping the exception to that one
	// class keeps the rule useful: a new error_log() anywhere else still fails.
	->ignoring( 'TenupFramework\BlockRegistrar' );

arch( 'never depends on test code' )
	->expect( 'TenupFramework' )
	->not->toUse( [ 'TenupFrameworkTests', 'TenupFrameworkTestClasses' ] );
