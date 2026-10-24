# PRD: Genetics module

> Plan: genetics-module. Keep it short. Every requirement has a stable id and a one-line acceptance check.

## Problem
The app researches supplements and exports PDF reports, but it knows nothing about the person reading them. The product's core story is "your genetics plus supplement research", and the interview demo on Monday 2026-10-26 has to show it end to end. Genetic results arrive in many shapes: fixed-column raw exports from 23andMe or AncestryDNA, marker CSVs, and free-form PDF reports. Turning them into a usable profile has to cost as little LLM spend as possible. Genetic data is sensitive and has to belong to a signed-in user.

## Solution
A user signs in as the seeded demo user and opens **My genetics**. There they can upload a file (.txt, .csv or .pdf, up to 25 MB) or add a single SNP by hand. A queued job handles the rest:
- It detects the format and parses it for free when it can.
- When it can't, it extracts the text locally and asks a cheap, configurable AI model to pull out only the marker lines.
- It stores the user's genotypes. Uploads and manual entries merge, and the newest genotype wins for each SNP.

The page polls while the job runs. When it finishes, each SNP shows its gene, its category, what it affects and what the user's genotype means.

Gene knowledge lives in a shared **gene reference table** with no link to any user:
- It is seeded with about 30 verified nutrigenomic SNPs.
- Unknown SNPs are looked up once each with Firecrawl plus a structured AI call, capped at 15 per upload.
- Every result is cached for everyone.

Supplement research gains cited **gene interactions**. These are generic facts such as "methylfolate is relevant to MTHFR rs1801133 T carriers". They add one extra Firecrawl search and no extra AI call.

A new thin **Recommendations** module matches the signed-in user's genotypes against those interactions using rules, with no AI. It shows a **Your genetics** panel on the supplement page. It also adds a **Your genetic relevance** section to the PDF when the owner downloads it; anonymous downloads stay as they are today.

## Requirements

### Auth
- **FR-01** The app shell has a Livewire login page (`/login`, named `login`) with email and password, plus a POST logout. It uses no new package. Failed logins are rate-limited to 5 per minute per email and IP. The layout header shows "Sign in" for guests, and the user's name with "Sign out" for signed-in users. Check: feature tests cover a valid login (redirect to My genetics), a bad password (error and still a guest), the 6th failed attempt (throttled), and logout.
- **FR-02** `DatabaseSeeder` creates one demo user from `DEMO_USER_NAME`, `DEMO_USER_EMAIL` and `DEMO_USER_PASSWORD`, with documented defaults. Seeding is idempotent. Check: running the seeder twice leaves one user who can log in.
- **FR-03** Every Genetics route and action requires `auth`. Guests are redirected to `login`. The dashboard, supplement catalog, supplement page and PDF route stay public. Check: a guest GET on `/genetics` redirects to `/login`, and the existing public-page tests still pass.

