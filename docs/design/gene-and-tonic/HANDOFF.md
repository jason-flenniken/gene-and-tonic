# Gene & Tonic — UI redesign handoff

This is the spec for re-skinning the app as **Gene & Tonic**, a demo built to show off clean Laravel code and product design. It maps each approved mock to the modules, Livewire components, DTOs and tests that already exist in this repo, and it calls out every place where a mock shows something the backend can't supply yet.

Read [CLAUDE.md](../../../CLAUDE.md) first. Its boundary rules, domain constraints and commands still apply, and **CLAUDE.md wins** wherever this document disagrees.

| | |
|---|---|
| Visual source of truth | [`screens/*.html`](screens) (open in a browser) and [`screenshots/`](screenshots) (desktop 1440 px and phone 390 px) |
| Tokens | [`tokens.css`](tokens.css): a Tailwind 4 `@theme` block, including the Flux accent |
| Brand | [`brand/`](brand): mark, small mark, lockups, app icon, favicons |
| Icons | [`icons/`](icons): 24 px stroke icons taken from the mocks |
| Live canvas | https://claude.ai/code/artifact/ea9583cc-98e7-4510-847f-9f512cb03009 (page "Screens") |

---

## 1. What we're building

The look is a **light, calm, research-grade product UI**: a warm off-white canvas, one white rounded panel per page, deep juniper green as the action colour and lime only in the mark. There is **one typeface (Figtree)**. Pastel icon tiles mark categories, and pill-shaped buttons and segmented controls replace the current underline tabs.

Principles to hold to while implementing:

1. **Honest data only.** Every number, label and grade on screen comes from a DTO. Where a mock shows something we don't store, the UI leaves it out instead of faking it (see §9).
2. **Genotype is the hero.** The two-circle *allele pair* (filled circle = effect allele) is the signature element. It appears everywhere a user's genotype appears.
3. **Calm, not clinical.** No red/green alarm styling for genetics. Gene relations use the muted status palette, and every genetics surface carries the not-medical-advice notice.
4. **One panel, generous spacing.** Content sits in a single white panel (max 1080 px, or 900 px on the supplement page) on the canvas. Inside the panel, separate sections with borders and tints, not shadows.

## 2. Information architecture (the biggest change)

Today everything lives on `/` behind `?tab=` (`pages::dashboard` → Overview, Supplements, Genetics, AI Usage). The design replaces the tabs with a **persistent sidebar and real routes**:

| Nav item | Route (name) | Livewire page | Owning module | Notes |
|---|---|---|---|---|
| Home | `/` (`home`) | `pages::dashboard` (rebuilt) | app shell composes module widgets | No more tabs |
| Supplements | `/supplements` (`supplements.index`) | `supplements::catalog` as a full page | Supplements | New route in `Modules/Supplements/routes/web.php` |
| ↳ Supplement | `/supplements/{supplement}` (`supplements.show`) | `supplements::show` | Supplements | Exists |
| Genetics | `/genetics` (`genetics.profile`) | `genetics::profile` as a full page, `auth` | Genetics | Today this route redirects to `/?tab=genetics`; turn it into the page |
| AI usage | `/usage` (`usage`) | `intelligence::usage-log` | Intelligence | New route in `Modules/Intelligence/routes/web.php`; public, like the tab is today |

- Keep old links working: on `/`, a `?tab=supplements|genetics|usage` query **redirects** to the matching new route.
- The "Back to supplements" link in `supplements::show` points to `route('home', ['tab' => 'supplements'])`. Change it to `supplements.index`. (The breadcrumb in the new design replaces it.)
- Guests can see Home, Supplements, a supplement and AI usage. The Genetics nav item stays visible to guests and leads to `/login` through the `auth` middleware.

## 3. Foundations

