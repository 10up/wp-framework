# Debugging class loaders

The framework ships a hidden admin page that shows the state of every class-loader cache active
on a site. It exists to answer one question quickly: **what is each loader actually loading, and
is any cache stale?** That matters most on production, where a failed build or a missed deploy can
leave an old cache file in place (see [Build and Deployment](Build-and-Deployment.md) and
[issue #30](https://github.com/10up/wp-framework/issues/30)).

## Opening the page

There is no menu item — the page is hidden. Visit it directly:

```
/wp-admin/admin.php?page=tenup-framework-loaders
```

It requires the `manage_options` capability.

## What it shows

A site can run **1..n** framework copies (one per plugin/theme that requires the package). The
page aggregates every loader recorded across all of them — even copies that are php-scoped
(prefixed) or on different versions — by collecting over the fixed-string
`tenup_framework_debug_loaders` filter. For each loader you get:

- **Owner** — the plugin or theme the directory belongs to (derived from the path).
- **Directory** — the directory passed to `ModuleInitialization::init_classes()`.
- **Framework version** — version and git reference of the copy that recorded it, so a
  version mismatch between plugins is visible.
- **Cache file** — its path, and the status: in use, present-but-not-used, discovering live
  (no file), or disabled. When a file is present, its age and size.
- **Stale cache files** — a warning if the cache directory holds files other than the current
  one (usually leftovers from an older framework version).
- **Classes loaded** — every class the loader resolved, with the file each one lives in. A class
  that no longer resolves is flagged as a likely stale entry.

## Staleness check

Each loader has a **Check this cache for staleness** button. It re-runs discovery live against the
directory and diffs the result against what the cache loaded, listing:

- classes **on disk but missing from the cache** (the cache is behind), and
- classes **in the cache but no longer on disk** (renamed/removed).

The check runs only when clicked, so the page itself stays cheap. If it reports drift, the cache
is stale: regenerate it in your build (`composer generate-class-cache`) or remove the file and
redeploy. The page is **read-only** — it never deletes or rewrites a cache, consistent with the
read-only runtime.

## Performance

The recording and the page are **admin-only**. On front-end requests nothing is recorded, no hooks
are added, and the debug class is never even loaded.

## Disabling it

Enabled by default in the admin. Turn it off with either:

```php
add_filter( 'tenup_framework_enable_loader_debug', '__return_false' );
```

```php
define( 'TENUP_FRAMEWORK_DISABLE_LOADER_DEBUG', true );
```

## See also
- [Docs Home](README.md)
- [Build and Deployment](Build-and-Deployment.md)
- [Modules and Initialization](Modules-and-Initialization.md)