### Genetics module: data
- **FR-04** A `Genetics` module exists (generated with the project's stubs). It depends only on Intelligence and Shared public namespaces and is not used by Supplements or Intelligence. Check: a Genetics `BoundaryTest` (Pest `arch()`) fails if Genetics uses another module's private namespaces or any `Modules\Supplements`/`Modules\Recommendations`/`Modules\Reports` code, and Supplements and Intelligence boundary tests fail if they use `Modules\Genetics`.
- **FR-05** A global `gene_variants` reference table holds one row per rsID with no user column. Each row has:
  - the rsID (unique);
  - the gene symbol;
  - the plus-strand reference and risk alleles;
  - a short category, such as "Methylation" or "Vitamin D";
  - "what it affects", in a sentence or two;
  - per-genotype effects;
  - cited sources (url, title, domain);
  - a lookup status (`seeded`, `pending`, `looking_up`, `complete`, `failed`).

  Check: the migration and model test round-trips a row, and inserting a duplicate rsID fails.
- **FR-06** A seeder loads about 30 curated nutrigenomic SNPs, including the MTHFR, COMT, VDR, GC, FUT2, BCMO1, APOE, CYP1A2, MTR, MTRR, PEMT, SOD2, FADS1 and HFE variants. Each risk allele is given on the plus strand and checked against its dbSNP page, and that dbSNP URL is stored as the row's source. Check: a seeder test asserts the count, that every rsID matches `^rs\d+$`, that alleles come from `ACGT`, and that every row has a dbSNP source URL. The plan's task records how each allele was checked.
- **FR-07** A `user_variants` table holds one row per user and rsID (unique on `user_id`, `rsid`). Its columns are `user_id` (an FK to `users`), the rsID in plaintext, a normalised genotype under an **encrypted cast**, the source (upload file name or "manual"), and the upload id when it came from a file. Check: a test shows the raw DB column is not the plaintext genotype while the model reads it back. A second write for the same user and rsID updates the row, so the newest wins.
- **FR-08** Genotype normalisation:
  - Input is upper-cased and stripped of the separators `/`, `;`, `|` and spaces.
  - Alleles are sorted, so `TC`, `C/T` and `C;T` all become `CT`.
  - Single-allele calls on X, Y or MT are allowed.
  - The alphabet is `A C G T D I`.
  - No-calls (`--`, `00`, empty) are dropped.
  - When the reference row's plus-strand alleles don't contain the given alleles but do contain their complements, the genotype is complemented to the plus strand. Strand-ambiguous SNPs (A/T, C/G) are stored as given.

  Strand is resolved again when matching (FR-25), against the reference row's plus-strand alleles. This means a SNP stored while its reference row was still `pending` matches correctly once the lookup completes.

  Check: unit tests cover each rule, including a gene-strand MTHFR input that is stored as its plus-strand equivalent.
- **FR-09** Genetics exposes a `GeneticProfileReader` contract: `forUser(int $userId): GeneticProfileData`. The DTO is a list of `VariantData`, each with rsid, gene, genotype, the reference row's plus-strand reference and risk alleles, category, what it affects, the effect for this genotype, lookup status and source. The contract ships with an in-memory fake under `Testing`. Check: a reader test returns only that user's variants joined to reference data, and the fake satisfies the contract in other modules' tests.

### Genetics module: input
- **FR-10** **My genetics** (`/genetics`, an auth-only Livewire page) lists the user's SNPs with gene, rsID, genotype, category, what it affects and the user's genotype effect, sorted by gene. SNPs that haven't been looked up show "Not looked up yet" and a **Look up** button. The dashboard gains a **Genetics** tab: signed-in users see their SNP count and a link to My genetics, and guests see a sign-in prompt. Check: a page test shows a seeded variant's fields and a pending variant's button, and doesn't show another user's variants. The dashboard test covers the new tab for a guest and for a signed-in user.
- **FR-11** **Add a SNP**: a form takes the rsID (`^rs\d{1,10}$`), the genotype (1–2 alleles, FR-08 alphabet) and an optional gene symbol. It stores or updates the variant with source "manual". When the rsID isn't in the reference table, the form creates a `pending` reference row and queues its lookup. Check: valid input stores a normalised row, invalid rsIDs and genotypes show validation errors, and an unknown rsID dispatches the lookup job once.
- **FR-12** **Upload**: the page accepts one `.txt`, `.csv` or `.pdf` file of at most 25 MB. Livewire's temporary-upload rule and the Genetics validation both use the configured max. Livewire's temporary uploads also go to that disk: `temporary_file_upload.disk` is set to `genetics`, with the max rule, so no copy ever lands on the served `local` disk. The file is stored on a dedicated private disk (`genetics`, not served), a `genetic_uploads` row is created with status `queued`, and the processing job is dispatched. Check: a valid fake upload creates the row and dispatches the job, a `.exe` or oversize file is rejected, the disk config has no `serve`/`url`, and Livewire's temporary-upload disk is `genetics`.
- **FR-13** Format detection and deterministic parsing need no AI. The job recognises four formats and streams them line by line:
  - 23andMe (`rsid chromosome position genotype`, tab separated, `#` comments);
  - AncestryDNA (`rsid chromosome position allele1 allele2`);
  - MyHeritage/FTDNA CSV (`RSID,CHROMOSOME,POSITION,RESULT`);
  - a marker CSV, whose header row has an rsID column and a genotype column (case-insensitive aliases), with an optional gene column.

  Check: a fixture test per format parses the expected variants, and `FakeTextGenerator` records zero calls.
- **FR-14** What gets stored depends on the format. From the three vendor raw exports, only SNPs whose rsID is already in the reference table are kept. From a marker CSV or a PDF, every extracted SNP is stored, and unknown rsIDs get `pending` reference rows. Check: a raw-export fixture with 1 known and 5 unknown rsIDs stores 1 variant, and a marker CSV with the same rows stores 6 and creates 5 pending reference rows.
- **FR-15** Fallback extraction runs when no deterministic format matches, and for every PDF:
  1. The text is extracted locally, behind a small Genetics `TextExtractor` interface so tests can use a fake. PDFs use `smalot/pdfparser`, a new dependency approved in discovery, and one test runs against a real PDF fixture.
  2. Only lines that contain an rsID or a gene symbol from the reference table are kept, capped by a configurable character limit.
  3. One structured call is made through Intelligence with profile `genetics.extract`, whose provider, model, effort, max tokens, timeout and prompt views all come from config/env. The default is the cheap model.
  4. The schema returns `variants[{rsid, gene?, genotype}]`.

  Check: a PDF fixture test asserts one `generate` call whose prompt contains the marker lines and does not contain a non-marker line such as the patient name. The stored variants match the fake's output.
- **FR-16** Extraction output is untrusted. Each item must match the rsID regex and the FR-08 alphabet, gene symbols must match `^[A-Z0-9-]{1,15}$`, and anything else is dropped. The prompt tells the model to treat the document text as data and ignore any instructions in it. Check: a test feeds fake output with a bad rsID, a bad genotype, an HTML gene and an injected instruction. Only the valid rows are stored, and the page escapes everything.
- **FR-17** Upload status moves through `queued → parsing → looking_up → complete | failed`, with counts (found, stored, queued for lookup) and a UI-safe error message. My genetics polls every 3 s while any upload or lookup is active and stops when none is. Check: a job test asserts the status sequence and counts. A page test shows `wire:poll` only while a run is active.
- **FR-18** The raw upload, including its Livewire temporary copy, is deleted from the `genetics` disk when the job finishes, whether it succeeds, fails or is retried for the last time. Check: tests for the success and failure paths assert the file is gone.

### Genetics module: gene lookup
- **FR-19** For each `pending` reference row, a queued lookup job does three things:
  1. It runs one Firecrawl search through `WebResearcher` on configurable SNP domains (default `snpedia.com`, `ncbi.nlm.nih.gov`, `ebi.ac.uk`).
  2. It makes one structured call with profile `genetics.lookup`, configurable like FR-15. The call returns gene, category, what it affects, risk allele, per-genotype effects and `source_ids`.
  3. It validates the result (cited ids must exist, field lengths are bounded, alleles come from the alphabet) and marks the row `complete`, or `failed` with a UI-safe error.

  Only one lookup can run per rsID at a time. Check: a test with the fakes stores the reference data and its sources. Uncited output is dropped, and a provider failure marks the row `failed`.
- **FR-20** An upload queues at most `GENETICS_LOOKUP_CAP` (default 15) lookups. The remaining unknown SNPs stay `pending`. **Look up** on a pending or failed SNP queues that one lookup. Check: an upload with 20 unknown SNPs dispatches 15 lookup jobs. Clicking Look up dispatches one and is ignored while that lookup is running.

### Supplements: gene interactions
- **FR-21** Supplement research runs a second Firecrawl search for gene-specific sources (`"<name> gene polymorphism nutrigenomics"`, same trusted domains, with a configurable limit). The sources are merged into the one existing AI call, which stays one call. The research schema gains `gene_interactions[{gene, rsid?, allele?, relation: helps|caution|related, explanation, source_ids}]`. Check: a pipeline test asserts 2 searches and 1 generate call, and the prompt contains sources from both searches.
- **FR-22** Interactions are validated before they are stored in a new `supplement_gene_interactions` table:
  - Gene symbols, rsIDs and alleles are checked against their patterns.
  - `relation` must come from the enum.
  - The explanation goes through `prose()`.
  - Each interaction needs at least one valid cited source.

  The interactions are exposed as `GeneInteractionData` on `SupplementResearchData`, with no user data. Check: a builder test drops invalid or uncited interactions, and a reader test returns the stored ones.
- **FR-23** The supplement page gets a **Genetics** tab listing the generic interactions with citations. Research completed before this change shows "Re-run research to check genetic interactions." Check: a page test shows an interaction and its source marker, and old research shows the hint.

### Recommendations: matching
- **FR-24** A `Recommendations` module exists. It depends on the public namespaces of Genetics, Supplements and Shared. Supplements and Genetics never use it. Check: boundary tests in all three directions.
- **FR-25** `GeneticRelevanceReader::forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData` matches with rules and no AI:
  - An interaction with rsID and allele matches when the user carries the allele. When the interaction allele is not one of the reference row's plus-strand alleles but its complement is, it is complemented first, because research often quotes gene-strand alleles such as MTHFR "T". The result reports 1 or 2 copies.
  - An interaction with rsID only matches when the user has that SNP.
  - A gene-only interaction matches every user SNP whose reference gene equals it.

  Each match carries the relation, explanation, the user's genotype and the interaction's source keys. Check: unit tests cover each rule, plus no match, the complemented-strand case and two copies against one. `FakeTextGenerator` records zero calls.
- **FR-26** Shared's page-action registry gains a second slot, `supplement.panels`. The supplement page renders it below the tabs, passing the supplement id. Recommendations registers a **Your genetics** panel there:
  - For guests it shows "Sign in to see how this relates to your genetics."
  - For a signed-in user with no SNPs it links to My genetics.
  - Otherwise it lists matched variants with gene, rsID, genotype, copies, relation (helps or caution badge) and explanation. When nothing matches it says "No known interactions with your tested variants."

  Check: page tests cover all four states, and Supplements' boundary test still forbids `Modules\Recommendations`.

### Reports: genetics section
- **FR-27** When the requester is signed in and has at least one match, the supplement PDF gains a **Your genetic relevance** section after Effects in both templates. It shows the user's matches, with citation markers into the same numbered source list. Guests, and users with no matches, get today's report unchanged. Reports reads the matches only through `GeneticRelevanceReader`. Check: with `Pdf::fake()`, a signed-in user with a match sees the section and genotype, a guest sees no section, and the Reports boundary test allows only the public namespaces of Recommendations.

### Hardening (brought in from earlier follow-ups)
- **FR-28** Rate limits:
  - Supplement add, retry and re-run: 10 per minute per IP.
  - Genetics upload: 5 per minute per user.
  - Add SNP: 20 per minute per user.
  - Look up: 10 per minute per user.

  Over the limit, the action shows "Too many requests, try again in a minute." and does nothing. Check: a test per limiter at limit + 1.
- **FR-29** Each module reaps its own stale runs, because a module writes only its own tables. Supplements has `supplements:reap-stale` for research runs. Genetics has `genetics:reap-stale` for uploads and reference lookups. Each is scheduled every 5 minutes and marks runs active longer than a configurable age (default 15 min) as `failed`, with "Timed out, try again." The scheduler is not part of Herd's `artisan dev`, so CLAUDE.md documents running `php artisan schedule:work`, and the demo does not depend on it. Check: a command test per module fails a stale run and leaves a fresh one alone.
- **FR-30** Source URLs are validated when they are stored, for both research and lookups:
  - `FILTER_VALIDATE_URL`, with the scheme `http` or `https`;
  - no userinfo;
  - an ASCII host;
  - at most 2048 characters.

  Invalid sources are dropped. Check: unit tests for each rejected shape.

### Docs
- **FR-31** Docs and config are updated:
  - In the CLAUDE.md module map, Genetics depends on Intelligence and Shared, Recommendations exists, and Reports depends on Supplements, Recommendations and Shared.
  - The "AI calls receive only the summarised profile" constraint gains the extraction exception (marker lines only, never full documents).
  - The "no authentication yet" line is replaced with how auth works.
  - Herd upload-limit steps are documented.
  - `.env.example` lists every new key (`DEMO_USER_*`, `AI_GENETICS_EXTRACT_*`, `AI_GENETICS_LOOKUP_*`, `GENETICS_LOOKUP_CAP`, `GENETICS_MAX_UPLOAD_KB`, `GENETICS_LOOKUP_DOMAINS`, the reaper age).

  Check: review the diff of CLAUDE.md and `.env.example`.
- **FR-32** Two synthetic demo files are generated around the demo supplement (methylfolate, whose interactions involve MTHFR, MTR, MTRR and FUT2):
  - a roughly 40-row 23andMe-style TXT that uses seeded rsIDs with risk genotypes for those genes, plus non-seeded rows that are filtered out;
  - a short report-style PDF, a minimal hand-built text PDF (not through the Reports `Pdf` facade), with a fake patient name and one or two unknown rsIDs so the lookup step shows.

  An artisan command, `genetics:demo-files`, writes both to a gitignored path (`storage/app/demo/`). Check: the command writes both files, `git check-ignore` reports them ignored, and uploading each through the page completes.

## Non-goals
- Registration, password reset and email verification, because the demo has one seeded user.
- A delete-my-genetic-data UI, because it wasn't picked for the time box. Raw files are still deleted after parsing (FR-18).
- Zip uploads, because users unzip before uploading.
- Upload history and multiple profiles per user, because the profile is one merged set.
- Dosing advice and AI-written personalised recommendations, because matching is rule-based over generic, cited interactions, which keeps genotypes away from the AI.
- Storing every SNP from a raw export, because it is bounded to the reference table to limit cost and how much sensitive data is held.
- Automatic re-research of existing supplements, because the user clicks Re-run.
- Arch tests beyond the per-module boundary tests, and detail-page polling for supplement research, because those follow-ups were declined.

## Open questions
- [x] Auth scope? (answer: hand-rolled login with a seeded demo user, `auth` on genetics only)
- [x] How are uploads parsed? (answer: deterministic first, then local text extraction plus a cheap configurable AI call on marker lines only)
- [x] What is kept from raw exports? (answer: only SNPs in the reference table)
- [x] How are unknown SNPs looked up? (answer: Firecrawl plus one AI call per SNP, cached globally, capped at 15 per upload)
- [x] How is supplement relevance computed? (answer: generic cited interactions from the existing research call, then rule matching in Recommendations)
- [x] Upload size? (answer: 25 MB. The user raises Herd's PHP `upload_max_filesize`/`post_max_size`, and nginx `client_max_body_size` if needed, before the demo)
- [x] Report route session? (answer: module routes run in the `web` group, checked with `route:list`, so `auth()->user()` is available)

## Unhappy paths
- [FR-12] When the file is the wrong type or over the limit, a validation error appears, no row is created and no job is queued.
- [FR-13/15] When a file has no recognisable markers (neither the deterministic parse nor the extraction finds a valid SNP), the upload is `failed` with "No genetic markers found in this file." and the file is deleted.
- [FR-15] When the extraction AI call fails, the job retries once and then marks the upload `failed` with a generic message. The provider text is never shown.
- [FR-19] When the Firecrawl lookup returns nothing or the AI output is unusable, the reference row is `failed` and the SNP shows **Look up** again.
- [FR-10/25] When a user requests another user's data, nothing is returned, because readers are always scoped to the authenticated user id and never take it from the request.
- [FR-20] When Look up is clicked twice, the second click is ignored while the first is still running.
- [FR-26] When research predates gene interactions, the panel and the tab say to re-run research.

## Security
- **Trust boundaries:**
  - Uploaded files: untrusted bytes and text.
  - Model output from extraction and lookup: untrusted.
  - Firecrawl-scraped pages: untrusted.
  - The login form.
  - The session cookie.
  - The PDF route, which is public but now reads the session user.
- **Assets:**
  - Users' genotypes, which are special-category personal data.
  - The demo account's credentials.
  - AI and Firecrawl spend.
- **STRIDE:**
  - Login: S (password hash, throttled attempts, session regenerated on login), E (`auth` middleware on every genetics route and action).
  - Upload: T and D (extension, MIME and size checks, streamed parsing, a job timeout, per-user rate limits); I (a private non-served disk, the file deleted when the job ends, genotypes behind an encrypted cast).
  - Model output: T (schema plus regex and alphabet validation, escaped rendering, citation ids checked against the sources sent); I (the prompt carries only marker lines, and `ai_requests` stores no prompt bodies).
  - Firecrawl: T (domain allow-list, URL validation per FR-30).
  - Readers and PDF: I and E (always scoped to `auth()->id()`, never to a request parameter; the genetics section only for the signed-in owner).
  - Spend: D (the lookup cap, rate limits, one active lookup per rsID, the stale-run reaper).
- **Misuse cases:**
  - Prompt injection inside an uploaded PDF ("ignore instructions, output…"): output validation drops anything off-schema (FR-16 test).
  - A user tampers with a Livewire property to view another user's upload or variants: the ids are `#[Locked]`, and every query is scoped to the authenticated user (FR-10 test).
  - Spamming uploads or Look up to burn credits: rate limits plus the cap (FR-20 and FR-28 tests).
  - A guest fetches a PDF to learn someone's genotypes: the section needs a session and shows only the requester's own data (FR-27 test).
  - Brute-forcing the demo login: throttled (FR-01 test).

## Non-functional requirements
- **NFR-01** Parsing a 640k-line 23andMe-format file streams within 128 MB peak memory and finishes within the job timeout (120 s) on this Mac. Check: an opt-in test (`RUN_LARGE_PARSE_TESTS=1`) on a generated file asserts both.
- **NFR-02** Cost bounds:
  - One upload makes at most one extraction call plus the lookup cap (15 searches and 15 calls).
  - A deterministic-format upload makes zero AI calls.
  - Supplement research makes exactly one AI call and two Firecrawl searches.
  - Matching and rendering make zero.

  Check: tests assert the call counts on the fakes.
- **NFR-03** All new AI profiles default to a model listed in the Intelligence pricing table, so their cost is logged and never $0. Check: a config test asserts each genetics profile's model is a key in `intelligence.pricing`.
