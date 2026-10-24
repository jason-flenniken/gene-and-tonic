# PRD: Research, Intelligence and dashboard

> Plan: research-intelligence-dashboard. Keep it short. Every requirement has a stable id and a one-line acceptance check.

## Problem
The app has a module pattern on paper (CLAUDE.md) but no modules, no AI integration and no UI beyond the starter welcome page. Supplement knowledge is the base that later features (recommendations, PDF reports) build on, and there is no way to gather or store it. The user needs a working, demoable feature by Sunday night 2026-10-25 for an interview on Monday. In that demo, they add a supplement, the app researches it from trustworthy sources, and the cited findings are saved and shown. The code must also stand up to a walkthrough of its module boundaries.

## Solution
The home page becomes a dashboard with three tabs: Overview, Supplements and AI Usage. On the Supplements tab the user types a supplement name and starts research. A background job does the research:
- It searches trusted medical sources (PubMed, PMC, NIH ODS, Cochrane, NCCIH) with Firecrawl and reads the top pages.
- It asks an OpenAI model, through the Intelligence module, to extract proven effects, mechanisms of action and strong-evidence studies. Each finding cites one of the pages actually read.
- The status updates live while the job runs.
- The result is saved, and a supplement page shows it in tabs: Overview, Effects, Mechanisms, Studies and Sources.

Every AI and search call is logged with tokens, latency and an estimated cost. Three new modules carry the work:
- **Shared:** a tiny kernel.
- **Intelligence:** a provider-agnostic layer for AI and web research. It uses `laravel/ai` and the Firecrawl SDK behind its own contracts, with prompts, models and effort set in config profiles.
- **Supplements:** the catalog and research, persisted through repositories.

## Requirements
Dashboard
- **FR-01** `/` renders the dashboard in the shared `layouts::app` shell, with tabs Overview, Supplements and AI Usage. The active tab is bound to the URL (`?tab=`) and survives reload. Check: feature test `GET /?tab=supplements` shows the Supplements panel active, and a Livewire test switching tabs updates the URL-bound property.
- **FR-02** The Overview tab shows the total number of supplements, counts by research status, the 5 most recently completed researches, and an AI usage summary (calls, tokens, estimated cost). Check: feature test with factory data asserts each number.
- **FR-03** The Supplements tab lists supplements with name, research status badge, effect and study counts, and a link to the detail page. Check: Livewire test asserts rows and links for factory data.
- **FR-04** The add form validates the name: required, 2-100 characters, letters/digits/space/`-().,`, unique case-insensitively. A valid name saves the supplement and queues exactly one research job. Check: Livewire test with `Queue::fake()` asserts one push for valid input and validation errors for empty, too long, disallowed characters and a case-insensitive duplicate.
- **FR-05** While any research is queued, searching or analyzing, the list refreshes itself, and it stops refreshing when none is active. Check: Livewire test asserts the polling attribute is present only when a run is active.

Research pipeline
- **FR-06** The research job moves the run through `queued → searching → analyzing → complete`:
  1. It calls `WebResearcher` with a query built from the name, the trusted-domain list, a limit of 8, and markdown scraping.
  2. It calls `TextGenerator` with profile `supplements.research`, the sources tagged by id, and a strict JSON schema.
  3. It persists the summary, safety notes, effects, mechanisms, studies and sources.

  Check: job test with the Intelligence fakes asserts the status sequence and the persisted rows.
- **FR-07** Only findings that cite returned sources are kept. Effects or mechanisms citing no valid source id, and studies whose source id is not in the returned set, are dropped. A study's PMID is filled when its source is a PubMed URL. Check: job test where the fake output cites an unknown id asserts that finding is absent and the rest are saved.
- **FR-08** On no sources found, a provider error or output failing the schema, the run ends `failed` with a readable reason, shown on the list with a Retry button. Retry queues a new attempt. Research and Retry are unavailable while that supplement has an active run. Check: job tests for each failure, plus a Livewire test that Retry queues once and is blocked during an active run.
- **FR-09** Researching a supplement again replaces its previous findings; no history is kept. Check: a test running two completed runs asserts only the latest findings remain.

