## Overview
WP Framework simplifies taxonomy registration by offering an abstract class to encapsulate the required functionality.

## Example
```php
namespace TenUpPlugin\Taxonomies;

use TenupFramework\Taxonomies\AbstractTaxonomy;

class Demo extends AbstractTaxonomy {

    public function get_name() {
        return 'tenup-demo-category';
    }

    public function get_singular_label() {
        return esc_html__( 'Category', 'tenup-plugin' );
    }

    public function get_plural_label() {
        return esc_html__( 'Categories', 'tenup-plugin' );
    }

    public function get_post_types() {
        return [ 'tenup-demo' ];
    }
}
```

## Key Points

* Define both singular and plural labels.
* Associate the taxonomy with the relevant post type(s).
* Keep taxonomy definitions modular for ease of maintenance.