# Record: Research Intelligence Dashboard

## Links
- PRD: docs/sdlc/plans/2026-10-24-research-intelligence-dashboard/prd.md
- Plan: docs/sdlc/plans/2026-10-24-research-intelligence-dashboard/plan.md
- PR: <pending>
- Tracker: none

## Why
Supplement research is the knowledge base the later Recommendations and PDF report features need. Building it first also proves the module pattern and the AI pipeline on data that isn't sensitive, in time to demo at an interview on Monday 2026-10-26. This plan ran at the Standard level, and time was the binding constraint: scope was cut before quality.

## What was built
- Dashboard: three tabs (Overview, Supplements, AI Usage). Users add a supplement and see its research status; AI Usage shows each call with tokens and estimated cost.
- Supplement detail: a page with five tabs showing the persisted, citation-validated findings.
- Research pipeline: adding a supplement queues a job that searches trusted medical domains via Firecrawl, then makes one structured OpenAI call and saves findings.
- Modules: Shared, Intelligence and Supplements (nwidart), each with its own config, routes, tests and migrations.
- Next plan: PDF generation. The `SupplementResearchReader` contract is ready for it.

## How it works
`Supplements/app/Jobs/RunSupplementResearch.php` runs the flow. It claims the run atomically, calls the `WebResearcher` contract (Firecrawl through Laravel's Http client), then the `TextGenerator` contract (OpenAI through laravel/ai). Both contracts live in `Modules/Intelligence/app/Contracts`. Prompt, model and effort come from config profiles via `ProfileRegistry`. Findings whose citations don't validate are dropped. Valid ones are saved through repositories in `Modules/Supplements/app/Repositories`. Each call is logged with tokens and cost.

## Findings
- Whole-branch review found duplicate paid job runs: the queue `retry_after` (90s) was shorter than job runtime. Fixed with `retry_after` 240, timeout 200 and a `WithoutOverlapping` lock.
- Live runs recorded $0 cost because pricing keyed on OpenAI's dated model id. Now priced by the configured alias.
- The re-check found the overlap lock TTL equal to `retry_after` could strand a timed-out run. You chose to fix by hand: lock is now `retry_after` minus 30s, with a test.
- Build-time security fixes: atomic run claim (double-click race), Firecrawl URL check bypass via backslash/userinfo closed by URL normalisation, search results capped at 8.
- Changed mid-plan: Firecrawl uses the Http client, not its SDK (the SDK forced Guzzle 8 to 7 and bypassed `Http::fake`). Boost's AGENTS.md and boost.json are committed. PHPStan uses a per-checkout tmpDir (a shared cache broke parallel worktrees) and Larastan `configDirectories`.
- Accepted out-of-scope edits: `phpstan.neon`, model `@property` docblocks.
- Verify: 129 tests pass (126 before review fixes), Pint and PHPStan level 7 clean, production build ok, 21 of 21 requirements met (the FR-13 timeout env gap was fixed during verify). Secret scan clean; composer and npm audit report 0 vulnerabilities.
- The live-run time limit (120s) was not verified: no API keys were available.
- Time: discover 43m, PRD 5m, plan 15m, build 29m, verify 3m, review 18m, ship 1m.

## Follow-ups
- Before the live demo: put `OPENAI_API_KEY` and `FIRECRAWL_API_KEY` in the main checkout `.env`, run `php artisan migrate`, and keep `composer run dev` running (queue worker).
- No findings were accepted as is.
- 5 open recommended follow-ups (1 security, 1 tests, 1 ux, 1 docs, 1 quality): run /cairn:suggestions. They include rate limits, a stale-run reaper, URL/PMID validation hardening, architecture tests, a live smoke test, detail-page polling, Firecrawl timeouts, a CLAUDE.md module map and `.env.example` keys.
- Dropped as done during the plan (4): ArrayableData ignore removal, module tests, WebResearcher logging reuse, Larastan configDirectories.
- Dismissed as trivial (5): race-test comment, Blade import order, nullable locals, inline @php, redundant assertion.
- No known failing test suites.
