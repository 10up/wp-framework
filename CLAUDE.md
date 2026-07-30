# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`10up/wp-framework` is a **Composer library** (not a runnable plugin/theme) that other 10up WordPress projects `composer require` and extend. It centralizes shared functionality — a module auto-loading system plus abstract base classes for post types, taxonomies, and asset metadata. Code here ships to many downstream projects, so treat the public API (class/method signatures, the `tenup_framework_module_init__{slug}` action, the `Module` trait contract) as stable; breaking changes require deliberate versioning.

- PHP **8.3+** (typed class constants are used throughout, so 8.2 is a parse error). PSR-4: `TenupFramework\` → `src/`. Test namespaces: `TenupFrameworkTests\` → `tests/`, `TenupFrameworkTestClasses\` → `fixtures/classes/`.
- Tests run **against a real WordPress install with a MySQL database**, via Pest 4 + Mantle Testkit. Brain Monkey is gone. See *Testing* below.

## Commands

```bash
composer test           # Pest (no coverage driver required)
composer test-coverage  # Pest with coverage; needs Xdebug or PCOV
composer lint           # PHPCS against ./phpcs.xml (10up-Default standard)
composer lint-fix       # PHPCBF auto-fix
composer static         # PHPStan, level 10, 1G memory limit

# Run a subset:
./vendor/bin/pest tests/Unit                          # one suite
./vendor/bin/pest tests/Integration/PostTypes         # one directory
./vendor/bin/pest --filter "registers the post type"  # one test by name
```

CI (`.github/workflows/php.yml`) runs lint, static analysis, and tests on PHP 8.3 for pushes/PRs to `trunk` and `develop`. `develop` is the default/working branch. The test job provisions a MySQL service.

## Architecture

### Module system (the core abstraction)

A "Module" is any class implementing `TenupFramework\ModuleInterface` (and usually `use`ing the `TenupFramework\Module` trait for a default `load_order()` of 10). The interface contract is three methods:

- `load_order(): int` — lower runs first. **No relation to WP hook priority** — it only orders registration among modules. Taxonomies default to `9` so they exist before post types (default `10`) associate with them.
- `can_register(): bool` — gate registration by context (admin-only, frontend-only, feature flag).
- `register(): void` — attach hooks/filters here. Keep constructors lightweight.

`ModuleInitialization` (a singleton) drives discovery and registration. Downstream projects bootstrap with:

```php
ModuleInitialization::instance()->init_classes( YOUR_PLUGIN_INC );
```

The discovery/init flow in `src/ModuleInitialization.php` is the heart of the library:

1. Scans the given directory with **`spatie/php-structure-discoverer`**.
2. `withoutChains()` is called deliberately — discovery does **not** resolve inheritance chains. This was an intentional change (see git history "Disable chains"); don't re-enable it without understanding the perf/behavior tradeoff.
3. Each class is reflected and **skipped** unless it is instantiable AND implements `ModuleInterface`. (This is why abstract bases and plain classes like the `Standalone` fixture are never registered.)
4. Fires `do_action( 'tenup_framework_module_init__{slug}', $instance )` before each module registers — the extension/observability hook. `{slug}` = FQN with `\` → `-`, passed through `sanitize_title`.
5. Sorts by `load_order()`, then calls `register()` only when `can_register()` is true. Registered instances are retrievable via `ModuleInitialization::get_module( $fqn )`.

**Class cache (read-only at runtime, build-time generated):** Optional and opt-in. At runtime `get_classes()` reads `{dir}/class-loader-cache/class-loader-cache-v2.php` if it exists (via `ReadOnlyFileDiscoverCacheDriver`, whose `put()`/`forget()` are no-ops and whose constructor does not `mkdir`), and discovers live otherwise — it **never writes**. This is the fix for the stale-cache bug (issue #30): a server can't hold a cache it never wrote. The cache is produced at build time by `ModuleInitialization::generate_cache()`, exposed via `bin/tenup-framework-generate-class-cache <dir>` (composer `bin`) and the `composer generate-class-cache` alias; both run without WordPress. Define `TENUP_FRAMEWORK_DISABLE_CLASS_CACHE = true` to ignore any shipped cache and always discover live. The `CACHE_FILENAME` constant (`...-v2.php`) is the invalidation lever — bumping it makes the runtime ignore caches from older versions. See `docs/Build-and-Deployment.md`.

**Loader debug page (`Debug\LoaderDebug`):** A hidden admin page (`admin.php?page=tenup-framework-loaders`, `manage_options`) that shows the state of every class-loader cache on the site and offers an on-demand live-vs-cache staleness diff. It's **admin-only** — `init_classes()` dispatches a loader record to `LoaderDebug::record()` only behind an `is_admin()` check placed *before* any reference to the class, so `LoaderDebug` never autoloads on the front end. Because a site can run 1..n framework copies (per-package installs, possibly php-scoped), aggregation happens over the fixed-string `tenup_framework_debug_loaders` filter rather than class references — every copy contributes its records, and the page registers once via the `$GLOBALS['tenup_framework_debug_page_registered']` guard. Read-only (never writes); disable via the `tenup_framework_enable_loader_debug` filter or `TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG`. See `docs/Debugging.md`.

### Abstract base classes

- `PostTypes\AbstractPostType` — implements `ModuleInterface` via the `Module` trait. Subclasses define `get_name()`, `get_singular_label()`, `get_plural_label()`, `get_menu_icon()`; `register()` calls `register_post_type()` + `register_taxonomies()` + `after_register()`. Override `get_options()`/`get_editor_supports()`/`get_supported_taxonomies()` to customize.
- `PostTypes\AbstractCorePostType` — for WP-builtin types (post/page). Labels/icon are no-ops; `register()` only wires taxonomies (the type already exists), and `can_register()` returns true.
- `Taxonomies\AbstractTaxonomy` — `load_order()` of `9`. Subclasses define name + labels; `get_post_types()` returns `[]` by default because **post types declare their own taxonomies**, not the reverse.
- `Assets\GetAssetInfo` (trait) — reads `*.asset.php` sidecar files (version + dependencies) emitted by the build. Call `setup_asset_vars( $dist_path, $fallback_version )` first or `get_asset_info()` throws `RuntimeException`. Looks under `dist/js/`, `dist/css/`, then `dist/blocks/`; falls back to the provided version + empty deps when no sidecar exists.

## Testing

**Pest 4 on PHPUnit 12, with Mantle Testkit booting a real WordPress.** `tests/bootstrap.php` calls `\Mantle\Testing\install()`, which downloads and installs WordPress into a temp directory on first run. No WordPress checkout or shell script is needed, but **a MySQL database is required**.

Three suites, bound to base classes in `tests/Pest.php`:

| Directory | Base class | Use for |
| --- | --- | --- |
| `tests/Unit/` | `Mantle\Testkit\Unit_Test_Case` | Code that calls no WordPress functions — filesystem, the class cache, subprocess runs |
| `tests/Integration/` | `Mantle\Testkit\Integration_Test_Case` | Anything touching WordPress. Each test runs in a DB transaction that rolls back |
| `tests/Arch/` | none | Pest architecture expectations (`arch()`) |

Gotchas worth knowing:

- **Database defaults live in `tests/bootstrap.php`** and are only applied when unset, so CI can override each. It defaults to a database named `wp_framework_tests` and a package-specific `WP_CORE_DIR`, deliberately *not* Mantle's `/tmp/wordpress` + `wordpress_unit_tests` defaults: the installer reuses any `wp-tests-config.php` it finds and then drops/recreates tables, so a shared temp dir will destroy another project's test database. The bootstrap refuses to run if the resolved config does not name the expected database.
- **Process state is not rolled back.** The DB transaction does not reset the `ModuleInitialization` singleton, `LoaderDebug`'s static records, `BlockRegistrar`'s static registries, registered post types/taxonomies, or the block registry. Use `tenup_reset_framework_state()` / `tenup_reset_block_registrar()` in `beforeEach`, and unregister types in `afterEach`.
- **`define()` needs a subprocess.** Testkit's `Unit_Test_Case` carries `#[RunTestsInSeparateProcesses]`, but that attribute does **not** survive Pest's generated test classes, so a constant defined in a test leaks into every later test. Tests that must define one shell out to `tests/scripts/`, e.g. `disable-class-cache.php`.
- **Admin context** comes from Mantle's `Admin_Screen` trait (`uses( Admin_Screen::class )`), which makes `is_admin()` true. `set_current_screen()` is unavailable, since Mantle skips the WP core test suite.
- **`_doing_it_wrong()` fails the test** unless declared with `$this->setExpectedIncorrectUsage( '...' )`.
- Shared helpers live in `tests/Helpers.php`, loaded via composer `autoload-dev.files`.
- `fixtures/classes/` holds sample modules used to exercise discovery — e.g. `Standalone` (no interface, must be skipped), `Loadable/InvalidChildClass` (un-loadable, excluded from PHPStan in `phpstan.neon`). When changing discovery logic, update these fixtures and the assertions in `tests/Integration/ModuleInitializationTest.php`.
- Coverage is measured against `./src/` only. `phpunit.xml.dist` deliberately declares no `<coverage><report>` block — doing so makes PHPUnit enable coverage on every run and hard-fail without Xdebug/PCOV.

## Conventions

- `declare( strict_types = 1 );` on every file (PHPCS enforces it, but not required on the first line).
- All linted code lives in `src/`, `tests/`, `fixtures/` (per `phpcs.xml`).
- The `tenup-plugin` text domain in base-class labels is a placeholder inherited by downstream projects.
