# Record: Supplement PDF reports

## Links
- PRD: docs/sdlc/plans/2026-10-24-supplement-pdf-reports/prd.md
- Plan: docs/sdlc/plans/2026-10-24-supplement-pdf-reports/plan.md
- PR: none (repo has no remote); merged locally into main
- Tracker: none

## Why
Supplement research was saved and shown on the detail page, but could not be taken away as a document. The PDF report is the product's main deliverable and the Monday demo needs it, with proof that the look is set by templates rather than hard-coded markup. Level: Standard.

## What was built
- First PDF end to end: a supplement page can now host actions from other modules through a Shared page-action slot. A new Reports module serves `/reports/supplements/{id}.pdf` and downloads a multi-page Clinical report (A4, serif): title block, overview, effects with evidence grades, mechanisms, studies with PubMed links, numbered sources, disclaimer and page numbers. Unknown templates and missing or incomplete research return 404, and the route is rate-limited.
- Second template, download button, docs: a completed supplement's page now has a template picker and a Download PDF button. Modern (Letter, sans, study cards) ships beside Clinical. An opt-in real Chrome render test was added. CLAUDE.md, `.env.example` and the research DTO docblocks were updated.

## How it works
The controller (`Modules/Reports/app/Http/Controllers`) checks the template against `reports.templates` config, reads research through the Supplements `SupplementResearchReader` contract, and hands the DTO to `SupplementReportRenderer`. The renderer builds a spatie PDF with the template's layout, header, footer, paper size and margins. Each template is a folder under `Modules/Reports/resources/views/templates/<name>` plus a config entry. Each template's layout picks section views with `@includeFirst`, template override first, then the shared `sections` partials. The button reaches the Supplements page through `PageActions` (`Modules/Shared/app/Contracts/PageActions.php`), registered in `ReportsServiceProvider`, so Supplements never names Reports.

## Findings
- Verify: 171 tests pass, 2 opt-in Chrome tests skipped by default. A real Chrome render ran once: Clinical and Modern each came out at 5 pages in 0.6-0.9 s. Both PDFs were opened and checked for layout, citations, PubMed links and page numbers. Build ok, secret scan clean, 0 audit vulnerabilities.
- 21 of 22 requirements fully met. The select-change behavior of the download menu is met by markup only; the select change and full-page integration are untested.
- Whole-branch review fixed two Required items in place. Reports test files only passed in suite order, so helpers moved to an autoloaded `ReportFixtures` class. The Modern title block lacked "Supplement research report", and its test passed only because of the hidden `<title>`; the text is fixed and the test now checks the visible body. Security review: PASS, nothing blocking.
- Mid-plan: the download-menu task changed the Shared binding test to use a private slot name, because the real menu now fills `supplement.header` at boot. Approved out-of-scope edits touched the Supplements page test, its header-action fixture view and that Shared test.
- The plan checkout needed `composer dump-autoload` after the Reports merge, the same autoload issue as the previous plan.
- Time: discover 9m, PRD 1m, plan 3m, build 23m, verify 1m, review 5m, ship under 1m.

## Follow-ups
- Before the demo: after merging to main, run `composer dump-autoload` in the main checkout and restart Herd's dev process. Otherwise the app returns a 500 with "ReportsServiceProvider not found".
- Each section starts on a new page, so short research gives sparse PDFs (logged as a ux follow-up).
- Dropped: 3 dismissed (test helpers fixed in review; the standalone templates test fixed in review; the Supplements-to-Reports arch check is now live). 24 others were merged into the open ones.
- 5 open recommended follow-ups (1 tests, 1 security, 2 quality, 1 ux): run /cairn:suggestions.
