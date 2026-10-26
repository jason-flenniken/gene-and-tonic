# Module-boundary enforcement and testing for a Laravel modular monolith (as of 2026-10-24)

Target stack: Pest 5.3, PHPUnit 13, Larastan 3.x, PHP 8.4, Laravel 13. Code samples marked "(constructed)" are my own assembly from the documented APIs; they were not run and not copied from a source.

## 1. Pest architecture testing (Pest 4/5): per-module rules, limitations, what changed in Pest 5

### Takeaway
Pest's `arch()` API is a namespace-level "who may use whom" checker, and the vocabulary you need for module rules (`not->toUse`, `toOnlyUse`, `toBeUsedIn`, `toOnlyBeUsedIn`, `ignoring`, wildcards) exists in the docs. The Pest 5 official announcement lists no architecture-testing changes. Pest 5's changes are the Tia engine, parallel/sharding, PHPStan/Rector plugins, agent/evals plugins and new expectations. The one arch-related item in the Pest 4.x releases is `toBeCasedCorrectly` (4.5.0), and 5.x release notes say Tia maps "Arch" files to dependent tests.

### Cited Findings
- `arch()` accepts a namespace (relative or fully qualified) or function name; example `arch()->expect('App')->toUseStrictTypes()->not->toUse(['die','dd','dump'])`. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Relevant expectations listed: `toUse`, `toUseNothing`, `toOnlyUse`, `toBeUsed`, `toBeUsedIn`, `toOnlyBeUsedIn`, `toExtend`, `toImplement`, `toOnlyImplement`, `toBeInterfaces`, `toBeFinal`, `toBeReadonly`, `toHaveSuffix`, `toHavePrefix`, `toBeCasedCorrectly`, etc. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Docs examples: `arch('models')->expect('App\Models')->toOnlyUse('Illuminate\Database');` and `->expect('App\Models')->toOnlyBeUsedIn('App\Repositories');`. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- `toUse()` is described as indicating files in a namespace should not use specific functions or classes (with `not`); `toBeUsedIn()` restricts specific classes/functions from being used within a given namespace. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Wildcards in expect() targets (e.g. `'App\*\Traits'`) exist since Pest 3.8. Useful for `Modules\*\Internal`-style rules. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Modifiers: `ignoring()` (namespaces/classes/functions), `classes()/enums()/interfaces()/traits()/abstracts()`, `extending()/implementing()/using()`. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Docs recommend ignoring the framework globally so Laravel code isn't treated as a dependency: `pest()->beforeEach(fn () => $this->arch()->ignore(['Illuminate'])->ignoreGlobalFunctions());` in `tests/Pest.php`. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Presets: `php`, `security`, `laravel`, `strict`, `relaxed`, and `custom` via `pest()->presets()->custom('name', fn)`, where the closure receives the app's PSR-4 namespaces. The custom preset is the natural hook for generating one rule set per module. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- The `php` preset and `toHaveSuspiciousCharacters()` need the `intl` extension. — [Pest arch docs](https://pestphp.com/docs/arch-testing)
- Pest 5.0.0 released 2026-07-28 (release notes) / reached Packagist 29 July per a Spanish-language guide; requires PHP 8.4+ and PHPUnit 13. Upgrade is `"pestphp/pest": "^5.0"`; plugins should also move to ^5. — [Pest GitHub releases](https://api.github.com/repos/pestphp/pest/releases?per_page=30); [Pest 5 announcement](https://pestphp.com/docs/pest5-now-available); [Spanish guide, via search snippet](https://www.cursosdesarrolloweb.es/blog/pest-5-tia-agentes-evals)
- Pest 5 headline features: Tia engine (`--tia`), agent plugin (`--agent`), evals (`--evals`), first-party PHPStan plugin (Pest-aware rules, types for the functional API), Rector plugin (60 rules), time-balanced sharding (`--update-shards`, `tests/.pest/shards.json`, `--shard=1/4`), new expectations (`toBeEmail`, `toBeUlid`, etc.). The announcement page does not mention architecture-testing changes. — [Pest 5 announcement](https://pestphp.com/docs/pest5-now-available)
- Time-based sharding actually arrived in 4.6.0 (2026-04-14); 4.5.0 (2026-04-10) added `toBeCasedCorrectly` arch assertion; 5.3.0 (2026-10-01) added browser `--trace` and Tia baseline reuse across replacement CI merge commits; 5.3.1 added PHPUnit 13.4.1 support; latest seen 5.3.3 (2026-10-18). — [Pest GitHub releases](https://api.github.com/repos/pestphp/pest/releases?per_page=30)
- Tia treats Arch tests as their own dependency class (Inertia, Blade, Arch, browser assets each map to dependent tests), so arch files re-run when relevant sources change. — [Pest Tia docs](https://pestphp.com/docs/tia)
- Third-party package (alpha) adds a `toRespectBoundaries()` Pest expectation on top of a static graph: `expect('Billing')->toRespectBoundaries()` and `expect(Cordon::modules())->each->toRespectBoundaries()`; needs a booted app (extend `Tests\TestCase`). — [Packagist: chrisabner/cordon-modulith](https://packagist.org/packages/chrisabner/cordon-modulith)
- A search-result snippet claims the 4.x docs list `not->toOnlyBeUsedIn()` / `not->toOnlyUse()` as unsupported with `not`, but the docs page I fetched does not state that. Conflict, unverified — [search snippet source](https://v4.pestphp.com/docs/arch-testing) vs [Pest arch docs](https://pestphp.com/docs/arch-testing)
- A blog source states "Pest v4 was released March 10, 2026"; this is a secondary source and likely wrong/unreliable (release table shows 4.x already at 4.4.6 on 2026-04-09 and I know Pest 4 launched in 2025). Do not repeat the date. — [allur blog via search](https://allur-website-hyqlykx8.on-forge.com/en/blog/pest-v4-launch-modernizing-the-php-testing-ecosystem)

### Inferences
- Per-module rules (constructed; verify against your installed Pest):

```php
// tests/Arch/ModuleBoundariesTest.php  (constructed)
$modules = ['Billing', 'Catalog', 'Shipping'];   // or glob app-modules/*

foreach ($modules as $m) {
    $others = array_values(array_diff($modules, [$m]));

    arch("$m does not reach into other modules' internals")
        ->expect("Modules\\$m")
        ->not->toUse(array_map(fn ($o) => "Modules\\$o\\Internal", $others));
        // or allow-list style:
        // ->toOnlyUse(['Modules\\'.$m, 'Modules\\Shared', 'Illuminate', ...])
}

arch('internals are not used from outside their module')
    ->expect('Modules\Billing\Internal')
    ->toOnlyBeUsedIn('Modules\Billing');
```

  Pattern: two styles. Deny-list (`not->toUse` other modules' Internal/Models namespaces) is easy to adopt and low noise; allow-list (`toOnlyUse([own, Shared, Contracts, vendor])`) is stricter but needs a long vendor list or `ignoring('Illuminate')`.
- A `pest()->presets()->custom('modules', fn (array $namespaces) => ...)` can loop over modules and register the above once, then `arch()->preset()->modules()` in `tests/Arch`.
- Limitations (inferred; the Pest docs do not list them): Pest arch is static analysis of `use`/references. Container-resolved strings (`app('billing.service')`), `class_string` config values, morph maps, event/listener string names, Eloquent relation strings and raw SQL joins across module tables are invisible to it. Cordon's README states the same limits for its own static approach (docblock-only types, string class names, dynamic references not detected). Facades and global helpers can hide cross-module calls too.
- No dependency-graph features (cycles, declared `depends_on` per module, public-API-by-attribute); you hand-write each pair. Cordon / Deptrac / the PHPStan boundary extension cover those.
- Pest 5 upgrade has no stated arch impact; main practical effect is Tia may re-run arch tests only when affected, and CI should still run the full suite (Tia docs say not to use `--tia` in the main CI pipeline).

### Gaps
- No official Pest 5 changelog entry on arch testing was found; the absence on the announcement page is not proof of no changes (the GitHub release notes I saw were terse).
- Could not verify `toOnlyBeUsedIn` semantics beyond the doc's one-line example (does it also flag use from within the module's own namespace subtree? sibling namespaces?). Test it in a scratch repo.
- Could not find a published blog/talk showing a complete per-module Pest arch suite for a Laravel modular monolith (search returned none).
- Could not confirm whether `not` is unsupported with `toOnlyBeUsedIn`/`toOnlyUse` (conflicting snippets above).

## 2. Deptrac status in 2026, per-module layer config, Laravel usage

### Takeaway
Deptrac is maintained and actively releasing (4.7.2 on 2026-09-15). The repo moved from qossmic/deptrac to deptrac/deptrac. It can model one layer per module and define allowed layer-to-layer edges, with baselines for legacy violations. I found no 2026 evidence on how widely Laravel teams use it (no usage survey or Laravel-specific write-up surfaced).

### Cited Findings
- Repo is `deptrac/deptrac`, default branch `4.x`, about 3.0k stars, requires PHP 8.2+; config in YAML or PHP, default file `deptrac.php`, generated by `vendor/bin/deptrac init`. — [GitHub](https://github.com/deptrac/deptrac)
- Recent releases: 4.7.2 (2026-09-15, extracted `TokenResolverInterface`), 4.7.1 (2026-07-23), 4.7.0 (2026-07-21: `deptrac.php` auto-detected over `deptrac.yaml`; layer collectors may now reference other layers; psalm dropped as standalone dependency), 4.6.2 (2026-07-01), 4.6.1 (2026-05-13). — [GitHub releases API](https://api.github.com/repos/deptrac/deptrac/releases?per_page=5)
- Config keys: `deptrac.paths`, `layers` (name + collectors), `ruleset` (layer -> allowed layers; `+Layer` also allows everything that layer allows), `skip_violations`, baseline via separate imported file (`imports: - deptrac.baseline.yaml`) generated by the `baseline` formatter, `analyser.types` (`class`, `use`, `file`, `function`, `function_call`, superglobals), `ignore_uncovered_internal_classes`, formatters (graphviz, codeclimate, etc.). — [Deptrac configuration docs](https://raw.githubusercontent.com/deptrac/deptrac/4.x/docs/configuration.md)
- Collectors: `classLike`, `class`, `interface`, `trait`, `classNameRegex`, `directory`, `glob`, `bool` (must/must_not), `layer`, `attribute`, `extends`/`implements`/`inherits`/`uses`, `tagValueRegex`, `composer`, `functionName`, `method`, `superglobal`, `php_internal`; custom via `CollectorInterface`. Any collector can set `private: true` so its tokens are referenceable only from the same layer (a built-in "internal to this layer" feature). — [Deptrac collectors docs](https://raw.githubusercontent.com/deptrac/deptrac/4.x/docs/collectors.md)
- Cordon's README itself positions as inspired by Spring Modulith and Packwerk and says the usual modular-monolith packages do not stop cross-module model access (vendor claim). — [Packagist](https://packagist.org/packages/chrisabner/cordon-modulith)

### Inferences
- Per-module config (constructed, `deptrac.yaml` flavour; PHP config equivalent is also supported):

```yaml
# deptrac.yaml  (constructed from documented keys)
deptrac:
  paths: [app-modules]
  layers:
    - name: Shared
      collectors: [{ type: directory, value: app-modules/shared/src/.* }]
    - name: Billing.Public
      collectors:
        - type: bool
          must:     [{ type: directory, value: app-modules/billing/src/.* }]
          must_not: [{ type: classNameRegex, value: '#Modules\\Billing\\Internal\\.*#' }]
    - name: Billing.Internal
      collectors: [{ type: classNameRegex, value: '#Modules\\Billing\\Internal\\.*#' }]
    - name: Catalog.Public
      collectors: [{ type: directory, value: app-modules/catalog/src/Contracts/.* }]
    # ... repeat per module
  ruleset:
    Billing.Internal: [Billing.Public, Shared, Catalog.Public]
    Billing.Public:   [Billing.Internal, Shared, Catalog.Public]
    Catalog.Public:   [Shared]
  skip_violations: {}
```

  Key idea: make each module's Public/Internal split a layer; allow other modules to depend only on `*.Public`. Alternatively use the `private: true` collector option to get the same effect with fewer layers. Layers can be generated by a PHP config file loop (the PHP config format makes per-module generation easier than YAML).
- Deptrac also catches the "everyone depends on Eloquent models of another module" case only if models sit in an Internal layer; a shared model namespace defeats it.
- Cost/benefit versus Pest arch: Deptrac gives a graph, cycle visibility, baselines and graphviz output but is a separate tool/config to maintain; Pest arch keeps everything in the test suite.

### Gaps
- No source found on how many Laravel teams use Deptrac in 2026, or Laravel-specific recipes. Do not claim popularity.
- Release notes do not mention Laravel- or PHP 8.4/8.5-specific changes; only "PHP 8.2 minimum" was documented.
- Did not verify `private: true` behaviour end to end.

## 3. PHPArkitect, PHPStan/Larastan custom rules, and Laravel-specific boundary checkers

### Takeaway
There is now a small set of Laravel-aware, young (2026) boundary tools: Cordon Modulith (Pest expectation + artisan + PHPStan rule, alpha) and happenv-com/laravel-true-modular-phpstan (PHPStan extension tied to the laravel-true-modular package). PHPArkitect is a mature, generic, PHP-code-as-config alternative to Deptrac. Writing your own PHPStan rule is straightforward for single-file checks, but cross-file checks need collectors.

### Cited Findings
- PHPArkitect: MIT, rules as PHP (`Rule::allClasses()->that(new ResideInOneOfTheseNamespaces('App\Domain'))->should(new NotHaveDependencyOutsideNamespace('App\Domain'))->because(...)`), config in `phparkitect.php` using `ClassSet::fromDir()`; commands `init`, `check`, `debug:expression`, `generate-baseline`, `prune-baseline`; output text/json/gitlab; phar distribution; parser targets PHP 8.0 to 8.6. ~937 stars. Latest version number not shown on the page. — [GitHub](https://github.com/phparkitect/arkitect)
- Cordon Modulith v0.1.1 (2026-09-29, alpha): rules `internal_access`, `undeclared_dependency`, `cycles`; resolvers `auto|namespace|nwidart|internachi`; public API by `#[PublicApi]`, `public_namespaces` (Contracts, Events, Data, Enums, Exceptions) or per-module `public`; `#[Internal]` overrides; `depends_on` per module; `'Shared' => ['open' => true]`; baseline file; `cordon:verify --module=Billing --format=github|json`; bundled PHPStan rule; requires PHP ^8.3, Laravel 12/13, php-parser ^5. Limits: no docblock-only types, string class names, dynamic refs; does not analyse code outside modules (e.g. `app/Http`). Excludes `tests` by default. — [Packagist](https://packagist.org/packages/chrisabner/cordon-modulith)
- happenv-com/laravel-true-modular-phpstan v1.1.0 (2026-09-17): zero-config "Module Boundary Enforcer" flags references to other modules' classes unless declared in that module's `composer.json` `require`, plus circular dependency detection; identifiers `trueModular.moduleBoundary`, `trueModular.circularDependency`; checks `use`, `new`, static calls, `instanceof`, `extends/implements`, `catch`, class constants; requires PHPStan ^2, PHP ^8.3, `happenv-com/laravel-true-modular` ^1.6, Laravel 12/13. Packagist shows 3,429 installs (a separate search snippet said ~244; snapshot difference, treat as small). — [Packagist](https://packagist.org/packages/happenv-com/laravel-true-modular-phpstan)
- InterNACHI/modular's README documents no boundary enforcement; modules are conventions only. — [GitHub](https://github.com/InterNACHI/modular)
- rafathomas/laravel-architecture-guard: generic linter with `forbidden_dependencies` and custom `ArchitectureRule`; notes dynamic class names/container strings can't be detected statically. Only seen via search snippet. — [Packagist](https://packagist.org/packages/rafathomas/laravel-architecture-guard)
- Larastan 3.x: PHP 8.2+, Laravel 11.15+, `composer require --dev "larastan/larastan:^3.0"`, include `vendor/larastan/larastan/extension.neon`; docs page doesn't explain writing custom rules (use PHPStan's rule docs). — [Larastan README](https://github.com/larastan/larastan)
- PHPStan custom rule recipe: implement `PHPStan\Rules\Rule` (`getNodeType`, `processNode`), `@implements Rule<...>`, build errors with `RuleErrorBuilder::message()->identifier()`, register under `rules:` in neon, test with `RuleTestCase`. Rules run in isolated processes, so cross-file checks need collectors (or `Scope&CollectedDataEmitter` from PHPStan 2.2.0+). — [PHPStan docs](https://phpstan.org/developing-extensions/rules)
- Pest 5 ships a first-party PHPStan plugin (Pest-aware rules, functional API types). — [Pest 5 announcement](https://pestphp.com/docs/pest5-now-available)

### Inferences
- A minimal homegrown rule (constructed): listen on `Node\Name` / `Node\Stmt\Use_`, derive module from `$scope->getNamespace()` (`Modules\Billing\...`), and error when the referenced class's second namespace segment differs and isn't `Contracts|Events|Data|Shared`. Per-file, so no collectors needed; add an identifier like `modules.boundary` so it can go in `ignoreErrors`/baseline.
- PHPStan integration is the best IDE/CI feedback loop (errors in editor), Pest arch is best for "lives with the tests", Deptrac/PHPArkitect for graph/baseline. Running two overlapping tools is probably redundant; pick one primary (my judgment).
- The Cordon/true-modular tools are weeks-to-months old; adoption risk is real (alpha, one-maintainer-looking packages).

### Gaps
- Latest PHPArkitect release number and PHP 8.4 support statement not found on the page.
- No Larastan-specific module-boundary rules found; "larastan architecture rules" (prinsfrank) appeared in search but I did not open it.
- Did not verify Cordon or true-modular-phpstan in a real project; claims come from package READMEs.
- Package choice and design patterns are out of scope per instructions.

## 4. Test organization: per-module vs central, phpunit.xml suites, single-module runs, factories, RefreshDatabase on MySQL

### Takeaway
Both layouts work: InterNACHI-style modules keep `tests/` inside each module and add a `Modules` suite via `php artisan modules:sync`; nwidart-style docs use a wildcard suite. Pest maps base class/traits onto module directories with `pest()->extend(...)->use(...)->in('../app-modules/*/tests')`. Laravel's parallel runner creates one database per process (`name_test_1`...), which matters for MySQL.

### Cited Findings
- InterNACHI/modular: each module scaffold has `tests/`; `php artisan modules:sync` adds a `Modules` testsuite to `phpunit.xml` if it exists and is idempotent (suggested in `post-autoload-dump`); `make:test --module=x`; `make:factory --module=x`; module factories auto-loaded; stubs allow customizing the test base class. — [GitHub](https://github.com/InterNACHI/modular)
- nwidart docs: default `phpunit.xml` only runs `tests/Feature` and `tests/Unit`; suggest a wildcard testsuite over `Modules/*/Tests/{Feature,Unit}` and SQLite `:memory:`; docs are for v8/v9/v11 so confirm for your version, and casing differs between pages. — [laravelmodules.com tests](https://laravelmodules.com/docs/v11/tests)
- Pest: `pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Feature')`; wildcard dirs work, e.g. `->in('../Modules/*/Tests/Browser')`. The docs show no per-module RefreshDatabase example. — [Pest configuring tests](https://pestphp.com/docs/configuring-tests)
- `oki-technologies/laravel-module-test` v1.0.1 (2026-10-24): `php artisan module:test -p Workspace` runs one module; `--coverage`, `--min`, `--mins=Auth,Activity:100`, `--shard=index/total`, `--root` for root suite; nwidart-only, Pest 4, PHP 8.3+, Laravel 12/13. Pest 5 compatibility not stated. — [Packagist](https://packagist.org/packages/oki-technologies/laravel-module-test)
- Laravel 13 testing: `php artisan test --parallel` (needs brianium/paratest) creates and migrates a DB per process suffixed with a token (`your_db_test_1`), persists between runs unless `--recreate-databases`; `ParallelTesting::setUpTestDatabase` hook for seeding; `WithCachedConfig` trait caches config across tests; `#[UnitTest]` attribute skips app boot for a single method. — [Laravel 13 testing docs](https://laravel.com/docs/13.x/testing)
- Pest filtering/running: Pest accepts the usual path argument and `--filter`, `--testsuite`; Pest 5 adds `--shard=1/4` with time-balanced shards from `tests/.pest/shards.json` (`--update-shards`). — [Pest 5 announcement](https://pestphp.com/docs/pest5-now-available)

### Inferences
- Recommended layout (constructed): keep unit/feature tests in `app-modules/<m>/tests/{Unit,Feature}`; a central `tests/Arch` for boundary tests and `tests/Integration` for cross-module flows. Reason: module-local tests move with the module and a central folder owns the cross-cutting tests that, by definition, belong to no module.

```xml
<!-- phpunit.xml (constructed) -->
<testsuites>
  <testsuite name="Billing"><directory>app-modules/billing/tests</directory></testsuite>
  <testsuite name="Catalog"><directory>app-modules/catalog/tests</directory></testsuite>
  <testsuite name="Arch"><directory>tests/Arch</directory></testsuite>
  <testsuite name="Integration"><directory>tests/Integration</directory></testsuite>
</testsuites>
```
  Run one module: `vendor/bin/pest --testsuite=Billing` or `php artisan test --parallel --testsuite=Billing`, or by path `vendor/bin/pest app-modules/billing/tests`. A per-module suite entry duplicates names; `modules:sync` or a wildcard (`app-modules/*/tests`) avoids hand-maintenance but loses per-module `--testsuite` selection, so path arguments are the fallback.

```php
// tests/Pest.php (constructed)
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\LazilyRefreshDatabase::class)
    ->in('Feature', 'Integration', '../app-modules/*/tests/Feature');
pest()->extend(Tests\TestCase::class)->in('../app-modules/*/tests/Unit'); // only if they need the app
```
- Factories: keep each module's factories in the module (`Database/Factories`) with explicit `newFactory()`/`#[UseFactory]` on the model; tests in module A should create module B's data via B's public API or factory only through a test-support namespace, not B's internals (my design suggestion; the arch tests should exclude `tests` from the boundary rule or have a dedicated allow-list).
- RefreshDatabase with MySQL: each module owning migrations means a single `migrate:fresh` loads all modules' migrations, so per-module test runs still pay the whole schema cost unless the suite's DB is persistent. `RefreshDatabase` wraps tests in transactions after one migrate per process (documented in Laravel testing docs elsewhere; not re-verified here); `DatabaseTruncation`/`DatabaseMigrations` are needed for tests that commit (browser tests, afterCommit events, tests spanning connections). MySQL parallel runs get `_test_N` databases, so the DB user needs CREATE privilege. SQLite `:memory:` (suggested by nwidart docs) is faster but hides MySQL-specific behavior (JSON, collation, FK, lock semantics), so run the MySQL service in CI at least for integration tests.
- Per-module coverage floors via `--coverage` need PCOV/Xdebug; `laravel-module-test` implements that for nwidart only.

### Gaps
- No source found comparing central vs in-module tests with data (only conventions).
- Not verified: RefreshDatabase/LazilyRefreshDatabase/DatabaseTruncation behaviour details in Laravel 13 (I only fetched the getting-started testing page, which does not cover the database page).
- Not verified whether `laravel-module-test` works with Pest 5 or with InterNACHI layout.
- Pest `--testsuite` selection with Pest 5 + parallel: assumed from PHPUnit semantics, not tested.

## 5. Testing inter-module contracts: faking contracts, Event::fake, contract tests

### Takeaway
Laravel's container-binding and Mockery/`Event::fake` helpers are enough to isolate a module from its collaborators; the stronger pattern is a reusable contract test (same assertions run against the real and fake implementations of an interface). I found no module-specific published guidance, so most of this section is inference.

### Cited Findings
- To mock an injected collaborator, bind it as an `instance`: `$this->instance(Service::class, Mockery::mock(...))`, or use `$this->mock()`, `partialMock()`, `spy()`; facade `Cache::expects()`/`spy()`. — [Laravel 13 mocking docs](https://laravel.com/docs/13.x/mocking)
- Laravel docs state that, when testing code that dispatches an event, you may mock listeners so the controller test isn't affected, with listeners tested in their own test case. — [Laravel 13 mocking docs](https://laravel.com/docs/13.x/mocking)
- Cordon's default public namespaces are `Contracts`, `Events`, `Data`, `Enums`, `Exceptions`, i.e. other modules may depend on these; so test doubles of those types are the legitimate seam. — [Packagist](https://packagist.org/packages/chrisabner/cordon-modulith)

### Inferences
- Faking another module's contract (constructed):

```php
// app-modules/billing/tests/Feature/InvoiceTest.php
use Modules\Catalog\Contracts\PriceLookup;

it('invoices at catalog price', function () {
    $this->app->instance(PriceLookup::class, new class implements PriceLookup {
        public function priceFor(string $sku): Money { return Money::of(1000, 'USD'); }
    });
    // ...
});
```
  Prefer a hand-written in-memory fake shipped by the owning module (`Modules\Catalog\Testing\InMemoryPriceLookup`) over Mockery, so the fake can't drift silently; put that fake under a `Testing` namespace and allow it in the arch rules.
- Contract test pattern (constructed): an abstract Pest/PHPUnit test in the owning module, e.g. `PriceLookupContractTest` with `abstract protected function make(): PriceLookup`, run against the real implementation and against the shipped fake. This guards the consumer's fakes from drifting from reality.
- Events: `Event::fake([OrderPlaced::class])` in the producer module and assert dispatched; in the consumer module test the listener directly (`(new ReserveStock)->handle(new OrderPlaced(...))`) and `Event::assertListening(OrderPlaced::class, ReserveStock::class)` to prove wiring. Beware `Event::fake()` without arguments also fakes Eloquent model events, which can break factories/observers in the module under test; use the argument form or `Event::fakeExcept` (method names from Laravel docs knowledge; I did not fetch the events-testing page to confirm them in v13).
- Event payload classes are shared contracts: a serialization/shape test (constructor signature, `toArray`) belongs with the producer.

### Gaps
- Did not fetch Laravel 13's events/queue testing pages, so `Event::fake`, `fakeExcept`, `assertListening` behaviour is from prior knowledge, not cited here.
- No cited public guidance on contract tests specific to Laravel modular monoliths.

## 6. CI patterns: run arch tests early, run only affected modules

### Takeaway
Run static boundary checks (Pest arch suite, PHPStan, Deptrac/Cordon) as a fast first job, fan out per-module test jobs, and keep a full-suite run as the merge gate. Pest 5's Tia is built for local dev, with a baseline-from-main workflow available but explicitly not recommended for the main CI pipeline.

### Cited Findings
- Tia docs: do not add `--tia` to the main CI pipeline; run the full suite (e.g. `pest --ci`). Optional separate workflow on `main` records a baseline (`--tia --coverage --fresh`) and uploads `pest --baseline` output as an artifact (`include-hidden-files: true`); developers opt in with `baselined()`; baseline fetch needs authenticated `gh`. — [Pest Tia docs](https://pestphp.com/docs/tia)
- Tia release notes: GitLab CI baseline support (5.2.0, 2026-09-14); baselines reused across replacement CI merge commits (5.3.0, 2026-10-01). Tia needs PCOV or Xdebug; config/unmapped files trigger a full run; `composer.lock`, `phpunit.xml*` changes rebuild the graph; `watch([...])` maps custom globs to tests (needed for non-standard layouts such as `app-modules/`). — [Pest releases](https://api.github.com/repos/pestphp/pest/releases?per_page=30); [Tia docs](https://pestphp.com/docs/tia)
- Sharding: `--update-shards` writes timings to `tests/.pest/shards.json`; `--shard=1/4` balances by time (4.6.0, refined 5.x; 5.1.2 fixed exit code with `--shard` plus `--parallel`; 5.3.2 shard filters no longer match classes outside their partition). — [Pest announcement](https://pestphp.com/docs/pest5-now-available); [releases](https://api.github.com/repos/pestphp/pest/releases?per_page=30)
- Cordon: `cordon:verify --format=github|json`, exit codes 0/1/2, `--module=X`, baseline. Deptrac has codeclimate/graphviz/baseline formatters; PHPArkitect has text/json/gitlab and baseline. — [Packagist](https://packagist.org/packages/chrisabner/cordon-modulith); [Deptrac config](https://raw.githubusercontent.com/deptrac/deptrac/4.x/docs/configuration.md); [PHPArkitect](https://github.com/phparkitect/arkitect)
- GitHub path filters at workflow level can't target jobs; `dorny/paths-filter` outputs changed-area flags to later jobs. — [oneuptime guide via search](https://oneuptime.com/blog/post/2025-12-20-monorepo-path-filters-github-actions/view)
- Laravel parallel testing creates DBs `name_test_N` per process (see section 4). — [Laravel 13 docs](https://laravel.com/docs/13.x/testing)

### Inferences
- Pipeline shape (constructed): job 1 `static` (pint, `phpstan` incl. boundary rule, `pest --testsuite=Arch`, deptrac/cordon) with no DB; job 2 matrix over modules `pest --testsuite=$MODULE --parallel` gated by `needs: static`; job 3 integration suite; full run on `main`/merge queue.
- Affected-modules: compute changed module directories with `git diff --name-only origin/main...HEAD | cut -d/ -f1-2 | sort -u`, then expand with reverse dependencies (modules that depend on a changed module) using the module graph (Cordon `depends_on`, Deptrac, or composer `require` of each module). A change to `Shared` or a contract then fans out to all dependents; a change to any root file (`composer.lock`, `config/`, `phpunit.xml`, `bootstrap/`) runs everything. This is my design; I found no published reference implementation for Laravel.
- Because the graph is static and misses dynamic coupling, affected-only runs are a speed-up for PR feedback, with a nightly/main full run as backstop.
- Tia is a good local-dev complement (fast replays), not a CI selector; its dependency recording is coverage-based, so it can see dynamic coupling that static boundaries miss (my inference from the docs' description).

### Gaps
- No published example of affected-module test selection for Laravel modular apps; the GitHub Actions snippets are generic.
- Tia details for GitHub Actions on non-standard module paths are untested; `watch()` mapping is documented only generally.
- Did not verify Pest 5.3 + paratest 7.26 parallel behavior with per-module testsuites.