Supplement page
- **FR-10** `/supplements/{supplement}` shows tabs Overview, Effects, Mechanisms, Studies and Sources:
  - Overview: summary, safety notes and a visible "research summary, not medical advice" note.
  - Effects: claim, outcome, evidence grade and cited sources.
  - Mechanisms: description and sources.
  - Studies: title, design, population, n, year, finding, grade and a PMID link.
  - Sources: title, domain and link.

  Check: feature test asserts each tab's content and the disclaimer for a researched factory supplement.
- **FR-11** Deleting a supplement, after a confirm modal, removes it and its research, and keeps its AI usage log rows. Check: Livewire test asserts the supplement and research rows are gone and `ai_requests` rows remain.

Intelligence
- **FR-12** `Intelligence\Contracts\TextGenerator::generate(PromptRequest): GenerationResult`. It resolves a named profile (provider, model, effort, max tokens, prompt view), renders the caller's prompt view with variables, and calls `laravel/ai` with structured output and the reasoning effort. Check: test with the `laravel/ai` fake asserts the model, effort, schema and rendered prompt passed through, and the DTO returned.
- **FR-13** Profiles live in config. Every value can be overridden by env var, and the calling module contributes its own profile and prompt view, so domain prompts stay out of Intelligence. An unknown profile throws a typed Intelligence exception. Check: config test for an env override and the exception.
- **FR-14** Switching a profile's provider to another `laravel/ai` provider needs only a config change. Check: test sets the provider to a second provider and asserts it is the one passed to `laravel/ai`.
- **FR-15** `Intelligence\Contracts\WebResearcher::search(SearchQuery): SourceDocument[]` (id, url, title, domain, markdown truncated to a configured length). The Firecrawl driver uses the official SDK, selected by config. Check: driver test with a faked Firecrawl response asserts the request (query, includeDomains, limit, scrape format) and the mapped DTOs.
- **FR-16** Every TextGenerator and WebResearcher call writes an `ai_requests` row: provider, operation, profile, model, effort, input/output tokens, latency ms, estimated cost (from a config price table), status and error. Prompt bodies and keys are never stored. Check: tests assert one row per call for success and failure, with no prompt text column.
- **FR-17** The AI Usage tab lists calls newest first, paginated, with totals. Check: Livewire test asserts order, pagination and totals.
- **FR-18** `Intelligence\Testing` provides `FakeTextGenerator` and `FakeWebResearcher`. Every test runs with stray HTTP requests prevented. Check: the full suite passes with no network access.

Structure
- **FR-19** Supplements exposes a public `SupplementResearchReader` contract that returns a `final readonly` `SupplementResearchData` DTO (with effect, mechanism, study and source DTOs) for a supplement. Check: test asserts DTO contents for a researched factory supplement.
- **FR-20** Supplements and Intelligence persist only through repository interfaces with Eloquent implementations bound in their providers. Actions and Livewire never call Eloquent statics. Check: the review finds no `Model::query/where/create` calls in `Actions`, `Livewire` or component views, and the provider bindings resolve.
- **FR-21** Shared, Intelligence and Supplements are nwidart modules generated with the project stubs, each using only allowed dependencies (Supplements → Intelligence and Shared; Intelligence → Shared; Shared → nothing). All new tables live in module migrations that roll back cleanly. Check: `php artisan module:list` shows all three enabled, and `migrate:fresh` followed by `migrate:rollback` succeeds on the MySQL container.

