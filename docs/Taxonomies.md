# Taxonomies

## Overview
WP Framework provides an AbstractTaxonomy base class to register and configure taxonomies with minimal boilerplate. Taxonomy classes are also Modules and follow the standard lifecycle (load_order → can_register → register). By default, taxonomies load at order 9 so they can be available for post types that load at the default 10.

Important: Taxonomies are associated to post types via the Post Type classes (`get_supported_taxonomies()`), not via the taxonomy class itself.

## Quick start
```php
namespace TenUpPlugin\Taxonomies;

use TenupFramework\Taxonomies\AbstractTaxonomy;

class DemoCategory extends AbstractTaxonomy {
    public function get_name() { return 'tenup-demo-category'; }
    public function get_singular_label() { return esc_html__( 'Category', 'tenup-plugin' ); }
    public function get_plural_label() { return esc_html__( 'Categories', 'tenup-plugin' ); }

    // Optional: make hierarchical like "category"; defaults to false (tags-like)
    public function is_hierarchical() { return true; }

    // Optional: adjust options beyond defaults
    public function get_options() {
        $opts = parent::get_options();
        $opts['rewrite'] = [ 'slug' => 'demo-category', 'with_front' => false ];
        return $opts;
    }

    // Optional: gate where this module runs
    public function can_register() { return true; }
}
```

Associate the taxonomy with your post type by declaring it in the post type class:
```php
// In your AbstractPostType subclass
public function get_supported_taxonomies() { return [ 'tenup-demo-category' ]; }
```

## API reference (AbstractTaxonomy)
Required methods:
- `get_name()`: string — Taxonomy slug, e.g., tenup-demo-category.
- `get_singular_label()`: string — Translated singular label.
- `get_plural_label()`: string — Translated plural label.

Common optional methods:
- `is_hierarchical()`: bool — Defaults to false (tags-like). Return true for category-like.
- `get_options()`: array — Returns args passed to `register_taxonomy()`; see below for defaults and keys.
- `after_register()`: void — Called after registration; use for additional setup.
- `can_register()`: bool — Implement to control when to register (admin vs. frontend, feature flags, etc.).
- `load_order()`: int — Defaults to 9 in `AbstractTaxonomy`; override to change load priority relative to other modules.

Registration (implemented by `AbstractTaxonomy`):
- `register()`: calls `register_taxonomy( get_name(), get_post_types(), get_options() )` and then `after_register()`. The base class returns an empty array from `get_post_types()` intentionally; association is handled by post types via `get_supported_taxonomies()`.

## Options (get_options) defaults
AbstractTaxonomy provides sensible defaults:
- `labels`: from `get_labels()`
- `hierarchical`: `is_hierarchical()`
- `show_ui`: true
- `show_admin_column`: true
- `query_var`: true
- `show_in_rest`: true
- `public`: true

You may override `get_options()` to set any core taxonomy args, including:
- `rewrite` (array|bool): slug, with_front, hierarchical, ep_mask
- `capabilities` (array)
- `default_term` (string|array{name: string, slug?: string, description?: string})
- `meta_box_cb` (bool|callable)
- `show_in_menu`, show_in_nav_menus, show_tagcloud, show_in_quick_edit
- `rest_base`, rest_namespace, rest_controller_class
- `update_count_callback` (callable)

## Labels
`AbstractTaxonomy::get_labels()` builds a robust labels array using your singular/plural labels. Ensure your label methods return translated strings using your project’s text domain.

## Examples
- Non-hierarchical tag-like taxonomy:
```php
public function is_hierarchical() { return false; }
public function get_options() {
    return [
        'labels'       => $this->get_labels(),
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'demo-tags' ],
    ];
}
```

- Custom capabilities:
```php
public function get_options() {
    $opts = parent::get_options();
    $opts['capabilities'] = [
        'manage_terms' => 'manage_demo_terms',
        'edit_terms'   => 'manage_demo_terms',
        'delete_terms' => 'manage_demo_terms',
        'assign_terms' => 'edit_posts',
    ];
    return $opts;
}
```

## Association pattern and rationale
- Associations are declared in Post Type classes via `get_supported_taxonomies()`; taxonomy classes should not declare post types with `get_post_types()`.
- Rationale: centralizes relationships in the place where object-type behavior lives; avoids duplication and fragile coupling; keeps taxonomy registration independent of associations.
- Migration: If you previously returned post types from a taxonomy class via `get_post_types()`, remove that method and add the taxonomy slug to each relevant post type's `get_supported_taxonomies()`.

## See also
- [Docs Home](README.md)
- [Autoloading and Modules](Autoloading.md)
- [Modules and Initialization](Modules-and-Initialization.md)
- [Post Types](Post-Types.md)
- [Asset Loading](Asset-Loading.md)
