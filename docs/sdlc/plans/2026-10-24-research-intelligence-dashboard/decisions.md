# Decisions: Research, Intelligence and dashboard

Append-only. Written by `cairn decide` as each decision is made.

### 1. Why build supplement research first?
**Decision:** Mostly foundation: it is the knowledge base Recommendations and the PDF report need, and it proves the module pattern and AI pipeline on non-sensitive data. Partly a showcase: built for the user and to show what they can build quickly in an interview on Monday 2026-10-26.
**Why:** user: 'mostly 1 but a little bit of 2 ... a showcase of what I can quickly build for an interview on monday'
_2026-10-24 14:51_

### 2. Why now?
**Decision:** An interview on Monday 2026-10-26; the feature must be demoable by then.
**Why:** user named the Monday interview as part of the reason
_2026-10-24 14:51_

### 3. What does success look like?
**Decision:** Live demo plus code walkthrough: add a supplement, watch the research run, and see cited proven effects, mechanisms of action and strong-evidence studies saved and shown in a clean tabbed view. The code walks through cleanly: modules, contracts, repositories, configurable prompts/models/effort, green CI.
**Why:** user picked the demo + walkthrough option
_2026-10-24 14:52_

### 4. Non-goal: PDF report generation in this plan
**Decision:** out of scope
**Why:** user: PDF generation is big for the interviewing company but will be built in a separate, later plan; this plan's data model should feed it cleanly
_2026-10-24 14:52_

### 5. Binding constraint?
**Decision:** Time: a working, polished demo by Sunday night 2026-10-25. Cut scope to protect that; whatever ships still follows the module rules and has tests.
**Why:** user picked time; interview is Monday
_2026-10-24 14:52_

### 6. Research runs async as a queued job with live status in the UI (queued, searching, analyzing, done, failed)
**Decision:** yes
**Why:** research takes 30-90s; the demo must show progress rather than a hung request
_2026-10-24 14:53_

### 7. Every saved finding (effect, mechanism, study) carries its source URL, PMID when available, and an evidence grade
**Decision:** yes
**Why:** the research must be trustworthy and cited, focused on proven effects and strong evidence
_2026-10-24 14:53_

### 8. Each AI call logs provider, model, effort, tokens, latency and estimated cost, surfaced in the dashboard
**Decision:** yes
**Why:** walkthrough material showing the Intelligence module's design
_2026-10-24 14:53_

### 9. Create the Shared kernel module now (typed IDs, base DTO helpers)
**Decision:** yes
**Why:** the first two modules need common building blocks; CLAUDE.md's map lists Shared first
_2026-10-24 14:53_

### 10. Non-goal: re-running research keeps a history of earlier runs
**Decision:** out of scope
**Why:** not picked; re-running research replaces the previous result
_2026-10-24 14:53_

### 11. Non-goal: seeded, pre-researched demo data
**Decision:** out of scope
**Why:** not picked
_2026-10-24 14:53_

### 12. Non-goal: Pest architecture tests enforcing module boundaries in this plan
**Decision:** out of scope
**Why:** not picked; stays a CLAUDE.md follow-up
_2026-10-24 14:53_

### 13. Non-goal: a live-API smoke-test artisan command
**Decision:** out of scope
**Why:** not picked
_2026-10-24 14:53_

### 14. Who are the users; is login needed?
**Decision:** A single demo user, no authentication in this plan. Supplements and their research are a shared catalog, not per-user data. Auth comes with the Genetics plan.
**Why:** user picked no login; supplement research isn't personal data
_2026-10-24 14:54_

### 15. What does the Intelligence provider layer build on?
**Decision:** laravel/ai (official Laravel AI SDK, v1.3) wrapped behind Intelligence's own contract (e.g. TextGenerator returning DTOs). OpenAI is the first provider; others are a config change. Other modules see only Intelligence contracts/DTOs; tests bind an Intelligence fake.
**Why:** user picked it: less code before Monday, first-party and defensible in an interview, built-in structured output, effort and fakes
_2026-10-24 14:55_

### 16. Which module owns Firecrawl?
**Decision:** Intelligence. It exposes a WebResearcher contract (search + scrape returning SourceDocument DTOs) with a Firecrawl driver on the official firecrawl-sdk, alongside TextGenerator. Supplements supplies the query and trusted domains, then persists through its own repository.
**Why:** user picked it: all external research providers behind one module with the same driver pattern and usage logging
_2026-10-24 14:56_

### 17. How are prompts, models and effort configured?
**Decision:** Named profiles in config, each with provider, model, effort, max tokens and a prompt template, every value overridable by env var. The calling module owns its prompt text as Blade/markdown views in its own resources (domain knowledge stays out of Intelligence). Default for research: OpenAI, a balanced model (gpt-6.1-sol class), medium effort. Versioned in git; no UI editor in this plan.
**Why:** user picked config profiles + prompt files
_2026-10-24 14:56_

