# Plan: Gene & Tonic redesign

> Source: prd.md. Each task is a thin, complete path through every layer, demoable on its own, and fits one context window.

## Goal
Re-skin the app as Gene & Tonic. It gets a sidebar shell, five real routes, shared `x-ui` components, five rebuilt screens, and the new research, lookup and usage data the mocks need.

## Architecture
The work is done in three layers:
1. **Foundation.** Tokens, Figtree, light-only rendering, the brand files and the `x-ui`/`x-icon`/`x-brand` anonymous components.
2. **Data, module by module.** Each data change lands in its owning module behind a public contract, DTO or enum, with its fake and tests:
   - Supplements: topics, titles, the listing and the status filter.
   - Genetics: variant names, the looked-up rule and `LookUpAll`.
   - Intelligence: profile labels, the usage window and `costByProfile`.
   - Recommendations: allele, variant name, `topForUser` and `VariantLinks`.
3. **Screens.** Each screen is restyled as its own task in the owning module.

The app shell's Home page is a composer. It embeds module SFCs and never reads another module's data. Cross-module UI goes through PageActions slots (`supplement.preview`, `supplement.card`). The one cross-module data read that needs to go from Recommendations into Genetics goes through a Shared contract (`VariantLinks`), bound to an empty default and rebound by Recommendations.

Follow the existing patterns:
- SFCs with `#[Computed]` reading contracts.
- `Fake*::install()` fakes under `Testing`.
- Strict structured-output schemas plus a `*Builder` validator.
- Add-only nullable migrations.
- `PageActions::register()` in a module provider's `boot()`.

## Global constraints
- Light only: no `dark:` classes, no `@fluxAppearance` and no `dark` custom variant anywhere in app or module views.
- Visual values come from `docs/design/gene-and-tonic/screens/*.html` and `tokens.css`. Tokens win over the mocks.
- `x-ui.*` components are presentation only: they take plain strings, ints, bools and arrays, and import nothing from `Modules\*`.
- New DTO constructor parameters are appended last with defaults (`EffectData`, `SupplementResearchData`, `VariantData`, `GeneticMatchData`, `ProfileData`), because tests build them positionally.
- Migrations are add-only and nullable, named `YYYY_MM_DD_HHMMSS_add_<col>_to_<table>_table.php`, dated `2026_10_25_2000NN` or later.
- Keep the hooks `data-panel`, `data-genotype`, `role="tab"`/`aria-selected` and `data-template`. Add `data-variant-gene` and `data-section` before restyling the variant list. When copy changes on purpose, update its assertion in the same commit.
- Genotypes, allele pairs, gene matches and linked chips render only for the signed-in owner.
- "Looked up" means `LookupStatus::isLookedUp()`, which is true for every status except Pending, LookingUp and Failed. Use it everywhere.
- Never name a Livewire action after a `$wire` alias. The new actions are `lookUpAll` and `selectSupplement`.
- Page tests call `withoutVite()`. Every test keeps `Http::preventStrayRequests()` and the Intelligence fake. No test launches Chrome unless `RUN_CHROME_TESTS=1`.
- Each task ends with `composer test` green, and Pint is run on dirty files.

## Phases
The phases are demoable milestones. Tasks in one phase may run in parallel when their scopes are disjoint.

