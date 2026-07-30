<?php
/**
 * Tests AbstractTaxonomy against a real WordPress registration.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFrameworkTestClasses\Taxonomies\Demo;

beforeEach(
	function (): void {
		$this->taxonomy = new Demo();
	}
);

afterEach(
	function (): void {
		if ( taxonomy_exists( 'tenup-tax-demo' ) ) {
			unregister_taxonomy( 'tenup-tax-demo' );
		}
	}
);

it(
	'registers the taxonomy with WordPress',
	function (): void {
		expect( taxonomy_exists( 'tenup-tax-demo' ) )->toBeFalse();

		$this->taxonomy->register();

		expect( taxonomy_exists( 'tenup-tax-demo' ) )->toBeTrue();
	}
);

it(
	'uses the plural label for name and the singular for singular_name',
	function (): void {
		$this->taxonomy->register();

		$labels = get_taxonomy( 'tenup-tax-demo' )->labels;

		expect( $labels->name )->toBe( 'Demo Terms' );
		expect( $labels->singular_name )->toBe( 'Demo Term' );
	}
);

it(
	'derives the remaining labels from the singular and plural labels',
	function (): void {
		$this->taxonomy->register();

		$labels = get_taxonomy( 'tenup-tax-demo' )->labels;

		expect( $labels->search_items )->toBe( 'Search Demo Terms' );
		expect( $labels->add_new_item )->toBe( 'Add Demo Term' );
		expect( $labels->new_item_name )->toBe( 'New Demo Term Name' );
		expect( $labels->all_items )->toBe( 'All Demo Terms' );
		// Lower-cased in the sentence-style labels.
		expect( $labels->separate_items_with_commas )->toBe( 'Separate demo terms with commas' );
		expect( $labels->not_found )->toBe( 'No demo terms found.' );
	}
);

it(
	'applies the default options from get_options()',
	function (): void {
		$this->taxonomy->register();

		$object = get_taxonomy( 'tenup-tax-demo' );

		expect( $object->public )->toBeTrue();
		expect( $object->show_ui )->toBeTrue();
		expect( $object->show_admin_column )->toBeTrue();
		expect( $object->show_in_rest )->toBeTrue();
		expect( $object->hierarchical )->toBeFalse();
		expect( $object->query_var )->toBe( 'tenup-tax-demo' );
	}
);

it(
	'attaches to no post types by default, leaving that to the post type classes',
	function (): void {
		expect( $this->taxonomy->get_post_types() )->toBe( [] );

		$this->taxonomy->register();

		expect( get_taxonomy( 'tenup-tax-demo' )->object_type )->toBe( [] );
	}
);

it(
	'registers before post types via its load order',
	function (): void {
		expect( $this->taxonomy->load_order() )->toBe( 9 );
	}
);

it(
	'produces a taxonomy that can actually store and retrieve a term',
	function (): void {
		$this->taxonomy->register();

		$term = wp_insert_term( 'Example Term', 'tenup-tax-demo' );

		expect( $term )->toBeArray();
		expect( get_term( $term['term_id'], 'tenup-tax-demo' )->name )->toBe( 'Example Term' );
	}
);