### 18. Core flow and research pipeline
**Decision:** Add a supplement by name on the dashboard's Supplements tab -> saved and a ResearchSupplement job queued -> status shown live (queued, searching, analyzing, complete, failed). Job: Firecrawl search restricted to trusted domains (PubMed, PMC, NIH ODS, Cochrane, NCCIH), top ~8 results scraped as markdown -> one structured-output LLM call (profile supplements.research) that returns summary, proven effects with outcome/evidence grade/source ids, mechanisms with source ids, strong-evidence studies (title, design, population, n, year, finding, grade, source id, PMID if any) and safety notes -> findings citing unknown source ids are dropped -> persisted. The supplement page has tabs: Overview, Effects, Mechanisms, Studies, Sources.
**Why:** user picked search -> scrape -> synthesize; the LLM may cite only sources Firecrawl returned, so citations can't be invented
_2026-10-24 14:58_

### 19. Home dashboard and tabs
**Decision:** The home route becomes a dashboard with 3 tabs: Overview (counts, recent research, AI cost summary), Supplements (list, add form, live status, retry on failure), AI Usage (per-call log). Tabs are built in-house from free Flux pieces with the active tab bound to the URL; the same tab component is reused on the supplement detail page (Overview, Effects, Mechanisms, Studies, Sources). No Flux Pro.
**Why:** user picked 3 tabs built in-house; free Flux has no tabs component
_2026-10-24 14:59_

### 20. Repository pattern strictness
**Decision:** Every module persists through repository interfaces with Eloquent implementations bound in its provider. Repositories are private and return Eloquent models within the module; Actions and Livewire depend on repository interfaces, never Eloquent statics. Only public Contracts return final readonly DTOs. Supplements exposes a public SupplementResearchReader contract now, ready for the PDF plan.
**Why:** user picked interfaces + Eloquent with models inside, plus the public reader
_2026-10-24 14:59_

### 21. Trust boundaries
**Decision:** Untrusted input: supplement name required, 2-100 chars, letters/digits/space/-()., unique case-insensitive, passed to Firecrawl/LLM only as data. Scraped content is untrusted: wrapped in delimited source blocks, prompt says to ignore instructions inside sources, strict JSON schema, findings citing source ids outside the returned set are dropped. Model output: escaped Blade only (never {!! !!}), links only to stored http(s) source URLs, no markdown-to-HTML. Auth: none (local demo, not publicly deployed). Personal data: none. Spend: one active run per supplement, Firecrawl limit 8, max output tokens per profile, job tries=2 with backoff and ~180s timeout, failures stored with a readable reason and a Retry button. Secrets: OPENAI_API_KEY and FIRECRAWL_API_KEY only in .env (empty in .env.example), never logged. Medical: visible 'research summary, not medical advice' note. Migrations: new module-owned tables only, reversible. Shell commands: none.
**Why:** user accepted the proposed rules
_2026-10-24 15:00_

### 22. Can the user delete a supplement?
**Decision:** Yes, behind a Flux confirm modal; removes the supplement and its research; AI usage log rows are kept so cost history survives.
**Why:** user picked deletes with confirm
_2026-10-24 15:00_

### 23. Read-back
**Decision:** Outcome: Shared, Intelligence and Supplements modules plus a 3-tab home dashboard. Adding a supplement queues a job that searches trusted sources with Firecrawl, then one OpenAI call (laravel/ai behind Intelligence's contracts, with config-driven prompt/model/effort profiles) extracts cited effects, mechanisms and strong-evidence studies. Results are saved through repositories and shown live in tabs; every AI call's usage and cost is logged. | User: the user as single demo user; no login; shared supplement catalog. | Why now: interview Monday 2026-10-26; proves the module pattern and AI pipeline; it is the knowledge base for the PDF reports. | Success: live demo (add, watch status, cited findings in tabs) plus a clean code walkthrough (boundaries, contracts, repositories, config profiles, green CI). | Constraint: demoable by Sunday night 2026-10-25; cut scope, not quality. | Out of scope: PDF reports (next plan), auth, research history, seeded demo data, Pest arch tests, live-API smoke command, prompt editor UI, Genetics, Recommendations, Reports.
**Why:** confirmed by the user
_2026-10-24 15:00_

### 24. API keys for the live demo
**Decision:** The user has both OpenAI and Firecrawl keys and will set OPENAI_API_KEY and FIRECRAWL_API_KEY in .env before the live check; build and tests use fakes.
**Why:** user has both
_2026-10-24 15:01_

### 25. Firecrawl client: official SDK or Laravel Http client?
**Decision:** Laravel Http client. The Intelligence Firecrawl driver posts to Firecrawl's v2 /search REST endpoint with Laravel's Http client; no SDK dependency. This supersedes the 'official firecrawl-sdk' part of the Firecrawl ownership decision.
**Why:** user picked it: the SDK forces Guzzle 8 down to 7, bypasses Http::fake and stray-request prevention, and silently falls back to a keyless tier on a missing key
_2026-10-24 15:11_

### 26. Commit Boost's AGENTS.md and boost.json?
**Decision:** Yes: un-ignore and commit them in the first task, so task checkouts get the guidelines CLAUDE.md imports and composer post-update scripts run cleanly.
**Why:** user picked commit; task checkouts were missing them
_2026-10-24 15:11_