### 3.1 Tokens and fonts
- Replace the `@theme` block in `resources/css/app.css` with the contents of [`tokens.css`](tokens.css) (keep the `tailwindcss` and Flux imports and the `@source` lines above it).
- Swap **Instrument Sans → Figtree** (weights 400–700). Load it the same way the layout loads fonts today (`@fonts`), or with a Google Fonts `<link>`.
- `--color-accent*` makes Flux's `variant="primary"` buttons, links and focus rings juniper. The optional zinc override warms Flux's stock greys so they sit on the canvas. Drop it if it causes trouble.
- Where `tokens.css` and a mock disagree (the peach tone's foreground was darkened to pass contrast), **tokens win**.

### 3.2 Light only, for now
The mocks are light. `layouts/app.blade.php` currently uses `@fluxAppearance`, `dark:` classes and a `dark` custom variant. For this pass, make the app render light regardless of OS setting (check the Flux docs through Boost's `search-docs` for the supported way to pin appearance), and don't add `dark:` variants to new markup. A dark theme can come later as its own token set.

### 3.3 Brand
- `.env` / `.env.example`: `APP_NAME="Gene & Tonic"`. The quotes are needed because of the `&`. Check the PDF header/footer templates and the page `<title>` after the change.
- Replace `public/favicon.svg`, `public/favicon.ico` and `public/apple-touch-icon.png` with the files in [`brand/`](brand).
- Add `resources/views/components/brand/mark.blade.php` (inline SVG from `brand/mark.svg`, with a `size` prop) and `brand/lockup.blade.php`. **At 24 px and below, use `brand/mark-small.svg`**: it drops the spokes and thickens the helix so it still reads.
- Mark anatomy, for reference: a lime wheel (juniper rind `#275E4B`, lime `#C6E06A`, pith `#EEF6D0`) whose vertical segment is a juniper double helix with four faint rungs.

### 3.4 Icons
Use the SVGs in [`icons/`](icons) as anonymous Blade components (`<x-icon.helix class="size-5" />`), stroke `currentColor`, 1.8 px on a 24 px grid. Heroicons through `flux:icon` are fine for anything not covered (for example `arrow-down-tray` on the PDF button), but the helix, capsule and group icons must come from this set.

## 4. App shell — `resources/views/layouts/app.blade.php`

Replace the header bar and `max-w-5xl` main with this layout:

```
body  bg-canvas text-ink font-sans antialiased
└─ div.flex.flex-wrap.min-h-screen
   ├─ aside   (flex: 1 1 210px; padding 28px 20px; column; gap 32px)
   │   ├─ brand row: <x-brand.mark size=34 /> + "Gene & Tonic" (18px/700, -0.01em)
   │   ├─ nav: Home · Supplements · Genetics · AI usage
   │   └─ (mt-auto) user row: avatar initials (26px circle, #E3DED6) + name, then Sign out; guests get "Sign in"
   └─ main    (flex: 999 1 560px; min-width 0; padding 24px clamp(16px,3vw,40px) 48px)
       └─ {{ $slot }}   (each page renders its own <x-ui.panel>)
```

- **Nav item:** 44 px tall, 8 px horizontal padding, 12 px gap, 20 px icon, 16 px/500, colour `ink-nav`. **Active:** colour `juniper`, weight 700, icon stroke 1.9, and `aria-current="page"`. Supplements is also active on `supplements.show`.
- **Mobile (< 1024 px):** the sidebar becomes a top bar (brand left, menu button right) that opens the nav as a sheet. `flux:sidebar` with `stashable` and `flux:sidebar.toggle` gives this behaviour. Confirm it is in free Flux with `search-docs`; otherwise use a small Alpine toggle. The nav must never be sticky or `100vh`.
- Keep `<flux:toast />`, `@fluxScripts` and the `@auth` sign-out `POST` form.

## 5. Shared UI components (app shell, `resources/views/components/ui/`)

These components are presentation only: no domain knowledge, no module imports. They're unprefixed because they belong to the app shell (boundary rule 5), and they take plain values (strings, ints, bools) so any module can use them without importing another module's types.

| Component | Purpose / spec |
|---|---|
| `x-ui.panel` | White page panel: `max-w-[1080px] mx-auto bg-surface border border-line rounded-panel p-[clamp(18px,3vw,36px)] flex flex-col gap-6`. A `narrow` prop sets max 900 px (supplement page). |
| `x-ui.page-header` | `h1` 30 px/700/-0.02em, line-height 1.15, plus a subtitle (15 px, `ink-muted`) and an `actions` slot. Wraps on small screens. |
| `x-ui.section` | `h2` 18 px/700 with an optional right-aligned link (14 px/600, juniper) and a slot. |
| `x-ui.stat` | Bordered card (`rounded-card`, 16×18 px padding): label 13 px `ink-muted`, value 28 px/700 tabular, caption 13 px. Must pass through `data-stat` (tests rely on it). |
| `x-ui.icon-tile` | Square tile (32, 40 or 44 px; `rounded-tile`) with a `tone` prop that maps to the `--color-tone-*` bg/fg pairs; slot holds an icon or 2–4 letters (12–13 px/700). |
| `x-ui.link-row` | Full-width `<a>` row: optional meta line (13 px `ink-subtle`), title (15 px/500), chevron. Variants: `bordered` (border `line`, `rounded-row`, 14×16 px padding) and `divided` (bottom border `line-soft`, min-height 64 px). |
| `x-ui.button` | Pill buttons, `rounded-full`, 46–52 px tall, 15–16 px/600. `primary` = juniper on white text; `secondary` = `surface-sunken` on ink; `on-tint` = white on ink (used on juniper-tint cards). Prefer `flux:button` with classes. If Flux's own classes win over yours, wrap it here instead of fighting it. |
| `x-ui.segmented` + `x-ui.segmented.item` | Pill tab control: track `surface-sunken`, `rounded-full`, 4 px padding; active item white with `shadow-segment`, 700 weight; inactive items `ink-nav`/600. Keeps `role="tablist"`/`role="tab"`/`aria-selected`. **Restyle the existing `x-tabs` / `x-tabs.item` to this look instead of replacing them**, so `supplements::show` keeps its API. Scroll horizontally on phones. |
| `x-ui.chip` | Filter chip `<button>`: 36 px tall, `rounded-tile`, 13 px/600. Inactive: white with `line-input` border. Active: juniper fill, white text, `aria-pressed="true"`. Optional count after the label. |
| `x-ui.status` | Dot + uppercase label (12 px/700, tracking .04em). Tones: `ok`, `warn`, `bad`, `neutral`, `active`. |
| `x-ui.progress-steps` | For active research: "Searching sources · step 2 of 4" (13 px/600 juniper) over a 4 px bar (`juniper-tint` track, juniper fill at n/4). |
| `x-ui.grade` | Evidence badge: bordered pill (`line-input`, 8 px radius), 8 px dot + 11 px/700 uppercase label. See §6.2. |
| `x-ui.allele-pair` | **Signature element.** Props: `alleles` (array of two letters), `effect` (?string), `size` (26/28/30). Each allele is a circle; an allele equal to `effect` is filled juniper with white text, otherwise it's an outlined circle (1.5 px `#D5DBD7`). Output `aria-label="Genotype G T; one copy of effect allele G"`. Callers split the genotype themselves (Genetics already has `Modules\Genetics\Data\Genotype`) and pass plain strings. |
| `x-ui.copies` | "1 COPY" / "2 COPIES" (11 px/700, tracking .06em, `ink-muted`). |
| `x-ui.notice` | Info row: `lime-tint` background, `rounded-row`, info icon in juniper, 14 px `ink-body`. Replaces the zinc `flux:callout` used for disclaimers. |
| `x-ui.dropzone` | `<label>` wrapping a visually hidden `<input type="file">`: dashed 1.5 px border `#C9D9CF`, background `#FAFCFA`, upload icon, title, help text, and a "Choose file" pill. Shows the chosen filename and upload progress. |

Hit targets are at least 44 px everywhere. Use real `<button>`, `<a href>` and `<label for>` elements. Every input has a label (visually hidden where the mock shows none).

## 6. Domain → UI mappings

### 6.1 `ResearchStatus` → `x-ui.status`
| Case | Tone | UI |
|---|---|---|
| Queued | neutral | "Queued" + `progress-steps` step 1 of 4 |
| Searching | active | "Searching sources" + step 2 of 4 |
| Analyzing | active | "Analyzing" + step 3 of 4 |
| Complete | ok | Counts line ("3 effects · 2 studies") instead of a badge |
| Failed | bad | `research.error` in `bad-text` + **Retry** button |

The enum's existing `color()` returns Flux colour names. Leave it alone and map to tones in Blade (or add a `tone()` method). Keep the `data-status` attributes the stats tests look for.

### 6.2 `EvidenceGrade` → `x-ui.grade`
The mocks say "Grade A" and "Grade C", but the enum has **three levels** (`strong`, `moderate`, `limited`), not letters. **Show the words, not letters**:

| Case | Dot | Text colour | Label |
|---|---|---|---|
| Strong | `ok` | `ok-text` | STRONG |
| Moderate | `juniper` | `juniper` | MODERATE |
| Limited | `warn` | `warn-text` | LIMITED |

### 6.3 `GeneRelation` → relation badge (inside "Your genes")
Helps → `ok` · Caution → `warn` · Related → `neutral`. Always show the relation label next to the genotype, because the mocks' "sits on this pathway" copy is deliberately neutral.

### 6.4 `VariantGroup` → icon tile
Add a `tone()` (and optionally `icon()`) method to the enum (`Modules\Genetics\Enums` is public), or map in Blade:

| Case | Icon (`icons/`) | Tone |
|---|---|---|
| Methylation | `helix` | green |
| VitaminsMinerals | `capsule` | butter |
| MetabolismWeight | `group-flame` | peach |
| HeartLipids | `group-heart` | coral |
| BrainMood | `group-smile` | blue |
| Hormones | `group-drop` | rose |
| DetoxDrugResponse | `group-shield` | teal |
| SleepCircadian | `group-moon` | indigo |
| AntioxidantInflammation | `group-leaf` | leaf |
| Other | `group-dots` | stone |
| *(not looked up)* | `search` | white tile on a `lime-tint` row |

### 6.5 Effect allele for the allele pair
- **Genetics pages:** `VariantData::$riskAllele` is the plus-strand effect allele (see the allele convention in CLAUDE.md). Fill the alleles that equal it. When `riskAllele` is null, render both outlined and hide the copies label.
- **Supplement "Your genes" card:** `GeneticMatchData` has `genotype` and `copies` but **not the allele itself**. Add `public ?string $allele` to `GeneticMatchData` (Recommendations owns it), set from `GeneInteractionData::$allele`, update `FakeGeneticRelevanceReader`, and render it to the signed-in owner only, as today.

## 7. Screens

Each screen below lists its sections from top to bottom, with **data sources**, **interactions** and **states**. Measurements are in the static HTML; copy them rather than eyeballing the PNGs.

### 7.1 Home — `pages::dashboard` · [`01-home.html`](screens/01-home.html)

The app-shell page stays a composer. It lays out widgets that each module owns, so no module reads another's data.

1. **Header:** "Good morning/afternoon/evening, {first name}" (guests: "Welcome to Gene & Tonic") and a subtitle "Sunday, October 25 · {n} supplements · {n} variants" (drop the variants part for guests). Right side: a search field.
   - *Search is new.* Ship it hidden, or make it a link to `/supplements?search=` once the catalog supports search. Don't ship a dead input.
2. **Gene match hero** (`juniper-tint` card) → **new** `recommendations::top-match` component.
   - Needs a new contract method, e.g. `GeneticRelevanceReader::topForUser(int $userId, int $limit = 1): list<GeneticRelevanceData>`, ranked by match count, plus the supplement name from `SupplementResearchReader::forSupplement()`. Add it to the fake too.
   - Shows: kicker "GENE MATCH"; title "{n} of your variants relate to {Supplement}" (the mock says "sits on a pathway with…", but keep it neutral because relations can be Caution); one-line body; buttons "Open {supplement}" (primary) and "Download PDF" (`on-tint`, via the `supplement.header` slot); and on the right, up to 3 rows of gene name + allele pair.
   - States: guest → "Sign in to see how supplements relate to your genes"; no profile → "Add your genetic results" → `/genetics`; no matches → hide the hero.
3. **Stat row** (4 × `x-ui.stat`, auto-fit min 200 px), each a small widget from its owning module:
   - Supplements (`supplements::stats`, restyled): `total()`; caption "Research complete" or "{n} in progress" from `countByStatus()`.
   - Variants (Genetics widget, auth): `GeneticProfileReader::forUser()->count()`; caption "{n} looked up" (variants whose `status` is `Complete`).
   - Gene matches (Recommendations widget, auth): total matches across supplements; caption "Across {n} supplements".
   - AI spend (`intelligence::usage-summary`, restyled): `costLast24hUsd` (2 decimals); caption "{callsLast24h} calls today".
   - Keep the existing `data-stat` values. Guests see the two public stats only.
4. **Two columns** (auto-fit min 400 px):
   - *Latest research* (Supplements): `recentlyCompleted(4)` as `x-ui.link-row bordered`. Meta line = `Complete · {diffForHumans}`. Title = "{Name}: {effects} effects across {studies} studies". The mock's topic tags ("Methylation · Heart") have no data; leave them out (§9).
   - *Your genes* (Genetics, auth): the top 5 `VariantGroup`s by count as `x-ui.link-row divided` with icon tiles ("12 variants"), linking to `/genetics?group={value}`. Below them, a `lime-tint` banner: "{n} variants are waiting for a lookup" + **Look up now**, which calls a new `lookUpAll` action using `QueueLookups::handle($rsids, $cap)` (it already skips rows being looked up). Pick a sensible cap and show a toast with the count queued. Guests: a "Sign in to add your genetic results" card.
5. **Your supplements** (Supplements): grid of cards (auto-fit min 220 px). Each card: icon tile with a 2–4 letter abbreviation (derive from the name, e.g. "Mg", "Fe", "Tyr", "MTHF"; a small config map for known names is fine), name 16 px/700, then "{effects} effects" and "Researched {diffForHumans}". Gene-match counts on cards are out of scope: Supplements can't read Recommendations (§9).

Empty states: no supplements → the "Your supplements" area becomes an add-a-supplement prompt linking to `/supplements`. No variants → the hero and Your genes become an upload prompt.

### 7.2 Supplements — `supplements::catalog` · [`02-supplements.html`](screens/02-supplements.html)

Keep all existing behaviour: `add` (with `ActionThrottle`), `retry`, the delete modal, pagination (10) and `wire:poll.3s` while any run is active.

1. Page header "Supplements" + subtitle "Name a supplement. We read the published research and check it against your genes."
2. **Add form:** a pill input (52 px, `surface-input`) + primary "Research it" (`wire:submit="add"`, `name` model, maxlength 100). Validation errors show under the input. The toast stays "{name} queued for research."
3. **Split view** (bordered, `rounded-section`, two columns at ≥ 720 px, stacked below):
   - **Left list** (`surface-muted`):
     - Status filter chips: All {n} · Complete {n} · In progress {n}, plus Failed {n} only when non-zero. Counts come from `countByStatus()`. The filter itself needs a repository parameter (new, small) on `paginateWithResearch`.
     - Rows: name 16 px/600 + one line by status (§6.1). The selected row is white with a 1.5 px juniper border.
     - Pagination sits below the list.
   - **Right preview** (selected supplement; `#[Url(as: 'selected')] public ?int $selectedId`, defaulting to the first complete one):
     - Kicker "TOP EFFECTS IN {NAME}" + "Researched {date}".
     - Top 3 effects (strong first) as numbered cards: claim + `x-ui.grade`.
     - A compact gene strip, rendered through a **new PageActions slot `supplement.preview`** registered by Recommendations. Supplements must not reference Recommendations directly (boundary rules 1 and 5).
     - Actions: "Open {name}" (primary → `supplements.show`), Re-run (`retry`), the `supplement.header` slot (PDF download menu), and a ghost Delete that opens the existing modal.
   - **Phone:** hide the preview; rows link straight to `supplements.show`.
4. Empty state: "No supplements yet. Add one above to start researching."

### 7.3 Supplement — `supplements::show` · [`03-supplement-detail.html`](screens/03-supplement-detail.html)

Use `x-ui.panel narrow` (900 px).

1. **Breadcrumb:** Supplements › {Name} (`nav aria-label="Breadcrumb"`). It replaces the back link.
2. **Hero** (`juniper-tint`, `rounded-section`, 24 px padding):
   - `h1` name (30 px).
   - Status, when not complete (status + `progress-steps`, keeping the poll).
   - The capsule icon tile on the right.
   - `summary` (15 px/1.6, max ~680 px).
   - Actions: the `supplement.header` slot (Reports download menu, restyled: a small template `flux:select` + primary "Download PDF"), then Re-run (secondary, disabled while active).
   - **Not built:** the mock's kicker ("B VITAMIN · ACTIVE FOLATE"), topic tags and "Add to stack" button. There is no data or feature for them, so leave them out (§9).
3. **Notice:** the existing disclaimer as `x-ui.notice`.
4. **Tabs:** keep all six existing tabs with their counts (Overview, Effects, Mechanisms, Studies, Genetics, Sources) in the restyled segmented control. The mock shows four only to save space.
5. **Overview tab:**
   - "How does {name} fit my genes?" row (`lime-tint`, helix icon) → `$set('tab', 'genetics')`. Show it only to signed-in users who have a profile.
   - Two columns (auto-fit min 340 px):
     - **Health effects:** the first 4 effects as rows (icon tile 32 px using a neutral tone, claim, grade on the right), then "Show all effects" → `tab=effects`. *The mock's effect filter chips (Methylation/Heart/Mood) have no data and are not built.*
     - **Your genes:** the `supplement.panels` slot (the Recommendations genetic panel), restyled. Header: a 56 px ring with the match count, "Your genes", and "{n} of your variants relate to this supplement". Rows: gene (15 px/700) + rsID (13 px), allele pair, copies, relation badge, and the explanation (14 px, `ink-body`) below. Keep `data-panel="your-genetics"` and `data-genotype`. Keep all four existing states: guest, no profile, not checked, no matches.
   - **Safety notes** (`safetyNotes`) as a final section. The mock calls this section "Dosage"; use "Safety notes", because that is what we store.
6. **Effects / Mechanisms / Studies / Sources / Genetics tabs:** same row language (bordered rows, 15 px titles, 13 px meta, grade or relation badges, the existing `x-supplements::source-badges`). The Genetics tab lists the population-level `geneInteractions` (gene, rsID, allele, relation, explanation) above the user's panel.

### 7.4 Genetics — `genetics::profile` · [`04-genetics.html`](screens/04-genetics.html)

This becomes a full page at `/genetics` (`auth`). Keep every existing action, property and poll: `file` + `uploadResults`, `rsid`/`genotype`/`gene` + `addVariant`, `group`, `search` (debounced), `toggleSection`, `lookUp`, and `wire:poll.3s` while uploads or lookups are active.

1. Page header "Genetics". Subtitle: "{n} variants from {n} upload(s). Raw files are deleted after processing; only marker lines ever reach the AI model." That subtitle replaces the mock's "parsed on our server", which isn't accurate for PDFs (see the domain constraints in CLAUDE.md).
2. `x-ui.notice` with the existing genetics disclaimer.
3. **Two cards** (auto-fit min 380 px):
   - **Upload results:**
     - `x-ui.dropzone` bound to `wire:model="file"`. Help text: "23andMe, AncestryDNA, MyHeritage, FamilyTreeDNA, a marker CSV or a PDF · up to {max_upload_kb/1024} MB". Show progress through Livewire's upload events and keep the existing submit.
     - Below it, the uploads list: file icon tile, `original_name`, `readSummary()` (it already produces "73 read, 0 via AI, 10 skipped"), "{queued_lookups} queued for lookup", status (`UploadStatus` → tone: active = `active`, Complete = `ok`, Failed = `bad` with `error`).
   - **Add a single SNP:** the three labelled inputs (rsID, Genotype, Gene *(optional)*) + secondary "Add SNP". Validation is unchanged.
4. **Your variants:**
   - Header + pill search (`search`).
   - Group chips replace the `flux:select`: "All {n}" + one chip per group with variants, bound to `group`. The mock's "Linked to a supplement" chip is out of scope (§9). "Two copies" is optional but cheap: Genetics can compute it from `genotype` and `riskAllele`.
   - Sections: restyle `x-genetics::variant-list`. Keep `<details>`/`<summary>` (or a button with `aria-expanded`) and `toggleSection`.
     - Group header: icon tile 40 px + label 16 px/700 + "· {n} variants" + chevron. Open sections get a `surface-muted` header.
     - **Variant row** (wraps on phones: identity and alleles on one line, text below): gene 16 px/700 + rsID 13 px; allele pair (30 px); `affects` (14 px/1.55); then a `juniper-tint` note "**Your {genotype}, {one copy|two copies}:** {genotypeEffect}" when `genotypeEffect` is set. The mock's "Linked: Methylfolate" chip is out of scope.
     - **Unlooked section** (`key === 'unlooked'`): `lime-tint` header row "Not looked up yet · {n} variants, {queued} queued" + **Look up now** (the same `lookUpAll` as Home). Rows keep "Looking up…", "Lookup failed" and the per-row **Look up** button.
5. Guests never reach this page (`auth`), so drop the guest branch of `genetics::summary`, or delete the component if nothing else uses it.

### 7.5 AI usage — `intelligence::usage-log` · [`05-ai-usage.html`](screens/05-ai-usage.html)

1. Page header "AI usage" + "Every model call goes through one gateway and is logged with its tokens and cost." The mock's 24h / 7 days / All time segmented control needs a time window on `summary()` and `paginateLatest()`. Build it if it's cheap; otherwise leave it out.
2. **Stat row** from `UsageSummary`:
   - Calls (caption "{failedCalls} failed" in `bad-text` when > 0).
   - Estimated cost (4 decimals; caption "About {cost/calls} a call", computed).
   - Input tokens and output tokens (no captions; the mock's captions were illustrative).
3. **Cost by task** (new): `AiRequestRepository::costByProfile()` returns rows of `{profile, operation, calls, failed, cost}`. Draw each row as label + "{calls} calls" + a 12 px juniper bar (width relative to the largest cost, minimum 2%) + cost.
   - Human labels: Intelligence has no domain knowledge, so add a `label` key to each module's profile config (`supplements.research` → "Supplement research", `genetics.lookup` → "Gene lookup", `genetics.group` → "Variant grouping", `genetics.extract` → "Marker extraction") and expose it through `ProfileData`.
   - Firecrawl `search` rows are credits, not tokens. Show them as "Web search · {credits} credits".
4. **Recent calls:** keep `paginateLatest(20)` and every column of information, re-laid out in a bordered, horizontally scrollable table:
   - Time (`diffForHumans`, exact time in `title`).
   - Task: the profile label (700) with "provider / model · effort" as a 13 px second line.
   - Tokens in / out (or credits) and latency.
   - Cost.
   - Status as `x-ui.status` (OK / FAILED), with the truncated error beneath.
   - "Export CSV" is out of scope (§9).

## 8. Responsive and accessibility

- **Breakpoints:** the layouts above use intrinsic grids (`repeat(auto-fit, minmax(min(Npx, 100%), 1fr))`) and flex-wrap, so they collapse without many breakpoints. The sidebar → top bar switch happens at `lg`. Check every screen at 390 px against the `*-mobile.png` screenshots.
- **Tables** (AI usage) scroll inside their own `overflow-x-auto` box and never scroll the page.
- **Contrast:** body and caption colours in `tokens.css` pass 4.5:1 on white and on the canvas. White on juniper is 7.5:1. Never put text on lime.
- Colour never carries meaning alone: every status dot sits next to a word, and the allele pair adds an `aria-label`.
- **Focus:** a visible ring in `accent` on every interactive element. Don't remove Flux's focus styles.
- **Livewire action names:** never name an action after a `$wire` alias (`upload`, `set`, `dispatch`, `refs`…). New actions in this handoff: `lookUpAll`, `selectSupplement`. `tests/Feature/LivewireActionNamesTest.php` will catch a clash.

## 9. What the mocks show that we don't build (yet)

These items are in the mocks for the sake of composition. **Don't fake them.** Leave each one out unless the work is listed as small and you choose to do it.

| In the mock | Status | What it would take |
|---|---|---|
| "Add to stack" button (supplement page) | **Omit** | A stack feature: new domain and tables |
| Kicker "B VITAMIN · ACTIVE FOLATE" and topic tags | **Omit** | New research fields filled by the research prompt |
| Effect filter chips (Methylation/Heart/Mood) | **Omit** | Effect categories in `EffectData` |
| "Grade A / C" letters | **Replaced** | Words from `EvidenceGrade` (§6.2) |
| "Dosage" section | **Replaced** | Shows `safetyNotes` as "Safety notes" |
| Home search box | Later | Catalog search, then link to it |
| Gene match hero, Gene matches stat | **Build** | `GeneticRelevanceReader::topForUser()` + fake |
| Allele pair on the supplement page | **Build** | `GeneticMatchData::$allele` |
| Supplements status filter | **Build (small)** | Status parameter on `paginateWithResearch` |
| Supplement preview gene strip | **Build** | `supplement.preview` PageActions slot |
| "Look up now" (all) | **Build (small)** | `lookUpAll` using `QueueLookups` |
| "Two copies" variant filter | Optional (small) | Genetics-internal filter |
| "Linked to a supplement" filter, "Linked: X" chips, "3 linked to methylfolate" | Later | A reverse lookup (variant → supplements) owned by Recommendations |
| Gene-match counts on Home supplement cards | Later | A PageActions slot on the card |
| AI usage period control | Optional | Time window on the repository methods |
| AI usage cost by task | **Build** | `costByProfile()` + profile labels |
| AI usage "Export CSV" | Later | A streamed CSV route |
| Sample numbers in the mocks (grades, cost split, Omega-3 in progress, call rows) | n/a | Illustrative only; real data replaces them |

## 10. Implementation plan

Each step is one reviewable change, ending with `composer test` green (Pint, PHPStan level 7, Pest).

1. **Foundations:** tokens, Figtree, light-only, brand assets and favicons, `APP_NAME`, icon components, the `x-ui.*` components, and the restyled `x-tabs`. Add a local-only `/_ui` route (`app()->isLocal()`) that renders every `x-ui` component in every state. It's useful for the showcase and for review.
2. **Shell and routes:** sidebar layout with mobile behaviour, the new routes from §2, `?tab=` redirects, and link updates. Before restyling any page, move each tab's component onto its route.
3. **Supplements index:** split view, status filter, `supplement.preview` slot.
4. **Supplement page:** hero, tabs, Overview layout, restyled genetic panel and download menu, `GeneticMatchData::$allele`.
5. **Genetics page:** dropzone, uploads list, add-SNP card, chips, restyled variant list, `lookUpAll`.
6. **AI usage:** stats, `costByProfile()` + labels, table.
7. **Home:** `topForUser()` + hero, stat widgets, Latest research, Your genes, Your supplements.
8. **Polish:** empty and loading states (`wire:loading` on buttons, skeleton rows while polling), a phone pass, an accessibility pass (keyboard-only run through every page).

### Tests to touch
- Existing feature tests assert `data-stat`, `data-status`, `data-panel`, `data-genotype`, tab roles and some copy. Keep the hooks. When copy changes on purpose, update the assertion in the same change, never the hook.
- Likely affected: `tests/Feature/DashboardTest.php` (tabs become routes), `tests/Feature/ResearchToRelevanceTest.php`, `Supplements/tests/Feature/{CatalogTest,SupplementPageTest,StatsTest}`, `Genetics/tests/Feature/{UploadPageTest,ProfilePageTest,VariantListFilterTest}`, `Intelligence/tests/Feature/UsageViewsTest`, `Recommendations/tests/Feature/GeneticPanelTest` and `Reports/tests/Feature/DownloadMenuTest`.
- New tests:
  - The routes and `?tab=` redirects, including `auth` on `/genetics`.
  - The status filter.
  - `topForUser()` (real and fake).
  - `GeneticMatchData::$allele`.
  - `costByProfile()`.
  - `lookUpAll` (respects the cap and skips rows already being looked up).
  - An `x-ui.allele-pair` render test: effect allele filled, null effect, and a homozygous genotype.
- Every test keeps `Http::preventStrayRequests()` and the Intelligence fake. No test launches Chrome unless `RUN_CHROME_TESTS=1`.

## 11. Acceptance checklist

- [ ] Every page matches its `screens/*.html` at 1440 px and its `*-mobile.png` at 390 px: spacing, sizes, colours, type.
- [ ] Only Figtree is loaded; no serif anywhere; no `dark:` styling is visible.
- [ ] The sidebar nav works with the keyboard, shows `aria-current`, and collapses on phones.
- [ ] Every number on screen comes from a DTO or repository; nothing from §9's Omit list appears.
- [ ] The allele pair renders the effect allele correctly, including the null and homozygous cases.
- [ ] Genotypes render only to their owner (unchanged privacy rules), and genetics pages show the disclaimer.
- [ ] Old `/?tab=` links redirect.
- [ ] `composer test` passes; no new PHPStan baseline entries.
- [ ] The favicon, title and PDF header say "Gene & Tonic".
