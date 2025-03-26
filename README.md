# WP Framework

[![Support Level](https://img.shields.io/badge/support-beta-blueviolet.svg)](#support-level) [![GPL-2.0-or-later License](https://img.shields.io/github/license/10up/wp-framework.svg)](https://github.com/10up/wp-framework/blob/develop/LICENSE.md) [![PHP Checks](https://github.com/10up/wp-framework/actions/workflows/php.yml/badge.svg)](https://github.com/10up/wp-framework/actions/workflows/php.yml)

> WP Framework is a PHP package designed to simplify the development of WordPress themes and plugins by centralizing shared functionality. It provides a set of foundational tools, abstract classes, and reusable components to handle common challenges, enabling developers to focus on project-specific logic while ensuring consistency across projects.

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

#### Custom Post Types

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

#### Core Post Types

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

#### Taxonomies

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

## Changelog

A complete listing of all notable changes to Distributor are documented in [CHANGELOG.md](https://github.com/10up/wp-framework/blob/develop/CHANGELOG.md).

## Contributing

Please read [CODE_OF_CONDUCT.md](https://github.com/10up/wp-framework/blob/develop/CODE_OF_CONDUCT.md) for details on our code of conduct and [CONTRIBUTING.md](https://github.com/10up/wp-framework/blob/develop/CONTRIBUTING.md) for details on the process for submitting pull requests to us.

## Support Level

**Beta:** This project is quite new and we're not sure what our ongoing support level for this will be. Bug reports, feature requests, questions, and pull requests are welcome. If you like this project please let us know, but be cautious using this in a Production environment!

## Like what you see?

<a href="http://10up.com/contact/"><img src="https://10up.com/uploads/2016/10/10up-Github-Banner.png" width="850"></a>
