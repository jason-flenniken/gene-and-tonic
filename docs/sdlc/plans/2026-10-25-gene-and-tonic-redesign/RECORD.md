# Record: Gene & Tonic redesign

## Links
- PRD: docs/sdlc/plans/2026-10-25-gene-and-tonic-redesign/prd.md
- Plan: docs/sdlc/plans/2026-10-25-gene-and-tonic-redesign/plan.md
- PR: none (no git remote; merged locally into main)
- Tracker: none

## Why
The app was a generic Flux dashboard with everything on one page behind `?tab=` links. The approved Gene & Tonic design existed only as mocks, and some of what it shows had no data behind it. This Standard-level plan rebuilds the UI to that design and adds the missing data.

## What was built
- Foundations and data: the app is light-only with the Gene & Tonic tokens, Figtree and brand icons. A shared set of `x-ui`, `x-icon` and `x-brand` components (including the two-circle allele pair) has a local-only `/_ui` gallery. Research now produces effect titles, topic tags and categories; lookups produce variant names.
- Shell and routes: a sidebar shell (top bar and menu sheet on phones) with real routes for Home, Supplements, a supplement, Genetics and AI usage. Old `/?tab=` links redirect.
- Screens: Supplements is a split view with status chips and a preview. A supplement page has a hero, segmented tabs and filterable health effects beside "Your genes". Genetics has a dropzone, add-SNP card, filter chips and linked-supplement chips. AI usage shows stats, the period control, cost by task and recent calls.
- Home: gene-match hero, four stats, latest research, your genes and your supplements.
- Polish: accessibility pass; PDFs carry the brand name.

## How it works
Pages are Livewire single-file components, drawn by the `x-ui` components in `resources/views/components/ui/` inside the shell `resources/views/layouts/app.blade.php`. Genetics gets variant-to-supplement links through the Shared `VariantLinks` contract (`Modules/Shared/app/Contracts/VariantLinks.php`), implemented by Recommendations. Recommendations' relevance reader and Genetics' profile reader are request-scoped, so a page builds each once.

## Findings
- Dark-mode regression: deleting the `dark` custom variant made Tailwind's `dark:` follow the OS colour scheme, so Flux inputs and labels rendered dark on a dark-mode Mac. Only the real-browser pass caught it. The class-bound variant is restored (bound to a `.dark` class nothing sets), with a regression test.
- Page-load cost needed request-scoped caching. The Genetics memo flushes on variant model events and explicitly after QueueLookups, which fires no events.
- Review: 0 blocking after 2 required fixes: a second, never-flushed profile cache in Recommendations removed, and dead 24-hour usage fields removed.
- Scope added by choice: effect titles, kicker, topic tags, categories (fixed health-topic list), variant names, linked chips and filter, match counts, sources count, usage period control. Shown only when present, no backfill. An AI-call subject was declined.
- Process: the first implementer committed on the plan branch instead of its task branch, and the commit was moved. A subagent left an `npm run dev` server running in the worktree on :5174. The process still needs stopping.
- Checks: 789 tests pass, 3 skipped; Pint, PHPStan level 7, build, secret scan and audits clean.
- Browser checks ran at http://gene-and-tonic.test on a copy of the dev database (`gene_and_tonic`); the `laravel` database was untouched.
- Time: discover 14m, PRD 11m, plan 6m, build 58m, check 35m, review 13m.

## Follow-ups
- Existing rows get the new titles, tags and names only after re-research or lookup.
- 5 open recommended follow-ups (1 tests, 1 security, 1 ux, 1 perf, 1 quality): run /cairn:suggestions.
- Dropped (10 already fixed on the branch; 60 more merged into the 5 above): 44px targets, 24-hour fields, sidebar sizing, button type prop, usage labels, supplement count, profile memo (two), upload progress, title test.
- Cleanup for the user: stop the stray dev server on :5174, and remove the Herd link and `gene_and_tonic` database.
