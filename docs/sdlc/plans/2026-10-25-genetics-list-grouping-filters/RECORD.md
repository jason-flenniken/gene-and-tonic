# Record: Genetics list grouping and filters

## Links
- PRD: docs/sdlc/plans/2026-10-25-genetics-list-grouping-filters/prd.md
- Plan: docs/sdlc/plans/2026-10-25-genetics-list-grouping-filters/plan.md
- PR: none (repo has no remote); merged locally into main
- Tracker: none

## Why
The Genetics tab showed a signed-in user's 73 variants as one flat table. The 38 free-text categories were mostly one-offs, so related genes were hard to find, and the "Your genotype" column was empty on almost every row. The goal was a list that is easy to scan before the demo. This was a Standard-level plan.

## What was built
- Groups in the data: every variant now belongs to one of ten broad groups (Methylation, Brain & mood, and so on). Seeded rows are mapped by hand, and new lookups pick a group from the fixed list. Anything outside the list is stored as Other.
- Grouped list and backfill: the list shows collapsed sections with a name and row count. Rows not yet looked up sit in a "Not looked up yet" section at the bottom, still with their Look up buttons. A group dropdown and a search box sit above the list. Search covers gene, rsID, group, category, Affects and genotype effect, never the genotype itself, and opens the sections that match. The "Your genotype" column is gone. A genotype effect now shows as a highlighted line under Affects. A new command, `genetics:group-variants`, assigns groups to existing looked-up rows in one AI call.

## How it works
A `VariantGroup` enum in `Modules/Genetics/app/Enums/VariantGroup.php` defines the ordered groups. It is stored in a new nullable `gene_variants.category_group` column (add-only migration). The lookup (`Modules/Genetics/app/Actions/LookupVariant.php`) returns a group, and invalid answers become Other. The backfill (`Modules/Genetics/app/Console/GroupVariantsCommand.php`) sends only gene symbols and category text, and applies only answers matching a sent row. The list lives in `Modules/Genetics/resources/views/livewire/⚡profile.blade.php` and renders through `Modules/Genetics/resources/views/components/variant-list.blade.php`. Filtering runs in memory over the user's own rows. Which sections are open is kept on the server.

## Findings
- Sections the user opened collapsed on every 3-second poll, because Livewire's re-render removes client-only `open` attributes. Fixed by keeping open state on the server (locked `openSections`, toggled by `toggleSection`).
- Branch review found that headers could not be closed while a filter or search was active. Fixed by syncing open sections when the filter changes.
- Browser check on a temporary local site: two opened sections stayed open across 3 polls, and a header closed under an active search stayed closed.
- One out-of-scope edit: the public-property list in `UploadPageTest` was updated for the new properties.
- Final checks: 567 tests passed, 3 skipped. Pint and PHPStan clean. Secret scan clean. Composer and npm audits clean.
- Time: discover 13m, PRD 2m, plan 3m, build 9m, check 5m, review 9m, ship under 1m.

## Follow-ups
- After merge, run `php artisan genetics:group-variants` once on main (one AI call) so the roughly 21 looked-up rows leave "Other". The migration has already run on local MySQL, and seeded rows were regrouped through `GeneVariantSeeder`.
- Accepted findings: none.
- 5 open recommended follow-ups (2 ux, 1 tests, 1 security, 1 quality): run /cairn:suggestions
  - Better genotype-effect sources in lookups (SNPedia/ClinVar); only 1 of 21 lookups filled one.
  - Deeper variant-group tests, including a browser test for open sections across polling.
  - Harden the group backfill: chunking and a per-run cap, whitespace collapse.
  - List polish: expand/collapse all, stale filter fallback, auto-open new matches under search.
  - Variant groups tidy (quality).
- Dropped: one follow-up resolved (search tests for rsID, category, effect and clear-both were added during verify). 16 were merged into the grouped items above.
