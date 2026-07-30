<?php
/**
 * Tests the Emoji module against real WordPress hooks.
 *
 * Replaces the previous source-code string matching with assertions on the actual hook
 * registry and on the filter callbacks running through apply_filters().
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\Core\Emoji;
use TenupFramework\ModuleInterface;

beforeEach(
	function (): void {
		$this->emoji = new Emoji();
	}
);

it(
	'is a module the loader will pick up',
	function (): void {
		expect( $this->emoji )->toBeInstanceOf( ModuleInterface::class );
		expect( $this->emoji->can_register() )->toBeTrue();
		expect( $this->emoji->load_order() )->toBe( 5 );
	}
);

/*
 * Asserted as whole sets rather than one test per hook. WordPress does not attach all of these
 * in every version or request context — print_emoji_styles was deprecated out of core, and the
 * admin_* hooks are not wired on a front-end request — so requiring each one to be attached up
 * front makes the test fail on WordPress changes that are not regressions in this module. What
 * matters is that none of them survive register(), plus a check that the set was not empty to
 * begin with so the assertion cannot pass vacuously.
 */
const TENUP_EMOJI_ACTIONS = [
	[ 'wp_head', 'print_emoji_detection_script' ],
	[ 'admin_print_scripts', 'print_emoji_detection_script' ],
	[ 'wp_print_styles', 'print_emoji_styles' ],
	[ 'admin_print_styles', 'print_emoji_styles' ],
];

const TENUP_EMOJI_FILTERS = [
	[ 'the_content_feed', 'wp_staticize_emoji' ],
	[ 'comment_text_rss', 'wp_staticize_emoji' ],
	[ 'wp_mail', 'wp_staticize_emoji_for_email' ],
];

it(
	'detaches every core emoji action it targets',
	function (): void {
		$attached_before = array_filter(
			TENUP_EMOJI_ACTIONS,
			static fn( array $hook ): bool => false !== has_action( $hook[0], $hook[1] )
		);

		expect( $attached_before )->not->toBeEmpty();

		$this->emoji->register();

		foreach ( TENUP_EMOJI_ACTIONS as [ $hook, $callback ] ) {
			expect( has_action( $hook, $callback ) )->toBeFalse();
		}
	}
);

it(
	'detaches every emoji staticize filter it targets',
	function (): void {
		$attached_before = array_filter(
			TENUP_EMOJI_FILTERS,
			static fn( array $hook ): bool => false !== has_filter( $hook[0], $hook[1] )
		);

		expect( $attached_before )->not->toBeEmpty();

		$this->emoji->register();

		foreach ( TENUP_EMOJI_FILTERS as [ $hook, $callback ] ) {
			expect( has_filter( $hook, $callback ) )->toBeFalse();
		}
	}
);

it(
	'attaches its own filters when registered',
	function (): void {
		$this->emoji->register();

		expect( has_filter( 'tiny_mce_plugins', [ $this->emoji, 'disable_emojis_tinymce' ] ) )->not->toBeFalse();
		expect( has_filter( 'wp_resource_hints', [ $this->emoji, 'disable_emoji_dns_prefetch' ] ) )->not->toBeFalse();
	}
);

it(
	'removes wpemoji from the TinyMCE plugin list',
	function (): void {
		$result = $this->emoji->disable_emojis_tinymce( [ 'wordpress', 'wpemoji', 'media' ] );

		expect( $result )->not->toContain( 'wpemoji' );
		// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- A literal TinyMCE plugin handle, not prose.
		expect( $result )->toContain( 'wordpress' )->toContain( 'media' );
	}
);

it(
	'leaves the TinyMCE plugin list untouched when wpemoji is absent',
	function (): void {
		$plugins = [ 'wordpress', 'media' ];

		expect( $this->emoji->disable_emojis_tinymce( $plugins ) )->toBe( $plugins );
	}
);

it(
	'drops the emoji CDN host from dns-prefetch hints',
	function (): void {
		$result = $this->emoji->disable_emoji_dns_prefetch(
			[
				'https://fonts.googleapis.com',
				'https://s.w.org/images/core/emoji/2/svg/',
				'https://example.com',
			],
			'dns-prefetch'
		);

		expect( $result )->not->toContain( 'https://s.w.org/images/core/emoji/2/svg/' );
		expect( $result )->toContain( 'https://fonts.googleapis.com' )->toContain( 'https://example.com' );
	}
);

it(
	'leaves hints for other relation types untouched',
	function (): void {
		$urls = [ 'https://s.w.org/images/core/emoji/2/svg/', 'https://example.com' ];

		expect( $this->emoji->disable_emoji_dns_prefetch( $urls, 'preconnect' ) )->toBe( $urls );
	}
);

it(
	'takes effect through the wp_resource_hints filter once registered',
	function (): void {
		$this->emoji->register();

		$hints = apply_filters(
			'wp_resource_hints',
			[ 'https://s.w.org/images/core/emoji/2/svg/', 'https://example.com' ],
			'dns-prefetch'
		);

		expect( $hints )->not->toContain( 'https://s.w.org/images/core/emoji/2/svg/' );
	}
);

it(
	'removes the emoji detection script from the rendered head',
	function (): void {
		$this->emoji->register();

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		expect( $head )->not->toContain( '_wpemojiSettings' );
	}
);
