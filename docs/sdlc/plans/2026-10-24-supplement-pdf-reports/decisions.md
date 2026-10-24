# Decisions: Supplement PDF reports

Append-only. Written by `cairn decide` as each decision is made.

### 1. Bring in docs follow-up
**Decision:** Update CLAUDE.md module map (Supplements->Intelligence, Reports->Supplements), list all config env keys in .env.example, document the reader's error field
**Why:** picked by the user from open follow-ups; this plan touches the same docs
_2026-10-24 16:36_

### 2. Why
**Decision:** Show PDF report generation, the capability the interviewing company relies on most, built on the persisted supplement research
**Why:** user: the PDF thing is big, this company relies heavily on PDF gen
_2026-10-24 16:36_

### 3. Why now
**Decision:** Interview Monday 2026-10-26; must be demoable by Sunday night 2026-10-25
**Why:** carried over from the research plan's interview
_2026-10-24 16:36_

### 4. Binding constraint
**Decision:** Time: cut scope, not quality
**Why:** carried over from the research plan's interview
_2026-10-24 16:36_

### 5. Success
**Decision:** From a researched supplement, one click downloads a professional multi-page PDF covering overview, effects, mechanisms, studies and sources; at least two templates can be switched in the UI to show look-and-feel control
**Why:** user picked download plus live template switch
_2026-10-24 16:37_

### 6. New Reports module that reads research only through SupplementResearchReader and its DTOs; CLAUDE.md map lets Reports depend on Supplements
**Decision:** yes
**Why:** module boundary rules; Reports is the read-only PDF consumer
_2026-10-24 16:38_

### 7. Every template carries the not-medical-advice disclaimer, generation date, and numbered citations linking to sources
**Decision:** yes
**Why:** credibility of a health report; matches the detail page
_2026-10-24 16:38_

### 8. One opt-in test launches real Chrome and checks a valid PDF is produced; normal tests use Pdf::fake
**Decision:** yes
**Why:** prove the render path works before the demo
_2026-10-24 16:38_

### 9. Non-goal: HTML preview route for templates
**Decision:** out of scope
**Why:** user did not pick it; time-bound
_2026-10-24 16:38_

### 10. Template structure
**Decision:** Each template = folder (layout, header, footer, styles) + config entry (label, format, margins); five shared section partials, any of which a template can override via view()->first; new template = folder + config line
**Why:** user wants to control both skin and structure without duplicating section markup
_2026-10-24 16:38_

### 11. Template styling
**Decision:** Plain hand-written print CSS per template with CSS variables, inlined; no Tailwind or Vite in PDFs
**Why:** no build dependency, works in tests and CI, readable in the interview
_2026-10-24 16:39_

### 12. Core flow
**Decision:** Completed supplement detail page header gets a template dropdown + Download PDF button; plain GET route /reports/supplements/{id}.pdf?template=x renders synchronously and downloads; nothing stored; button hidden/disabled until research is Complete
**Why:** user picked the simplest demoable flow
_2026-10-24 16:40_

### 13. Users
**Decision:** Demo viewer / interviewer; no login, same as the rest of the app
**Why:** settled in the research plan (auth comes before Genetics)
_2026-10-24 16:40_

### 14. Trust boundaries
**Decision:** Template name whitelisted against config, unknown -> 404; all model/scraped text Blade-escaped, links only via http(s) safeUrl()/pubmedUrl(); no JS and no remote assets in templates (Chrome never fetches network); route throttled 10/min per IP; N/A: personal data, money, deletes, migrations; Chrome binary path from .env only
**Why:** accepted by the user
_2026-10-24 16:40_

### 15. Non-goal: saved report history, queued generation, catalog-row PDF action
**Decision:** out of scope
**Why:** user chose synchronous on-demand download from the detail page
_2026-10-24 16:40_

### 16. Non-goal: user-editable or DB-stored templates
**Decision:** out of scope
**Why:** templates are code (Blade + config); storing executable Blade is a security risk and not needed for the demo
_2026-10-24 16:40_

### 17. UI seam between Supplements page and Reports
**Decision:** Shared gets a small page-action slot registry; Reports registers its template picker + download menu into a 'supplement.header' slot; Supplements renders registered actions and never names Reports
**Why:** keeps Reports -> Supplements one-way, no module cycle; interview talking point
_2026-10-24 16:42_

### 18. Read-back
**Decision:** Outcome: new Reports module; completed supplement detail page gets a template picker + Download PDF button rendering a multi-page PDF via spatie/laravel-pdf (Chrome): title block, Overview (summary, safety notes), Effects with evidence grades, Mechanisms, Studies, numbered Sources, page numbers. Two starter templates: Clinical (serif, A4) and Modern (sans, Letter, overrides Studies section), each a folder + config entry with plain inlined CSS. Shared page-action slot keeps Reports -> Supplements one-way. Docs follow-up included. | User: interviewer and the developer, demo, no login | Why now: interview Monday 2026-10-26, demoable by Sunday night; PDF generation is the company's core capability | Success: one click gives a professional PDF of all five sections; switching template changes the look with no code change; one opt-in real-Chrome render test | Constraint: time, cut scope not quality | Out of scope: saved/queued reports, catalog-row action, HTML preview, user-editable or DB templates, Tailwind in PDFs
**Why:** confirmed by the user
_2026-10-24 16:42_
