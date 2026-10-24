# Decisions: Genetics module

Append-only. Written by `cairn decide` as each decision is made.

### 1. Follow-up brought into scope
**Decision:** Security hardening: rate-limit add/retry/re-run (and the new genetics upload/analysis actions), stale-run reaper, stricter URL/PMID validation at persistence and render, bounded timeouts/tokens/field sizes
**Why:** genetics adds more paid AI calls; carried from research-intelligence-dashboard:s78
_2026-10-24 18:00_

### 2. Why
**Decision:** Complete the app's core story for the interview demo: user's genetic results combined with supplement research, ending in the PDF report (the interviewing company's core capability)
**Why:** settled in the earlier research and PDF plans; Genetics is the next module on the CLAUDE.md map
_2026-10-24 18:03_

### 3. Why now
**Decision:** Demo is due Sunday night 2026-10-25 for an interview Monday 2026-10-26
**Why:** settled in earlier sessions
_2026-10-24 18:03_

### 4. Binding constraint
**Decision:** Time: cut scope, not quality; also keep LLM usage as cheap as possible
**Why:** settled earlier; the user asked explicitly to minimise LLM cost for this module
_2026-10-24 18:03_

### 5. Success
**Decision:** Demo flow works end to end on a synthetic sample file: upload, see parsed genes with plain-language effects, add a single SNP by hand, open a supplement and see which of the user's variants it relates to and why
**Why:** the user picked the full upload-to-match flow; PDF genetics section not required for done
_2026-10-24 18:24_

### 6. Auth
**Decision:** Hand-rolled Livewire login/logout plus a seeded demo user, no new package; auth middleware only on genetics routes and the personal genetics panel; supplement catalog and PDF reports stay public; every genetics row carries user_id
**Why:** CLAUDE.md requires auth before Genetics; the user wants the smallest version that keeps existing tests passing
_2026-10-24 18:25_

### 7. Parsing strategy
**Decision:** Detect format first and parse deterministically when possible (23andMe, AncestryDNA, MyHeritage, FTDNA TXT/CSV, zero AI); otherwise extract text locally (approved new dependency: smalot/pdfparser) and send only rsID/gene lines to a cheap model with a strict schema. Every AI profile (provider, model, effort, max tokens, timeout, prompt views) is config/env-driven like supplements.research
**Why:** user: 'determine beforehand if we can parse cheap, otherwise send to the LLM; make prompts, models and effort configurable'
_2026-10-24 18:26_

### 8. CLAUDE.md exception: AI sees genotype lines
**Decision:** Allowed for PDF/unknown-format extraction only: the prompt carries only the extracted lines mentioning rsIDs/genes, never names, DOB or the full document; update the CLAUDE.md domain constraint to say so
**Why:** follows from the parsing choice; keeps the spirit of 'AI sees only what it needs'
_2026-10-24 18:26_

### 9. Raw-file scope
**Decision:** From raw exports store only SNPs whose rsID is in the gene reference table (curated seed of ~30 nutrigenomic SNPs plus any SNP added before); PDF and single-SNP entries are always stored and unknown ones looked up
**Why:** bounds cost and the amount of sensitive data per upload
_2026-10-24 18:26_

### 10. Gene lookup for unknown SNPs
**Decision:** Firecrawl search on SNP domains (e.g. snpedia.com, ncbi.nlm.nih.gov; configurable) per unknown SNP, then one structured AI call per SNP (configurable profile) producing gene, category, what it affects and per-genotype effects with cited sources; result stored in a global reference table with no user link and reused forever
**Why:** user chose the cited Firecrawl + AI path, same pattern as supplement research; caching keeps repeat cost at zero
_2026-10-24 18:44_

### 11. Supplement genetic relevance
**Decision:** Supplement research (same single AI call, plus one extra Firecrawl search for gene-specific sources) also returns cited gene interactions (gene, rsID, affected genotype/allele, helps or caution, explanation), stored in Supplements with no user data. A thin new Recommendations module (depends on Genetics, Supplements, Shared) matches the signed-in user's genotypes against them with rules, no AI, and renders a 'Your genetics' panel on the supplement page through a new PageActions slot. Existing supplements need a Re-run to gain interactions
**Why:** no extra AI call, no genotypes sent to the provider for matching, follows the CLAUDE.md module map
_2026-10-24 18:45_

### 12. Strand-aware genotype matching
**Decision:** yes
**Why:** PDF, raw-file and manual entries mix plus-strand and gene-strand notation; matching must agree
_2026-10-24 18:49_

### 13. Queued processing with live status
**Decision:** yes
**Why:** uploads and lookups are slow; same pattern as supplement research, page polls
_2026-10-24 18:49_

### 14. Genetics page + dashboard tab
**Decision:** yes
**Why:** the demo flow needs a place to see parsed genes and add SNPs
_2026-10-24 18:49_

### 15. Genetics section in the PDF
**Decision:** yes
**Why:** user wants the PDF (core deliverable) to show the signed-in user's genetic relevance for the supplement
_2026-10-24 18:49_

### 16. Verified curated seed
**Decision:** yes
**Why:** ~30 nutrigenomic SNPs with risk alleles checked against dbSNP so common genes need no lookups
_2026-10-24 18:49_

### 17. Non-goal: delete my genetic data UI
**Decision:** out of scope
**Why:** not picked; time-boxed demo
_2026-10-24 18:49_

### 18. Demo input files
**Decision:** The plan generates small synthetic files (~40-row 23andMe-style TXT and a short report-style PDF) in a gitignored location for the demo; tests use tiny inline fixtures; no real DNA in the repo
**Why:** user picked synthetic files kept out of git
_2026-10-24 18:56_

### 19. Upload size
**Decision:** Accept plain .txt/.csv/.pdf up to 25 MB, streamed line by line; the user raises upload_max_filesize/post_max_size in Herd; Livewire temporary-upload max raised to match
**Why:** user wants real full raw exports to work
_2026-10-24 18:56_

### 20. Non-goal: zip uploads
**Decision:** out of scope
**Why:** not picked; users unzip before uploading
_2026-10-24 18:56_

### 21. Users
**Decision:** A signed-in user (the seeded demo user for the interview); interviewers watch the demo
**Why:** single-user demo; auth decision covers sign-in
_2026-10-24 18:58_

### 22. Core flow
**Decision:** Sign in, open My genetics, upload a file or add a single SNP (rsID + genotype, optional gene); a queued job parses, looks up unknown SNPs and stores them; the page polls and shows each SNP with gene, category, what it affects and the user's genotype effect; open a supplement to see the Your genetics panel; download the PDF with a genetics section. Profile is one set per user: uploads and manual entries merge, newest genotype wins per SNP, each SNP records its source
**Why:** user picked merge, newest wins
_2026-10-24 18:58_

### 23. Lookup cap
**Decision:** At most 15 unknown-SNP lookups per upload (env-configurable); the rest stored genotype-only as 'not yet looked up' with a per-SNP Look up button
**Why:** bounds worst-case cost per upload
_2026-10-24 18:58_

### 24. Trust boundaries
**Decision:** (1) raw uploads on a private non-served disk, deleted when the job ends either way; (2) genotype encrypted cast, rsID plaintext for matching; (3) users see only their own data, PDF genetics section only for the signed-in owner, public PDFs omit it; (4) uploaded text is untrusted model input: output validated (rsID regex, allele alphabet, cited source ids), rendered escaped; (5) per-user rate limits on upload, add-SNP and look-up; (6) migrations only add tables/columns; no deletes, no money, no shell commands, no public API
**Why:** user accepted all; covers untrusted input, auth, personal data, model output, migrations
_2026-10-24 18:58_

### 25. Non-goal: registration, password reset, multiple profiles, dosing advice, AI-written personal recommendations, upload history
**Decision:** out of scope
**Why:** time-boxed demo; matching is rule-based and generic interactions only
_2026-10-24 18:58_

### 26. Read-back
**Decision:** Outcome: Genetics module with demo-user sign-in; TXT/CSV/PDF upload (<=25 MB) parsed deterministically when possible, cheap configurable AI otherwise; single-SNP entry; shared gene reference table (~30 verified seed SNPs, Firecrawl+AI lookups capped 15/upload); supplement research gains cited gene interactions; new Recommendations module rule-matches the user's genotypes into a Your genetics panel and a PDF genetics section. User: signed-in demo user shown to interviewers. Why now: demo Sunday night 2026-10-25, interview Monday. Success: on a synthetic file, upload -> genes with plain-language effects -> add one SNP -> open a supplement and see which variants it relates to and why. Constraint: time (cut scope, not quality) and minimal LLM cost; raw genotypes reach AI only as rsID/gene lines from PDFs. Out of scope: registration/password reset, delete UI, zip uploads, upload history, multiple profiles, dosing advice, AI-written personal recommendations. Order work so the PDF section is last.
**Why:** confirmed by the user
_2026-10-24 19:15_
