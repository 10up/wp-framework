# Post Types

## Overview
WP Framework provides base classes for implementing both custom and core post types with minimal boilerplate and a consistent API. Post type classes are also Modules, so they participate in the standard lifecycle (load_order → can_register → register).

- Custom post types: extend `TenupFramework\PostTypes\AbstractPostType`
- Core post types: extend `TenupFramework\PostTypes\AbstractCorePostType`

## Quick start (custom post type)
```php
namespace TenUpPlugin\Posts;

use TenupFramework\PostTypes\AbstractPostType;

class Demo extends AbstractPostType {
    public function get_name() { return 'tenup-demo'; }
    public function get_singular_label() { return esc_html__( 'Demo', 'tenup-plugin' ); }
    public function get_plural_label() { return esc_html__( 'Demos', 'tenup-plugin' ); }
    public function get_menu_icon() { return 'dashicons-chart-pie'; }

    // Optional: run only in specific contexts
    public function can_register() { return true; }

    // Optional: fine-tune options beyond defaults
    public function get_options() {
        $options = parent::get_options();
        $options['supports'] = [ 'title', 'editor', 'thumbnail', 'excerpt' ];
        $options['rewrite']  = [ 'slug' => 'demos', 'with_front' => false ];
        $options['has_archive'] = true;
        return $options;
    }

    // Declare related taxonomies (registered separately via taxonomy classes)
    public function get_supported_taxonomies() { return [ 'tenup-demo-category' ]; }
}
```

## Working with core post types
If you need to attach taxonomies or behaviors to built-in types (post, page, attachment), extend `AbstractCorePostType`:
```php
namespace TenUpPlugin\Posts;

use TenupFramework\PostTypes\AbstractCorePostType;

class Post extends AbstractCorePostType {
    public function get_name() { return 'post'; }

    public function get_supported_taxonomies() {
        return [ 'tenup-demo-category' ];
    }

    public function after_register() {
        // Add metaboxes, filters, etc.
    }
}
```
Notes:
- Core post types are already registered by WordPress. `AbstractCorePostType::register()` does not call `register_post_type()`; it only registers taxonomies (via `get_supported_taxonomies()`) and then calls `after_register()`.
- `can_register()` returns `true` by default in `AbstractCorePostType`.

## Taxonomy association pattern and migration
- Associations are declared in Post Type classes via `get_supported_taxonomies();` taxonomy classes should not declare post types with `get_post_types()`.
- Rationale: centralizes relationships in the object type; avoids duplication and fragile coupling; taxonomy registration stays independent of associations.
- Migration: If you previously returned post types from a taxonomy class via `get_post_types()`, remove that method and add the taxonomy slug to each relevant post type’s `get_supported_taxonomies()`.

## API reference (AbstractPostType)
Required methods you must implement:
- `get_name()`: string — The post type slug, e.g., tenup-demo.
- `get_singular_label()`: string — Translated singular label.
- `get_plural_label()`: string — Translated plural label.
- `get_menu_icon()`: string — A dashicons class, base64 SVG, or 'none'.

Common optional methods to override:
- `get_menu_position()`: ?int — Position in admin menu; null by default.
- `is_hierarchical()`: bool — Defaults to false.
- `get_editor_supports()`: array<string> — Defaults to [title, editor, author, thumbnail, excerpt, revisions].
- `get_options()`: array — Returns options passed to `register_post_type()`. See below for keys and defaults.
- `get_supported_taxonomies()`: string[] — Array of taxonomy slugs to associate via `register_taxonomy_for_object_type()`.
- `after_register()`: void — Called after the type is registered and taxonomies associated.
- `can_register()`: bool — Implement to control when this module should run; e.g., limit to admin.

Registration methods (already implemented):
- `register()`: calls `register_post_type()`, `register_taxonomies()`, then `after_register()`; returns `true`.
- `register_post_type()`: wraps WordPress `register_post_type( $this->get_name(), $this->get_options() )`.
- `register_taxonomies()`: iterates get_supported_taxonomies() and calls `register_taxonomy_for_object_type()`.

## Options (get_options) overview
AbstractPostType provides sensible defaults and merges in:
- `labels`: from `get_labels()`
- `public`: true
- `has_archive`: true
- `show_ui`: true
- `show_in_menu`: true
- `show_in_nav_menus`: false
- `show_in_rest`: true
- `supports`: `get_editor_supports()`
- `menu_icon`: `get_menu_icon()`
- `hierarchical`: `is_hierarchical()`
- `menu_position`: included if `get_menu_position()` returns a number

You can override get_options() in your class to set any supported core args, including:
- `rewrite` (array|bool): slug, with_front, feeds, pages, ep_mask
- `capability_type` (string|array), capabilities (array), map_meta_cap (bool)
- `taxonomies` (array) — Note: prefer `get_supported_taxonomies()` to associate after registration
- `has_archive` (bool|string)
- `show_in_rest` (bool), rest_base, rest_namespace, rest_controller_class
- `register_meta_box_cb` (callable)
- `template` (array), template_lock (string|false)

## Labels
`AbstractPostType::get_labels()` builds a comprehensive labels array using the singular/plural labels you provide. Ensure your `get_*_label()` methods return translated strings using your project text domain.

## Tips
- Flush rewrite rules on activation only (not within `register()`).
- Keep post type classes focused on registration; put custom business logic in separate modules/services.
- Link taxonomies via `get_supported_taxonomies()` for clarity; implement taxonomy classes separately (see Taxonomies.md).

## See also
- [Taxonomies](Taxonomies.md)
- [Modules and Initialization](Modules-and-Initialization.md)