```yaml cairn-tasks
phases:
  - id: foundations
    name: Foundations and data
    tasks:
      - id: theme-light
        name: Tokens, Figtree, light only, brand files
        covers: [FR-01, FR-02, FR-03]
        blocked_by: []
        scope: ["resources/css/**", "vite.config.js", "resources/views/layouts/**", "resources/views/components/tabs/**", "public/favicon.ico", "public/favicon.svg", "public/apple-touch-icon.png", ".env.example", "Modules/*/resources/views/**", "tests/Feature/LayoutTest.php"]
        complexity: simple
        risky: false
      - id: ui-components
        name: x-ui, x-icon, x-brand components and /_ui
        covers: [FR-04, FR-05, FR-06, FR-07]
        blocked_by: [theme-light]
        scope: ["resources/views/components/**", "resources/views/pages/⚡ui.blade.php", "routes/web.php", "tests/Feature/Ui/**"]
        complexity: standard
        risky: false
      - id: research-topics
        name: Research topics, kicker, effect titles and categories
        covers: [FR-13, FR-14, FR-16, FR-45]
        blocked_by: [theme-light]
        scope: ["Modules/Supplements/app/**", "Modules/Supplements/database/**", "Modules/Supplements/resources/views/prompts/**", "Modules/Supplements/tests/**"]
        complexity: standard
        risky: true
        riskReason: "stores new model output and adds migrations"
      - id: variant-names-lookups
        name: Variant names, looked-up rule, LookUpAll action
        covers: [FR-15, FR-37, FR-45]
        blocked_by: [theme-light]
        scope: ["Modules/Genetics/app/**", "Modules/Genetics/database/**", "Modules/Genetics/resources/views/prompts/**", "Modules/Genetics/tests/**"]
        complexity: standard
        risky: true
        riskReason: "model output, a migration, and a paid bulk lookup action over a user's genetic data"
      - id: usage-data
        name: Profile labels, usage window, cost by profile
        covers: [FR-22, FR-23]
        blocked_by: [theme-light]
        scope: ["Modules/Intelligence/app/**", "Modules/Intelligence/database/**", "Modules/Intelligence/config/**", "Modules/Intelligence/tests/**", "Modules/Intelligence/resources/views/livewire/⚡usage-summary.blade.php", "Modules/Genetics/config/config.php", "Modules/Supplements/config/config.php"]
        complexity: standard
        risky: false
      - id: pdf-brand
        name: Gene & Tonic in PDF headers and footers
        covers: [FR-46]
        blocked_by: [theme-light]
        scope: ["Modules/Reports/resources/views/templates/**", "Modules/Reports/tests/Feature/TemplatesTest.php"]
        complexity: simple
        risky: false
      - id: supplement-listing
        name: Supplements listing contract, sources count, status filter
        covers: [FR-18, FR-21]
        blocked_by: [research-topics]
        scope: ["Modules/Supplements/app/**", "Modules/Supplements/tests/**"]
        complexity: standard
        risky: false
      - id: relevance-contracts
        name: Match allele and name, topForUser, VariantLinks
        covers: [FR-17, FR-19, FR-20]
        blocked_by: [supplement-listing, variant-names-lookups]
        scope: ["Modules/Recommendations/app/**", "Modules/Recommendations/tests/**", "Modules/Shared/**", "Modules/Genetics/app/Data/Genotype.php", "Modules/Genetics/tests/Unit/GenotypeTest.php"]
        complexity: complex
        risky: true
        riskReason: "reads users' genotypes and exposes match data across modules"
  - id: shell
    name: Shell and routes
    tasks:
      - id: shell-routes
        name: Sidebar shell, real routes, tab redirects
        covers: [FR-08, FR-09, FR-10, FR-11, FR-12]
        blocked_by: [ui-components, research-topics, variant-names-lookups, usage-data, pdf-brand, supplement-listing, relevance-contracts]
        scope: ["resources/views/layouts/**", "resources/views/pages/**", "routes/web.php", "Modules/*/routes/web.php", "Modules/Genetics/resources/views/livewire/⚡summary.blade.php", "Modules/Supplements/resources/views/livewire/⚡show.blade.php", "tests/Feature/**", "Modules/*/tests/Feature/**", "CLAUDE.md"]
        complexity: standard
        risky: true
        riskReason: "changes auth on /genetics and the public routes"
  - id: screens
    name: Screens
    tasks:
      - id: supplements-index
        name: Supplements split view, filter, preview and card slots
        covers: [FR-30, FR-31, FR-32, FR-33]
        blocked_by: [shell-routes]
        scope: ["Modules/Supplements/resources/views/livewire/⚡catalog.blade.php", "Modules/Supplements/resources/views/components/catalog/**", "Modules/Supplements/tests/Feature/CatalogTest.php", "Modules/Supplements/tests/Feature/HardeningTest.php", "Modules/Recommendations/resources/views/slots/**", "Modules/Recommendations/app/Providers/**", "Modules/Recommendations/tests/Feature/SlotsTest.php"]
        complexity: standard
        risky: true
        riskReason: "public page that renders an owner-only gene strip through a slot"
      - id: supplement-page
        name: Supplement hero, tabs, Overview grid, genetic panel
        covers: [FR-34, FR-35, FR-36, FR-16]
        blocked_by: [shell-routes]
        scope: ["Modules/Supplements/resources/views/livewire/⚡show.blade.php", "Modules/Supplements/resources/views/components/show/**", "Modules/Supplements/resources/views/components/source-badges.blade.php", "Modules/Supplements/tests/Feature/SupplementPageTest.php", "Modules/Recommendations/resources/views/livewire/⚡genetic-panel.blade.php", "Modules/Recommendations/tests/Feature/GeneticPanelTest.php", "Modules/Reports/resources/views/components/**", "Modules/Reports/tests/Feature/DownloadMenuTest.php"]
        complexity: standard
        risky: true
        riskReason: "public page showing model output and owner-only genotypes"
      - id: genetics-page
        name: Genetics page, chips, variant list, Look up now
        covers: [FR-37, FR-38, FR-39, FR-40, FR-41]
        blocked_by: [shell-routes]
        scope: ["Modules/Genetics/resources/views/livewire/⚡profile.blade.php", "Modules/Genetics/resources/views/components/**", "Modules/Genetics/app/Support/VariantGrouping.php", "Modules/Genetics/tests/Feature/VariantListFilterTest.php", "Modules/Genetics/tests/Feature/UploadPageTest.php", "Modules/Genetics/tests/Feature/ProfilePageTest.php", "Modules/Genetics/tests/Feature/ComplexMarkerCsvTest.php", "Modules/Genetics/tests/Feature/LookupTest.php", "Modules/Genetics/tests/Unit/VariantGroupingTest.php"]
        complexity: standard
        risky: true
        riskReason: "personal genetic data and the paid Look up now action"
      - id: usage-page
        name: AI usage page with period, cost by task, table
        covers: [FR-42, FR-43, FR-44]
        blocked_by: [shell-routes]
        scope: ["Modules/Intelligence/resources/views/**", "Modules/Intelligence/tests/Feature/UsageViewsTest.php", "Modules/Intelligence/tests/Feature/UsagePageTest.php"]
        complexity: standard
        risky: true
        riskReason: "public page that takes an untrusted window parameter"
  - id: home
    name: Home
    tasks:
      - id: home-hero-stats
        name: Home header, gene-match hero, stat row
        covers: [FR-24, FR-25, FR-26]
        blocked_by: [supplements-index, supplement-page, genetics-page, usage-page]
        scope: ["resources/views/pages/⚡dashboard.blade.php", "tests/Feature/DashboardTest.php", "tests/Feature/HomeTest.php", "Modules/Recommendations/resources/views/livewire/⚡top-match.blade.php", "Modules/Recommendations/resources/views/livewire/⚡match-stat.blade.php", "Modules/Recommendations/tests/Feature/TopMatchTest.php", "Modules/Supplements/resources/views/livewire/⚡stats.blade.php", "Modules/Supplements/tests/Feature/StatsTest.php", "Modules/Genetics/resources/views/livewire/⚡variant-stat.blade.php", "Modules/Genetics/tests/Feature/HomeWidgetsTest.php", "Modules/Intelligence/resources/views/livewire/⚡spend-stat.blade.php", "Modules/Intelligence/tests/Feature/SpendStatTest.php"]
        complexity: standard
        risky: true
        riskReason: "public page that shows owner-only genotypes"
      - id: home-sections
        name: Latest research, your genes, your supplements
        covers: [FR-27, FR-28, FR-29]
        blocked_by: [home-hero-stats]
        scope: ["resources/views/pages/⚡dashboard.blade.php", "tests/Feature/HomeTest.php", "Modules/Supplements/resources/views/livewire/⚡latest-research.blade.php", "Modules/Supplements/resources/views/livewire/⚡your-supplements.blade.php", "Modules/Supplements/config/config.php", "Modules/Supplements/tests/Feature/HomeSectionsTest.php", "Modules/Genetics/resources/views/livewire/⚡your-genes.blade.php", "Modules/Genetics/tests/Feature/HomeWidgetsTest.php"]
        complexity: standard
        risky: true
        riskReason: "triggers the paid Look up now action from a public page"
  - id: polish
    name: Polish
    tasks:
      - id: polish-a11y
        name: Phone, keyboard and loading-state pass
        covers: [FR-47, FR-48]
        blocked_by: [home-sections]
        scope: ["resources/views/**", "Modules/*/resources/views/**", "tests/**", "Modules/*/tests/**"]
        complexity: standard
        risky: false
```

