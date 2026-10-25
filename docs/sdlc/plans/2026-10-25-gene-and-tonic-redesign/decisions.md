# Decisions: Gene & Tonic redesign

Append-only. Written by `cairn decide` as each decision is made.

### 1. Why / success / constraint
**Decision:** Re-skin the app as Gene & Tonic per docs/design/gene-and-tonic/HANDOFF.md; success = the HANDOFF §11 acceptance checklist; CLAUDE.md boundary rules and domain constraints win on conflict
**Why:** settled by HANDOFF.md and README kickoff; demo meant to show clean Laravel code and product design
_2026-10-25 18:26_

### 2. Which mock-only data does this plan build?
**Decision:** Short effect titles; topic tags + kicker on supplements; common variant name (e.g. A1298C) on gene_variants. Not built: AI call subject on usage rows
**Why:** user wants the data to fit the design rather than omitting what the mocks show
_2026-10-25 18:26_

### 3. Which variant/supplement links and extras does this plan build?
**Decision:** Linked-to-supplement chips + filter (reverse lookup owned by Recommendations); gene-match counts on catalog rows and Home cards (PageActions slot); sources count on catalog rows; AI usage period control (24h / 7 days / All time)
**Why:** user picked all four from the mocks
_2026-10-25 18:26_

### 4. How are existing rows handled for the new model-filled fields?
**Decision:** Show when present: fall back to claim / no tags / no variant name; new research and Re-run fill them; no backfill command, no automatic spend
**Why:** avoid surprise AI spend; demo data refreshes through normal Re-run
_2026-10-25 18:27_

### 5. Build effect categories and the Overview effect filter chips?
**Decision:** Yes: each effect gets a category from the same tag vocabulary; Overview health-effects list gets filter chips
**Why:** one prompt change covers tags and effect categories
_2026-10-25 18:27_

