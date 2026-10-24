# Plan: Supplement PDF reports

> Source: prd.md. Each task is a thin, complete path through every layer, demoable on its own, and fits one context window.

## Goal
Add a Reports module that renders a supplement's completed research as a templated PDF (Clinical and Modern templates) through spatie/laravel-pdf's Chrome driver. Downloads come from a template picker on the supplement detail page, which is attached through a Shared page-action slot.

## Architecture
**Reports** (new nwidart module) owns everything PDF:
- A `TemplateRegistry` that reads `config('reports.templates')`.
- A `SupplementReportRenderer` that turns `SupplementResearchData` (read through `SupplementResearchReader`) into a `PdfBuilder` with the template's view, header, footer, format and margins.
- A controller behind `GET /reports/supplements/{supplement}.pdf`, with a named `reports` rate limiter.
- Blade views: `reports::report` (a shell that includes the template's layout), shared `reports::sections.*`, and `reports::templates.<t>.{layout,header,footer,styles}`. Optional `reports::templates.<t>.sections.*` overrides are resolved with `view()->first()`.

**Shared** gains a tiny `PageActions` contract (register a view under a slot, list the views for a slot). The Supplements detail page renders the views registered for `supplement.header`. Reports registers its download menu there in its provider, so dependencies run Reports → Supplements and Reports → Shared only.

## Global constraints
- Stack: PHP 8.4, Laravel 13.38, Livewire 4.4, Pest 5.3 (with `pestphp/pest-plugin-arch` 5), PHPStan level 7, spatie/laravel-pdf 2.14 with chrome-php/chrome 1.16. `composer test` must pass.
- Create the module with `php artisan module:make Reports && composer dump-autoload`. Use `module:make-*` for module files. The module config file is `Modules/Reports/config/config.php`, so its key is `reports`.
- Reports may use only `Modules\Supplements\{Contracts,Data,Enums,Testing}` and `Modules\Shared\*`. Reports code and tests never touch Supplements models, factories or repositories. Tests build `SupplementResearchData` directly and bind `Modules\Supplements\Testing\FakeSupplementResearchReader`.
- Only Reports uses the `Pdf` facade. Every Reports test except the opt-in Chrome test calls `Pdf::fake()`. No test needs `npm run build`.
- Templates: plain hand-written CSS inlined via `<style>`. No `<script>`, no `<link>`, and no remote `src` or `url(...)`. Use system font stacks only: serif for `clinical`, sans for `modern`. Header and footer views carry their own inline styles with an explicit `font-size` (at least 8pt) and horizontal padding that matches the margins. Page numbers use `@pageNumber` and `@totalPages`.
- Data renders only through escaped `{{ }}`. Links come only from `SourceData::safeUrl()` and `StudyData::pubmedUrl()`. `SupplementResearchData::$error` is never rendered in a PDF.
- Disclaimer text (identical to the detail page): "This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement."
- Templates:
  - `clinical`: label "Clinical", format `a4`, margins in mm `[22, 16, 22, 16]` (top, right, bottom, left), portrait.
  - `modern`: label "Modern", format `letter`, margins `[20, 18, 20, 18]`, portrait.
  - The default comes from `env('REPORTS_DEFAULT_TEMPLATE', 'clinical')`.
- Download name: `Str::slug($research->name).'-'.$template.'.pdf'`.
- Rate limiter `reports`: `Limit::perMinute(10)->by($request->ip())`.
- Citation numbering: a source's number is its 1-based position in `$research->sources`. Markers render as `[n]`, and unknown keys are dropped (the same rule as the `source-badges` component).

## Phases

```yaml cairn-tasks
phases:
  - id: first-pdf
    name: First PDF end to end
    tasks:
      - id: shared-page-actions
        name: Shared page-action slot
        covers: [FR-16]
        blocked_by: []
        scope: ["Modules/Shared/**", "Modules/Supplements/resources/views/livewire/⚡show.blade.php", "Modules/Supplements/tests/Feature/PageActionsSlotTest.php", "Modules/Supplements/tests/Unit/BoundaryTest.php"]
        complexity: simple
        risky: false
      - id: clinical-report
        name: Clinical PDF report
        covers: [FR-01, FR-02, FR-03, FR-04, FR-05, FR-06, FR-07, FR-08, FR-09, FR-10, FR-11, FR-12, FR-13, FR-15, FR-18]
        blocked_by: []
        scope: ["Modules/Reports/**", "modules_statuses.json", "Modules/Supplements/app/Testing/**", "Modules/Supplements/tests/Feature/FakeSupplementResearchReaderTest.php"]
        complexity: complex
        risky: true
        riskReason: "new public route taking untrusted input, model and scraped text rendered into HTML that Chrome executes, launches a local process"
  - id: templates-and-button
    name: Second template, download button, docs
    tasks:
      - id: modern-template
        name: Modern template and section overrides
        covers: [FR-11, FR-12, FR-13, FR-14, FR-15]
        blocked_by: [clinical-report]
        scope: ["Modules/Reports/resources/views/templates/**", "Modules/Reports/resources/views/sections/**", "Modules/Reports/config/**", "Modules/Reports/app/Services/**", "Modules/Reports/tests/Feature/TemplatesTest.php"]
        complexity: standard
        risky: true
        riskReason: "new template renders model and scraped text into HTML that Chrome executes"
      - id: download-menu
        name: Download menu on the detail page
        covers: [FR-17]
        blocked_by: [shared-page-actions, clinical-report]
        scope: ["Modules/Reports/resources/views/components/**", "Modules/Reports/app/Providers/ReportsServiceProvider.php", "Modules/Reports/tests/Feature/DownloadMenuTest.php"]
        complexity: simple
        risky: false
      - id: chrome-render-and-docs
        name: Real Chrome render test and docs
        covers: [FR-19, FR-20]
        blocked_by: [modern-template, download-menu]
        scope: ["CLAUDE.md", ".env.example", "Modules/Reports/tests/Feature/ChromeRenderTest.php", "Modules/Supplements/app/Data/SupplementResearchData.php", "Modules/Supplements/app/Contracts/SupplementResearchReader.php"]
        complexity: simple
        risky: false
```

### Task: shared-page-actions

**Check:** a view registered under `supplement.header` appears in the header of `/supplements/{id}`, and an arch test proves Supplements never uses `Modules\Reports`.

**Interfaces**
- Consumes: none.
- Produces:
  - `Modules\Shared\Contracts\PageActions` with `register(string $slot, string $view): void` and `for(string $slot): list<string>` (in registration order, no duplicates).
  - Implementation `Modules\Shared\Services\InMemoryPageActions`, bound as a singleton in `SharedServiceProvider::register()`.
  - The detail page header (inside the `flex items-center gap-3` row, pushed right with `ml-auto`) does `@include($view, ['supplementId' => $supplementId, 'status' => $research?->status])` for each `app(PageActions::class)->for('supplement.header')`.
  - Arch test `Modules/Supplements/tests/Unit/BoundaryTest.php`: `arch()->expect('Modules\Supplements')->not->toUse('Modules\Reports')`.

### Task: clinical-report

**Check:** with research complete, `GET /reports/supplements/{id}.pdf` downloads `<slug>-clinical.pdf` containing all five sections, the disclaimer and page-number footer. Unknown template, unknown id and incomplete research give 404, and the 11th request in a minute gives 429.

**Interfaces**
- Consumes: `Modules\Supplements\Contracts\SupplementResearchReader::forSupplement(int): ?SupplementResearchData` and the Supplements `Data` DTOs and `Enums`.
- Produces:
  - `Modules\Supplements\Testing\FakeSupplementResearchReader`:
    - `static install(): self` binds it as the reader.
    - `with(SupplementResearchData $research): self` stores it by `supplementId`.
    - `forSupplement()` returns the stored DTO or null.
  - The Reports module (generated, registered in `modules_statuses.json`) with `Modules/Reports/config/config.php`:
    - `default_template` from `REPORTS_DEFAULT_TEMPLATE`.
    - `templates.clinical = ['label','format','margins' => [top,right,bottom,left],'orientation']`.
  - `Modules\Reports\Services\TemplateRegistry`:
    - `all(): array<string,array{label:string,format:string,margins:array{int,int,int,int},orientation:string}>`
    - `has(string): bool`
    - `get(string): array`
    - `default(): string`
  - `Modules\Reports\Services\SupplementReportRenderer::render(SupplementResearchData $research, string $template): PdfBuilder`:
    - Sets `view('reports::report', [...])`, `headerView`, `footerView`, `format`, `margins(..., Unit::Millimeter)`, `name(...)` and `download()`.
    - Section partials resolve through `view()->first(["reports::templates.$t.sections.$s", "reports::sections.$s"])`.
  - Route `reports.supplements.pdf` (`/reports/supplements/{supplement}.pdf`, `whereNumber`, `middleware('throttle:reports')`) points to `Modules\Reports\Http\Controllers\SupplementReportController`.
    - The controller returns 404 unless the template is registered, the research exists, and its status is `Complete`.
  - Views: `reports::report`, `reports::sections.{overview,effects,mechanisms,studies,sources}`, `reports::templates.clinical.{layout,header,footer,styles}`.
  - The named rate limiter `reports` is defined in `ReportsServiceProvider::boot()`.
  - Arch test `Modules/Reports/tests/Unit/BoundaryTest.php`: Reports doesn't use `Modules\Supplements\{Models,Repositories,Actions,Jobs,Support,Livewire,Providers}` or `Modules\Intelligence`.

**Test cases**
- Happy: fake reader with a complete research DTO (2 effects, 1 mechanism, 2 studies, 3 sources); `GET ?template=clinical` → `assertRespondedWithPdf` with `isDownload()`, `downloadName === 'magnesium-clinical.pdf'`, view `reports::report`, format a4, and HTML containing the name, generated date, summary, safety notes, claim, outcome, grade, mechanism, study fields, `PMID`, numbered sources, `[1]` markers, disclaimer, and `@pageNumber` markup in the footer HTML.
- Happy: no `template` parameter → the clinical (default) template renders. With `REPORTS_DEFAULT_TEMPLATE` config set to another registered template, that one renders.
- Happy: empty effects, mechanisms or studies show the "No … recorded." fallbacks. A null summary shows "No summary available."
- Unhappy: an unknown id gives 404. Research with status `Analyzing` gives 404. Research `Failed` with an `error` gives 404, and the error text never appears.
- Unhappy: `?template=nope` gives 404 and nothing is responded with a PDF.
- Security: `?template=../../welcome` gives 404 (whitelist, never a view path).
- Security: a source titled `<script>alert(1)</script>` and a claim containing `<img src=http://169.254.169.254/>` appear escaped (`&lt;script&gt;`, `&lt;img`), and the HTML contains no `<script` and no `src="http`.
- Security: a source with URL `javascript:alert(1)` renders its title with no `href`. A study with PMID `12a` renders no PubMed link.
- Security: 10 requests succeed and the 11th from the same IP gets 429.

### Task: modern-template

**Check:** `?template=modern` downloads `<slug>-modern.pdf` in Letter format, with studies rendered as cards (the template override) while clinical keeps the shared table. A test loops every configured template for the disclaimer, header, footer and inlined-CSS rules.

**Interfaces**
- Consumes: `TemplateRegistry`, `SupplementReportRenderer`, and the shared section views from clinical-report.
- Produces:
  - Config `templates.modern`.
  - Views `reports::templates.modern.{layout,header,footer,styles}` and the override `reports::templates.modern.sections.studies` (cards).
  - `Modules/Reports/tests/Feature/TemplatesTest.php` loops over `config('reports.templates')`.

**Test cases**
- Happy: modern → format letter, margins `[20,18,20,18]`, a card-markup marker in studies, and no shared `<table` for studies. Clinical → the shared table.
- Happy: for each configured template: the disclaimer, a header and footer with page-number markup, and a `<style>` block.
- Happy: a test-only config entry `templates.testing`, pointing at a view folder registered in the test, renders with no code changes. This proves a template is a folder plus a config line.
- Security: for each template, the hostile DTO (script title, img claim, `javascript:` URL) renders escaped with no `<script`, `<link`, `src="http` or `url(http` in the body, header or footer HTML.

### Task: download-menu

**Check:** a completed supplement's detail page shows a template select (Clinical and Modern, with the default preselected) and a Download PDF link to `reports.supplements.pdf` with `?template=`. A queued or failed supplement shows neither.

**Interfaces**
- Consumes:
  - `PageActions::register()` from shared-page-actions.
  - `TemplateRegistry::all()`, `default()` and the route `reports.supplements.pdf` from clinical-report.
- Produces:
  - The anonymous view `reports::components.download-menu`, which receives `$supplementId` and `?ResearchStatus $status`. It renders nothing unless the status is `Complete`. It uses an Alpine `x-data` select that rewrites the link's `template` query, with plain `flux:select` and `flux:button` markup, `href` set to the default template, and `target` unset so the browser downloads.
  - `ReportsServiceProvider::boot()` calls `app(PageActions::class)->register('supplement.header', 'reports::components.download-menu')`.

### Task: chrome-render-and-docs

**Check:** `RUN_CHROME_TESTS=1 vendor/bin/pest Modules/Reports/tests/Feature/ChromeRenderTest.php` writes real PDFs that start with `%PDF-` for both templates in under 10 s each. Without the flag the test is skipped. CLAUDE.md, `.env.example` and the DTO docblock are updated.

**Interfaces**
- Consumes: the renderer and both templates.
- Produces:
  - `ChromeRenderTest` (skipped unless `getenv('RUN_CHROME_TESTS') === '1'`) saves each template to a temp path and asserts the `%PDF-` prefix and elapsed time.
  - CLAUDE.md:
    - The module map gets Supplements → Intelligence, Shared and Reports → Supplements, Shared (plus Recommendations and Genetics later).
    - The Shared row mentions `PageActions`.
    - The stale "No module exists yet" line is removed.
    - A short "Adding a report template" section is added (a folder under `Modules/Reports/resources/views/templates/<name>/` plus a config entry, and optional section overrides).
    - The opt-in Chrome test command is documented.
  - `.env.example`: every config env key in use (`AI_RESEARCH_*`, `AI_LOG_REQUESTS`, `RESEARCH_DRIVER`, `FIRECRAWL_*`, `LARAVEL_PDF_CHROME_*` including a commented macOS `LARAVEL_PDF_CHROME_BINARY` example, `REPORTS_DEFAULT_TEMPLATE`, `RUN_CHROME_TESTS`), each commented with its default.
  - A docblock on `SupplementResearchData::$error` and on `SupplementResearchReader`: the failure message is only for Failed runs, is safe to show in the app UI, and is not rendered in reports.
