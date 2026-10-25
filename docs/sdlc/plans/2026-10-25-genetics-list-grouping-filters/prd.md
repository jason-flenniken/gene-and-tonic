# PRD: Genetics list grouping and filters

> Plan: genetics-list-grouping-filters. Keep it short. Every requirement has a stable id and a one-line acceptance check.

## Problem
The Genetics tab shows a signed-in user's variants (73 rows for the demo user) as one flat table sorted by gene. It is hard to scan: the 38 free-text categories are mostly one-offs, so the Category column doesn't help you find related genes. The "Your genotype" column is empty on almost every row (it is filled for the 30 seeded genes and only 1 of 21 looked-up genes), which wastes width. The demo is on 2026-10-26.

## Solution
The list is grouped into about ten broad groups (Methylation, Brain & mood, and so on). Each group starts collapsed with its name and row count, and you click a header to open it. Rows that haven't been looked up, or whose lookup failed, sit together in a "Not looked up yet" group at the bottom, still with their Look up buttons. Above the list, a filter bar holds a group dropdown and a search box. Search matches gene, rsID, group, specific category, Affects and the genotype effect, and opens every group that has matches. The "Your genotype" column is gone: when a genotype effect exists, it shows as a highlighted line under Affects. New lookups pick a broad group from the fixed list, and existing looked-up rows get theirs from one batched AI call.

## Requirements
- **FR-01** A fixed, ordered list of broad groups exists as a Genetics enum: Methylation, Vitamins & minerals, Metabolism & weight, Heart & lipids, Brain & mood, Hormones, Detox & drug response, Sleep & circadian, Antioxidant & inflammation, Other. Each `gene_variants` row has a nullable group column (add-only migration). Check: migration adds the column; enum cases and labels are covered by a unit test.
- **FR-02** Every seeded reference row has a hand-mapped group in the seed data, and seeding writes it. Check: after `db:seed`, every seeded row has a non-null group, and MTHFR rs1801133 is Methylation.
- **FR-03** The gene lookup asks the model to pick one group from the fixed list. An answer outside the list is stored as Other. The specific category text is still stored as today. Check: a lookup test with the fake returning a valid group stores it; one returning "Banana" stores Other.
- **FR-04** An artisan command assigns a group to every looked-up row (has a category, no group) in one batched AI call. It sends only each row's gene symbol and category text, validates every answer against the list (Other when it is invalid or missing), skips rows that already have a group, and reports counts. Check: a test with the fake asserts the prompt holds only gene and category, invalid answers become Other, a second run makes no AI call, and the command exits 0.
- **FR-05** The list renders one section per group in enum order, showing only groups with rows. Each header shows the group label and its row count. Inside, rows keep the gene-then-rsID sort and show gene, rsID, genotype, the specific category and Affects. A looked-up row with no group yet is shown under Other. Check: a feature test sees the group headers in order with correct counts, and a row appears under its group.
- **FR-06** Pending, looking-up and failed rows appear in a "Not looked up yet" section after all groups, with the same status text and Look up buttons as today. Check: a feature test sees a pending row inside the last section with a Look up button.
- **FR-07** All sections start collapsed. Clicking a header opens or closes that section, and that state survives the list's 3-second polling. Check: a feature test asserts the collapsed initial state; a browser check toggles a section and sees it stay open across a poll.
- **FR-08** The filter bar's dropdown offers All, then each group that has rows, then "Not looked up yet". Choosing one shows only that section, opened. Check: a feature test sets the filter to Methylation and sees only Methylation rows, opened.
- **FR-09** The search box matches case-insensitively, as a substring, against gene, rsID, group label, specific category, Affects and the genotype effect. It never matches the genotype letters. Input is trimmed and capped at 100 characters. Only matching rows show, every section with a match is opened, and sections without matches are hidden. Check: a feature test searching "comt" shows only COMT rows; a search term that appears only in a fixture row's genotype returns no matches.
- **FR-10** The dropdown and search combine, so a row must match both. When nothing matches, the list shows a short "No variants match" message. Check: a feature test with filter Methylation plus search "COMT" shows the empty message.
- **FR-11** Clearing both the search and the dropdown returns the list to all sections collapsed. Check: a feature test sets then clears the search and asserts the collapsed state.
- **FR-12** The "Your genotype" column is removed. When a row has a genotype effect, it shows under the Affects text as a highlighted line labelled with the user's genotype (e.g. "Your AG: …"). Check: a feature test sees no "Your genotype" header and sees the effect line for a seeded row.

## Non-goals
- Better genotype-effect sources (SNPedia/ClinVar in lookups) because it's a separate change to lookup research; logged as a follow-up.
- A "Look up all" button, pagination, and saving filter or collapse state across reloads because there are 73 rows and the demo deadline is tonight.
- Changing the PDF report's genetics section because the request is the dashboard list.
- SQL-side search because the genotype is encrypted and the list is small; filtering runs in memory.

## Open questions
- [x] What happens to "Your genotype"? (answer: fold under Affects as a highlighted line)
- [x] Group by raw category or broad groups? (answer: about 10 broad fixed groups; specific category stays on the row)
- [x] Where do unlooked-up rows go? (answer: one "Not looked up yet" group at the bottom)
- [x] Default collapse state? (answer: all collapsed)
- [x] How do existing rows get a group? (answer: one batched AI call with gene and category only)
- [x] Does the dropdown include "Not looked up yet"? (answer: yes)

## Unhappy paths
- [FR-04] When the AI call fails or times out, the command exits non-zero with a count-only message and leaves groups unset; those rows still render under Other (FR-05).
- [FR-04] When the model returns extra rows, duplicates, or genes that weren't sent, only answers matching a sent row are applied.
- [FR-09] Search text longer than 100 characters is cut to 100. Markup in it is shown escaped and never matched as HTML.
- [FR-07] A Look up that finishes during polling moves its row into its group without opening or closing other sections.

## Security
- Trust boundaries:
  - search text and dropdown value from the browser (Livewire properties);
  - model output from the lookup and the backfill;
  - the backfill's outbound prompt.
- Assets: the user's genotypes (encrypted, personal) and the shared `gene_variants` reference table.
- STRIDE:
  - Search and dropdown:
    - Tampering: the dropdown value is validated against the enum plus "unlooked", and anything else is treated as All.
    - Information disclosure: the list stays scoped to `auth()->id()` through the existing reader. Search runs only over that user's rows, and matching never includes the genotype.
    - Denial of service: search is capped at 100 characters and runs in memory over at most a few hundred rows.
  - Model output:
    - Tampering: a group is accepted only if it's an enum value, otherwise it becomes Other. Text fields keep their existing caps.
  - Outbound prompt:
    - Information disclosure: it holds only gene symbols and category text from the shared reference table. No user IDs, genotypes or rsIDs tied to a user.
- Misuse cases:
  - Crafted dropdown value: falls back to All. Covered by a test.
  - Search for genotype letters to infer another user's data: impossible, since only your own rows are in memory and genotype isn't matched. Covered by the genotype-only search test in FR-09.
  - Prompt injection via category text making the model emit a bogus group: enum validation. Covered by a test.
  - Another user's variants showing up: covered by the existing scoping test.
- Genotypes are never logged. The backfill logs counts only.
