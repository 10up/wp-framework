<?php
/**
 * Tests HeadOverrides against real WordPress hooks.
 *
 * The previous suite asserted on the *source code* of register() as a string, because mocked
 * remove_action() calls prove nothing. With WordPress loaded we can assert the hooks are
 * genuinely detached and that the markup disappears from wp_head.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\Core\HeadOverrides;
use TenupFramework\ModuleInterface;

/**
 * The wp_head callbacks HeadOverrides is responsible for detaching.
 */
const TENUP_HEAD_CALLBACKS = [ 'wp_generator', 'wlwmanifest_link', 'rsd_link' ];

beforeEach(
	function (): void {
		$this->head_overrides = new HeadOverrides();

		// Record what was actually attached before we start, so the assertions hold regardless
		// of which of these WordPress still ships (wlwmanifest_link went away in WP 6.3).
		$this->attached_before = array_filter(
			TENUP_HEAD_CALLBACKS,
			static fn( string $callback ): bool => false !== has_action( 'wp_head', $callback )
		);
	}
);

it(
	'is a module the loader will pick up',
	function (): void {
		expect( $this->head_overrides )->toBeInstanceOf( ModuleInterface::class );
		expect( $this->head_overrides->can_register() )->toBeTrue();
		// Ahead of taxonomies (9) and post types (10), since it only detaches core output.
		expect( $this->head_overrides->load_order() )->toBe( 5 );
	}
);

it(
	'detaches every wp_head callback it targets',
	function (): void {
		// Guard against a vacuous pass: if WordPress attached none of them, there is nothing to prove.
		expect( $this->attached_before )->not->toBeEmpty();

		$this->head_overrides->register();

		foreach ( TENUP_HEAD_CALLBACKS as $callback ) {
			expect( has_action( 'wp_head', $callback ) )->toBeFalse();
		}
	}
);

it(
	'removes the generator tag from the rendered head',
	function (): void {
		ob_start();
		do_action( 'wp_head' );
		$before = (string) ob_get_clean();

		expect( $before )->toContain( '<meta name="generator"' );

		$this->head_overrides->register();

		ob_start();
		do_action( 'wp_head' );
		$after = (string) ob_get_clean();

		expect( $after )->not->toContain( '<meta name="generator"' );
	}
);

it(
	'leaves unrelated wp_head callbacks attached',
	function (): void {
		$this->head_overrides->register();

		// A representative core callback it must not touch.
		expect( has_action( 'wp_head', 'wp_robots' ) )->not->toBeFalse();
	}
);
