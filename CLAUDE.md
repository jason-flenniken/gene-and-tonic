# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Laravel Boost's framework guidelines (PHP style, Pint, Pest, Livewire, Artisan, Herd) live in AGENTS.md and are imported here. Where this file disagrees with them, this file wins.

@AGENTS.md

## What this app is

A demo app for supplement research and recommendations, built as a **modular monolith**. A user uploads genetic test results. The app combines them with supplement research, asks an AI model (over its API) for recommendations, and produces a **PDF report** of the findings. The PDF report is the main deliverable of the product.

Stack: Laravel 13, Livewire 4 (single-file components by default), Flux UI (free), Tailwind 4, Pest 5, PHP 8.4, MySQL 8.4, nwidart/laravel-modules 13, and spatie/laravel-pdf 2 (Chrome driver).

## Environment

- Herd serves the app at http://laravel.test. Never start a server.
- MySQL is **not** a Herd service: there is no Herd Pro, so Herd's `services`/`db` tools fail. It runs in the OrbStack container `laravel-mysql` (port 3306, root/`password`, volume `laravel-mysql-data`). If the DB is unreachable, check that OrbStack is running: `docker --context orbstack ps`. The default Docker context points at Docker Desktop, which usually isn't running.
- Tests run on SQLite `:memory:` (see `phpunit.xml`), not MySQL.
- The developer usually has `composer run dev` (`artisan dev`) running, so Vite serves assets on :5173.

## Commands

```bash
composer test                     # pint --test, phpstan (level 7), then the full Pest suite
composer lint                     # pint --parallel (fix)
composer types:check              # phpstan; needs --memory-limit=1G, which the script passes
php artisan test --compact        # Pest only
vendor/bin/pest --testsuite=Modules             # every module's tests
vendor/bin/pest Modules/Genetics/tests          # one module
vendor/bin/pest --filter="parses raw results"   # one test
npm run build                     # production assets (vite-plus)
```

## Modules (nwidart/laravel-modules)

All domain code lives in `Modules/<Name>/`. The root `app/` is the application shell: the `User` model, app providers, and nothing domain-specific. `resources/views/layouts/app.blade.php` is the single shared shell for every Livewire page (`layouts::app`). Modules supply content, not chrome.

Creating things:

```bash
php artisan module:make Genetics && composer dump-autoload   # merge-plugin autoloads Modules/*/composer.json
php artisan module:make-model Variant Genetics               # likewise module:make-{migration,event,listener,enum,interface,job,test,...}
php artisan make:livewire genetics::upload-results           # SFC -> Modules/Genetics/resources/views/livewire/⚡upload-results.blade.php
php artisan module:make-test UploadResultsTest Genetics --feature   # generates a Pest test
```

Use `module:make-*` (not `make:*`) for anything that belongs to a module. The Boost guideline to use `make:` applies only to the app shell.

The generator is customized. Keep it that way rather than hand-editing generated modules:
- `config/modules.php` turns off nwidart's per-module `package.json`, `vite.config.js`, Sass, master layout, API routes and default controller, and maps `module:make-interface` to `app/Contracts`. All modules share the one Vite/Tailwind/Flux build.
- `stubs/nwidart-stubs/` holds **only the stubs we override**; every other stub falls back to the vendor copy:
  - The module service provider registers the module's Livewire namespace.
  - The route provider maps web routes only.
  - `routes/web.php` starts with no middleware.
  - Test stubs are Pest.

Livewire in modules:
- The namespace is the lowercase module name: `<livewire:genetics::upload-results />` and `Route::livewire('/genetics/upload', 'genetics::upload-results')` in the module's `routes/web.php`. Tests use `Livewire::test('genetics::upload-results')`.
- **Prefer single-file components.** `make:livewire genetics::foo --class` writes a broken `render()` whose view name is built from the absolute filesystem path. A class component without `render()` looks for its view in the app's `resources/views/livewire`, not the module's. Every class component therefore needs `render(): View { return view('genetics::livewire.foo'); }`.
- Module views are `genetics::path.to.view`. Anonymous Blade components in `resources/views/components` are `<x-genetics::name>`.
- Flux is global. Use `<flux:*>` anywhere, and never `flux:publish` into a module.
- Tailwind 4 already scans `Modules/` (verified, no `@source` needed). `vite.config.js` adds module views, routes and Livewire classes to hot reload. PHPStan analyzes `Modules/` but excludes module `resources/` and `tests/`.

