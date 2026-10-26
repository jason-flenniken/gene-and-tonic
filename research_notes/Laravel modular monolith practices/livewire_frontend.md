# Livewire 4 and Blade front end in a modular Laravel 13 app (as of 2026-10-24)

Method note: Livewire/modular/nwidart/modular-livewire/mhmiton claims below marked "(source read)" were verified by cloning the repos on 2026-10-24 and reading the code (livewire main, which is just past v4.4.8 released 2026-10-21). Docs pages were fetched from livewire.laravel.com/docs/4.x. Latest tags at the time: Livewire v4.4.8 (also v3.8.11 maintenance line), Flux v2.21.0 ([releases](https://github.com/livewire/livewire/releases), [flux releases](https://github.com/livewire/flux/releases)). Blog-post advice was not used.

## 1. Livewire 4 component registration outside default directories

### Takeaway
Livewire 4 has a first-class, resolver-based registry (`livewire.finder`) with three config/API layers: `component_locations` (unnamespaced view dirs), `component_namespaces` (named view dirs, `ns::name`), and the programmatic `Livewire::addLocation()` / `addNamespace()` / `addComponent()`. The config keys only cover view-based (SFC/MFC) components; class components in a module need the programmatic API, and `Livewire::addNamespace()` does NOT register a Blade view namespace (only the config path does).

### Cited Findings
- v4 config adds `component_locations` (defaults `resource_path('views/components')`, `resource_path('views/livewire')`) and `component_namespaces` (defaults `layouts` and `pages` => `resources/views/layouts|pages`); `layout` renamed `component_layout` (default `layouts::app`), `lazy_placeholder` renamed `component_placeholder`; `smart_wire_keys` now defaults true — [Upgrade guide](https://livewire.laravel.com/docs/4.x/upgrading), [config/livewire.php](https://github.com/livewire/livewire/blob/main/config/livewire.php)
- Documented programmatic API (call in a service provider `boot()`): `Livewire::addComponent(name:, viewPath:)`, `Livewire::addLocation(viewPath:)`, `Livewire::addNamespace(namespace: 'ui', viewPath:)`, `Livewire::addComponent(name:, class:)`, `Livewire::addLocation(classNamespace:)`, `Livewire::addNamespace(namespace:, classNamespace:, classPath:, classViewPath:)` — [Components docs](https://livewire.laravel.com/docs/4.x/components)
- Actual signatures (source read): `LivewireManager::addLocation($viewPath = null, $classNamespace = null)`; `addNamespace($namespace, $viewPath = null, $classNamespace = null, $classPath = null, $classViewPath = null)`; `addComponent($name, $viewPath = null, $class = null)`; `component($name, $class)` still exists as alias for class registration — [LivewireManager.php](https://github.com/livewire/livewire/blob/main/src/LivewireManager.php)
- Only the config loop calls `app('blade.compiler')->anonymousComponentPath($location, $namespace)` and `app('view')->addNamespace($namespace, $location)` (and only `if is_dir`); `Livewire::addNamespace()` just records into the Finder (source read) — [LivewireServiceProvider.php](https://github.com/livewire/livewire/blob/main/src/LivewireServiceProvider.php)
- The default class location is registered as `addLocation(classNamespace: config('livewire.class_namespace'))` ('App\\Livewire'). Config keys `class_namespace`, `class_path`, `view_path` still drive `make:livewire --class` for unnamespaced names (source read) — same file and [MakeCommand.php](https://github.com/livewire/livewire/blob/main/src/Features/SupportConsoleCommands/Commands/MakeCommand.php)
- Resolution order in `Factory::resolveComponentNameAndClass` (source read): (1) class component (namespaced: `classNamespaces[ns]['classNamespace']` + studly dotted name; tries `Foo`, `Foo\Index`, `Foo\Foo`; unnamespaced: explicit `addComponent` first, then each class location); (2) multi-file component directory; (3) single-file component file; else `ComponentNotFoundException`. Resolved class cached per name — [Factory.php](https://github.com/livewire/livewire/blob/main/src/Factory/Factory.php), [Finder.php](https://github.com/livewire/livewire/blob/main/src/Finder/Finder.php)
- SFC lookup inside a namespace dir tries, in order, for name `a.b`: `⚡b.blade.php` (also the two emoji variation selectors), `b/⚡index.blade.php`, `b/⚡b.blade.php`, then non-emoji `b.blade.php`, `b/index.blade.php`, `b/b.blade.php`. A file only counts as an SFC if it contains `<?php ... new [#[attrs]] class` (so a plain Blade partial in the same dir is not mistaken for one). MFC: directory `⚡b/` (or `b/`, `b/index`, `b/b`) containing `<basename>.php` and `<basename>.blade.php` (source read) — Finder.php
- Namespaced lookups do NOT fall through to other namespaces or locations; an unknown namespace returns null (source read) — Finder.php. Names normalise `/` to `.` and strip ⚡ — Finder.php
- Format equivalence: "All three formats resolve to the same name in Blade and routes" (`post.create`, `pages::post.create`) — [Components docs](https://livewire.laravel.com/docs/4.x/components)
- Blade usage: `<livewire:pages::post.create />`; routes: `Route::livewire('/admin/users', 'admin::users-table')` — Components docs
- Class-based component names derived from class: `Finder::normalizeName` maps a class under a registered class namespace/location back to `ns::kebab.name` (strips namespace prefix, `.index` collapses) (source read), so `<livewire:billing::invoice-table>` and `Livewire::test(InvoiceTable::class)` are consistent.
- `make:livewire` (source read, MakeCommand.php): name is kebab-normalised; `billing::invoice-table` with default `make_command.type` (sfc) calls `resolveSingleFileComponentPathForCreation` -> `<namespace viewPath>/⚡invoice-table.blade.php`; unregistered namespace errors with "Namespace [x] is not registered. Register it in config/livewire.php under 'component_namespaces' or use Livewire::addNamespace() in a service provider." Without a namespace the file goes to the FIRST entry of `component_locations`. Flags: `--sfc|--mfc|--class|--type=`, `--emoji=true|false`, `--test`, `--js`, `--css` (js/css are MFC-only) — [Components docs](https://livewire.laravel.com/docs/4.x/components)
- Class-with-namespace creation (source read): `make:livewire billing::invoice-table --class` reads `classNamespaces['billing']` and takes `classPath` / `classViewPath` from it to write the class and view; if those were not passed to `addNamespace`, they are null and the computed file paths degenerate (`'' . '/' . 'InvoiceTable.php'`, i.e. filesystem root). So any provider that wants `--class` generation for a module must pass all three class args.
- `livewire:convert name [--sfc|--mfc]` converts formats; `livewire:layout` creates the layout file named by `component_layout`, and resolves a `ns::` layout through `config('livewire.component_namespaces.<ns>')` (source read: LayoutCommand.php).
- Default for new installs is `make_command.type = 'sfc'`, `emoji = true` per the shipped config; the docs example shows `'type' => 'class', 'emoji' => false` as the way to keep v3-style — config/livewire.php; Components docs.
- Whole-segment class-namespace matching bug fix shipped in 4.4.1 (prevents `Admin` matching `AdminExtra`) — [v4.4.1 notes](https://github.com/livewire/livewire/releases/tag/v4.4.1)

### Inferences
- Recommended per-module provider (works for all three formats; adapt names):
  ```php
  // Modules/Billing/src/Providers/BillingServiceProvider.php  boot()
  $this->loadViewsFrom(__DIR__.'/../../resources/views', 'billing');          // Blade views + <x-billing::...> fallback
  Blade::anonymousComponentPath(__DIR__.'/../../resources/views/components', 'billing'); // <x-billing::button>
  Livewire::addNamespace(
      namespace: 'billing',
      viewPath: $base.'/resources/views/livewire',          // SFC + MFC
      classNamespace: 'Modules\\Billing\\Livewire',          // class components
      classPath: $base.'/src/Livewire',                      // needed for make:livewire --class
      classViewPath: $base.'/resources/views/livewire',
  );
  ```
  Using the same string for the Livewire namespace and the Blade view namespace keeps `billing::foo` unambiguous everywhere.
- Because a namespace key is mandatory for module isolation (unnamespaced locations are global and first-match-wins), prefer `addNamespace` over `addLocation` for modules; use `addLocation` only for a shared/global kit.
- Config-file `component_namespaces` is workable for a handful of modules but cannot express class components; a provider-based registration is the better fit for a modular app (and is what both integration packages do).
- Directory shape per module (common SFC-first layout): `resources/views/livewire/⚡invoice-table.blade.php` -> `<livewire:billing::invoice-table />`; nested `livewire/reports/⚡monthly.blade.php` -> `billing::reports.monthly`.

### Gaps
- The docs do not state precedence between a class component and an SFC with the same name inside one namespace (source says class wins, then MFC, then SFC).
- `make:livewire --class` with a namespace is only exercised in Livewire's own tests for the happy path with all args; the null-path failure mode is my reading of the code, not a reproduced bug.

## 2. Full-page components per module: `Route::livewire`, layouts, `layouts::`

### Takeaway
`Route::livewire($uri, 'billing::invoices.index')` is the required way to route SFC/MFC components as pages and works with module namespaces. Layout is resolved per component: `#[Layout('...')]`, `->layout()` in render, or the global `component_layout`; the layout name can be any view or anonymous Blade component, so a per-module layout is just a view name, and `layouts::` is only the default config namespace.

### Cited Findings
- `Route::get('/x', Dashboard::class)` still works in v4 but is "no longer recommended"; `Route::livewire()` "is required for single-file and multi-file components to work as full-page components"; view-based components are referenced by name, e.g. `Route::livewire('/dashboard', 'pages::dashboard')` — [Upgrade guide](https://livewire.laravel.com/docs/4.x/upgrading)
- Implementation (source read): macro `Route::livewire($uri, $component)` = `Route::get($uri, LivewirePageController::class)` with `action['livewire_component']` set, returns the normal `Route` so `->name()`, `->middleware()`, `->where()`, groups, prefixes, and route caching (string component) all work; `LivewirePageController` calls `app('livewire')->new($component)` then `__invoke` — [HandleRouting.php](https://github.com/livewire/livewire/blob/main/src/Mechanisms/HandleRouting/HandleRouting.php), [LivewirePageController.php](https://github.com/livewire/livewire/blob/main/src/Mechanisms/HandleRouting/LivewirePageController.php)
- Route params go to `mount()` by name; implicit model binding works via `mount(Post $post)` or `public Post $post` — [Pages docs](https://livewire.laravel.com/docs/4.x/pages)
- Default layout `layouts::app` = `resources/views/layouts/app.blade.php`; must contain `{{ $slot }}`, `@livewireStyles`, `@livewireScripts`; generate with `php artisan livewire:layout`; override default with `'component_layout' => 'layouts::dashboard'` — Pages docs
- Per component: `new #[Layout('layouts::dashboard')] class extends Component {` or `$this->view()->layout('layouts::dashboard')` in `render()`; `#[Title('...')]` or `->title()`; layout needs `<title>{{ $title ?? config('app.name') }}</title>`; named slots from the component via `<x-slot:lang>fr</x-slot>` outside the root element — Pages docs
- Mechanism (source read): the layout is rendered with `@component($layout->view, $layout->params)` with the page content in `slot` (`type = 'component'`); the legacy `->extends()` / `@section` path also exists. Since it is `@component(<view name>)`, any resolvable view name or anonymous component path works (e.g. `billing::layouts.app`, or `layouts::billing`). Layout params (`#[Layout('x', ['foo'=>1])]` and `->layoutData()`) are merged into the component's data — [SupportPageComponents.php](https://github.com/livewire/livewire/blob/main/src/Features/SupportPageComponents/SupportPageComponents.php), [PageComponentConfig.php](https://github.com/livewire/livewire/blob/main/src/Features/SupportPageComponents/PageComponentConfig.php)
- v4 changed the layout default from `components.layouts.app` to `layouts::app`; the `layouts` namespace is registered by config (`component_namespaces.layouts`), which also registers it as a Blade anonymous-component path and view namespace — config/livewire.php; LivewireServiceProvider.php (source read)
- Route-level lazy loading macro (`->lazy()`) is registered by `SupportLazyLoading::registerRouteMacro()` (source read, HandleRouting.php)

### Inferences
- Module routes file: `Route::middleware(['web','auth'])->prefix('billing')->name('billing.')->group(function () { Route::livewire('/invoices', 'billing::invoices.index')->name('invoices.index'); });` — routes are loaded by the module package (modular auto-loads `routes/*.php` via its `RoutesPlugin`; nwidart via the module's RouteServiceProvider).
- Layout options: (a) one shared `layouts::app` (default; one shell, modules only supply content — best for most modular monoliths); (b) per-module layout selected by `#[Layout('billing::layouts.app')]` on every page component (repetitive; instead put the attribute on a module base class for class components, or set `->layout()` ...); (c) a shared base layout in a core module and thin module layouts that `<x-layouts::app>`-wrap it. Recommended: keep one app shell with a nav built from a registry (see section 6), and use per-module layouts only for genuinely different chrome (e.g. an admin or public portal).
- SFC `#[Layout]` is applied as an attribute on the anonymous class (`new #[Layout(...)] class extends Component`), per docs; there is no per-route layout argument on `Route::livewire`, so per-module default layout cannot be set at the route-group level (inference from the macro source taking only `$uri, $component`).

### Gaps
- No documented way to set a default layout per route group; not found in docs or source.
- Did not verify whether `layouts::` as a view namespace can be extended by multiple modules (view namespaces accept multiple paths in Laravel via `addNamespace`/`loadViewsFrom`, but Livewire config maps one path per namespace).

## 3. Blade view namespaces, anonymous components, Flux as shared UI kit

### Takeaway
Use Laravel's standard primitives per module (`loadViewsFrom` for `module::view`, `Blade::anonymousComponentPath($dir, $prefix)` for `<x-module::name>`, `Blade::componentNamespace` for class components). Both module packages do the view and class-component registration; neither registers anonymous component paths for you. Flux is a global, vendor-level kit (`<flux:*>`) that needs no per-module wiring.

### Cited Findings
- nwidart/laravel-modules `ModuleServiceProvider::registerViews()` (source read): `loadViewsFrom([...published, $sourcePath], $nameLower)` and `Blade::componentNamespace(config('modules.namespace').'\\'.$this->name.'\\View\\Components', $this->nameLower)`; no `anonymousComponentPath` — [ModuleServiceProvider.php](https://github.com/nWidart/laravel-modules/blob/master/src/Support/ModuleServiceProvider.php)
- internachi/modular v3 plugins (source read): `ViewPlugin` adds a view namespace per module (namespace = module name, path = module `resources/views`), `BladePlugin` calls `$blade->component($fqcn, null, $moduleName)` for class components and `$blade->componentNamespace(<module ns>\View\Components, $moduleName)`; no anonymous component path — [BladePlugin.php](https://github.com/InterNACHI/modular/blob/main/src/Plugins/BladePlugin.php), [ViewPlugin.php](https://github.com/InterNACHI/modular/blob/main/src/Plugins/ViewPlugin.php)
- Livewire's own config loop registers anonymous component paths for `component_locations` (unnamespaced; so `resources/views/components` content is available as `<x-name>`) and `component_namespaces` (prefixed), if the directory exists at boot (source read, LivewireServiceProvider.php)
- Flux install on Tailwind v4: `@import 'tailwindcss'; @import '../../vendor/livewire/flux/dist/flux.css'; @custom-variant dark (&:where(.dark, .dark *));`; requires Tailwind >= 4.2; layout needs `@fluxAppearance` in head and `@livewireScripts` then `@fluxScripts` before `</body>`; `flux:publish` copies component Blade files into the app (docs flag them as your responsibility to maintain); Pro via `flux:activate` / composer http-basic — [Flux installation](https://fluxui.dev/docs/installation)
- `flux.css` itself contains `@source "../../flux-pro/stubs"; @source "../stubs";` so Tailwind picks up Flux's own Blade classes without extra app config (fetched raw) — [flux.css](https://github.com/livewire/flux/blob/main/dist/flux.css)
- Flux docs say nothing about modules or custom component paths — Flux installation page.
- nwidart issue "Flux UI components not working" (#2090, closed 2025-06) exists but the body was empty in the CLI fetch, so no diagnosis was recoverable — [issue](https://github.com/nWidart/laravel-modules/issues/2090)

### Inferences
- Shared UI kit pattern: Flux (`<flux:button>`, `<flux:input>` ...) is the base primitives layer, available in every module's views with no registration. Domain-agnostic composites go in a `ui`/`core` module as anonymous components (`Blade::anonymousComponentPath(core/resources/views/components, 'ui')` -> `<x-ui::page-header>`), not class components, to avoid a PHP dependency from every module on core. Modules depending on `<x-ui::...>` or Flux creates a view-level dependency on core/Flux: acceptable and should be recorded in the module dependency rules.
- Do not publish Flux components into module directories (`flux:publish` writes to `resources/views/flux`, which is app-global); overrides belong at app level so there is one source of truth.
- Prefer prefix-per-module for anything module-owned (`<x-billing::...>`, `billing::` views) and unprefixed names only for the shared kit, to prevent anonymous component name collisions across modules (anonymous paths without prefix are first-found).

### Gaps
- No evidence on whether Flux's `flux:` tag precompiler has any issue inside SFC compiled views stored under `storage/framework/views/livewire` (no reports found; no reason to expect one).
- Could not read the body of nwidart #2090.

## 4. Vite + Tailwind 4 for modules

### Takeaway
Tailwind v4 auto-scans everything under the project (cwd) that is not git-ignored, so `app-modules/*`/`Modules/*` views are detected with zero config as long as they are inside the root and not ignored; add `@source` only for paths outside the root or ignored (vendor, symlinks into vendor, git-ignored dirs). Use one app CSS entry and one JS entry; do not create per-module Vite entries unless modules ship independently loaded bundles. Livewire 4 component-level `<style>`/`<script>`/`.css`/`.js` files bypass Vite entirely.

### Cited Findings
- `@source "../node_modules/@acmecorp/ui-lib";` registers extra paths relative to the stylesheet; auto-detection scans all files except those in `.gitignore`, `node_modules`, binary files, CSS files, lock files; `@import "tailwindcss" source("../src");` sets the base; `source(none)` disables auto-detection (then `@source` every dir); `@source not "../src/legacy"` excludes; `@source inline("...")` safelists with brace expansion — [Tailwind: detecting classes](https://tailwindcss.com/docs/detecting-classes-in-source-files). The page does not document glob syntax for `@source` paths (it lists directories); Flux's own css uses directory paths.
- Laravel plugin: `laravel(['resources/css/app.css','resources/js/app.js'])`; multiple entry points supported; `refresh: true` watches only `app/Livewire/**`, `app/View/Components/**`, `lang/**`, `resources/lang/**`, `resources/views/**`, `routes/**`; custom list e.g. `refresh: ['resources/views/**']`, or `{ paths: [...], config: { delay: 300 } }` — [Laravel 13 Vite docs](https://laravel.com/docs/13.x/vite)
- nwidart/laravel-modules documented approach (older, per-module asset entry pattern): root `vite.config.js` does `import collectModuleAssetsPaths from './vite-module-loader.js'; const allPaths = await collectModuleAssetsPaths(paths, 'Modules');` then `laravel({ input: allPaths })`; each module `vite.config.js` exports `paths = ['Modules/Blog/resources/assets/sass/app.scss', ...]`; Blade uses `@vite(\Nwidart\Modules\Module::getAssets())`; docs say not to mix module-level and root approach — [laravelmodules.com compiling assets](https://laravelmodules.com/docs/13/basic-usage/compiling-assets)
- nwidart issue #2120 (open) "Hot Refresh for Blade templates in Modules using root vite.config.js" confirms the default `refresh: true` doesn't cover module view dirs — [issue](https://github.com/nWidart/laravel-modules/issues/2120)
- Livewire 4 component CSS: SFC root-level `<style>` is scoped using CSS nesting under the component's `wire:name` root attribute (`&` = root); `<style global>` or `.global.css` for unscoped; MFC `name.css`; deduplicated per page; relies on CSS nesting (Chrome 120+, Firefox 117+, Safari 17.2+); `@assets` for external stylesheets — [Livewire styles](https://livewire.laravel.com/docs/4.x/styles)
- Livewire 4 component JS: bare `<script>` in SFC/MFC, runs before the component initialises, `$wire` available; `@script` needed for class-based components; `@assets` loads external scripts/styles once; `livewire:init` for global hooks — [Livewire javascript](https://livewire.laravel.com/docs/4.x/javascript)
- Mechanism (source read): MFC/SFC JS is served by a Livewire route (`EndpointResolver::componentJsPath()`) as `scriptModuleSrc()` file content with filemtime caching, announced via a `scriptModule` effect and a `<link rel=modulepreload>` in head; component names with `::` and `.` are encoded in the URL as `---` and `--`. The file is served as-is, not through Vite — [SupportJsModules.php](https://github.com/livewire/livewire/blob/main/src/Features/SupportJsModules/SupportJsModules.php). Fixes: 404 with slashed names (4.3.1), module-preload/suspend behaviour (4.4.3) — [4.3.1](https://github.com/livewire/livewire/releases/tag/v4.3.1), [4.4.3](https://github.com/livewire/livewire/releases/tag/v4.4.3)

### Inferences
- Default recommended setup: `resources/css/app.css` containing `@import 'tailwindcss'; @import '../../vendor/livewire/flux/dist/flux.css';` and, only if module code lives somewhere ignored or outside root, `@source '../../app-modules';` / `@source '../../Modules';`. internachi/modular path-repo symlinks live under `vendor/` (git-ignored), but Tailwind scans the real `app-modules/` directory, which is not ignored — so no `@source` should be needed (not verified in a running app; confirm the `.gitignore` doesn't list the modules dir).
- Because component `<style>` blocks are emitted as plain CSS (with nesting transform) and not processed by Vite/Tailwind, `@apply` and theme tokens inside them are not available (the docs do not address `@apply`; this follows from the served-as-is mechanism). Use utility classes in markup for Tailwind-driven styling and reserve component `<style>` for small, truly component-local CSS.
- Likewise `import` of npm packages inside component `<script>`/`.js` cannot rely on Vite resolution (served as raw file). Put npm-dependent JS in the single app JS entry (or a module entry) and register via Alpine/`Livewire.directive`/`window` hooks; use `@assets` for CDN scripts.
- Per-module Vite entries are only warranted when a module has heavy, page-specific JS/CSS (charts, editors) that shouldn't ship to every page. Then add the entry explicitly: `laravel({ input: ['resources/css/app.css','resources/js/app.js','app-modules/reports/resources/js/reports.js'], refresh: [...] })` and `@vite('app-modules/reports/resources/js/reports.js')` from that module's page/layout slot (`@push('scripts')`). A glob-based loader (as in nwidart's `vite-module-loader.js` pattern) automates this.
- Set `refresh` to include module view/class dirs, e.g. `refresh: ['resources/views/**','routes/**','app-modules/*/resources/views/**','app-modules/*/src/Livewire/**','app-modules/*/routes/**']`.

### Gaps
- Tailwind docs fetched don't state glob support in `@source`; Tailwind v4 does accept globs in practice but I did not find the doc statement, so treat as unverified.
- Did not test Vite HMR for SFC files within symlinked module paths (Vite `server.fs.allow`/symlink handling) — no source found.
- The `vite-module-loader.js` source was not located; only the doc excerpt.

## 5. How modular packages integrate with Livewire 4

### Takeaway
Neither core package auto-registers Livewire components. internachi/modular needs the separate `internachi/modular-livewire` plugin (supports Livewire ^3 and ^4 per composer.json); nwidart/laravel-modules needs the third-party `mhmiton/laravel-modules-livewire` (Livewire ^4 only, nwidart >= 13). Both use the v4 `addNamespace` API. Watch the SFC-only-module gap in modular-livewire.

### Cited Findings
- internachi/modular v3 dropped `make:livewire --module`; replacement is `composer require internachi/modular-livewire`; v3 requires PHP 8.3+ and Laravel 11+ — [CHANGES-V3.md](https://github.com/InterNACHI/modular/blob/main/CHANGES-V3.md); no mention of Livewire anywhere in core code (grep, source read)
- `internachi/modular-livewire` (last commit 2026-09-07; composer: `livewire/livewire ^3.0|^4.0`, `internachi/modular ^3.0`, php >=8.3; README still says "^3.0" and describes only `src/Livewire` class discovery) — [repo](https://github.com/InterNACHI/modular-livewire)
- Its plugin (source read): discovers `*.php` in `<modules>/*/src/Livewire`; on Livewire 4 (detected via `LivewireManager::$v4 === true`) does, per module that HAS discovered class files, `Livewire::addNamespace(namespace: $module, classNamespace: $moduleConfig->qualify('Livewire'), viewPath: $moduleConfig->path('resources/views/livewire'))`; on Livewire 3 does `Livewire::component("{module}::{kebab.name}", fqcn)` — [ModularLivewirePlugin.php](https://github.com/InterNACHI/modular-livewire/blob/main/src/ModularLivewirePlugin.php)
- Consequences of that code (my reading): (1) namespace = module directory name (e.g. `billing::invoice-table`); (2) SFC/MFC in `resources/views/livewire` are resolvable but only for modules that also contain at least one class under `src/Livewire` (registration iterates discovered classes); an SFC-only module won't get its namespace registered; (3) `classPath`/`classViewPath` are not passed, so `make:livewire module::name --class` is unsupported/unsafe (see section 1); (4) it adds no `--module` option and no Blade view namespace (the modular `ViewPlugin` supplies `module::` views); (5) discovery data participates in `php artisan modules:cache`/`modules:sync`, so re-run after adding components in production.
- Workaround for (2) if needed: register your own namespace in the module's provider (section 1 snippet), which is idempotent with respect to the Finder's keyed array (last registration wins per key).
- modular-livewire issue tracker has one closed issue ("When this package will be usable?", 2026-05) and modular #139 ("Livewire package for v3 upgrade does not exist") was closed 2026-05-19 with "`internachi/modular-livewire` v1 is now released and working" — [modular #139](https://github.com/InterNACHI/modular/issues/139). Older open modular issues (#72, #85, #84) concern pre-v3 `--module` behaviour and wrong view paths (`view('var.www.html...')`) — [#85](https://github.com/InterNACHI/modular/issues/85), [#72](https://github.com/InterNACHI/modular/issues/72); not relevant to the new plugin.
- nwidart/laravel-modules core: no Livewire code at all (grep); Livewire is handled by `mhmiton/laravel-modules-livewire` (last commit 2026-03-20; requires `livewire/livewire ^4.0`, `nwidart/laravel-modules >=13.0`, Laravel ^10-^13) — [composer.json](https://github.com/mhmiton/laravel-modules-livewire/blob/master/composer.json)
- mhmiton package (source read): per module registers `Livewire::addLocation(viewPath: <module>/resources/views/livewire)`, `Livewire::addNamespace(namespace: <lower module name>, viewPath: ...)`, `Livewire::addLocation(classNamespace: Modules\X\Livewire)`, and `Livewire::addNamespace(namespace: lower, classNamespace:, classPath:, classViewPath:)` — i.e. it passes all class args, and ALSO adds global (unnamespaced) locations, so unprefixed names resolve across modules (collision risk). Commands: `module:make-livewire {component} {module} {--sfc|--mfc|--class...} {--emoji=}`, e.g. `module:make-livewire sfc.post.create Core --sfc` yields `modules/Core/resources/views/livewire/sfc/post/⚡create.blade.php`, tag `<livewire:core::sfc.post.create />`; supports Volt via a view-factory swap — [README](https://github.com/mhmiton/laravel-modules-livewire), [LivewireComponentServiceProvider.php](https://github.com/mhmiton/laravel-modules-livewire/blob/master/src/Providers/LivewireComponentServiceProvider.php)
- Reported nwidart-era problem: namespace `Modules\Blog\app\Livewire` vs Livewire default (issue #1819 "Unable to find component: [blog::index]"); maintainer advice was to drop the `app/` segment from the PSR-4 namespace — [issue 1819](https://github.com/nWidart/laravel-modules/issues/1819). Applies to v3-era; with explicit `classNamespace` you can match whatever namespace you use.
- Livewire v4.4.x release notes mention no module-specific changes; the only namespace-related change found is the whole-segment match fix (4.4.1).

### Inferences
- Known-issues summary for v4 SFCs: no reported SFC-specific bugs in either package were found; the only structural gap is the SFC-only-module registration in modular-livewire (code reading, unconfirmed at runtime) and absence of `make:livewire --module` (v3 behaviour is gone; use the namespace form `make:livewire billing::name`).
- For a package-agnostic design, writing the 6-line `Livewire::addNamespace` in each module's own provider avoids both dependencies and works with either module system.

### Gaps
- Could not run either package against Livewire 4.4.8 to confirm behaviour; findings are from source reading.
- No GitHub issues found describing Livewire 4 SFC breakage in either package; absence of reports is not proof.
- internachi/modular-livewire README is stale (says Livewire ^3, no mention of v4 or SFC).

## 6. Cross-module UI composition and events

### Takeaway
Embed other modules' components by name through a registry of strings (component names + metadata) owned by a shared/core module, render with `<livewire:is :component="$name" />` (dynamic component) and communicate with Livewire events (`dispatch`, `#[On]`) using string event names as the cross-module contract. No Livewire-specific module-composition feature exists; this is architecture on top of the primitives.

### Cited Findings
- Dynamic components: tag compiler accepts `<livewire:dynamic-component :component="$name" />` and `<livewire:is :is="$name" />` (source read) — [LivewireTagPrecompiler.php](https://github.com/livewire/livewire/blob/main/src/Mechanisms/CompileLivewireTags/LivewireTagPrecompiler.php)
- `Livewire::exists($nameOrClass)` returns false instead of throwing for unknown components (source read) — LivewireManager.php / Factory.php
- Events: `$this->dispatch('post-created', title: $post->title)`; target one component `->to(component: Dashboard::class)` or `->to(self: true)`; listen with `#[On('post-created')]` (dynamic names like `#[On('post-updated.{post.id}')]`); child events in Blade `@saved="close($event.detail.postId)"` on `<livewire:...>`; JS `Livewire.on(...)` inside `livewire:init`; Alpine `x-on:post-created.window`; `wire:click="$dispatch(...)"` / `$dispatchTo('posts', ...)` — [Events docs](https://livewire.laravel.com/docs/4.x/events)
- Slots into Livewire components exist in v4 (`SupportSlots`, `$slots` forwarded by `@livewire(..., $__slots)`), and layouts forward named slots from the page component (source read: LivewireTagPrecompiler.php line ~130; SupportPageComponents).
- Lazy loading with placeholders: `component_placeholder` config key (v4 rename), route macro `->lazy()`; useful for dashboards of widgets — config/livewire.php; HandleRouting.php (source read)
- Islands feature present in v4 (`SupportIslands` in source tree) — directory listing of [src/Features](https://github.com/livewire/livewire/tree/main/src/Features); not researched further.

### Inferences
- Widget registry pattern (decoupled): a `core` (or `dashboard`) module defines `WidgetRegistry` with `register(string $component, string $module, int $order, ?string $permission, array $params = [])`; each module's provider registers its widget during `boot()` (`$this->app->afterResolving(WidgetRegistry::class, fn ($r) => $r->register('billing::revenue-widget', ...))` or in `boot` calling the singleton). The dashboard page iterates the registry: `@foreach ($widgets as $w) <livewire:is :component="$w->component" :key="$w->component" lazy />`. The dashboard knows only strings, never classes. Filter by `Livewire::exists()` (guards a disabled module) and by authorisation before render. Same shape works for nav items and settings tabs (view composers or a `Navigation` registry feeding the layout).
- Contract between modules = (1) component name string, (2) its accepted public props (documented, treated like an API), (3) event names + payload shape. Prefer passing IDs, not models, through props/events (Livewire serialises props into the snapshot; IDs avoid cross-module model coupling).
- Event naming convention: `module.entity.action` (e.g. `billing.invoice.paid`), payload with scalar IDs; listeners use `#[On('billing.invoice.paid')]`. Events are browser-level, so they only reach components currently on the same page; durable cross-module reactions must still go through Laravel events/jobs on the server. Use `->to(component: 'billing::invoice-list')` (string name, not class) to avoid an import dependency.
- For pages composed of other modules' parts, prefer slots/`wire:ignore`-free composition at the Blade level (`<x-ui::card><livewire:billing::summary :customer-id="$id" /></x-ui::card>`) over the host component importing foreign classes; for a host that must render arbitrary module contributions use the registry + `<livewire:is>`.
- Always `wire:key` dynamic-component loops (upgrade guide says `smart_wire_keys` default doesn't remove the need in loops).

### Gaps
- No official Livewire or module-package documentation on cross-module widget registries; the registry pattern above is my design, not a sourced convention.
- Did not verify whether `dispatch()->to(component: 'ns::name')` with a namespaced string name matches by registered name (docs examples use class names/plain names); test before relying on it.
- Islands not researched.

## 7. Testing Livewire components inside modules

### Takeaway
`Livewire::test('billing::invoice-table')` works with namespaced names (same resolver), and `Livewire::test(InvoiceTable::class)` works for class components. For `.test.php` files colocated beside SFC/MFC components you must point Pest/PHPUnit at the directories that hold them; the default docs only cover `resources/views`, so module paths need to be added explicitly.

### Cited Findings
- `Livewire::test('post.create')` and `Livewire::test(UpdatePost::class, ['post' => $post])` (params go to `mount()`); `assertSeeLivewire('post.create')`; `assertDispatched('post-created')`, with named params or closure; `assertNotDispatched`; the docs page does not show a `pages::` namespaced name — [Testing docs](https://livewire.laravel.com/docs/4.x/testing)
- Namespaced names in tests are used in Livewire's own suite (`Livewire::test('testns::lazy-wrapper')`) (source read) — [SupportJsModules/UnitTest.php](https://github.com/livewire/livewire/blob/main/src/Features/SupportJsModules/UnitTest.php); the Finder suite tests `admin::...` class/SFC resolution — [Finder/UnitTest.php](https://github.com/livewire/livewire/blob/main/src/Finder/UnitTest.php)
- `make:livewire ... --test` creates `⚡create.test.php` next to an SFC, `create/create.test.php` for MFC, and `tests/Feature/Livewire` for class components; Pest needs `->in('Feature', '../resources/views')`; PHPUnit needs `<directory suffix=".test.php">resources/views</directory>` — Testing docs
- `MakeCommand` test path for namespaced SFC derives from the SFC file path (`getSingleFileComponentTestPath($path)`) (source read), so tests land inside the module's view dir.

### Inferences
- Pest config for modules: `pest()->extend(Tests\TestCase::class)->in('Feature', '../app-modules/*/tests', '../app-modules/*/resources/views');` (the second glob lets colocated `.test.php` files run). PHPUnit: add `<directory suffix=".test.php">app-modules/billing/resources/views</directory>` per module (or a testsuite with several `<directory>` entries). Module package defaults vary (modular puts tests in `app-modules/x/tests`).
- The module under test must have its provider booted in the test app (autoloaded/discovered), otherwise `billing::` is unregistered and `ComponentNotFoundException` ("Unable to find component: [billing::...]") results — same message as the nwidart #1819 symptom.
- Page-level tests: `$this->get(route('billing.invoices.index'))->assertSeeLivewire('billing::invoices.index')` exercises routing + layout + namespace together; keep a few of these per module plus unit-style `Livewire::test()` for behaviour.

### Gaps
- Docs don't show a worked namespaced-module test; the Pest glob config is my inference and untested.
- Whether `.test.php` colocated files under module paths conflict with Pest's file-name filters was not verified.

## v3-era advice that no longer applies (flags)
- `config('livewire.layout')` = `components.layouts.app` -> now `component_layout` = `layouts::app` (old keys still read as fallback in `bootConfig`, source read). `lazy_placeholder` -> `component_placeholder` — [Upgrade guide](https://livewire.laravel.com/docs/4.x/upgrading)
- `Route::get('/x', Component::class)` is discouraged; use `Route::livewire`, mandatory for SFC/MFC pages — Upgrade guide
- `Livewire::component('billing::name', Class::class)` (v3 per-class registration, what modular-livewire still does on v3) is superseded by `addNamespace(classNamespace: ...)` for namespaces; `Livewire::component()` still exists as an alias for single registrations (source read).
- `make:livewire x --module=y` (modular v2 / hacks in modular issues #72 and #85) is gone; use `make:livewire module::x` (needs registered namespace) or `module:make-livewire` (mhmiton).
- Volt is replaced by SFC; remove `VoltServiceProvider` and `livewire/volt` — Upgrade guide. mhmiton's package still ships Volt support for module components.
- Livewire asset/endpoint URLs moved to `/livewire-{hash}/...` — adjust firewall/CDN/CSP rules — Upgrade guide.
- `wire:model` no longer reacts to child element events (use `.deep`); `wire:model.blur/.change` need `.live` for the v3 behaviour; `wire:transition` modifiers removed — Upgrade guide.
- Tailwind v3 `content: [...]` array in `tailwind.config.js` is replaced by v4 automatic detection + `@source` in CSS — [Tailwind docs](https://tailwindcss.com/docs/detecting-classes-in-source-files).