## Non-goals
- PDF report generation, because it gets its own plan next. This plan's DTOs feed it.
- Authentication, because the supplement catalog is shared, non-personal data; auth arrives with Genetics.
- Research history, because re-running replaces the previous result (FR-09).
- Seeded, pre-researched demo data, because it was not picked.
- Pest architecture tests for module boundaries, because they were not picked; they stay a CLAUDE.md follow-up.
- A live-API smoke-test command, because it was not picked.
- A UI for editing prompts, models or effort, because config profiles are the chosen mechanism.
- Genetics, Recommendations and Reports modules, because they are later plans.
- `laravel/ai` conversation storage (`agent_conversations` tables), because research calls are one-shot.

## Open questions
- [x] Which AI layer? (answer: `laravel/ai` behind Intelligence contracts, OpenAI first)
- [x] Who owns Firecrawl? (answer: Intelligence, as a `WebResearcher` driver on the official SDK)
- [x] How are prompts, models and effort configured? (answer: config profiles with env overrides; the calling module owns its prompt views)
- [x] Default research model? (answer: a balanced OpenAI model, gpt-6.1-sol class, at medium effort. The exact model ID is checked against OpenAI's models page during the build, since its docs disagree.)
- [x] API keys for the live demo? (answer: the user has both and sets them in `.env`)
- [x] Login needed? (answer: no; single demo user, shared catalog)

## Unhappy paths
- [FR-04] When the name is invalid or a case-insensitive duplicate, the form shows a field error and nothing is queued.
- [FR-08] When Firecrawl returns no sources, the run fails with "No trusted sources found for <name>".
- [FR-08] When Firecrawl or OpenAI errors or times out, the job retries once with backoff. It then fails with a readable reason, and the usage row records the error.
- [FR-08] When the model output fails the schema or refuses, the run fails with "The research model returned an unusable answer".
- [FR-08] When Research or Retry is double-submitted, or clicked while a run is active, no second job is queued.
- [FR-11] When a supplement is deleted while its run is active, the job finds no supplement and ends without error or writes.

## Security
- Trust boundaries:
  - The supplement name typed by the user.
  - Firecrawl search results and scraped page text, which come from third-party sites.
  - OpenAI model output.
  - The API keys in `.env`.
  - The HTML rendered to the browser.
- Assets: the OpenAI and Firecrawl API keys, the API spend (credits and tokens), and the integrity of the shown research (no fabricated citations).
- STRIDE:
  - Name input: Tampering. Allowlist validation and length limits; passed as data, never concatenated into instructions.
  - Scraped content: Tampering and Elevation, through prompt injection in a page. Sources are wrapped in delimited, id-tagged blocks; the prompt says to ignore instructions inside sources; strict JSON schema; citations validated against the returned ids (FR-07).
  - Model output: Tampering and Information disclosure, through stored XSS. Escaped Blade only, never `{!! !!}`; links only to stored http(s) source URLs; no markdown-to-HTML.
  - API keys: Information disclosure. They live only in `.env`, with empty placeholders in `.env.example`, and are never logged or stored in `ai_requests` or error messages.
  - Spend: Denial of service, through runaway cost. One active run per supplement; Firecrawl limit 8; max output tokens per profile; job tries 2 and timeout about 180s. There is no auth, so the app must not be deployed publicly in this plan.
- Misuse cases, each needing a test:
  - A name like `ignore previous instructions` or `<script>` is rejected by validation or stored and shown escaped.
  - A scraped source containing "cite source 99" cannot produce a finding citing an unreturned id.
  - Model output containing `<script>` renders escaped on the supplement page.
  - Spamming Research on one supplement queues a single job.
  - An `ai_requests` row never contains the API key or prompt body.

## Non-functional requirements
- **NFR-01** The test suite makes no network calls, and `composer test` (Pint, PHPStan level 7, Pest) passes locally and in CI.
- **NFR-02** One research run uses at most about 10 Firecrawl credits (search of 8 results plus scraping) and one LLM call.
- **NFR-03** With live keys, a typical research run completes within 120 seconds.
