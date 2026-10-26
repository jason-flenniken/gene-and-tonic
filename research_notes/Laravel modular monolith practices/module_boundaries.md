# Module boundaries in a Laravel modular monolith (as of Oct 2026)

Research caveat: ~14 tool calls. Search surfaced mostly practitioner blogs/package READMEs; I could NOT retrieve Laravel Beyond CRUD, Spatie docs, Laracon talks, Kamil Grzybek, or any detailed Laravel post-mortem. Items under "Cited Findings" are only what was fetched; "Inferences" are my own reasoning.

## 1. Module public API (contracts, facades, actions, service classes; public vs internal)

### Takeaway
Sources converge on: modules expose a small published surface (interfaces bound in the module's service provider, plus events and DTOs), and everything else (Domain, Infrastructure, models) is private. Enforcement by CI static analysis, not convention, is repeatedly emphasized. I found no source evaluating facades as a module API.

### Cited Findings
- Contract pattern: interface (e.g. `InventoryServiceInterface` with `reserveItems`, `releaseItems`, `checkAvailability`), concrete service, binding in the module's service provider, consumers type-hint the interface. Advice: "Resist the temptation to access another module's models directly." — [Steve McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- Layout `app/Modules/<Name>/{Domain,Application,Http,Infrastructure,Providers}`; only the public surface is reachable from outside; Domain and Infrastructure are private; cross-module reads go through an interface the owning module publishes. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- Same source: thin controllers; logic in action classes (e.g. `PlaceOrder`), a plain object with one public method, unit-testable without HTTP and reusable from commands/jobs; validation in form request, serialization in resource. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- Port/adapter variant: `Auth` module defines `UserProviderInterface` in its `Contracts`; the `User` module implements it via `AuthUserProviderAdapter`. Each domain has Http, UseCases, Services, Repositories, Entities, Factories, DTOs, Events/Listeners, Contracts/Adapters, Providers. Quality gates: Pint, Larastan level 6, Rector. — [oohegor/laravel-modular-monolith](https://github.com/oohegor/laravel-modular-monolith)
- Laracasts "Modular Laravel" (instructor handle `mateusjatenee`, per search; identity link to Mateus Guimarães unverified) episode list: interfaces at module entry points enabling polymorphism (ep. 16), "Fake Boundaries" for tests (ep. 21), Vertical Slices vs Grouping by Type (eps. 19-20), per-module service providers (ep. 3). Only blurbs seen, not content. — [Laracasts](https://laracasts.com/series/modular-laravel)
- Shopify Packwerk retrospective (Ruby, general principles): privacy/public-API enforcement was "simple" but pulled attention to API design and was removed in Packwerk 3.0; it forced an `app/public` dir that fought framework conventions and produced poorly documented "public" code. Public APIs "need deliberate documentation and design, separate from dependency enforcement." — [Shopify Engineering](https://shopify.engineering/a-packwerk-retrospective)
- Cordon Modulith (alpha package) fails CI if a module uses another module's internal classes rather than its public API. — [Packagist](https://packagist.org/packages/chrisabner/cordon-modulith)

### Inferences
- Interface-in-provider is the dominant, least ceremonious Laravel pattern; action classes are a good "public command" unit (one class = one use case) and can be public (cross-module callable) or internal; this is a design choice not stated by sources.
- Shopify's lesson suggests a namespace convention (e.g. `Contracts/`, `Data/`, `Events/` = public; everything else private) plus a dependency-graph check is more durable than per-class privacy enforcement.

### Gaps
- No source found on facades as a module API (tradeoff I'd expect: static/global access and hidden dependencies; uncited).
- No source on "module service" class vs action-per-use-case tradeoffs beyond the above.
- Laravel Beyond CRUD (Spatie) content not retrieved.

## 2. Inter-module communication (sync contracts vs events; event sourcing; transactional consistency; outbox)

### Takeaway
Pattern: synchronous contract calls for reads/queries needing an immediate answer; domain events with queued listeners for side effects/writes in other modules. Dispatch after commit; pass IDs not models in queued events. For true outbox needs, several new Laravel outbox packages exist but none is clearly dominant.

### Cited Findings
- Orders dispatches `OrderCreated`; Billing's listener `CreateInvoiceForOrder` creates the invoice; Orders never references Billing. — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- Order saved in `DB::transaction`, then `OrderPlaced` dispatched; consumer is a `ShouldQueue` listener; a downstream outage delays invoices rather than rejecting orders; new consumers need no publisher changes. Pitfall: sync side effects in the main flow can roll back a valid order. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- Pass IDs, not models, in events: serialized models are a snapshot that may be stale when the job runs. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith); McDougall's example instead uses `SerializesModels` with the Order, so sources differ in style — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- Circular dependency between modules: break with events or a shared service. — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- Laravel 10.30 added `ShouldDispatchAfterCommit` (event held until the surrounding transaction commits; discarded on rollback), `ShouldHandleEventsAfterCommit` for listeners, `ShouldQueueAfterCommit`. Runs immediately if no transaction is open. Edge cases (nested transactions, observers) unverified. — [Laravel News](https://laravel-news.com/index.php/laravel-10-30-0)
- Laracasts course covers temporal coupling and events (ep. 13) and re-architecting checkout to events (eps. 23-24). — [Laracasts](https://laracasts.com/series/modular-laravel)
- Outbox packages (all third-party, newer ones low adoption): gpalyan/laravel-outbox v2.0.0 (May 2026, outbox+inbox, PHP 8.4, Laravel 12/13); bugfix666/laravel-outbox (June 2026, DLQ, retry); mohamedhekal/event-outbox v0.1.0; dnakitare/laravel-outbox beta; ludovicose/transaction-outbox (~21k installs, older, Redis only). No clear leader found. — [gpalyan on Packagist](https://root.packagist.org/packages/gpalyan/laravel-outbox)
- Extraction criteria: different scaling profile, release cadence owned by another team, or compliance boundary; modules already on interfaces+events make extraction a matter of swapping dispatcher for a queue and implementation for an HTTP client. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)

### Inferences
- In one process and one DB, after-commit queued listeners on a durable queue give most outbox benefit; a real outbox table matters mainly if queue and DB can diverge (e.g. Redis queue, crash between commit and enqueue) or when publishing to external brokers. My reasoning.
- Idempotent listeners are needed with at-least-once queues (the inbox/dedupe key in the gpalyan package hints at this).

### Gaps
- No sourced guidance on spatie/laravel-event-sourcing or Verbs as an inter-module bus.
- No Laravel-specific source on sync-vs-async decision rules beyond the above.

## 3. Data transfer across boundaries (DTOs vs Eloquent models)

### Takeaway
Sources lean to DTOs/contracts at the boundary and "don't touch other modules' models," but no source gives a hard consensus on Eloquent leakage or on spatie/laravel-data vs plain readonly classes.

### Cited Findings
- Laracasts course has DTO episodes: "passing data between modules using DTOs" (ep. 8) and "consolidating data into DTOs ... stable contracts" (ep. 14). — [Laracasts](https://laracasts.com/series/modular-laravel)
- Controller converts request to DTO; ports are the stable contract. — [oohegor](https://github.com/oohegor/laravel-modular-monolith)
- McDougall's article does not cover DTOs (arrays used). — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- A Laracasts forum thread raises the tradeoff: returning Eloquent models leaks relationships; one big DTO risks loading unneeded relations; asks about a DTO per query. — [Laracasts discussion](https://laracasts.com/discuss/replies/929803)
- Shared-kernel guidance (.NET): DTOs/view models are API concerns and should not live in the shared kernel; strongly typed IDs crossing boundaries can. — [Milan Jovanovic](https://milanjovanovic.tech/blog/shared-kernel-pattern-modular-monolith)

### Inferences
- Put DTOs in the providing module's public namespace so consumers depend on the provider, never the reverse; avoid a shared DTO bucket.
- Readonly classes on PHP 8.4 cover most needs without a package; spatie/laravel-data adds casting/validation useful at HTTP edges. Uncited.

### Gaps
- No source on spatie/laravel-data specifically; no source quantifying "consensus."

## 4. Database ownership (tables, FKs, cross-module relationships, reporting)

### Takeaway
Sources suggest per-module migrations and model ownership; cross-module FKs are a conscious tradeoff (integrity vs extractability). Cross-module Eloquent relationships are technically possible but I found no endorsement.

### Cited Findings
- Cross-module FK: Moduark analyzer warns (not errors) by default, since a relational monolith may keep DB integrity while accepting migration coupling; it also flags cross-owner writes inside transactions. — [Moduark](https://root.packagist.org/packages/cluion/moduark)
- InterNACHI/modular docs: referenced table's migration must run first (adjust timestamps); example `constrained('users')` against an app-level table. — [Modular docs](https://www.mintlify.com/internachi/modular/guides/module-migrations)
- Avoidance view (non-Laravel): store `customer_id` as a plain UUID without FK, check existence via service; prefer separate DB schemas per module. — [Backend Engineering Adventures](https://backendengineeringadventures.substack.com/p/database-design-for-modular-monoliths)
- Runtime-registered cross-module relations via `Model::resolveRelationUsing()` exist as packages (plus a PHPStan extension), implying some teams do cross-module relations. — [happenv PHPStan](https://packagist.org/packages/happenv-com/laravel-true-modular-phpstan)
- Don't create a module per Eloquent model; start with business domains, not tables. — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)
- Cenedy and McDougall do not discuss table ownership or FKs. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)

### Inferences
- Pragmatic middle: FKs to a stable upstream module (e.g. users) are low-risk; FKs between peer modules are what block extraction. Uncited judgment.
- Cross-module reporting likely wants a dedicated read model/query class or reporting module; no source.

### Gaps
- Table prefixes: no source found. Cross-module Eloquent relationship consensus: none found. Cross-module reporting: none found.

## 5. Shared kernel / support modules

### Takeaway
Keep the kernel tiny; include only what multiple modules genuinely need; default to not sharing.

### Cited Findings
- Belongs: base entity/value-object types, common interfaces, event contracts, result type, cross-boundary typed IDs. Not: module logic, infrastructure, single-module utilities, DTOs. Test: "if removing a type would break only one module, it doesn't belong." Pitfalls: becoming "Common", convenience helpers, dependency creep, breaking changes, ungoverned changes, "when in doubt, don't share." (.NET; principles transferable) — [Milan Jovanovic](https://milanjovanovic.tech/blog/shared-kernel-pattern-modular-monolith)
- Laravel example: `Core` = base Entity, marker exceptions, HTTP base, enums; separate `Common` for shared domain helpers. — [oohegor](https://github.com/oohegor/laravel-modular-monolith)
- Shopify: isolated a base package (Platform Essentials) with zero dependencies, with CI to keep it that way; grouping by semantic name misplaced code (billing settings with fraud status belonged at base). — [Shopify](https://shopify.engineering/a-packwerk-retrospective)
- Deptrac ruleset where Ordering and Billing each depend only on Shared. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)

### Inferences
- Rule: Shared depends on nothing; feature modules depend on Shared; Shared never depends on feature modules.

### Gaps
- No Laravel-specific discussion of where outbox/event-bus infrastructure should live.

## 6. Internal layering and module granularity

### Takeaway
Two camps: pragmatic (actions + models + thin controllers) and DDD-lite/hexagonal (UseCases, Repositories, Entities, Ports). Sources favor domain-based granularity and warn against module-per-model.

### Cited Findings
- Pragmatic: action classes, thin controllers, models enriched with behavior (course eps. 10-12). — [Laracasts](https://laracasts.com/series/modular-laravel); [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- DDD/hexagonal: Controller -> UseCase -> Port -> Entity/Adapter -> Repository; rich Eloquent entities without public setters; repositories used. — [oohegor](https://github.com/oohegor/laravel-modular-monolith)
- Course compares vertical slices vs grouping by type inside modules. — [Laracasts](https://laracasts.com/series/modular-laravel)
- Pitfalls: over-modularizing (module per model); circular dependencies; splitting because of file count yields a distributed monolith. — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide); [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- Modularize only once domain boundaries are clear; extract packages only when reuse is proven. — [Contra](https://contra.com/community/ozPDcmG4-laravel-modular-development-internal-modules)

### Inferences
- Repository-over-Eloquent is the contested ceremony; here it appears only in the strict hexagonal example.

### Gaps
- No source on Livewire/Jobs placement within modules; Spatie's Beyond CRUD domain/app split not retrieved.

## 7. Case studies and retrospectives

### Takeaway
Only one substantive retrospective retrieved (Shopify, Ruby). Laravel-specific material is guides and a production claim, not post-mortems.

### Cited Findings
- Shopify: todo files grew faster than fixes; tool blind spots (dynamic constants, routes, fixtures) let packages pass checks yet fail at runtime; domain-based components didn't match runtime behavior; "running code ... the best indicator of real progress"; boundaries should follow the dependency graph. Adoption trick: generate a todo file for existing violations to block new ones. — [Shopify](https://shopify.engineering/a-packwerk-retrospective); [GAT](https://www.globalapptesting.com/engineering/implementing-packwerk-to-delimit-bounded-contexts)
- Cenedy describes a Laravel logistics/order backend in production for years as a single deployable; claims monoliths fail when every part can reach every other part; single-author blog, no metrics. — [Cenedy](https://cenedy.is-a.dev/blog/laravel-modular-monolith)
- McDougall's e-commerce story with pitfalls list (see above). — [McDougall](https://www.juststeveking.com/articles/building-modular-systems-in-laravel-a-practical-guide)

### Inferences
- Baseline-then-ratchet (Packwerk todo) maps to Laravel via Deptrac baselines.

### Gaps
- No Laracon talk, Laravel team post-mortem, or Spatie/Brent material found. Treat the Laravel evidence base as thin and largely opinion.
