# Autoloading and Modules

## Overview
WP Framework follows PSR-4 autoloading and discovers your classes at runtime to initialize Modules. Instead of extending a base class, you implement ModuleInterface and use the Module trait to participate in the lifecycle.

## Composer PSR-4 setup
Add your project namespace and source directory in composer.json:

```json
{
  "autoload": {
    "psr-4": {
      "YourVendor\\YourPlugin\\": "inc/"
    }
  }
}
```

Run `composer dump-autoload` after changes.

## Recommended plugin structure & bootstrap
A simple plugin layout that works well with the framework:

```
my-plugin/
├─ my-plugin.php                // main plugin file
├─ composer.json
├─ inc/                         // PHP source (PSR-4 autoloaded)
│  ├─ Features/
│  ├─ Posts/
│  └─ Taxonomies/
├─ dist/                        // built assets from your toolchain
│  ├─ js/
│  │  ├─ admin.js
│  │  └─ admin.asset.php
│  ├─ css/
│  │  └─ admin.css
│  └─ blocks/
│     ├─ my-block.js
│     └─ my-block.asset.php
└─ readme.txt
```

Define a few useful constants in your main plugin file (or a bootstrap class), then initialize modules:

```php
// Plugin main file or bootstrap
define( 'YOUR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'YOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YOUR_PLUGIN_INC', YOUR_PLUGIN_PATH . 'inc/' );
define( 'YOUR_PLUGIN_VERSION', '1.0.0' );

use TenupFramework\ModuleInitialization;
ModuleInitialization::instance()->init_classes( YOUR_PLUGIN_INC );
```

## Initialization
Call the framework’s initializer with the directory where your classes live (e.g., inc or src):

```php
use TenupFramework\ModuleInitialization;

ModuleInitialization::instance()->init_classes( YOUR_PLUGIN_INC );
```

- Classes must be instantiable and implement `TenupFramework\ModuleInterface`.
- The initializer sorts modules by `load_order()` (defaults to `10` via the `Module` trait) and then calls `register()` only if `can_register()` returns `true`.
- A hook fires before registration: `tenup_framework_module_init__{slug}`, where slug is a sanitized class FQN.

### Verify discovery and environment behavior
In development, you can verify discovered/initialized modules:

```php
add_action( 'plugins_loaded', function () {
    $mods = TenupFramework\ModuleInitialization::instance()->get_all_classes();
    // For local/dev only:
    // error_log( print_r( array_keys( $mods ), true ) );
} );
```

Class caching (optional, build-time):
- Discovery is fast, but on large codebases you can cache the discovered class list. The cache is **opt-in and produced at build time**: the framework reads it at runtime but never writes it, so it can never go stale on a server.
- With no cache file present (the default), classes are discovered live on every request. This is correct and is the right default for small projects.
- To produce a cache, run the shipped command in your build/deploy pipeline and ship the result as a build artefact:
  ```bash
  vendor/bin/tenup-framework-generate-class-cache YOUR_PLUGIN_INC
  # or, via the Composer alias (see Build and Deployment):
  composer generate-class-cache -- inc/
  ```
- Define `TENUP_FRAMEWORK_DISABLE_CLASS_CACHE` as `true` to ignore any shipped cache and always discover live (useful for debugging a suspected stale cache).
- See [Build and Deployment](Build-and-Deployment.md) for CI examples and the per-package caching model.

## Defining a Module
```php
namespace YourVendor\YourPlugin\Features;

use TenupFramework\ModuleInterface;
use TenupFramework\Module;

class YourModule implements ModuleInterface {
    use Module; // provides default load_order() = 10

    public function can_register(): bool {
        // Only run on frontend, for example
        return ! is_admin();
    }

    public function register(): void {
        add_action( 'init', function () {
            // Add hooks/filters here
        } );
    }
}
```

## Best practices
- Keep Modules small and focused; compose behavior via multiple classes.
- Use `can_register()` to gate context-specific behavior (admin vs. frontend, REST, multisite, feature flags).
- Prefer dependency injection via constructor where practical; avoid doing heavy work before `register()`.


## See also
- [Docs Home](README.md)
- [Modules and Initialization](Modules-and-Initialization.md)
- [Build and Deployment](Build-and-Deployment.md)
- [Post Types](Post-Types.md)
- [Taxonomies](Taxonomies.md)
- [Asset Loading](Asset-Loading.md)
