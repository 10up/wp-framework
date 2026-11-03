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

Environment caching:
- Discovery tokenizes every PHP file in the directory, so it is cached. Both caches live under the directory you pass to `init_classes()`, in a "class-loader-cache" folder (e.g., `YOUR_PLUGIN_INC . 'class-loader-cache'`).
- **Prebuilt cache (recommended for production).** Run `vendor/bin/wp-framework-cache <dir>` in your build step after `composer install` and ship the generated `class-loader-cache/classes.php` with the code. It is read on every host, including read-only ones like WordPress VIP, and only while it matches the code: it stores a hash of the directory's PHP files, so a stale cache is ignored and discovery runs instead. See [Prebuilt class cache](#prebuilt-class-cache).
- **Runtime cache.** Without a prebuilt cache, discovery results are written on the first request in production and staging environments (per `wp_get_environment_type()`). It is keyed by the same hash, so changing a class rebuilds it. It is skipped when the constant `VIP_GO_APP_ENVIRONMENT` is defined, since VIP's filesystem is read-only.
- `define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true )` turns off both caches.

### Prebuilt class cache

Generate it for each directory you pass to `init_classes()`, after `composer install`, in CI or your release build:

```sh
vendor/bin/wp-framework-cache src
# several at once, e.g. a theme and a plugin
vendor/bin/wp-framework-cache themes/my-theme/src plugins/my-plugin/src
```

The command doesn't need WordPress. Commit the generated folder to your built or deployed branch, not your source branch (add `class-loader-cache/` to `.gitignore` there), and regenerate it on every build. On a project's local environment, a missing or outdated cache just falls back to discovery.

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
- [Post Types](Post-Types.md)
- [Taxonomies](Taxonomies.md)
- [Asset Loading](Asset-Loading.md)