### Task: theme-light

**Check:** `npm run build` succeeds; grep finds no `dark:`, `@fluxAppearance` or `Instrument Sans` in views, CSS or `vite.config.js`; `/` renders `<title>` with "Gene &amp; Tonic" (single escape) and the new favicons.

**Interfaces**
- Consumes: none
- Produces:
  - Tailwind tokens (`bg-canvas`, `text-ink`, `bg-juniper`, `bg-tone-*-bg`, `rounded-panel`, `shadow-segment`, `tabular`, …) from `tokens.css`. Copy in the `@theme` block, `@layer base` and `@utility tabular`, and delete `@custom-variant dark`.
  - Figtree 400–700 via `bunny()` in `vite.config.js`.
  - `.env.example` `APP_NAME="Gene & Tonic"`.
  - `public/` favicons copied from `brand/`.
  - `LayoutTest` covering the title and favicon links.

### Task: ui-components

**Check:** the render tests pass for every `x-ui.*` component, including the allele-pair cases (effect filled, null effect, homozygous, one letter). `/_ui` returns 404 outside local and lists every component in local.

**Interfaces**
- Consumes: the tokens from theme-light.
- Produces:
  - Icons: `x-icon.{arrow-up,capsule,chart,chevron-down,chevron-right,chevron-up,file,group-dots,group-drop,group-flame,group-heart,group-leaf,group-moon,group-shield,group-smile,helix,home,info,search,upload}`. Strip the c2pa metadata and the fixed width/height; use `currentColor`.
  - Brand: `x-brand.mark size=` (small mark at 24 px and below) and `x-brand.lockup`.
  - Layout: `x-ui.panel narrow`, `x-ui.page-header title subtitle` (with an `actions` slot), `x-ui.section title link-href link-label`, `x-ui.stat label value caption` (passes `data-stat`).
  - Tiles and rows: `x-ui.icon-tile tone size`, `x-ui.link-row href meta title variant=bordered|divided`.
  - Controls: `x-ui.button variant=primary|secondary|on-tint`, `x-ui.segmented` and `x-ui.segmented.item active`, `x-ui.chip active count`.
  - Status: `x-ui.status tone label`, `x-ui.progress-steps step total label`, `x-ui.grade grade=strong|moderate|limited`.
  - Genetics: `x-ui.allele-pair :alleles effect size`, `x-ui.copies count`.
  - Other: `x-ui.notice`, `x-ui.dropzone` (wraps a `wire:model` file input; label, help and filename).
  - `x-tabs`/`x-tabs.item` restyled to the segmented look, with the same props and roles.
  - The `/_ui` route, registered only when `app()->isLocal()`.