## Module map

| Module | Owns | May depend on |
|---|---|---|
| Shared | Kernel: typed IDs, base DTO/enum helpers. Keep it tiny; a type used by only one module doesn't belong here. | nothing |
| Genetics | Upload and parsing of genetic results, variants/genotypes. All raw genetic data. | Shared |
| Supplements | Supplement catalog, research/evidence, gene–supplement interaction knowledge | Shared |
| Intelligence | Provider-agnostic AI gateway contract, prompt templates, request/usage logging. No domain knowledge. | Shared |
| Recommendations | Orchestration: GeneticProfile + supplement evidence → Intelligence → stored recommendation sets | Genetics, Supplements, Intelligence, Shared |
| Reports | PDF generation from recommendation and profile DTOs, storage, download. A read-only consumer. | Recommendations, Genetics, Shared |

No module exists yet. Build them in this order.

## Boundary rules

1. A module's **public** API is its `Contracts`, `Data` (DTOs), `Events`, `Enums`, `Exceptions` and `Testing` (fakes) namespaces. Everything else (`Models`, `Actions`, `Services`, `Livewire`, `Http`, `Listeners`, `Policies`, `Providers`) is private. Another module may use only the public namespaces of a module it is allowed to depend on (see the map).
2. Synchronous cross-module reads go through a contract bound in the owning module's service provider. The contract returns `final readonly` DTOs from the owner's `Data` namespace, never Eloquent models.
3. Side effects in another module go through events. Events implement `ShouldDispatchAfterCommit` and carry IDs and scalars, not models. Listeners are queued and idempotent.
4. A module reads and writes only its own tables. There are no Eloquent relationships, joins or writes across modules. Foreign keys may point only at stable upstream tables (`users`), never between peer modules.
5. Module-owned Blade/Livewire names are always prefixed (`genetics::`, `<x-genetics::…>`). Unprefixed components belong to Flux or the app shell.
6. Each owning module ships an in-memory fake of its contracts under `Testing`. Other modules' tests use the fake, not Mockery against internals.

These rules are not enforced automatically yet. When the first two modules exist, add Pest architecture tests under `tests/Arch/` that fail on any import of another module's private namespaces, and consider Deptrac for the module dependency graph and cycles. Background, evidence and caveats: `reports/Laravel modular monolith practices.md`. That report recommended internachi/modular; the project chose nwidart instead, and the boundary advice applies either way.

## Domain constraints

- **Genetic data is sensitive.** Raw uploads and genotypes stay inside Genetics, stored with encrypted casts and private storage. Other modules, including the AI calls, receive only the summarized `GeneticProfile` DTO.
- **There is no authentication yet.** Genetic data, recommendations and reports belong to a `User`, so add auth before building Genetics.
- **AI calls go only through the Intelligence contract.** Nothing outside Intelligence talks to a provider SDK or HTTP API. Tests bind the Intelligence fake and never hit a real API. The AI layer is `laravel/ai` (pinned `^1.3`, migrations never published) behind Intelligence contracts. Firecrawl is called over REST through `Http` (no SDK). Tests run with `Http::preventStrayRequests()` (set in `tests/TestCase.php`).
- **PDF reports render from DTOs only.** Reports never queries other modules' tables.
- **PDFs use spatie/laravel-pdf v2 with the `chrome` driver** (chrome-php driving a local Chrome/Chromium binary, so no Node or Puppeteer). The default is set in `config/laravel-pdf.php`. Only the Reports module uses the `Pdf` facade.
  - Chrome renders modern CSS, so report views can use Tailwind.
  - Feature tests call `Pdf::fake()` and assert with `Pdf::assertRespondedWithPdf()` and `assertSaved()`, so normal tests don't launch Chrome.
  - Production and CI need a Chrome binary. GitHub's Ubuntu runners ship one, but a real-render test there will likely also need `LARAVEL_PDF_CHROME_NO_SANDBOX=true`.
