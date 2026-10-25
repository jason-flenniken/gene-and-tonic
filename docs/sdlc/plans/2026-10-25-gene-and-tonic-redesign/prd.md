# PRD: Gene & Tonic redesign

> Plan: gene-and-tonic-redesign. Keep it short. Every requirement has a stable id and a one-line acceptance check.
>
> Visual source of truth: `docs/design/gene-and-tonic/` (HANDOFF.md, screens/*.html, screenshots, tokens.css, brand/, icons/). This PRD records what the handoff decides plus the corrections and additions agreed in discovery; where they differ, this PRD and CLAUDE.md win.

## Problem
The app is a generic Laravel/Flux dashboard: everything lives on `/` behind `?tab=` underline tabs, in default zinc styling with Instrument Sans. It is a demo meant to show off clean Laravel code and product design, and it doesn't look like a product. An approved design, Gene & Tonic, exists as static mocks. Some of what the mocks show has no data behind it today: short effect titles, topic tags, variant names, links between variants and supplements, match counts, a usage period control and cost by task.

## Solution
The app becomes Gene & Tonic. It has a persistent sidebar (a top bar with a menu sheet on phones) and five real routes: Home, Supplements, a supplement, Genetics and AI usage. Old `/?tab=` links redirect. The app has a warm canvas, one white panel per page, juniper actions and the Figtree font, and it is light only. A shared set of `x-ui` components draws every screen. Its signature element is the two-circle allele pair, with the effect allele filled.

Each screen is rebuilt to its mock:
- Home: a gene-match hero, four stats, latest research, your genes and your supplements.
- Supplements: a split view with status filter chips and a preview of the selected supplement.
- A supplement: a hero, segmented tabs, and an Overview grid with filterable health effects beside "Your genes".
- Genetics: a dropzone, an add-SNP card, group and filter chips, a restyled variant list with linked-supplement chips, and Look up now.
- AI usage: stats, a period control, cost by task and a recent-calls table.

Research now also produces short effect titles, a kicker, topic tags and effect categories. Gene lookups produce a common variant name. Existing rows show these fields once they are re-researched or looked up again; until then the UI falls back cleanly. PDFs carry the brand name.

## Requirements

### Foundations
- **FR-01** `resources/css/app.css` uses the tokens from `docs/design/gene-and-tonic/tokens.css`: the `@theme` block, the `@layer base` and `@utility tabular`, and the Flux accent. The font is Figtree, weights 400–700, loaded through `vite.config.js`, and Instrument Sans is gone. Check: `npm run build` succeeds; the built CSS contains `--color-juniper` and Figtree; no `Instrument Sans` remains in the repo outside docs.
- **FR-02** The app always renders light. `@fluxAppearance` and the `dark` custom variant are removed, and no `dark:` classes remain in app or module views. Check: grep finds no `dark:` in `resources/views` or `Modules/*/resources/views`; the layout has no `@fluxAppearance`.
- **FR-03** `APP_NAME="Gene & Tonic"` is set in `.env.example`. The page `<title>` shows "Gene & Tonic" once, with no `&amp;amp;`. The favicons and apple touch icon are replaced from `brand/`. Check: a feature test sees `<title>…Gene &amp; Tonic…</title>` on `/`; the files in `public/` match the brand files.
- **FR-04** `x-brand.mark` (with a `size` prop, switching to the small mark at 24 px and below) and `x-brand.lockup` exist as inline SVG components. Every icon in `icons/` is an `x-icon.*` anonymous component that uses `currentColor`. Check: render tests show `x-brand.mark size=20` outputs the small-mark paths and `size=34` the full mark.
- **FR-05** The presentation-only `x-ui` components from HANDOFF §5 exist in `resources/views/components/ui/`, take plain values and import no module: panel (with `narrow`), page-header, section, stat, icon-tile, link-row, button, segmented and segmented.item, chip, status, progress-steps, grade, allele-pair, copies, notice and dropzone. `x-tabs`/`x-tabs.item` are restyled to the segmented look and keep their API and `role`/`aria-selected`. Check: a render test per component; `x-tabs` still outputs `role="tab"` with `aria-selected`.
- **FR-06** `x-ui.allele-pair` draws one circle per allele, filling each allele that equals `effect`. It outputs an aria-label such as "Genotype G T; one copy of effect allele G". A null effect draws both outlined. A homozygous genotype draws two filled circles, or two outlined ones. A one-letter genotype (for example D/I or hemizygous) draws a single circle. Check: a render test covers effect filled, null effect, homozygous, and a one-letter genotype.
- **FR-07** A local-only `/_ui` route (registered only when `app()->isLocal()`) renders every `x-ui` component in every state. Check: a feature test gets 404 for `/_ui` outside local; in local, the page renders every component name.

### Shell and routes
- **FR-08** The layout is a sidebar shell. It has a brand row, nav (Home · Supplements · Genetics · AI usage) with 44 px items, and a user row at the bottom with initials, name and Sign out, or Sign in for guests. Main content sits beside the sidebar on the canvas. Below `lg`, a top bar with a menu button opens the nav as a sheet (`flux:sidebar collapsible="mobile"` or an Alpine toggle). The nav is never sticky or `100vh`. `<flux:toast />`, `@fluxScripts` and the POST logout form remain. Check: a feature test sees all four nav links and the logout form for a signed-in user, and Sign in for a guest.
- **FR-09** The active nav item has `aria-current="page"`. Supplements is also active on a supplement page. Check: feature tests on `/supplements` and `/supplements/{id}` see `aria-current="page"` on the Supplements link only.
- **FR-10** These routes exist:
  - `/` → `home`
  - `/supplements` → `supplements.index`, the catalog as a full page
  - `/supplements/{supplement}` → `supplements.show`
  - `/genetics` → `genetics.profile`, the full Genetics page behind `auth`
  - `/usage` → `usage`, public

  Each page sets its title. Check: feature tests get 200 for each public route as a guest; `/genetics` redirects a guest to `/login` and renders for a signed-in user.
- **FR-11** On `/`, `?tab=supplements`, `?tab=genetics` and `?tab=usage` redirect to the matching route. `?tab=overview`, or no tab, renders Home. Check: a feature test asserts each redirect target.
- **FR-12** Every link that pointed to `route('home', ['tab' => …])` points to the new routes. Login still redirects intended or to `genetics.profile`, which now lands on the Genetics page. CLAUDE.md is updated for the new routes and the `/genetics` page, and its stale "arch tests not enforced yet" line is corrected. Check: grep finds no `'tab' =>` links to `home`; LoginTest passes.

### Data additions
- **FR-13** A public enum `Modules\Supplements\Enums\HealthTopic` holds a fixed list: Methylation, Heart, Mood, Sleep, Stress, Energy, Focus, Blood, Immune, Digestion, Other. Each case has a `label()`. Check: a unit test covers cases and labels.
- **FR-14** The research prompt also asks for:
  - per supplement: a kicker (a short free-text line) and 1–3 topic tags
  - per effect: a short title and one category

  Tags and categories must be `HealthTopic` values. Invalid ones are dropped (tags) or become Other (category). The kicker and title are trimmed, whitespace-collapsed and capped (kicker 60 chars, title 80). Add-only, nullable migrations store them. `SupplementResearchData` gains `?kicker` and `list<HealthTopic> topics`. `EffectData` gains `?title` and `?HealthTopic category`. Check: a research test with the Intelligence fake stores valid values, turns "Banana" into Other or drops it, and caps an over-long title; the reader returns them on the DTOs.
- **FR-15** The gene lookup prompt also asks for a common variant name, such as "A1298C" or "Val158Met". It still sends only rsIDs and gene symbols. The name is stored in a nullable `gene_variants` column, capped at 40 chars, and exposed as `VariantData::$variantName`. Check: a lookup test with the fake stores the name and asserts the prompt contains no genotype.
- **FR-16** Every new field falls back when empty, with no backfill:
  - effect title → claim
  - no kicker → no kicker line
  - no topics → no tag line and no effect chips
  - no variant name → rsID only

  Re-run research and new lookups fill these fields. Check: a feature test with legacy data (nulls) renders the supplement, the catalog and Genetics without errors and shows the fallbacks.
- **FR-17** `GeneticMatchData` gains `?string $allele`, the plus-strand effect allele resolved the same way `Genotype::copiesOf` resolves strand. It is null for rsID-only and gene-only matches. `FakeGeneticRelevanceReader` supports it. Check: a unit test where the interaction allele is gene-strand `T` with plus-strand ref `G`/risk `A` yields `allele = 'A'`; a gene-only match yields null.
- **FR-18** Supplements gets a new public read method listing supplements for other modules. It returns id, name, status, completedAt, effect/study/source counts, topics and gene interactions, as DTOs. Its fake supports it. Check: a contract test against the real reader and the fake.
- **FR-19** `GeneticRelevanceReader::topForUser(int $userId, int $limit = 1)` returns the supplements with the most matches for the user. It is ranked by match count, then name. The result carries the supplement name and matches, and excludes supplements with no matches. The fake supports it. Check: tests against real and fake: the ranking order, the limit, and an empty profile returns [].
- **FR-20** A Shared contract `VariantLinks::forUser(int $userId)` returns, for each rsID, the supplements (id and name) the variant is a gene match for. Shared binds an empty default; Recommendations rebinds it. Genetics depends only on Shared. Check: a test that the default returns []; a Recommendations test that a matched rsID maps to the supplement; the boundary tests still pass.
- **FR-21** Supplements' catalog rows get a sources count alongside the effects and studies counts. `paginateWithResearch` takes an optional status filter: all, complete, active (in progress) or failed. Check: a repository test for each filter value and for the sources count; CatalogTest's fake repository is updated.
- **FR-22** Intelligence profiles have a human `label`, set under each module's `*_profile` config key and exposed on `ProfileData`. The labels are:
  - research → "Supplement research"
  - lookup → "Gene lookup"
  - group → "Variant grouping"
  - extract → "Marker extraction"

  A null or unknown profile name on a stored row falls back to the raw name, or to "Web search" for search rows, and never throws. Check: a registry test for the labels; a usage view test with an unknown profile name renders it.
- **FR-23** `AiRequestRepository` adds a time window: 24h, 7 days or all time.
  - `summary()` takes the window.
  - `paginateLatest()` takes the window.
  - New `costByProfile()` takes the window and returns `{profile, operation, calls, failed, credits, cost}` rows.

  Check: repository tests seed rows at 2h, 3d and 30d ago and assert the counts per window and the per-profile aggregates, including search credits.

### Home (`/`)
- **FR-24** The header greets the user by first name with the time of day ("Good morning, Demo"), or "Welcome to Gene & Tonic" for guests. The subtitle shows the date and "{n} supplements", plus "· {n} variants" for signed-in users. No search box is shown. Check: feature tests for a guest and a signed-in user assert the copy.
- **FR-25** The gene-match hero (`recommendations::top-match`, signed-in only) shows "{n} of your variants relate to {Supplement}". Below it is a one-line body, "Open {supplement}" (primary) and the PDF download when the research is complete. On the right are up to 3 gene rows, each with an allele pair. Guests see "Sign in to see how supplements relate to your genes". A user with no profile sees "Add your genetic results", linking to `/genetics`. With no matches, the hero is hidden. Check: a feature test per state, using the fakes.
- **FR-26** The stat row shows Supplements (total, captioned "Research complete" or "{n} in progress"), Variants (count, "{n} looked up"), Gene matches (total across supplements, "Across {n} supplements") and AI spend (24h cost to 2 decimals, "{n} calls in 24h"). Guests see only Supplements and AI spend. "Looked up" means the status is not Pending, LookingUp or Failed. Check: feature tests assert the values and captions for both audiences.
- **FR-27** Latest research lists the 4 most recently completed supplements as bordered rows. The meta line is the topic labels if any, otherwise "Complete · {diffForHumans}". The title is "{Name}: {n} effects across {m} studies"; when there are 0 effects it is "{Name}: no effects were graded". Check: a feature test sees rows in order with that copy.
- **FR-28** Your genes (signed-in) lists the top 5 groups by count as divided rows, each with an icon tile and "{n} variants", plus "· {k} linked" when any are linked. Each row links to `/genetics?group={value}`. Below the list, when variants await lookup, a banner says "{n} variants are waiting for a lookup", with Look up now (FR-37). Guests see a sign-in card. Check: a feature test sees groups ordered by count and the banner count.
- **FR-29** Your supplements is a card grid. Each card has a 2–4 letter abbreviation tile (a small config map for known names, derived otherwise), the name, "{n} effects", the gene-match count for signed-in users (rendered through a PageActions slot that Recommendations registers), and "Researched {diffForHumans}". With no supplements, it shows a prompt linking to `/supplements`. Check: feature tests for cards, the slot count when signed in, its absence for guests, and the empty state.

### Supplements index (`/supplements`)
- **FR-30** The page keeps `add` (throttled; toasts "{name} queued for research."), `retry`, the delete modal, pagination (10) and `wire:poll.3s` while any listed run is active. The add form is a pill input with "Research it". Check: the existing CatalogTest behaviours pass against the new route.
- **FR-31** Status filter chips show All {n} · Complete {n} · In progress {n}, plus Failed {n} only when non-zero. The counts come from `countByStatus()`. The chips filter the list and reset pagination. Check: a feature test selects In progress and sees only active rows, and Failed is absent when there are none.
- **FR-32** Each row shows the name and one status line. Complete rows read "{n} effects · {m} studies · {k} sources", plus the gene-match count via the slot when signed in. Active rows show the progress steps (Queued 1/4, Searching 2/4, Analyzing 3/4). Failed rows show the error and Retry. Check: a feature test per status.
- **FR-33** The preview (`#[Url(as: 'selected')] selectedId`, defaulting to the first complete supplement; action `selectSupplement`) contains:
  - "TOP EFFECTS IN {NAME}" and "Researched {date}"
  - the top 3 effects sorted strong → moderate → limited, each as title (or claim) plus its grade
  - a gene strip, rendered through a new `supplement.preview` slot that Recommendations registers; it is signed-in only, with allele pairs
  - Open (→ `supplements.show`), Re-run, the `supplement.header` slot, and a ghost Delete

  On phones the preview is hidden and rows link to the supplement page. Check: a feature test sets `selectedId` and sees sorted effects; Supplements has no import of Recommendations (boundary test).

### Supplement page (`/supplements/{id}`)
- **FR-34** The page uses the narrow panel, with a breadcrumb "Supplements › {Name}" in place of the back link. The hero contains:
  - the kicker, when present
  - the name
  - topic tags, when present
  - the status and progress steps while the run is active
  - the capsule tile
  - the summary, clamped to a readable length with the full text available
  - the restyled download menu and Re-run

  The page polls every 3s only while research is queued, searching or analyzing. Check: feature tests see the breadcrumb, `wire:poll.3s` present while active and absent when complete, and the kicker and tags when present.
- **FR-35** The disclaimer shows as `x-ui.notice`. The six tabs (Overview, Effects, Mechanisms, Studies, Genetics, Sources) keep their counts in the segmented control. Overview contains:
  - "How does {name} fit my genes?" (signed-in with a profile) → Genetics tab
  - Health effects: the first 4 effects with title or claim and grade, plus effect-category filter chips when any effect has a category, and "Show all effects"
  - Your genes: the `supplement.panels` slot
  - Safety notes

  The Genetics tab lists the population-level interactions, then the same slot. No other tab renders the slot. Check: a feature test asserts the slot output on Overview and Genetics only, and the chips filter effects.
- **FR-36** The genetic panel has a ring with the match count, "Your genes" and "{n} of your variants relate to this supplement". Each row shows the gene, then the variant name and rsID, the allele pair (owner only, using `GeneticMatchData::$allele`), copies, the relation badge (Helps ok / Caution warn / Related neutral) and the explanation. `data-genotype` stays, and the four states remain: guest, no profile, not checked, no matches. Check: GeneticPanelTest passes with updated copy; a test asserts a guest sees no genotype markup.

### Genetics page (`/genetics`)
- **FR-37** `lookUpAll` queues lookups for the signed-in user's own Pending and Failed variants only, up to `genetics.lookup_cap`. It is rate-limited separately, and its toast reports the count queued. It is shared by Home and Genetics. Check: a test where another user's pending rsID is not queued; the cap is respected; rows already LookingUp are skipped; the limiter blocks the second call in a window.
- **FR-38** The header says "{n} variants from {u} upload(s)", plus " and {m} added by hand" when any were. Below it: "Raw files are deleted after processing; only marker lines ever reach the AI model." Then the disclaimer as `x-ui.notice`. Check: a feature test asserts the copy for upload-only and mixed sources.
- **FR-39** The upload card has a dropzone bound to `file`. Its help text lists the formats and "up to {MB} MB", it shows upload progress, and the existing `uploadResults` submit is kept. Below it, the uploads list shows a file tile, the name, `readSummary()`, "{n} queued for lookup" and a status (active/ok/bad with the error). The add-SNP card has the three labelled inputs and "Add SNP", with validation unchanged. Check: the existing UploadPageTest and ProfilePageTest behaviours pass.
- **FR-40** Filter chips: All {n}, Linked to a supplement {n}, Two copies {n}, Not looked up {n}, then one chip per group with variants. The chips bind to `group`. `group` and `search` are `#[Url]`, and sections sync on mount so that `/genetics?group=methylation` opens that section. Check: feature tests for each chip filter and for the URL-driven group open on load.
- **FR-41** The variant list keeps `<details>`/`<summary>` and `toggleSection`. Group headers show an icon tile in the `VariantGroup` tone, the label, "· {n} variants" and a chevron. Each row shows:
  - the gene, plus the variant name and rsID
  - the allele pair, with the risk allele filled
  - `affects`
  - "Your {genotype}, {one copy|two copies}: {genotypeEffect}", or "Your {genotype}: …" when copies is 0 or unknown
  - "Linked: {Supplement}" chips that link to the supplement

  The unlooked section has a lime header with "{n} variants, {q} queued" (q is the live LookingUp count) and Look up now. Its rows keep Looking up…, Lookup failed and the per-row Look up. Check: VariantListFilterTest and LookupTest pass, with selectors updated in the same change; a test sees the linked chip.

### AI usage (`/usage`)
- **FR-42** The page shows a 24 hours / 7 days / All time segmented control (`#[Url]`, default All time) that drives every number on the page. There are four stats:
  - Calls, captioned "{n} failed" in bad-text only when greater than 0
  - Estimated cost, to 4 decimals, captioned "About ${x} a call"
  - Input tokens
  - Output tokens

  The Home AI spend stat and this page share no layout-specific component. Check: feature tests per window value; the failed caption appears only when non-zero.
- **FR-43** Cost by task shows one row per profile, with the label, "{calls} calls", a juniper bar (width relative to the largest cost, at least 2%) and the cost. Search rows read "Web search · {credits} credits". Check: a feature test sees labelled rows and the credits row.
- **FR-44** Recent calls is `paginateLatest(20)` for the window, in a horizontally scrolling bordered table. The columns are:
  - Time: `diffForHumans`, with the exact time in `title`
  - Task: the label, with provider / model · effort beneath
  - Tokens in / out, or credits
  - Latency
  - Cost
  - Status: OK / FAILED, with the truncated error

  Check: a feature test sees a row with each column and "No AI calls recorded yet." when there are none.

### Cross-cutting
- **FR-45** These mappings are applied in Blade or through `tone()` methods:
  - `ResearchStatus` → status tone and step
  - `EvidenceGrade` → grade words, not letters (§6.2)
  - `GeneRelation` → badge tone
  - `VariantGroup` → icon and tone (§6.4)
  - `UploadStatus` → tone

  Existing `color()` methods are left in place. Check: unit tests for any new `tone()`/`icon()` methods.
- **FR-46** Every PDF template's header and footer show "Gene & Tonic" as text, inline-styled at 8pt or more, with no remote assets. Check: TemplatesTest asserts the brand text in the header and footer HTML for each template.
- **FR-47** Every page is usable at 390 px with no page-level horizontal scroll (tables scroll inside their own box), hit targets are at least 44 px, every interactive element shows the accent focus ring, and every input has a label. Check: a browser pass at 390 px and 1440 px against the screenshots, plus a keyboard-only run through each page.
- **FR-48** `composer test` passes: Pint, PHPStan level 7 with no new baseline entries, and the full Pest suite. The existing hooks (`data-panel`, `data-genotype`, `role="tab"`/`aria-selected`, `data-template`) are kept. Where copy changes on purpose, the assertion changes in the same commit. Check: `composer test` exits 0.

## Non-goals
- The AI call subject on usage rows, because the user chose not to add an `ai_requests` subject column.
- Export CSV, Add to stack, and the Home search box, because they are separate features with no backing today (HANDOFF §9).
- Dark theme, because this pass is light only; a dark token set can come later.
- A backfill command or automatic re-research, because new fields fill through normal research and Re-run, with no surprise AI spend.
- PDF content changes beyond the brand name, because the PDF still renders from the same DTO sections.
- A mid-plan visual review stop, because the user chose to review at ship.

## Open questions
- [x] How much mock-only data to build? (answer: effect titles, topics/kicker/effect categories, variant names, linked chips + filter, gene-match counts on cards, sources count, usage period control; not the AI call subject)
- [x] Existing rows without the new fields? (answer: show when present, no backfill)
- [x] Effect filter chips? (answer: yes, from a fixed `HealthTopic` enum)
- [x] Poll the supplement page? (answer: yes, 3s while active)
- [x] Where does Your genes sit? (answer: Overview grid and Genetics tab only)
- [x] Brand in the PDF? (answer: name as text in every header and footer)
- [x] How does Genetics read links? (answer: a `VariantLinks` contract in Shared, implemented by Recommendations; linked = a gene match for the user)

## Unhappy paths
- [FR-14, FR-15] When the model returns an invalid tag or category, an over-long title or kicker, or omits a field, the value is dropped, mapped to Other or capped, and the research or lookup still completes.
- [FR-19, FR-20, FR-25] When the user has no profile or no matches, `topForUser` and `VariantLinks` return empty, and the hero and chips hide without errors.
- [FR-22, FR-43] When a stored row has a null or unregistered profile name, the usage page renders its raw name instead of throwing.
- [FR-37] When the limiter is hit, `lookUpAll` queues nothing and toasts a "try again shortly" message. When nothing is pending, it toasts "Nothing to look up."
- [FR-10] When a guest opens `/genetics`, they are redirected to `/login` and land back on `/genetics` after signing in.

## Security
- Trust boundaries:
  - model output, newly including the kicker, tags, effect title, effect category and variant name
  - the Livewire request payload (`selectedId`, `group`, `search`, `window`, the status filter, `lookUpAll`)
  - the new Shared `VariantLinks` contract crossing from Recommendations into Genetics
  - the public routes `/`, `/supplements` and `/usage`
- Assets: the owner's genotypes and variant list, AI spend (paid lookups and research), and the integrity of the demo data.
- STRIDE:
  - Model output:
    - Tampering: enum-validate, cap, collapse whitespace.
    - Information disclosure: Blade-escape everything, never `{!! !!}`.
  - Livewire payload:
    - Tampering: validate `window` and the status filter against an allow-list.
    - Elevation: the `selectedId` lookup tolerates unknown ids; `lookUpAll` scopes to `auth()->id()`.
    - DoS: a dedicated rate limiter on `lookUpAll`.
  - `VariantLinks` / `topForUser`:
    - Information disclosure: they take the authenticated user id only and return supplement names, never genotypes.
  - Public routes:
    - Information disclosure: genotype, allele pair, gene matches and linked chips render only for the signed-in owner; guests get sign-in prompts.
- Misuse cases, each needing a test:
  1. A guest loads `/`, `/supplements` or a supplement and sees no genotype or allele markup.
  2. A signed-in user calls `lookUpAll` and another user's pending rsID is not queued.
  3. Spamming `lookUpAll` hits the limiter.
  4. Research output with `<script>` in the title or kicker renders escaped.
  5. An invalid `window` or status filter value falls back to the default.

## Non-functional requirements
- **NFR-01** Home makes no more than one `GeneticProfileReader::forUser` decrypt pass per component render. Supplements listing for Recommendations is one query plus eager loads, not N+1. Check: a query-count assertion on Home with 4 supplements.
- **NFR-02** No test launches Chrome unless `RUN_CHROME_TESTS=1`. Every test keeps `Http::preventStrayRequests()` and the Intelligence fake. Check: the suite runs offline.
