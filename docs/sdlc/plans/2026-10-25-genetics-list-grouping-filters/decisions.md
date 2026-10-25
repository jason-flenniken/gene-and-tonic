# Decisions: Genetics list grouping and filters

Append-only. Written by `cairn decide` as each decision is made.

### 1. Why
**Decision:** The genetics list is a long flat table (73 rows, 38 categories) that is hard to scan
**Why:** user wants genes grouped by category with collapse, plus filter and search
_2026-10-25 14:36_

### 2. Why now / binding constraint
**Decision:** Demo on 2026-10-26; build must land tonight
**Why:** interview demo deadline; cut scope, not quality
_2026-10-25 14:36_

### 3. Data scope
**Decision:** Filter and search run in memory on the user's variant list, not SQL
**Why:** genotype is encrypted and one user has 73 rows; no pagination today
_2026-10-25 14:36_

### 4. Your genotype column
**Decision:** Drop the column; show the genotype effect as a highlighted line under Affects when one exists
**Why:** column is empty for most rows; nothing lost
_2026-10-25 14:39_

### 5. Grouping
**Decision:** About 10 broad fixed groups; seeded rows mapped by hand, lookup picks from the list, one-off backfill for existing looked-up rows; the specific category stays as a row label
**Why:** 38 free-text categories, ~25 singletons, would make useless groups
_2026-10-25 14:39_

### 6. Unlooked-up rows
**Decision:** One 'Not looked up yet' group at the bottom holding pending and failed rows with their Look up buttons
**Why:** keeps unknowns together, out of the way
_2026-10-25 14:39_

### 7. Default collapse state
**Decision:** All groups collapsed on load; headers show the group name and count
**Why:** user choice: scan groups first, open what matters
_2026-10-25 14:39_

### 8. Search scope
**Decision:** Matches gene, rsID, broad group, specific category, Affects and the genotype-effect line; matching groups open while searching, clearing returns to collapsed
**Why:** user picked broad search
_2026-10-25 14:39_

### 9. Backfill
**Decision:** One artisan command, one batched AI call with gene symbol and category text only, answers validated against the fixed list, fallback Other
**Why:** cheap, runs once, no user data sent
_2026-10-25 14:39_

### 10. Dropdown
**Decision:** All, each broad group that has rows, and Not looked up yet; empty groups hidden
**Why:** user choice
_2026-10-25 14:39_

### 11. Trust boundaries
**Decision:** Search text is untrusted: length-capped, matched in memory, Blade-escaped, no SQL. List stays scoped to auth()->id(). Genotypes never logged or sent to the model; backfill and lookup send gene and category only. Model's group answer validated against the fixed list. Migration is add-only (nullable group column on gene_variants). No shell, deletes, money or public API.
**Why:** checked each boundary
_2026-10-25 14:39_

### 12. Non-goal: better genotype-effect sources
**Decision:** out of scope
**Why:** pointing lookups at SNPedia/ClinVar is a separate change; logged as follow-up
_2026-10-25 14:39_

### 13. Non-goal: Look up all, pagination, saving filter state across reloads
**Decision:** out of scope
**Why:** 73 rows, demo deadline
_2026-10-25 14:39_

### 14. Read-back
**Decision:** Outcome: Genetics tab list grouped into ~10 broad groups, collapsed by default with counts, 'Not looked up yet' group last; filter bar with group dropdown (All, each group, Not looked up yet) and search over gene, rsID, group, category, Affects, genotype effect; 'Your genotype' folds under Affects; lookup picks a group from the fixed list; one batched AI call backfills existing rows. User: signed-in user viewing own variants (demo user, 73 rows). Why now: demo 2026-10-26, flat table hard to scan. Success: scan group headers; search COMT or pick Methylation shows only matches with groups opened; pending rows together at bottom with Look up. Constraint: lands tonight; genotypes never logged or sent to the model. Out of scope: better genotype-effect sources (follow-up), Look up all, pagination, saving filter state across reloads.
**Why:** confirmed by the user
_2026-10-25 14:47_

### 15. Non-goal: PDF report genetics layout
**Decision:** out of scope
**Why:** user asked for the dashboard list UI; the PDF section stays as is
_2026-10-25 14:48_
