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
   - If a pre-built class cache is present it is read; otherwise classes are discovered live on every request. The runtime never writes the cache — see [Class caching](#class-caching) below.
3. Reflect on each discovered class and skip any that:
   - are not instantiable,
   - do not implement `TenupFramework\ModuleInterface`.
4. Instantiate the class.
5. Fire an action before registration for each module: `tenup_framework_module_init__{slug}`
   - `slug` is the sanitized class FQN (backslashes replaced with dashes, then passed through `sanitize_title`).
6. Sort modules by `load_order()` (lower numbers first) and iterate in order.
7. For each module, call `register()` only if `can_register()` returns true.
8. Store initialized modules for later retrieval.

## Class caching
Caching the discovered class list is **optional and produced at build time**. The runtime only ever reads the cache; it never writes one, so a server cannot end up serving a stale cache it generated itself (the failure mode this model replaced — see [issue #30](https://github.com/10up/wp-framework/issues/30)).

- Default (no cache file): classes are discovered live on every request. Correct, and the right default for small codebases — caching is opt-in.
- With a cache file present: the framework reads it and skips discovery.
- Where it lives: a `class-loader-cache` folder inside the directory you pass to `init_classes()`, e.g. `YOUR_PLUGIN_INC . 'class-loader-cache'`. The filename is versioned (`class-loader-cache-v2.php`) so a cache written by an older framework version is ignored after an upgrade rather than served stale; the old file is harmless cruft a clean deploy clears.
- How to produce it: run `vendor/bin/tenup-framework-generate-class-cache <dir>` (or `composer generate-class-cache -- <dir>`) in your build/deploy pipeline and ship the result as a build artefact.
- How to bypass: define `TENUP_FRAMEWORK_DISABLE_CLASS_CACHE` as `true` to ignore any shipped cache and always discover live.

Gitignore the `class-loader-cache` directory and regenerate it on every deploy. See [Build and Deployment](Build-and-Deployment.md) for CI examples and the per-package caching model, and [Debugging class loaders](Debugging.md) for the hidden admin page that shows what each cache is loading and flags stale ones.

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
