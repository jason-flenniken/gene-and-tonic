# PRD: Supplement PDF reports

> Plan: supplement-pdf-reports. Keep it short. Every requirement has a stable id and a one-line acceptance check.

## Problem
Supplement research is persisted and shown on the detail page, but there is no way to take it away as a document. PDF generation is the capability the interviewing company relies on most, and the demo on Monday 2026-10-26 needs to show it. The demo also needs to show that the report's look and feel is controlled by templates, not hard-coded markup.

## Solution
The detail page of a supplement with completed research gets a template picker and a **Download PDF** button. One click downloads a multi-page report:
- A title block.
- Overview: summary and safety notes.
- Effects, with evidence grades.
- Mechanisms.
- Studies.
- Numbered sources.
- A disclaimer and page numbers.

There are two starter templates, **Clinical** (serif, A4) and **Modern** (sans, Letter). Switching between them changes the look with no code change. Each template is a folder of Blade views plus one config entry. It shares the five section partials and can override any of them.

All of this lives in a new **Reports** module. It reads research only through the Supplements `SupplementResearchReader` contract and DTOs, and it attaches its button to the Supplements page through a small page-action slot in Shared, so Supplements never names Reports.

## Requirements
- **FR-01** A Reports module exists (nwidart, generated with the project's stubs). It uses only Supplements' public `Contracts`, `Data` and `Enums` namespaces, plus Shared. Check: a Pest arch test fails if `Modules\Reports` uses any other `Modules\Supplements\*` namespace or any Intelligence namespace.
- **FR-02** `GET /reports/supplements/{supplement}.pdf?template=<name>` (named `reports.supplements.pdf`, numeric id) renders the report with the chosen template and returns it as a download named `<supplement-name-slug>-<template>.pdf`. Check: with `Pdf::fake()`, `assertRespondedWithPdf` sees `isDownload()`, the expected `downloadName` and the template's view.
- **FR-03** When `template` is omitted, the default template from config is used. Check: the request without the parameter renders the default template's view.
- **FR-04** An unknown `template` value returns 404. Check: `?template=nope` gives 404, and no PDF is generated (`Pdf::assertNotQueued`, nothing responded).
- **FR-05** A missing supplement, a supplement with no research, or research that isn't `Complete` returns 404. Check: one feature test per case, each a 404.
- **FR-06** The report opens with a title block (supplement name, "Supplement research report", generated date, template label), followed by Overview (summary and safety notes). Overview uses the same fallbacks as the detail page: "No summary available." and "No safety notes recorded." Check: `assertSee` on the name, date, both texts and both fallbacks.
- **FR-07** Effects lists each effect's claim, its outcome when present, its evidence grade, and citation markers `[n]` that refer to the numbered source list. Check: `assertSee` claim, outcome, grade and the `[n]` for its source key. An empty list shows "No effects recorded."
- **FR-08** Mechanisms lists each description with its citation markers. Check: `assertSee` description and marker. An empty list shows "No mechanisms recorded."
- **FR-09** Studies shows title, design, population, sample size, year, finding, grade, and a PubMed link built only from `StudyData::pubmedUrl()`. Check: `assertSee` each field and the `pubmed.ncbi.nlm.nih.gov/<pmid>` link. An invalid PMID produces no link.
- **FR-10** Sources is a numbered list (the numbering matches the `[n]` markers) with title, domain, and a link only when `SourceData::safeUrl()` is non-null. Check: numbering and link present for an http(s) source. No `href` for a `javascript:` URL.
- **FR-11** Every template shows the not-medical-advice disclaimer, in the same wording as the detail page. Check: a test iterates all configured templates and asserts the disclaimer in each.
- **FR-12** Every template has a header and a footer, with "Page X of Y" in the footer from `@pageNumber` / `@totalPages`, with explicit font sizes and padding. Check: the builder for each template has header and footer HTML, and the footer contains the page-number markup.
- **FR-13** Templates are registered in Reports config (`reports.templates`). Each entry has a `label`, paper `format`, `margins` and `orientation`, plus `reports.default_template` (env `REPORTS_DEFAULT_TEMPLATE`). Two ship: `clinical` (A4, serif) and `modern` (Letter, sans). The builder applies each template's format and margins. Check: for each template, the faked builder shows the configured format and margins, and adding a test-only config entry with a view folder makes it renderable without code changes.
- **FR-14** A section view resolves template-first: `reports::templates.<t>.sections.<section>`, then `reports::sections.<section>`. `modern` overrides Studies (cards instead of a table); `clinical` uses the shared table. Check: the modern output contains the card markup for studies, and the clinical output contains the shared table.
- **FR-15** Each template inlines its own plain CSS (CSS variables, print rules, page breaks between sections). The rendered HTML has no `<script>`, no `<link>`, and no remote `src` or `url(...)`. Check: for each template, the HTML contains a `<style>` block and none of `<script`, `<link`, `src="http` or `url(http`.
- **FR-16** Shared provides a page-action slot registry (register a view under a slot name, list the views for a slot). The Supplements detail page renders every view registered for `supplement.header`, passing the supplement id and research status. Check: a unit test of the registry, a page test with a dummy registered view, and an arch test that `Modules\Supplements` never uses `Modules\Reports`.
- **FR-17** Reports registers a download menu in `supplement.header`: a template select (labels from config, default preselected) and a Download PDF link pointing at the route from FR-02 for the selected template. It renders only when research is `Complete`. Check: a page test shows the select and a link with `?template=` on a completed supplement, and neither on a queued or failed one. Changing the select updates the link's template.
- **FR-18** The PDF route is rate-limited to 10 requests per minute per IP. Check: the 11th request within a minute returns 429.
- **FR-19** One opt-in test renders a real PDF through the Chrome driver for each template. It is skipped unless `RUN_CHROME_TESTS=1`. Check: with the flag on this Mac, the output bytes start with `%PDF-` for both templates. Without the flag, the test is skipped and `composer test` stays green.
- **FR-20** Docs:
  - The CLAUDE.md module map is updated: Supplements → Intelligence, Shared; Reports → Supplements, Shared (Recommendations and Genetics later). The page-action slot is described, "No module exists yet" is removed, and there is a short "Adding a report template" section.
  - `.env.example` lists every config env key (`AI_RESEARCH_*`, `FIRECRAWL_*`, `RESEARCH_DRIVER`, `AI_LOG_REQUESTS`, `LARAVEL_PDF_CHROME_*`, `REPORTS_DEFAULT_TEMPLATE`).
  - The `SupplementResearchData::$error` field is documented.

  Check: review the diff of CLAUDE.md, `.env.example` and the contract or DTO docblock.

## Non-goals
- Saved report history, or queued generation with notifications: the demo needs one-click download, and storage adds scope.
- A PDF action on catalog rows: the detail page is the one entry point.
- An HTML preview route: not picked, because time is the constraint.
- User-editable or database-stored templates: templates are code, and storing executable Blade is a security risk.
- Tailwind or Vite in PDFs: plain inlined CSS needs no build and works in tests and CI.
- PDF metadata (`meta()`), tagged PDFs and outlines: the Chrome driver doesn't support them.
- Genetics or Recommendations content in reports: those modules don't exist yet.

## Open questions
- [x] How do templates control look and feel? (answer: a folder of layout, header, footer and styles plus a config entry; shared section partials that each template can override)
- [x] Styling approach? (answer: plain hand-written print CSS per template, inlined; no Tailwind)
- [x] How does the button reach the Supplements page without a module cycle? (answer: Shared page-action slot registry)
- [x] Sync or queued? (answer: sync on-demand download, nothing stored)

## Unhappy paths
- [FR-04] When the template name is unknown, the response is 404 and Chrome is never launched.
- [FR-05] When research is missing or not complete, the response is 404. The button is not shown (FR-17), so this happens only via a crafted URL.
- [FR-02] When Chrome is missing or fails to start, the exception is reported and the user gets Laravel's standard 500 page, with no stack trace or binary path in production. `.env.example` documents `LARAVEL_PDF_CHROME_BINARY`.
- [FR-18] When the rate limit is exceeded, the response is 429.

## Security
- Trust boundaries:
  - The request: the `supplement` id and the `template` query value.
  - Persisted model output and scraped text: summary, claims, study fields, source titles and URLs, which originally came from OpenAI and Firecrawl.
  - The Chrome process that renders the HTML.
- Assets: the server (Chrome runs locally with network access), availability (each request launches Chrome), and the integrity of a health document.
- STRIDE:
  - Request (T, D, E): the template is whitelisted against config, so it can never become an arbitrary view path. The id is numeric. The route is throttled to 10 per minute per IP. There's no auth, consistent with the app.
  - Model and scraped text into HTML (T, I, E): everything is output through Blade `{{ }}`. Links come only from `safeUrl()` / `pubmedUrl()` (http(s) and numeric PMID). There is no `{!! !!}` on data.
  - Chrome render (I, D): templates contain no JavaScript and no remote assets, so injected markup can't make Chrome fetch internal URLs (SSRF) or run code. The binary path comes only from `.env`. The package timeouts bound the render.
- Misuse cases, each a test:
  - `?template=../../welcome` or any unlisted name gives 404 (FR-04).
  - A source titled `<script>…</script>` or `<img src=http://169.254.169.254>` renders escaped (FR-15).
  - A `javascript:` source URL renders with no link (FR-10).
  - Hammering the route gives 429 (FR-18).

## Non-functional requirements
- **NFR-01** A report for a fully researched supplement renders in under 10 seconds locally with the Chrome driver (measured once with the opt-in test).
- **NFR-02** Normal `composer test` never launches Chrome or needs `npm run build` for the Reports tests (`Pdf::fake()`).