### Task: research-topics

**Check:** a research run through the Intelligence fake stores the kicker, topics, effect titles and categories. Invalid values are dropped, mapped to Other or capped. `SupplementResearchReader::forSupplement()` returns them on the DTOs, and legacy rows return nulls or empty values without errors.

**Interfaces**
- Consumes: none
- Produces:
  - `Modules\Supplements\Enums\HealthTopic` (Methylation, Heart, Mood, Sleep, Stress, Energy, Focus, Blood, Immune, Digestion, Other) with `label()`.
  - `SupplementResearchData::$kicker` (`?string`) and `$topics` (`list<HealthTopic>`), appended with defaults.
  - `EffectData::$title` (`?string`) and `$category` (`?HealthTopic`), appended with defaults.
  - New enum methods:
    - `ResearchStatus::tone()` (neutral/active/ok/bad) and `ResearchStatus::step()` (`?int`: 1, 2 or 3 of 4).
    - `EvidenceGrade::tone()` and `EvidenceGrade::rank()` (strong=0, moderate=1, limited=2).
    - `GeneRelation::tone()` (ok/warn/neutral).
  - Migrations adding `supplement_researches.kicker` (string 60) and `topics` (json), and `supplement_effects.title` (string 80) and `category` (string 20).
  - The research schema extended (strict, required and nullable), with the schema key assertion in `ResearchPipelineTest` updated.

**Test cases**
- Happy: the fake returns a kicker "B vitamin · active folate", topics `["methylation","heart"]`, and an effect titled "Homocysteine levels" with category `heart`. The reader returns them as `HealthTopic` cases and strings.
- Unhappy: the fake returns topic "banana", category "banana", a 200-char title and a missing kicker. Topics `[]`, category Other, the title capped at 80 and kicker null; the run still completes.
- Unhappy: a legacy research row with null new columns. The reader returns `kicker` null, `topics []`, effect `title` null and `category` null.
- Security: the fake returns a title and kicker containing `<script>alert(1)</script>` and newlines. They are stored collapsed to one line, and rendering the stored values through Blade outputs `&lt;script&gt;`.

