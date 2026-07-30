# Example loader directories

Small, self-contained directories that mirror what a real plugin/theme passes to
`ModuleInitialization::init_classes()` — the same directories you would point the
`tenup-framework-generate-class-cache` build command at.

They exist so the class-cache tooling can be exercised end-to-end against realistic
input rather than throwaway inline strings:

- `plugin-inc/` — a typical plugin `inc/` directory: one `ModuleInterface` module
  (`Modules\GreetingModule`) plus a plain support class (`Support\Formatter`) that is
  discovered but never registered.
- `second-inc/` — a second directory, used to prove the build command caches several
  directories in a single run (as a multi-package project would).

The classes are intentionally tiny. Discovery reads them with a tokenizer and never
loads them, so they do not need to be autoloadable to be cached.

Generated `class-loader-cache/` directories are git-ignored build artefacts; the tests
create them in a temporary copy and clean them up.
