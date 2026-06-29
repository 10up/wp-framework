# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/) and will adhere to [Semantic Versioning](http://semver.org/).

## [Unreleased] - TBD
### Added
- Build-time class-cache generation: a `tenup-framework-generate-class-cache` command (installed to `vendor/bin/`) and a `composer generate-class-cache` alias that build the cache in CI without bootstrapping WordPress. See [Build and Deployment](docs/Build-and-Deployment.md) ([#30](https://github.com/10up/wp-framework/issues/30)).
- Hidden admin page (`admin.php?page=tenup-framework-loaders`, `manage_options`) that aggregates every class-loader cache on the site — across all framework copies — and shows each cache's path, status, loaded classes, and an on-demand live-vs-cache staleness check. Admin-only (no front-end overhead) and read-only. Disable with the `tenup_framework_enable_loader_debug` filter or the `TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG` constant. See [Debugging class loaders](docs/Debugging.md).

### Changed
- The class-loader cache is now **read-only at runtime** and opt-in. The framework reads a pre-built cache if present and discovers live otherwise, but never writes one on the server — fixing stale caches that could only be cleared by hand ([#30](https://github.com/10up/wp-framework/issues/30)).
- Bumped the cache identifier so a cache written by an older version is ignored after upgrade rather than served stale.
- `TENUP_FRAMEWORK_DISABLE_CLASS_CACHE` now forces live discovery (ignores any shipped cache).

### Removed
- Automatic runtime cache generation and its environment gating — `should_use_cache()`, the `production`/`staging` checks, and the `VIP_GO_APP_ENVIRONMENT` handling. Caching is now produced at build time instead.

## [1.2.0] - 2025-03-20
### Changed
- Lowered the minimum required PHP version from 8.3 to 8.2 (props [@s3rgiosan](https://github.com/s3rgiosan) via [#8](https://github.com/10up/wp-framework/pull/8)).

## [1.1.0] - 2025-03-13
### Added
- Asset loading functionality (props [@darylldoyle](https://github.com/darylldoyle), [@darylldoyle](https://github.com/darylldoyle) via [#5](https://github.com/10up/wp-framework/pull/5)).

### Changed
- Use project-based lint commands instead of global reference (props [@claytoncollie](https://github.com/claytoncollie) in [#2](https://github.com/10up/wp-framework/pull/2)).
- Specify `WordPress.WP.I18n.MissingTranslatorsComment` rule in AbstractTaxonomy class (props [@s3rgiosan](https://github.com/s3rgiosan), [@darylldoyle](https://github.com/darylldoyle) via [#4](https://github.com/10up/wp-framework/pull/4)).

## [1.0.0] - 2025-01-09
- Base repo and readme creation

[Unreleased]: https://github.com/10up/wp-framework/compare/trunk...develop
[1.2.0]: https://github.com/10up/wp-framework/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/10up/wp-framework/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/10up/wp-framework/commit/341fc55c8abf302380ad0d1e269b13366bdd710a