### Task: variant-names-lookups

**Check:** a lookup through the fake stores `variant_name`, and `VariantData::$variantName` returns it. `LookupStatus::isLookedUp()` replaces the three inline checks. `LookUpAll::handle($userId)` queues only that user's Pending and Failed rows, up to `genetics.lookup_cap`, behind its own limiter.

**Interfaces**
- Consumes: none
- Produces:
  - A migration adding `gene_variants.variant_name` (string 40, nullable); the lookup schema and prompt rule ask for a common variant name.
  - `VariantData::$variantName` (`?string`), appended with a default.
  - `LookupStatus::isLookedUp(): bool`.
  - `VariantGroup::tone(): string` and `VariantGroup::icon(): string` (§6.4 of the handoff).
  - `UploadStatus::tone(): string`.
  - `Modules\Genetics\Actions\LookUpAll::handle(int $userId): LookUpAllResult`. The result carries `queued`, `limited` and `nothingPending`. It uses the rate-limiter key `genetics-lookup-all:{userId}` at 3 per minute and calls `QueueLookups::handle($rsids, config('genetics.lookup_cap'))`.

**Test cases**
- Happy: the fake returns `variant_name` "A1298C". It is stored, and `forUser()` returns it on the variant.
- Happy: a user with 20 pending rows and `lookup_cap` 15. 15 rows are queued, and the result reports `queued` = 15.
- Unhappy: an over-long name (60 chars) is capped at 40. A missing name stores null.
- Unhappy: a user with nothing Pending or Failed. The result is `nothingPending`, and no job is dispatched.
- Security: user A calls `LookUpAll` while user B has a pending rsID that A doesn't hold. B's row stays Pending, and no job is dispatched for it.
- Security: a fourth call within a minute returns `limited` and dispatches nothing.
- Security: the recorded lookup prompt contains the rsID and gene only, never a genotype string from `user_variants`.
- Security: rows already LookingUp are not queued again.

### Task: usage-data

**Check:** repository tests seed rows at 2h, 3d and 30d. `summary()`, `paginateLatest()` and `costByProfile()` return the correct counts per window, including the search-credit rows. `ProfileData::$label` returns the configured label, and an unknown or null name falls back without throwing.

**Interfaces**
- Consumes: none
- Produces:
  - `Modules\Intelligence\Enums\UsageWindow` (Day = `24h`, Week = `7d`, All = `all`) with `label()` and `since(): ?CarbonImmutable`.
  - The repository methods, each taking `UsageWindow $window = UsageWindow::All`:
    - `AiRequestRepository::summary(...)`
    - `AiRequestRepository::paginateLatest(int $perPage, ...)`
    - `AiRequestRepository::costByProfile(...): list<ProfileCost>`, where each row has `profile`, `operation`, `calls`, `failed`, `credits` and `cost`.
  - `ProfileData::$label` (appended with a default).
  - `ProfileRegistry::labelFor(?string $name, string $operation): string`, which never throws ("Web search" for search rows with a null name).
  - `label` keys under `supplements.research_profile`, `genetics.lookup_profile`, `genetics.group_profile` and `genetics.extract_profile`.
  - An `AiRequestFactory` with `HasFactory` on `AiRequest`.
  - The existing `usage-summary` SFC still renders.

### Task: pdf-brand

**Check:** TemplatesTest asserts "Gene &amp; Tonic" in the rendered header and footer HTML of every configured template, inline-styled at 8pt or more.

**Interfaces**
- Consumes: none
- Produces: the brand text in `templates/{clinical,modern}/{header,footer}.blade.php`.

### Task: supplement-listing

**Check:** the listing method returns DTOs with counts, topics and gene interactions in one query plus eager loads, and the fake mirrors it. `paginateWithResearch(10, 'active')` returns only active rows, and catalog rows carry `sources_count`.

**Interfaces**
- Consumes: `HealthTopic` and `SupplementResearchData::$topics` from research-topics.
- Produces:
  - `SupplementResearchReader::all(): list<SupplementSummaryData>`. `SupplementSummaryData` has `supplementId`, `name`, `status`, `?completedAt`, `effectsCount`, `studiesCount`, `sourcesCount`, `topics` and `geneInteractions`.
  - `FakeSupplementResearchReader::withSummary(SupplementSummaryData)`.
  - `Supplement::sources()` (has-many-through).
  - `SupplementRepository::paginateWithResearch(int $perPage, string $status = 'all')`, where status is one of all, complete, active or failed, and unknown values are treated as all.
  - The `CatalogTest` stub repository updated to the new signature.

