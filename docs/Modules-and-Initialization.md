# Modules and Initialization

## Overview
The WP Framework organizes functionality into small Modules. A Module is any class that implements TenupFramework\ModuleInterface and typically uses the TenupFramework\Module trait. Modules are discovered at runtime and initialized in a defined order.

Key interfaces and utilities:
- `TenupFramework\ModuleInterface`: declares `load_order()`, `can_register()`, `register()`.
- `TenupFramework\Module` trait: provides a default `load_order()` of `10` and leaves `can_register()` and `register()` abstract for your class to implement.
- `TenupFramework\ModuleInitialization`: discovers, orders, and initializes your Modules.

## Bootstrapping
Call the initializer at plugin or theme bootstrap, pointing it at the directory containing your namespaced classes (e.g., `inc/` or `src/`):

```php
use TenupFramework\ModuleInitialization;

ModuleInitialization::instance()->init_classes( YOUR_PLUGIN_INC );
```

`YOUR_PLUGIN_INC` (or your equivalent constant/path) should resolve to an existing directory. If it does not exist, a RuntimeException will be thrown.

## How discovery and initialization work
ModuleInitialization performs the following steps:
1. Validate the directory exists; otherwise throw a RuntimeException.
2. Discover class names within the directory using spatie/structure-discoverer.
   - In production and staging environments (wp_get_environment_type), results are cached for performance using a file-based cache.
   - Caching is skipped entirely when the constant `VIP_GO_APP_ENVIRONMENT` is defined.
3. Reflect on each discovered class and skip any that:
   - are not instantiable,
   - do not implement `TenupFramework\ModuleInterface`.
4. Instantiate the class.
5. Fire an action before registration for each module: `tenup_framework_module_init__{slug}`
   - `slug` is the sanitized class FQN (backslashes replaced with dashes, then passed through `sanitize_title`).
6. Sort modules by `load_order()` (lower numbers first) and iterate in order.
7. For each module, call `register()` only if `can_register()` returns true.
8. Store initialized modules for later retrieval.

Environment cache behavior
- Where cache lives: under the directory you pass to `init_classes()`, in a `class-loader-cache` folder (e.g., `YOUR_PLUGIN_INC . 'class-loader-cache'`).
- When it's used: only in `production` and `staging` environment types (`wp_get_environment_type()`).
- How to clear: delete the `class-loader-cache` folder; it will be rebuilt on next discovery.
- How to disable: use `development` or `local` environment types, define `VIP_GO_APP_ENVIRONMENT`, or set `define( 'TENUP_FRAMEWORK_DISABLE_CACHE', true )` to skip the cache. The `TENUP_FRAMEWORK_DISABLE_CACHE` constant is useful for environments that don't support writable file systems.

Hooks
- Action: `tenup_framework_module_init__{slug}` — fires before each module’s `register()` runs.
  - Parameters: the module instance.
  - Example:
    ```php
    add_action( 'tenup_framework_module_init__yourvendor-yourplugin-features-frontendtweaks', function ( $module ) {
        // Inspect or adjust before register()
    } );
    ```

Load order dependencies example
- If Module B depends on Module A:
  ```php
  class ModuleA implements ModuleInterface { use Module; public function load_order(): int { return 5; } }
  class ModuleB implements ModuleInterface { use Module; public function load_order(): int { return 10; } }
  ```
  Lower numbers run first. Taxonomies typically use 9 so post types (default 10) can associate afterward.

Utilities:
- `ModuleInitialization::get_module( $classFqn )` retrieves an initialized module instance by its fully qualified class name.
- `ModuleInitialization::instance()->get_all_classes()` returns all initialized module instances keyed by slug.

## Module lifecycle in your code
Your Module should be lightweight at construction time. Use the following methods effectively:
- `load_order(): int` — controls initialization order (default = 10 via Module trait). Override to run earlier/later. For example, taxonomy modules may run at 9 so they are available before post types.
- `can_register(): bool` — return true only when the module should register hooks in the current context (e.g., only in admin, only on frontend, only if a feature flag is enabled).
- `register(): void` — attach your WordPress hooks/filters and perform setup here.

### Example
```php
namespace YourVendor\YourPlugin\Features;

use TenupFramework\ModuleInterface;
use TenupFramework\Module;

class FrontendTweaks implements ModuleInterface {
    use Module; // default load_order() = 10

    public function can_register(): bool {
        return ! is_admin(); // only on frontend
    }

    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        // ... enqueue assets here ...
    }
}
```

## Troubleshooting
- Directory is required — If `init_classes()` is called with an empty or non-existent directory, a RuntimeException is thrown.
- Class not initialized — Ensure the class is instantiable and implements `TenupFramework\ModuleInterface`.
- Order of initialization — If you have inter-module dependencies, adjust `load_order()` to ensure prerequisites are registered first.
- Observability — Use the `tenup_framework_module_init__{slug}` action to inspect or modify module instances before they register.
