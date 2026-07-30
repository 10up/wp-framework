<?php
/**
 * Tests AbstractPostType against a real WordPress registration.
 *
 * The previous mock-based test could only prove register_post_type() was called. These
 * assert what WordPress actually ended up with.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFrameworkTestClasses\PostTypes\Demo;
use TenupFrameworkTestClasses\Taxonomies\Demo as DemoTaxonomy;

beforeEach(
	function (): void {
		$this->post_type = new Demo();
	}
);

afterEach(
	function (): void {
		// Post type and taxonomy registries are process globals, not database state, so the
		// transaction rollback between tests does not clear them.
		if ( post_type_exists( 'tenup-demo' ) ) {
			unregister_post_type( 'tenup-demo' );
		}

		if ( taxonomy_exists( 'tenup-tax-demo' ) ) {
			unregister_taxonomy( 'tenup-tax-demo' );
		}
	}
);

it(
	'registers the post type with WordPress',
	function (): void {
		expect( post_type_exists( 'tenup-demo' ) )->toBeFalse();

		$this->post_type->register();

		expect( post_type_exists( 'tenup-demo' ) )->toBeTrue();
	}
);

it(
	'uses the plural label for name and the singular for singular_name',
	function (): void {
		$this->post_type->register();

		$labels = get_post_type_object( 'tenup-demo' )->labels;

		expect( $labels->name )->toBe( 'Demos' );
		expect( $labels->singular_name )->toBe( 'Demo' );
	}
);

it(
	'derives the remaining labels from the singular and plural labels',
	function (): void {
		$this->post_type->register();

		$labels = get_post_type_object( 'tenup-demo' )->labels;

		expect( $labels->add_new_item )->toBe( 'Add New Demo' );
		expect( $labels->edit_item )->toBe( 'Edit Demo' );
		expect( $labels->view_items )->toBe( 'View Demos' );
		expect( $labels->all_items )->toBe( 'All Demos' );
		expect( $labels->archives )->toBe( 'Demo Archives' );
		// Lower-cased in the sentence-style labels.
		expect( $labels->not_found )->toBe( 'No demos found.' );
		expect( $labels->insert_into_item )->toBe( 'Insert into demo' );
	}
);

it(
	'applies the default options from get_options()',
	function (): void {
		$this->post_type->register();

		$object = get_post_type_object( 'tenup-demo' );

		expect( $object->public )->toBeTrue();
		expect( $object->has_archive )->toBeTrue();
		expect( $object->show_ui )->toBeTrue();
		expect( $object->show_in_menu )->toBeTrue();
		expect( $object->show_in_rest )->toBeTrue();
		// Deliberately false by default, unlike most of the flags above.
		expect( $object->show_in_nav_menus )->toBeFalse();
		expect( $object->hierarchical )->toBeFalse();
	}
);

it(
	'sets the menu icon declared by the subclass',
	function (): void {
		$this->post_type->register();

		expect( get_post_type_object( 'tenup-demo' )->menu_icon )->toBe( 'dashicons-chart-pie' );
	}
);

it(
	'registers the default editor supports',
	function (): void {
		$this->post_type->register();

		foreach ( [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ] as $feature ) {
			expect( post_type_supports( 'tenup-demo', $feature ) )->toBeTrue();
		}

		expect( post_type_supports( 'tenup-demo', 'comments' ) )->toBeFalse();
	}
);

it(
	'associates the taxonomies the post type declares',
	function (): void {
		// The taxonomy has to exist before register_taxonomy_for_object_type() will attach it,
		// which is exactly why AbstractTaxonomy uses load order 9 and post types use 10.
		( new DemoTaxonomy() )->register();

		$this->post_type->register();

		expect( get_object_taxonomies( 'tenup-demo' ) )->toContain( 'tenup-tax-demo' );
	}
);

it(
	'produces a post type that can actually store and retrieve a post',
	function (): void {
		$this->post_type->register();

		$post_id = wp_insert_post(
			[
				'post_type'   => 'tenup-demo',
				'post_title'  => 'A demo item',
				'post_status' => 'publish',
			]
		);

		expect( $post_id )->toBeInt()->toBeGreaterThan( 0 );
		expect( get_post( $post_id )->post_type )->toBe( 'tenup-demo' );
	}
);

it(
	'registers after taxonomies via its load order',
	function (): void {
		expect( $this->post_type->load_order() )->toBe( 10 );
		expect( ( new DemoTaxonomy() )->load_order() )->toBeLessThan( $this->post_type->load_order() );
	}
);