### Task: relevance-contracts

**Check:**
- A gene-strand interaction allele `T` against plus-strand ref `G`/risk `A` yields `GeneticMatchData::$allele` = `A`, and gene-only matches yield null.
- `topForUser` ranks by match count, then name.
- `VariantLinks::forUser` maps matched rsIDs to supplements.
- The Shared default returns `[]`.
- The boundary tests pass.

**Interfaces**
- Consumes:
  - `SupplementResearchReader::all()` from supplement-listing.
  - `VariantData::$variantName` from variant-names-lookups.
- Produces:
  - `Genotype::effectAlleleOnPlusStrand(string $allele, ?string $ref, ?string $risk): ?string`, sharing the strand logic with `copiesOf`.
  - `GeneticMatchData::$allele` and `$variantName` (`?string`, appended).
  - `GeneticRelevanceReader::topForUser(int $userId, int $limit = 1): list<SupplementMatchesData>`, where `SupplementMatchesData` has `supplementId`, `name`, `status` and `matches`.
  - `FakeGeneticRelevanceReader::top(int $userId, list<SupplementMatchesData>)`.
  - `Modules\Shared\Contracts\VariantLinks::forUser(int $userId): array<string, list<LinkedSupplementData>>`, with `Modules\Shared\Data\LinkedSupplementData` (`supplementId`, `name`) and an empty default bound in `SharedServiceProvider`.
  - A Recommendations implementation rebound in `RecommendationsServiceProvider::register()`.
  - `Modules\Shared\Testing\FakeVariantLinks::install()->for($userId, $map)`.

**Test cases**
- Happy: a profile with MTHFR rs1801131 GT, risk G, and a supplement interaction on rs1801131 with allele G. The match has allele G and copies 1, and `VariantLinks` maps `rs1801131` to that supplement.
- Happy: 3 supplements with 2, 3 and 0 matches. `topForUser($u, 5)` returns the 3-match then the 2-match supplement and excludes the 0-match one.
- Unhappy: a user with no profile. `topForUser` returns `[]` and `VariantLinks::forUser` returns `[]`.
- Unhappy: an interaction allele "C677T" (unusable). The match is rsID-only and allele is null.
- Security: `VariantLinks::forUser($a)` never includes rsIDs that only user B holds. `LinkedSupplementData` and `SupplementMatchesData` serialized to an array contain no genotype for the Shared contract output.

### Task: shell-routes

**Check:**
- Each of `/`, `/supplements`, a supplement and `/usage` returns 200 for a guest.
- `/genetics` redirects a guest to `/login` and renders the genetics page when signed in.
- `/?tab=supplements|genetics|usage` redirects.
- The nav shows `aria-current="page"` on the active item (Supplements is also active on a supplement page).
- The mobile top bar and toggle render.

**Interfaces**
- Consumes: `x-brand.mark`, `x-icon.*` and `x-ui.*` from ui-components.
- Produces:
  - The sidebar layout in `layouts/app.blade.php`. Use `flux:sidebar collapsible="mobile"`, a hand-built `flux:header` top bar with `flux:sidebar.toggle`, and nav items with `aria-current` set by hand from `request()->routeIs()`.
  - The routes:
    - `supplements.index` (`/supplements` → `supplements::catalog` full page)
    - `genetics.profile` (`/genetics` → `genetics::profile`, auth)
    - `usage` (`/usage` → `intelligence::usage-log`)
  - Each page has a `#[Title]`.
  - The `?tab=` redirects in `pages::dashboard`'s `mount()`.
  - The supplement page's back link pointing to `supplements.index`.
  - `genetics::summary` removed if it is unused.
  - The tests moved off `/?tab=`.
  - CLAUDE.md updated for the routes, and its stale arch-test line corrected.

**Test cases**
- Happy: a signed-in user opens `/genetics` and sees "Upload results". A guest opens `/usage` and gets 200.
- Unhappy: `/?tab=garbage` renders Home, with no redirect loop.
- Security: a guest requesting `/genetics` is redirected to `/login`, and after login the intended redirect lands on `/genetics`. The genetics markup never appears on `/` for a guest.

### Task: supplements-index

**Check:**
- The split view renders.
- The In progress chip shows only active rows, and Failed appears only when the count is greater than 0.
- `?selected={id}` previews that supplement's top 3 effects sorted strong first.
- The `supplement.preview` and `supplement.card` slots render Recommendations content for a signed-in user only.
- Phones hide the preview.

