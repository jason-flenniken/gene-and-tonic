# Module tooling for modular-monolith Laravel apps (as of 2026-10-24)

Research date: 2026-10-24. Target app: Laravel 13.38, Livewire 4.4, Flux 2.x, Pest 5, PHP 8.4, MySQL. Data pulled from the GitHub API (`gh`), the Packagist JSON API, and vendor docs. Items older than about 12 months (before ~Oct 2025) are labelled **[OLD]**. Version and date are given for every package claim.

## 1. What are the main options as of October 2026, and is there any first-party Laravel movement toward modules?

### Takeaway
Three realistic options: internachi/modular (convention-over-config, modules are Composer path packages), nwidart/laravel-modules (its own `Modules/` layout, runtime enable/disable, generators), and a "no package" approach (hand-rolled folders or path repos with one service provider per module). The newer contenders are tiny and low-adoption. I found no first-party Laravel module feature in the Laravel 13 release notes or the Laracon US 2026 announcements.

### Cited Findings
- Laravel 13.x release notes page: a text search for "modul" returned no hits in about 67k characters of page text, so no first-party modules feature is announced there. This is a negative result from my own search of the page — [Laravel 13.x release notes](https://laravel.com/docs/13.x/releases)
- Laracon US 2026 announcement roundup (published 2026-07-29) lists: image manipulation API, Laravel LSP, Inertia DevTools, CPX, a package skeleton, `artisan dev`, a head tag API, Blade support in Pint, `artisan doctor`, refreshable locks, debounced jobs, "complex bindings", AI SDK/Boost updates, and Cloud features. None is a modules or modular-monolith feature. The "package skeleton" is for building packages, not app modules — [Everything we announced at Laracon US 2026](https://laravel.com/blog/everything-we-announced-at-laracon-us-2026)
- Laracon talk archive (626 talks) has these modular-monolith talks: "Unveiling The Modular Monolith" by Mateus (Laracon EU 2024) **[OLD]**; "Modularising the Monolith" by Ryuta Hamasaki (Laracon Online 2022, domain modules, contracts/DTOs, Deptrac boundaries) **[OLD]**; "Modularizing Inertia" by Ryuta Hamasaki (Laracon India 2025, described as a developer at Laravel on the Nightwatch team) **[OLD-ish, ~12+ months]**; Figured's modular monolith talk (Laracon AU 2019) **[OLD]**. I found no 2026 talk on modules in the archive text — [Laracon Archive](https://laracon-talks.laravel.cloud/)
- Laracasts "Modular Laravel" series by Mateus Guimarães: 25 episodes, 3h 31m, advanced, last updated 2024-01-04 **[OLD]**. The instructor is listed as "Software Developer at Laravel". The page does not name a package; episode descriptions suggest a custom directory structure (not confirmed) — [Laracasts: Modular Laravel](https://laracasts.com/series/modular-laravel)
- Laravel 13 framework event discovery supports multiple directories and wildcards natively: `->withEvents(discover: [__DIR__.'/../app/Domain/*/Listeners'])`. It also has a `ShouldBeDiscovered` interface for conditional discovery. This is what makes a no-package setup viable for events — [Laravel 13.x Events](https://laravel.com/docs/13.x/events)
- Livewire 4 has a native namespace API (`Livewire::addNamespace(...)`, `component_namespaces` config, `addLocation()`, `component_locations`), so module-aware Livewire registration no longer needs a third-party hack — [Livewire 4 docs: components](https://livewire.laravel.com/docs/4.x/components)
- Newer/smaller contenders on Packagist (as of 2026-10-24):
  - saeedvir/laravel-modular v1.4.0 (2026-06-18), 258 total downloads, 29 stars, PHP ^8.2|^8.3, illuminate/support ^11|^12|^13. Its own benchmark claims against nwidart are unverified — [Packagist](https://packagist.org/packages/saeedvir/laravel-modular)
  - alizharb/laravel-modular v1.2.0 (2026-07-07), about 2,988 downloads, 23 stars, PHP ^8.2. Companion alizharb/laravel-modular-livewire v1.1.0 (2026-07-09) requires livewire ^4.0 and about 348 downloads — [Packagist](https://packagist.org/packages/alizharb/laravel-modular), [Livewire companion](https://packagist.org/packages/alizharb/laravel-modular-livewire)
  - studeo/modular v0.2.0 (2026-10-24), 3 downloads, requires PHP ^8.4 and illuminate/support ^13.0. Brand new (v0.1.0 was 2026-10-21) — [Packagist](https://packagist.org/packages/studeo/modular)
  - filefabrik/modular 3.0.4 (2024-03-30), an InterNACHI fork, effectively stale **[OLD]** — [Packagist](https://packagist.org/packages/filefabrik/modular)
  - aurnob/laravel-ddd-modular: 1 download, opinionated DDD — [Packagist](https://packagist.org/packages/aurnob/laravel-ddd-modular)
- Package-free approach, as documented in a 2026 blog post (2026-08-20, Wendell Adriel): plain folders under `app/Modules/Billing/`, a service provider per module registered in `bootstrap/providers.php`, and Pest architecture tests to enforce boundaries. The author says Laravel "does not require one specific modular structure". The article recommends no package and does not mention Deptrac, internachi/modular, or nwidart. The author notes arch tests can't catch raw SQL writes to another module's tables, shared cache keys, or undocumented HTTP calls — [Wendell Adriel](https://wendelladriel.com/blog/modular-monoliths-creating-real-boundaries-before-reaching-for-microservices)
- A search summary says another 2026 article argues Composer path repositories plus a per-module ServiceProvider match what a package gives. I did not open that source, so treat it as unverified — [search result summary, source not fetched]

### Inferences
- With no first-party movement visible in the Laravel 13 notes or the Laracon US 2026 roundup, the ecosystem stays community-driven. The pieces Laravel has added (multi-path event discovery, Livewire 4 namespaces, package skeleton) lower the cost of the no-package route.
- Only internachi/modular and nwidart are mature enough for production reliance. The rest have fewer than 3k total downloads (versus about 1.26M and 19M for the two main ones).

### Gaps
- Could not read Laravel's package-development docs to confirm the exact current text on `extra.laravel.providers` discovery for local path packages; the claim comes via the InterNACHI README.
- No direct source for a Taylor Otwell statement on modules. Absence of evidence only.
- Laracon US 2026 full talk list was not checked; only the announcement roundup was.

## 2. For each option: latest version and date, Laravel 13 support, maintenance health, philosophy, layout

### Takeaway
Both main packages support Laravel 13 and PHP 8.4. internachi/modular 3.0.2 (2026-03-23) is a plugin-based rewrite; its last main-branch push was 2026-05-21. nwidart/laravel-modules v13.0.0 (2026-03-19) is the current stable; the last master commit was 2026-04-13 and its open-issue backlog is larger.

### Cited Findings
**internachi/modular**
- Latest release 3.0.2, published 2026-03-23; notes: "Added Laravel 13 support", a fix to the Boost skill's module directory, and an option to skip the phpunit update in `modules:sync`. Earlier: 3.0.0 on 2026-02-06 (plugin rewrite), 3.0.1 on 2026-02-06 — [GitHub releases](https://github.com/InterNACHI/modular/releases)
- Requirements for 3.0.2: PHP >=8.3, illuminate/support ^11|^12|^13|14.x-dev, composer/composer ^2.1, internachi/modularize ^1.1.0 — [Packagist](https://packagist.org/packages/internachi/modular)
- Adoption/health: about 1.26M total downloads, about 146k monthly, 1,211 stars, 41 open issues; last push to main 2026-05-21; not archived — [Packagist](https://packagist.org/packages/internachi/modular), [GitHub repo](https://github.com/InterNACHI/modular)
- Philosophy: "as much a set of conventions as it is a package". Uses Composer path repositories for autoloading, Laravel package discovery for module initialization, and minimal tooling for gaps. Modules live in `app-modules/` and "follow existing Laravel conventions" — [README](https://github.com/InterNACHI/modular)
- Layout from `make:module`: `app-modules/my-module/{composer.json, src/, tests/, routes/, resources/, database/}`. It adds a path-repository entry and a `modules/my-module:*` require to the app's `composer.json`, then you run `composer update modules/my-module`. Default namespace `Modules\`; README recommends an org namespace via the published config (`modular-config`) — [README](https://github.com/InterNACHI/modular)
- README's own comparison: chose conventions over a custom structure and "lighter weight"; says nwidart is the better fit "if you are building a CMS that needs to support 3rd-party modules that can be dynamically enabled and disabled" — [README](https://github.com/InterNACHI/modular)
- 3.0 breaking changes: PHP 8.3+ and Laravel 11+, `ModularEventServiceProvider` removed, cache file moved to `bootstrap/cache/app-modules.php`, `make:livewire --module` removed in favour of the separate `internachi/modular-livewire` package — [release 3.0.0](https://github.com/InterNACHI/modular/releases/tag/3.0.0), [CHANGES-V3.md](https://github.com/InterNACHI/modular/blob/main/CHANGES-V3.md)
- Open issues are mostly questions and small bugs. Examples: #141 nested modules/submodules feature request (2026-06-13), #137 custom translators in 3.x (2026-03-29), #125 routes with auth middleware in module routes file (2025-11-10), #116 ModuleRegistry fails if nwidart was previously installed (2025-03-23) — [GitHub issues](https://github.com/InterNACHI/modular/issues)

**nwidart/laravel-modules**
- Latest stable v13.0.0, published 2026-03-19 ("Laravel 13.x Compatibility", "prep for l13"), same day as v12.0.5. Before that v12.0.4 was 2025-06-29 **[OLD]**. Version number tracks the Laravel major — [GitHub releases](https://github.com/nWidart/laravel-modules/releases)
- composer.json: PHP ^8.3 (8.2 dropped in the L13 prep commit), ext-dom, ext-json, ext-simplexml, wikimedia/composer-merge-plugin ^2.1; dev-requires laravel/framework ^13.0 and testbench ^11 — [composer.json](https://github.com/nWidart/laravel-modules/blob/master/composer.json)
- Adoption/health: about 19.0M total downloads, about 938k monthly, 6,217 stars, 24 open issues in the first-page list I pulled; last master commit 2026-04-13 (Laravel Boost skill/guideline support); v12.0.5 notes mention PHP 8.5 CI testing, a `ModuleServiceProvider` base class, and Inertia support — [Packagist](https://packagist.org/packages/nwidart/laravel-modules), [GitHub](https://github.com/nWidart/laravel-modules), [v12.0.5 notes](https://github.com/nWidart/laravel-modules/releases/tag/v12.0.5)
- Philosophy: "A Module is like a Laravel package, it has some views, controllers or models". It has its own structure and generator, module.json per module, runtime enable/disable, and a composer-merge-plugin to autoload `Modules/*/composer.json`. Docs say from v11 the `"Modules\\": "modules/"` PSR-4 root entry is no longer needed; new modules autoload from an `app/` folder — [README](https://github.com/nWidart/laravel-modules), [Upgrade docs](https://laravelmodules.com/docs/13/getting-started/upgrade)
- Layout (v13 docs): new modules include `app/`, `config/`, `database/`, `resources/`, `routes/`, `tests/`, `composer.json`, `module.json`, `package.json`, `vite.config.js`. Generator paths (controllers `app/Http/Controllers`, migrations `database/migrations`, views `resources/views`, etc.) are configurable in `config/modules.php`, and many folder types default to `generate => false` — [config.php](https://github.com/nWidart/laravel-modules/blob/master/config/config.php), [Upgrade docs](https://laravelmodules.com/docs/13/getting-started/upgrade)
- Open issues show some friction: #2159 `module:migrate*` commands use two different code paths with inconsistent results (2026-05-07); #2164 provider class not found after setup (2026-05-24); #2128 auto-discovering event listeners across modules (2025-11-09); #2120 hot refresh for Blade in modules with root vite.config.js (2025-10-02) — [GitHub issues](https://github.com/nWidart/laravel-modules/issues)

**Side-by-side summary (my synthesis from the cited facts)**

| | internachi/modular 3.0.2 | nwidart/laravel-modules v13.0.0 | No package |
|---|---|---|---|
| Released | 2026-03-23 | 2026-03-19 | n/a |
| L13 / PHP | illuminate ^13, PHP >=8.3 | laravel ^13, PHP ^8.3 | native |
| Dir | `app-modules/<name>/src` | `Modules/<Name>/app` (+ module.json) | any (e.g. `app/Modules/X`) |
| Discovery | Composer path repo + package discovery + plugins | merge-plugin + own scanner + module.json | `bootstrap/providers.php` and PSR-4 |
| Runtime toggle | no (all modules always load) | yes | DIY |
| Livewire | separate `modular-livewire` 2.0.1 | third-party `mhmiton/laravel-modules-livewire` v7.0.0 | `Livewire::addNamespace` |

### Inferences
- Both packages are alive but neither shows rapid cadence. Both shipped L13 support within a few weeks of Laravel 13's release (modular 2026-03-23, nwidart 2026-03-19) and had no tagged releases after March 2026. internachi's last main push is 2026-05-21 and nwidart's last master push is 2026-04-13. That suggests "stable, low-churn" rather than abandoned, but I could not verify that no L13.x regressions are open.
- nwidart's version-tracks-Laravel scheme means it needs a new major each Laravel major (typically a day-one Shift-style PR); internachi uses a version range, so it tends to widen the constraint instead.

### Gaps
- Did not find explicit PHP 8.4-specific notes for either package; both require >=8.3 and nwidart CI tests PHP 8.5, so 8.4 should work but this is inferred.
- Did not confirm that Laravel 13.38 specifically has been tested with either package. Only the "13.x" constraint evidence exists.
- Dates of Packagist version `time` values can differ from GitHub release timestamps by minutes; I used GitHub release dates.

## 3. Per-module migrations, routes, config, views, translations, factories, seeders, commands, policies, events, tests

### Takeaway
internachi/modular auto-discovers nearly everything through plugins (routes, views, Blade components, translations, events, migrations, policies, commands) and also factories and seeders via `--module` flags. Config is not in its plugin list, so it is registered through the module's own provider (my inference). nwidart relies on a generated per-module service provider and RouteServiceProvider to wire resources, and its test wiring is a wildcard phpunit testsuite. The no-package route needs explicit wiring for each, but Laravel 13 already handles event wildcards.

### Cited Findings
**internachi/modular 3.0.x**
- Built-in plugins: `ModulesPlugin` (finds module `composer.json`), `RoutesPlugin` (only when routes are not cached), `ViewPlugin` (view namespaces), `BladePlugin` (component namespaces), `TranslatorPlugin` (translation namespaces and JSON paths), `EventsPlugin` (listener discovery), `MigratorPlugin` (migration paths), `GatePlugin` (policies), `ArtisanPlugin` (commands). The `modules:cache` command caches all discovery data in `bootstrap/cache/app-modules.php` — [release 3.0.0 notes](https://github.com/InterNACHI/modular/releases/tag/3.0.0), [CHANGES-V3.md](https://github.com/InterNACHI/modular/blob/main/CHANGES-V3.md)
- README states: commands auto-registered, migrations run by the Migrator, factories auto-loaded for `factory()`, policies auto-discovered, Blade components auto-discovered, listeners auto-discovered. `make:*` commands accept `--module=<name>` (cast, controller, command, component, channel, event, exception, factory, job, listener, mail, middleware, model, notification, observer, policy, provider, request, resource, rule, seeder, test). `db:seed --module=<name>` runs `Modules\<Name>\Database\Seeders\DatabaseSeeder` (or `--class`) — [README](https://github.com/InterNACHI/modular)
- Views and translations: `view('demo::...')` / `__('demo::messages.welcome')` with translation files at `resources/lang/en/messages.php`. Blade components: class components at `src/View/Components/Basic.php` become `<x-demo::basic />`; anonymous components at `resources/components/anonymous.blade.php` become `<x-demo::anonymous />` — [README](https://github.com/InterNACHI/modular)
- Config: `config/` is not in the README auto-discovery list or the plugin table. The migration guide says providers are optional because views, translations and migrations are auto-discovered, and that a provider is listed under `extra.laravel.providers` in the module composer.json — [from-nwidart migration guide (Mintlify page, not confirmed official)](https://mintlify.wiki/internachi/modular/migration/from-nwidart)
- Tests: `php artisan modules:sync` adds a `Modules` testsuite to `phpunit.xml` with directory `./app-modules/*/tests` (it reads `app-modules.modules_directory`, default `app-modules`) and skips if it exists; the 3.0.2 release added `--no-phpunit` to skip the update — [ModulesSync.php](https://github.com/InterNACHI/modular/blob/main/src/Console/Commands/ModulesSync.php), [release 3.0.2](https://github.com/InterNACHI/modular/releases/tag/3.0.2)
- Module settings: `make:module` stubs are customizable via the published `app-modules.php` config, with placeholders like `StubModuleName`, `StubComposerNamespace`, `StubTestCaseBase` — [README](https://github.com/InterNACHI/modular)

**nwidart/laravel-modules v13**
- Tests: add a `Modules` testsuite with wildcard paths `./Modules/*/tests/Feature` and `./Modules/*/tests/Unit` (files ending `Test.php`); coverage includes `./app` and `./Modules`. Run one module with `--filter 'contacts'`. Pest: docs say to `uses(Tests\TestCase::class)` in the file and run `vendor/bin/pest` with the same filter. The docs don't mention Pest 5 — [Tests docs](https://laravelmodules.com/docs/13/advanced/tests)
- Docs index lists dedicated pages for Migrations, Models, Seeders and Factories, Routing, Controllers, Views, Service Providers, Middleware, Module Events, Languages and Translations, Artisan Commands, Console Commands, Inertia, Livewire — [Docs index](https://laravelmodules.com/docs/13)
- Generator config defaults: provider and route-provider generate `true` under `app/Providers`; controller, config, factory, migration, seeder, views and assets generate `true`; policies, listeners, events, models and many others default to `false` — [config.php](https://github.com/nWidart/laravel-modules/blob/master/config/config.php)
- Event discovery across modules is an open feature request (#2128, 2025-11-09), which suggests listeners are not auto-discovered by the package — [issue list](https://github.com/nWidart/laravel-modules/issues)
- Migrations: #2159 reports `module:migrate*` commands use two different code paths with inconsistent results (2026-05-07) — [issue list](https://github.com/nWidart/laravel-modules/issues)

**No package (Laravel 13 native)**
- Events: `->withEvents(discover: [__DIR__.'/../app/Domain/*/Listeners'])` with wildcard `*` in `bootstrap/app.php`; `event:cache`/`optimize` caches the manifest — [Laravel 13.x Events](https://laravel.com/docs/13.x/events)
- Service providers per module registered in `bootstrap/providers.php`, with Pest architecture tests for boundaries — [Wendell Adriel, 2026-08-20](https://wendelladriel.com/blog/modular-monoliths-creating-real-boundaries-before-reaching-for-microservices)

### Inferences
- Modular's plugin list covers most per-module resources automatically; a module's `config/` and any service bindings still need a provider, which can be listed under `extra.laravel.providers`.
- For the no-package route, migrations (`loadMigrationsFrom`), routes, views, translations, config merging and policies must each be wired in a provider (standard `ServiceProvider` methods; I did not fetch Laravel's docs for these, so treat as general framework knowledge). Event listener discovery is the one area Laravel 13 now helps natively.
- Pest 5 module test discovery depends on `phpunit.xml` testsuites and on `tests/Pest.php` `pest()->extend(...)->in(...)` bindings. With any approach, module test directories outside `tests/` need the base TestCase bound (nwidart docs say `uses(Tests\TestCase::class)` per file). I did not find Pest-5-specific documentation for modules.

### Gaps
- Could not confirm exactly how internachi/modular registers module `config/` files or seeders' factory resolution beyond the README (source for ModulesPlugin/factory helper not read in full).
- Policy discovery for nwidart not confirmed (no doc page read).
- Pest 5 plus modules: no source found; blog posts found were generic.

## 4. Integration with Livewire 4, Blade components, and Vite/Tailwind 4

### Takeaway
internachi/modular does register Blade component namespaces automatically but handles Livewire through a separate plugin package (internachi/modular-livewire 2.0.1, which supports Livewire 4 via `Livewire::addNamespace`). Neither package ships Tailwind 4 guidance; nwidart documents a Vite module loader. Tailwind 4's automatic source detection scans the project from the working directory, so module folders inside the repo (not gitignored) are scanned without extra config.

### Cited Findings
**Livewire**
- internachi/modular-livewire: 2.0.1 (2026-09-07), 2.0.0 (2026-07-21, "Drops Laravel 11 Support"), 1.1.0 (2026-05-25, "Livewire v4 support"); requires PHP >=8.3, illuminate/support ^12|^13, internachi/modular ^3.0, livewire ^3.0|^4.0; the main repo last pushed 2026-10-21; 2 stars. The 2.0.1 release is only dependabot bumps — [releases](https://github.com/InterNACHI/modular-livewire/releases), [Packagist](https://packagist.org/packages/internachi/modular-livewire)
- Behavior (source, `ModularLivewirePlugin.php`): scans `*.php` in each module's `src/Livewire`; naming is `{module}::{kebab.path}`, e.g. `<livewire:billing::invoice-table />`. On Livewire 4 it calls `Livewire::addNamespace(namespace: $module, classNamespace: <module>\Livewire, viewPath: <module>/resources/views/livewire)`; on v3 it calls `Livewire::component()` per class — [source](https://github.com/InterNACHI/modular-livewire/blob/main/src/ModularLivewirePlugin.php), [README](https://github.com/InterNACHI/modular-livewire)
- The README still lists `livewire/livewire ^3.0` under requirements while composer.json says `^3.0|^4.0` (README is stale) — [README](https://github.com/InterNACHI/modular-livewire), [Packagist](https://packagist.org/packages/internachi/modular-livewire)
- Livewire 4 provides the primitives: `Livewire::addNamespace(namespace, viewPath, classNamespace, classPath, classViewPath)` for SFC/MFC and class components, `addLocation()`, `component_locations`; usage `<livewire:admin::users-table />`, `Route::livewire('/admin/users', 'admin::users-table')` — [Livewire 4 docs](https://livewire.laravel.com/docs/4.x/components); latest Livewire release v4.4.8 on 2026-10-21 — [Livewire releases](https://github.com/livewire/livewire/releases)
- nwidart: docs point to the third-party `mhmiton/laravel-modules-livewire`; Packagist shows v7.0.0 (2026-03-19) requiring livewire ^4.0, laravel/framework ^10-^13, nwidart/laravel-modules >=13.0, about 613k downloads, 234 stars. Manual alternative from the docs: `Livewire::component('module::name', Class::class)` in the module provider's `boot` — [nwidart Livewire docs](https://laravelmodules.com/docs/13/resources/livewire), [Packagist](https://packagist.org/packages/mhmiton/laravel-modules-livewire)

**Blade components / Flux**
- internachi/modular registers both class and anonymous Blade component namespaces automatically, giving `<x-demo::basic />`, `<x-demo::anonymous.nested />` — [README](https://github.com/InterNACHI/modular)
- No source found on Flux 2.x with either package. Flux is installed app-level and its `<flux:...>` tags are not module-scoped, so module views should work as long as they render inside the app's layout (inference).

**Vite / Tailwind 4**
- nwidart documents two options: (1) the root `vite.config.js` imports a published `vite-module-loader.js` and calls `collectModuleAssetsPaths(paths, 'Modules')`; each module's `vite.config.js` exports only a `paths` array; Blade uses `@vite(\Nwidart\Modules\Module::getAssets())`; or (2) per-module builds into `public/build-{module}` using `module_vite()`. The page says don't use both and does not mention Tailwind. Root-config Vite hot refresh for module Blade files is an open issue (#2120, 2025-10-02) — [Compiling Assets docs](https://laravelmodules.com/docs/13/basic-usage/compiling-assets), [issue list](https://github.com/nWidart/laravel-modules/issues)
- internachi/modular: no Vite or Tailwind mention in the README or plugin list; assets are left to the app's own Vite config — [README](https://github.com/InterNACHI/modular)
- Tailwind v4 automatic source detection scans everything under the current working directory except `.gitignore`d files, `node_modules`, binaries, CSS and lockfiles. `@source "../path"` (relative to the stylesheet) adds directories, and `@source not` excludes; `source(none)` disables auto-detection — [Tailwind docs](https://tailwindcss.com/docs/detecting-classes-in-source-files)

### Inferences
- A module directory at the repo root (`app-modules/` or `Modules/`) is not gitignored, so Tailwind 4 should scan its Blade and Livewire view files automatically when the Vite build runs from the repo root. Only `vendor/` copies (the Composer symlinks) are skipped. This is inferred; I did not run a build.
- For internachi/modular with Livewire 4: the plugin's `discover()` only keys on PHP files in `src/Livewire`, so a module containing only single-file components (no PHP class) may not get its namespace registered. This is an inference from reading the source; I did not test it. A manual `Livewire::addNamespace()` in an app or module provider would work around it.
- With a single root `vite.config.js` and Tailwind entry, inter-module asset conflicts are avoided; the per-module build option in nwidart adds build complexity that a Tailwind 4 single-stylesheet app probably doesn't need.

### Gaps
- No verified account of Flux 2.x components used inside module views.
- Did not test `modular-livewire` with Livewire 4 SFC/MFC (⚡) component files.
- Could not verify Pest 5 + Livewire testing inside modules.

## 5. Pain points, migration stories, community sentiment, and what experienced developers recommend

### Takeaway
Evidence of 2026 sentiment is thin. The only first-hand material I found: the InterNACHI README (own recommendation), a 2026 blog advocating the no-package path with Pest arch tests, and older Reddit-style consensus (2021-2022) favouring InterNACHI for organization and nwidart for tooling/dynamic modules. I did not find a credible, recent, hands-on comparison.

### Cited Findings
- InterNACHI's own recommendation: choose modular for organization with Laravel conventions; choose nwidart if you need dynamically enabled/disabled third-party modules — [README](https://github.com/InterNACHI/modular)
- The (likely project-hosted, not confirmed official) migration guide maps `Entities/` to `src/Models/`, `Http/Controllers/` to `src/Http/Controllers/`, `Config/` to `config/`, `Database/Migrations/` to `database/migrations/`, and `module.json` to `composer.json`. It notes modular has no runtime enable/disable ("All modules always loaded") and suggests feature flags or route middleware, or staying with nwidart — [migration guide](https://mintlify.wiki/internachi/modular/migration/from-nwidart)
- A migration trap is on record: issue #116 "ModuleRegistry fails if you have previously installed nWidart/laravel-modules" (2025-03-23, still open in the list pulled 2026-10-24) — [GitHub issues](https://github.com/InterNACHI/modular/issues)
- Laravel News coverage of InterNACHI/modular dated 2024-02-21 **[OLD]**: highlights autoloading, `--module` flag on `make:*`, auto-discovery of commands, migrations, policies, Blade components and listeners, and that it doesn't dictate what belongs in a module versus `app/` — [Laravel News](https://laravel-news.com/laravel-modular-package)
- Older community sentiment **[OLD]** (Reddit 2021-2022, via a search summary; not read directly): leaning to InterNACHI/modular for simple organization and nwidart for tooling/stubs; nwidart described as more Blade-oriented — [LibHunt comparison](https://www.libhunt.com/compare-laravel-modules-vs-InterNACHI--modular) (search summary only)
- A Laracasts thread (undated in search result) describes wanting module Livewire components kept thin, with logic extracted to services/actions — [Laracasts discussion](https://laracasts.com/discuss/replies/963288)
- Boundary enforcement: Pest architecture tests (2026 blog) and Deptrac (Ryuta Hamasaki, Laracon Online 2022 **[OLD]**) — [Wendell Adriel](https://wendelladriel.com/blog/modular-monoliths-creating-real-boundaries-before-reaching-for-microservices), [Laracon Archive](https://laracon-talks.laravel.cloud/)
- Known open feature gap: nested modules/submodules in InterNACHI (#141, 2026-06-13) — [GitHub issues](https://github.com/InterNACHI/modular/issues)

### Inferences
- For this app (Laravel 13.38, Livewire 4.4, Flux, Pest 5, MySQL, organization-driven modularization, no need for runtime toggling), internachi/modular + internachi/modular-livewire fits the stated criterion best on paper: Composer-package model, Livewire 4 support, `--module` generators, plus the easy escape hatch of extracting a module to a real package. nwidart is better only if you need runtime enable/disable or its Inertia/Livewire/Vite loader conventions.
- The no-package route (own folders plus a provider per module plus Pest arch tests) is a credible third choice if the team wants zero dependencies on a one-maintainer-led project. internachi/modular's maintainer base looks narrow (Chris Morrell plus a few contributors; modular-livewire has 2 stars), so it is a bus-factor risk. The cost of leaving is low because the modules are plain Composer packages.
- Neither package governs boundaries; either approach needs Pest arch tests or Deptrac to enforce them.

### Gaps
- No recent (2026) first-person migration stories or Reddit/Laracasts threads comparing the two; the summaries I saw were 2021-2022.
- Could not locate talks or posts by Spatie or Tighten on modular tooling in 2026.
- Livewire 4 plus modules pain points: only the thin-component Laracasts thread and the SFC inference above.
- No Packagist/GitHub evidence of regressions on Laravel 13.38 specifically for either package.
