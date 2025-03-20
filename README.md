__This software is currently BETA.__

# WP Framework

WP Framework is a PHP package designed to simplify the development of WordPress themes and plugins by centralizing shared functionality. It provides a set of foundational tools, abstract classes, and reusable components to handle common challenges, enabling developers to focus on project-specific logic while ensuring consistency across projects.

## Key Features
- **Shared Functionality:** Provides commonly used abstract classes and utilities to reduce boilerplate code in WordPress projects.
- **Extendability:** Built for easy extension. Engineers can subclass or override functionality as needed to tailor it to their projects.
- **Centralized Updates:** Simplifies rolling out updates and new features across projects using this framework.
- **Modern Standards:** Compatible with PHP 8.2+ and adheres to modern development practices.

## Installation

You can include WP Framework in your project via Composer:

```bash
composer require 10up/wp-framework
```

## Usage

### Autoloading

The framework follows the PSR-4 autoloading standard, making it easy to include and extend classes in your project.

It also builds upon the module autoloader that was previously used in the WP-Scaffold. The only difference is that now,
instead of extending the `Module` class, you should implement the `ModuleInterface` interface. To help with this, we
have also provided a `Module` trait that gives you a basic implementation of the interface.

```php
namespace YourNamespace;

use TenupFramework\ModuleInterface;
use TenupFramework\Module;

class YourModule implements ModuleInterface {
    use Module;

	public function can_register(): bool {
		return true;
	}

	public function register(): void {
	    // Register hooks and filters here.
	}
}
```

### Helpful Abstract Classes

**Custom Post Types**

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

**Core Post Types**

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
		// Do nothing.
	}
}
```

**Taxonomies**

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


## Contributions

Contributions to WP Framework are welcome! To get started:
1. Clone the repository.
2. Install dependencies:
     ```bash
     composer install
    ```


## License

WP Framework is open-source software licensed under the GPL-2.0-or-later.

## Support

If you have any questions or encounter issues, please create an issue in the GitHub repository.