**Interfaces**
- Consumes:
  - `paginateWithResearch($perPage, $status)` and `sources_count` from supplement-listing.
  - `ResearchStatus::step()/tone()` and `EvidenceGrade::rank()` from research-topics.
  - `GeneticRelevanceReader` from relevance-contracts.
- Produces:
  - Catalog properties `#[Url] status` and `#[Url(as:'selected')] selectedId`, and the action `selectSupplement(int)`.
  - PageActions slot `supplement.preview` (receives `supplementId`) → `recommendations::slots.preview-strip`.
  - PageActions slot `supplement.card` (receives `supplementId`) → `recommendations::slots.match-count`, which prints "{n} gene matches" or "no gene matches" for a signed-in user with a profile, and nothing otherwise.

**Test cases**
- Happy: 2 complete and 1 searching supplement. The In progress chip shows only the searching row, with "Searching sources" and step 2 of 4.
- Unhappy: `?status=bogus` falls back to All. `?selected=99999` (unknown) shows the first complete supplement, or the empty state.
- Security: a guest viewing `/supplements?selected={id}`, where the fake relevance has matches, sees no `data-genotype` or allele-pair markup and no match count.

### Task: supplement-page

**Check:**
- The breadcrumb replaces the back link.
- The hero shows the kicker and tags when present.
- `wire:poll.3s` is present only while active.
- The genetic panel renders on Overview and Genetics only, with ring, allele pairs (owner only) and relation badges.
- The effect category chips filter Health effects.
- Legacy null data renders the fallbacks.

**Interfaces**
- Consumes:
  - `HealthTopic`, the effect `title`/`category`, `kicker`/`topics`, `ResearchStatus::step()/tone()` and `EvidenceGrade::tone()` from research-topics.
  - `GeneticMatchData::$allele/$variantName` from relevance-contracts.
- Produces:
  - The restyled `supplements::show`, with `#[Url] effectTopic` for the chips.
  - The `supplement.panels` slot rendered inside the overview and genetics branches only.
  - The restyled `reports::components.download-menu` (same contract: `supplementId` and `status`; renders only when Complete).
  - The restyled `recommendations::genetic-panel`, keeping `data-genotype` and the four states.

**Test cases**
- Happy: a complete supplement with kicker, topics and titled effects. The kicker, tag labels and effect titles show, and selecting category Heart leaves only Heart effects.
- Unhappy: legacy research with null kicker and topics and untitled effects. There is no kicker line and no chips, and the effects show their claims.
- Unhappy: a searching run. `wire:poll.3s` and "step 2 of 4" are present; once complete, there is no `wire:poll`.
- Security: a guest viewing a supplement with matches for another user sees no `data-genotype` and no allele pair. A stored kicker `<script>` renders escaped.

### Task: genetics-page

**Check:**
- `/genetics` shows the header copy, the dropzone, the uploads list, the add-SNP card and the chips.
- `?group=methylation` opens that section on load.
- The Linked chip lists only linked variants, with "Linked: X" chips.
- Look up now toasts the queued count.
- All existing genetics feature tests pass with selectors moved to `data-variant-gene` and `data-section`.

**Interfaces**
- Consumes:
  - `LookUpAll`, `LookupStatus::isLookedUp()`, `VariantGroup::tone()/icon()`, `UploadStatus::tone()` and `VariantData::$variantName` from variant-names-lookups.
  - `VariantLinks` from relevance-contracts.
  - `x-ui.dropzone`, `x-ui.chip` and `x-ui.allele-pair` from ui-components.
- Produces:
  - `#[Url] group` and `#[Url] search`, with sections synced on mount.
  - Group values extended with `linked` and `two_copies`.
  - The action `lookUpAll()`.
  - `Modules\Genetics\Support\VariantGrouping`, a plain class holding the grouping, filtering and count logic so the Home widgets can reuse it.
  - The `data-variant-gene` and `data-section` hooks.

**Test cases**
- Happy: `VariantLinks` faked to map rs1801131 to Methylfolate. The Linked chip count is 1, and the row shows "Linked: Methylfolate" linking to `supplements.show`.
- Happy: `lookUpAll` with 3 pending rows toasts "3 variants queued for lookup."
- Unhappy: `lookUpAll` with nothing pending toasts "Nothing to look up." A rate-limited call toasts the limiter message and queues nothing.
- Security: a signed-in user A's page never shows user B's genotype (the existing `assertDontSee` check stays). `?group=<script>` is treated as All and is not echoed raw.

