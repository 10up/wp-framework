## Overview
WP Framework provides abstract classes to help define both custom and core post types. This minimizes boilerplate while ensuring consistency.

## Custom Post Types Example

```php
namespace TenUpPlugin\Posts;

use TenupFramework\PostTypes\AbstractPostType;

class Demo extends AbstractPostType {

    public function get_name() {
        return 'tenup-demo';
    }

    public function get_singular_label() {
        return esc_html__( 'Demo', 'tenup-plugin' );
    }

    public function get_plural_label() {
        return esc_html__( 'Demos', 'tenup-plugin' );
    }

    public function get_menu_icon() {
        return 'dashicons-chart-pie';
    }
}
```

## Core Post Types Example
```php
namespace TenUpPlugin\Posts;

use TenupFramework\PostTypes\AbstractCorePostType;

class Post extends AbstractCorePostType {

    public function get_name() {
        return 'post';
    }

    public function get_supported_taxonomies() {
        return [];
    }

    public function after_register() {
        // No additional functionality.
    }
}
```

## Key Points

* Extend `AbstractPostType` for new content types.
* Extend `AbstractCorePostType` to modify or extend built-in types.
* Use translation functions for labels.