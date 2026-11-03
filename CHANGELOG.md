# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/) and will adhere to [Semantic Versioning](http://semver.org/).

## [Unreleased] - TBD
### Added
- A prebuilt class cache: `vendor/bin/wp-framework-cache <dir>` writes `class-loader-cache/classes.php` in a build step, and module discovery reads it on any host, including WordPress VIP and other read-only filesystems, while it matches the code. Discovery tokenizes every PHP file on each request where no cache can be written ([#41](https://github.com/10up/wp-framework/issues/41)).

### Fixed
- The runtime class cache is keyed by a hash of the directory's PHP files, so changing or adding a class rebuilds it instead of serving a stale class list ([#30](https://github.com/10up/wp-framework/issues/30)).

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