### Task: usage-page

**Check:** `/usage?window=24h|7d|all` changes every number on the page. Cost by task shows labelled rows and "Web search · {n} credits". The table scrolls inside its own box. The "{n} failed" caption appears only when the count is greater than 0.

**Interfaces**
- Consumes: `UsageWindow`, `summary($window)`, `paginateLatest($n, $window)`, `costByProfile($window)` and `ProfileRegistry::labelFor()` from usage-data.
- Produces: `intelligence::usage-log` as the full page, with `#[Url] window`, the stat row, cost by task and the table. `usage-summary` is folded into it or removed (Home uses its own stat).

**Test cases**
- Happy: rows at 2h and 3d. `window=24h` shows Calls 1, and `all` shows 2.
- Unhappy: `?window=bogus` falls back to All without an error. With no rows, the page shows "No AI calls recorded yet."
- Security: a stored error containing `<script>` renders escaped (the existing assertion stays). A null or unknown profile name renders without a 500.

### Task: home-hero-stats

**Check:**
- A signed-in user sees "Good {morning|afternoon|evening}, {first name}", the hero with the top match's genes and allele pairs, and four stats with captions.
- A guest sees "Welcome to Gene & Tonic", the sign-in hero and only the two public stats.
- No search input appears.

**Interfaces**
- Consumes:
  - `GeneticRelevanceReader::topForUser` from relevance-contracts.
  - `GeneticProfileReader` and `LookupStatus::isLookedUp()` from variant-names-lookups.
  - `summary(UsageWindow::Day)` from usage-data.
  - The `supplement.header` slot.
  - `x-ui.stat`.
- Produces:
  - The rebuilt `pages::dashboard` header and composition.
  - `recommendations::top-match`.
  - `recommendations::match-stat` (total matches, "Across {n} supplements").
  - `genetics::variant-stat` (count, "{n} looked up").
  - `intelligence::spend-stat` (24h cost to 2 decimals, "{n} calls in 24h").
  - `supplements::stats` restyled to a single `x-ui.stat`.

**Test cases**
- Happy: the fake top match gives Methylfolate 3 matches. The hero reads "3 of your variants relate to Methylfolate", with 3 gene rows and "Open Methylfolate".
- Unhappy: a signed-in user with no profile sees "Add your genetic results" linking to `/genetics`. A profile with no matches hides the hero.
- Security: a guest loading `/` sees no `data-genotype`, no allele-pair markup and no Variants or Gene matches stat.

### Task: home-sections

**Check:** Latest research lists 4 rows with topics or the "Complete · …" meta. Your genes lists the top 5 groups by count, with "· {k} linked" and a working Look up now banner. Your supplements shows the abbreviation tiles and the `supplement.card` match count, plus the empty-state prompt.

**Interfaces**
- Consumes:
  - `SupplementResearchReader::all()` from supplement-listing.
  - `VariantGrouping`, `lookUpAll` and the `VariantGroup` tones from genetics-page and variant-names-lookups.
  - `VariantLinks` from relevance-contracts.
  - The `supplement.card` slot from supplements-index.
- Produces:
  - `supplements::latest-research` and `supplements::your-supplements`, with an `abbreviations` map in `Modules/Supplements/config/config.php`.
  - `genetics::your-genes`, with the action `lookUpAll()`.

**Test cases**
- Happy: groups Brain & mood (12), Hormones (8) and Methylation (5, 3 linked) render in count order, with "· 3 linked" on Methylation.
- Unhappy: no supplements shows the add-a-supplement prompt linking to `/supplements`. A supplement with 0 effects reads "{Name}: no effects were graded".
- Security: a guest sees the sign-in card in place of Your genes and no Look up now button. A signed-in `lookUpAll` from Home queues only the user's own rows.

### Task: polish-a11y

**Check:**
- A browser pass at 390 px and 1440 px against the screenshots, with no page-level horizontal scroll and every hit target at least 44 px.
- A keyboard-only run through each page with a visible focus ring.
- `wire:loading` on submit buttons.
- A query-count test on Home with 4 supplements meets NFR-01.
- `composer test` is green, with no new PHPStan baseline entries.

**Interfaces**
- Consumes: every screen.
- Produces: loading states, a11y fixes, and the Home query-count test.
