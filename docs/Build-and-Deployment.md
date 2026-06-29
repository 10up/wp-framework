# Build and Deployment

This page covers the **build-time class cache** — how to generate it, how to wire it into
CI, and the per-package model the framework assumes.

## Why caching is a build step

Discovering Modules at runtime is fast, but on large codebases you may want to cache the
discovered class list. The framework's cache is deliberately **read-only at runtime**: it
reads a cache file if one is present and discovers live otherwise, but it never writes one.

That single rule removes a whole class of "works locally, not on the server" bugs. A server
that can't write the cache can't hold a stale one, so the only cache that can exist is the
one your build produced for that exact deploy. There is no freshness check, no background
regeneration, and nothing to clear by hand. (For the history, see
[issue #30](https://github.com/10up/wp-framework/issues/30).)

Caching is therefore **opt-in**: do nothing and your project runs uncached, which is correct
and is the right default for a project with a handful of classes. Add the generate step when
you have a performance reason to.

## Generating the cache

The framework ships a standalone command (installed to your project's `vendor/bin/`) that
runs **without bootstrapping WordPress**, so it is safe to run in CI:

```bash
vendor/bin/tenup-framework-generate-class-cache <dir> [<dir> ...]
```

Pass the same directory you pass to `ModuleInitialization::init_classes()` — usually your
plugin/theme `inc/`. It writes `class-loader-cache/class-loader-cache-v2.php` inside each
directory. Multiple directories may be passed in one call.

### Composer alias

For nicer ergonomics, add a one-line script alias to your project's `composer.json`:

```json
{
  "scripts": {
    "generate-class-cache": "tenup-framework-generate-class-cache inc/"
  }
}
```

Then run it with:

```bash
composer generate-class-cache
```

> Composer does not propagate a dependency's scripts into your project, so the alias lives in
> your own `composer.json`. The `vendor/bin` command is the portable entry point either way.

### Bypassing the cache

Define `TENUP_FRAMEWORK_DISABLE_CLASS_CACHE` as `true` (e.g. in `wp-config.php`) to ignore any
shipped cache and always discover live. Useful when debugging a suspected stale or incorrect
cache.

## Gitignore the cache

The cache is a build artefact, not source. Ignore it and regenerate it on every deploy so an
old copy can never linger:

```gitignore
# Generated class-loader cache (built in CI, shipped with the deploy)
**/class-loader-cache/
```

## Wiring it into CI

The generate step always sits **after** `composer install` (it needs the framework and its
dependencies on disk) and **before** the deploy/packaging step (so the cache ships with the
build). `spatie/php-structure-discoverer` and this command are runtime dependencies, so
`composer install --no-dev` keeps them available.

### GitHub Actions

```yaml
name: Deploy
on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer:v2
      - name: Install PHP dependencies
        run: composer install --no-dev --prefer-dist --no-progress
      - name: Generate the class-loader cache
        run: composer generate-class-cache
        # or: vendor/bin/tenup-framework-generate-class-cache inc/
      - name: Deploy
        run: ./bin/deploy.sh   # your deploy ships inc/class-loader-cache/ with the build
```

### GitLab CI

```yaml
stages:
  - build
  - deploy

build:
  stage: build
  image: php:8.3
  script:
    - composer install --no-dev --prefer-dist --no-progress
    - vendor/bin/tenup-framework-generate-class-cache inc/
  artifacts:
    paths:
      - vendor/
      - inc/class-loader-cache/

deploy:
  stage: deploy
  script:
    - ./bin/deploy.sh   # ships the artifacts produced by the build stage
```

### CircleCI

```yaml
version: 2.1

jobs:
  build-and-deploy:
    docker:
      - image: cimg/php:8.3
    steps:
      - checkout
      - run:
          name: Install PHP dependencies
          command: composer install --no-dev --prefer-dist --no-progress
      - run:
          name: Generate the class-loader cache
          command: vendor/bin/tenup-framework-generate-class-cache inc/
      - run:
          name: Deploy
          command: ./bin/deploy.sh   # ships inc/class-loader-cache/ with the build

workflows:
  deploy:
    jobs:
      - build-and-deploy:
          filters:
            branches:
              only: main
```

If the build can't run the generate step for some reason, the deploy still works — it just
runs uncached. A broken cache after a build means the build is the thing to fix, not the
server.

## The per-package model

The framework discovers classes from **one directory mapped to one namespace, per Composer
package**. Each plugin or theme that uses the framework is its own unit: its own namespace,
its own `inc/` (or `src/`) directory, its own cache, and it requires the framework via
Composer in its own `composer.json`.

This is why caching is per package rather than per project. A single cache at the project
root could not tell which discovered classes belong to which plugin without scanning
everything — which is exactly the work the cache exists to avoid. So the cache is generated
per package, into the directory passed to that package's `init_classes()`, and shipped inside
that package.

The trade-off is that each package carries its own `vendor/` (a duplicated framework install)
and its own CI generate step, in exchange for domain-focused units that decouple cleanly. If
your build produces several packages, generate each one's cache — either with a call per
package, or by passing every directory to a single invocation:

```bash
vendor/bin/tenup-framework-generate-class-cache \
  wp-content/plugins/foo/inc \
  wp-content/plugins/bar/inc
```

## See also
- [Docs Home](README.md)
- [Autoloading and Modules](Autoloading.md)
- [Modules and Initialization](Modules-and-Initialization.md)