### 6. Poll the supplement page while research is active?
**Decision:** Yes: wire:poll.3s only while status is Queued/Searching/Analyzing (reverses the genetics PRD's declined detail-page polling)
**Why:** the hero's progress steps need to move and the page should fill when the run completes
_2026-10-25 18:27_

### 7. Stop for a visual review after foundations?
**Decision:** No: build straight through; review at ship
**Why:** user's choice
_2026-10-25 18:27_

### 8. Where do topic tags and effect categories come from?
**Decision:** A fixed public enum in Supplements (e.g. Methylation, Heart, Mood, Sleep, Energy, Focus, Stress, Blood, Immune, Other); invalid model answers drop or become Other; the kicker is a short free-text line, length-capped and escaped
**Why:** stable filter chips, model output kept on rails
_2026-10-25 18:30_

### 9. Where does the 'Your genes' panel sit on the supplement page?
**Decision:** In the Overview two-column grid beside Health effects, and on the Genetics tab under population-level interactions; not on other tabs; SupplementPageTest order assertion updated
**Why:** match the mock
_2026-10-25 18:30_

### 10. Brand the PDF?
**Decision:** Yes: 'Gene & Tonic' as text in every template's header and footer, inline-styled, 8pt or more, no remote assets
**Why:** APP_NAME alone never reaches the PDF templates
_2026-10-25 18:30_

### 11. Users
**Decision:** The signed-in demo user (owner of genetic data) and anonymous guests; guests see Home, Supplements, a supplement and AI usage; Genetics needs auth
**Why:** unchanged from today's access model, per HANDOFF §2
_2026-10-25 18:30_

### 12. Core flow
**Decision:** Sidebar nav across five real routes: Home composes module widgets (gene match hero, stats, latest research, your genes, your supplements); Supplements split view with status filter and preview; supplement page with hero, tabs, Overview grid; Genetics page with dropzone, add SNP, group chips, variant list, Look up now; AI usage stats, cost by task, period control, recent calls. Old /?tab= links redirect
**Why:** HANDOFF §2 and §7
_2026-10-25 18:30_

### 13. Trust boundaries
**Decision:** Genotypes and allele pairs render only to the signed-in owner on every new surface (Home hero, Home your genes, catalog preview strip, linked chips); genetics pages keep the disclaimer; lookUpAll is a paid action: only the caller's own Pending/Failed rsIDs, existing genetics.lookup_cap, its own rate limiter; new model fields (effect title, kicker, tags, effect category, variant name) are validated, length-capped and escaped, tags/categories must be enum values; new columns come with migrations and nullable defaults; variant names come from the lookup prompt which still sends only rsIDs and gene symbols; /usage stays public; no new deletes; no shell commands; no money beyond existing AI spend
**Why:** checked each boundary against CLAUDE.md domain constraints
_2026-10-25 18:30_

### 14. Non-goals
**Decision:** out of scope: AI call subject on usage rows; Export CSV; Add to stack; Home search box; dark theme; backfill command; detail-page features with no data
**Why:** user declined the AI subject; the rest are HANDOFF §9 Later/Omit items the user did not pick
_2026-10-25 18:30_

### 15. Corrections to HANDOFF found in research (carry into PRD)
**Decision:** GeneticMatchData::$allele must be strand-resolved like Genotype::copiesOf and null for gene-only/rsid-only matches; flux:sidebar uses collapsible=mobile (stashable deprecated in Flux 2.21); fonts live in vite.config.js (bunny), Figtree 400-700; pin light by removing @fluxAppearance; Recommendations needs a new public Supplements contract method to list supplements (Repositories are private); profile labels go under *_profile config keys and ConfigProfileRegistry/ProfileData, tolerating null/unknown names; costByProfile rows include credits; caption reads 'calls in 24h' (rolling window); 'looked up' means status not in Pending/LookingUp/Failed everywhere; genetics group/search get #[Url] and sections sync on mount; tests assert copy not data-stat, and the blast radius includes VariantListFilterTest regexes, CatalogTest's anonymous SupplementRepository, LookupTest, ComplexMarkerCsvTest, LoginTest; usage-summary must serve both the Home stat and the usage page; summary/claim lengths need clamping in hero/rows; CLAUDE.md updated for the new /genetics page and stale arch-test line; layout <title> must not double-escape the ampersand
**Why:** verified against the code
_2026-10-25 18:30_

### 16. Read-back
**Decision:** Outcome: Gene & Tonic redesign (sidebar shell, 5 real routes, tokens/Figtree/brand, x-ui components, every screen rebuilt to the mocks) plus new data: effect titles, topic tags + kicker + effect categories (fixed enum), variant names, linked-to-supplement chips/filter, gene-match counts on cards, sources count, AI usage period control and cost by task | User: signed-in demo user (owns genetic data) and guests (Home, Supplements, a supplement, AI usage) | Why now: demo to show off clean Laravel code and product design; design approved | Success: HANDOFF §11 checklist (screens match at 1440/390, Figtree only, light only, keyboard nav, honest data, correct allele pairs, ?tab= redirects, favicon/title/PDF say Gene & Tonic, composer test green) | Constraint: CLAUDE.md module boundaries and genetic-data rules win; new model fields shown only when present (no backfill spend); genotypes owner-only | Out of scope: AI call subject, CSV export, Add to stack, Home search, dark theme, backfill command, mid-plan review stop
**Why:** confirmed by the user
_2026-10-25 18:30_

### 17. How does Genetics get variant→supplement links for the chips and the Linked filter?
**Decision:** A small contract in Shared (VariantLinks) taking the user's rsIDs and gene symbols only (no genotypes) and returning rsID → list of linked supplement id+name; Shared binds an empty default, Recommendations rebinds it using a new public Supplements listing method; Genetics depends only on Shared
**Why:** Genetics may not depend on Recommendations; a slot can draw chips but cannot feed the filter; dependency inversion through Shared keeps the module map intact
_2026-10-25 18:39_

### 18. What counts as 'linked'?
**Decision:** A variant is linked to a supplement when it is one of that supplement's gene matches for the user (same rules as the Your genes panel), so the contract takes the user id and Recommendations reads the profile itself; amends decision 17's input shape
**Why:** keeps 'linked', 'gene matches' and the Home hero on one definition; still no genotypes pass through Shared
_2026-10-25 18:39_
