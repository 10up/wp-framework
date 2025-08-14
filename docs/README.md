# WP Framework Documentation

WP Framework is a lightweight set of building blocks for structuring WordPress plugins and themes around small, composable Modules. It provides:
- Module discovery and initialization (with a predictable lifecycle)
- Base classes for custom and core post types
- Base class for taxonomies
- Asset helpers that read modern build sidecars (`.asset.php`)

## Who is this for?
- External engineers — Start here: follow the Quick Start and then read Autoloading and Modules → Modules and Initialization → Post Types/Taxonomies → Asset Loading.
- Internal engineers — Concepts: jump straight to Modules and Initialization and the specific Post Types/Taxonomies APIs. Skim the Quick Start for constants and bootstrap.
- Non‑technical stakeholders — Overview: This framework standardizes how we register types, taxonomies, and assets so teams ship features faster with less boilerplate and more consistency.

## Quick Start (at a glance)
1) Add PSR-4 autoloading in composer.json for your project namespace (inc/ or src/).
2) Define constants (`YOUR_PLUGIN_PATH`, `YOUR_PLUGIN_URL`, `YOUR_PLUGIN_INC`, `YOUR_PLUGIN_VERSION`) in your plugin/theme bootstrap.
3) Initialize modules: `TenupFramework\ModuleInitialization::instance()->init_classes( YOUR_PLUGIN_INC )`.
4) Implement small classes that implement `ModuleInterface` (use the `Module` trait) and optionally extend `AbstractPostType` / `AbstractTaxonomy`.
5) Load assets via the `GetAssetInfo` trait using dist/.asset.php sidecars.

## Table of Contents
- [Autoloading and Modules](Autoloading.md) — how classes are discovered and initialized
- [Modules and Initialization](Modules-and-Initialization.md)
- [Post Types](Post-Types.md) — building custom and core post type integrations
- [Taxonomies](Taxonomies.md) — registering and configuring taxonomies
- [Asset Loading](Asset-Loading.md) — working with dist/.asset.php for dependencies and versioning

## Conventions
- Namespaces: use your project namespace (e.g., `YourVendor\\YourPlugin`) for app code; reference framework classes via the TenupFramework namespace.
- Translation: return translated strings from label methods using the correct text domain.
- Keep Modules small and focused; guard execution in `can_register()` to keep admin/frontend/REST behaviors tidy.
