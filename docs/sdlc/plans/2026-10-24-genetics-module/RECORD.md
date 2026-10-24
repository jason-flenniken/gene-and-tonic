# Record: Genetics module

## Links
- PRD: docs/sdlc/plans/2026-10-24-genetics-module/prd.md
- Plan: docs/sdlc/plans/2026-10-24-genetics-module/plan.md
- PR: <pending>
- Tracker: none

## Why
The app researched supplements but knew nothing about the person reading them. The goal was an end-to-end demo of "your genetics plus supplement research" for the interview on 2026-10-26, with genetic data tied to a signed-in user and AI spend kept low. This was a Standard plan.

## What was built
- Sign-in, profile, gene interactions: a demo user can sign in and open My genetics, add a SNP by hand, and see each SNP's gene, category and what the genotype means. Supplement research now also records cited gene interactions.
- Uploads and lookups: a user can upload a .txt, .csv or .pdf (up to 25 MB). Known formats are parsed for free; PDFs and unknown formats go through a cheap AI step that extracts only marker lines. Unknown SNPs are looked up once with Firecrawl plus AI and cached for everyone.
- Relevance: the supplement page shows a Your genetics panel, and the PDF gets a Your genetic relevance section when the owner downloads it. Anonymous downloads are unchanged.
- Hardening and docs: rate limits, stale-job reapers, stored-URL validation, a `genetics:demo-files` command and CLAUDE.md updates.

## How it works
A new Genetics module owns uploads, a shared gene reference table (30 seed SNPs checked against dbSNP) and per-user genotypes under an encrypted cast. `reference_allele` is the plus-strand non-risk allele. A queued job (`Modules/Genetics/app/Jobs/ProcessGeneticUpload.php`) parses the file and keeps only reference-table SNPs from raw exports. Lookups are capped at 15 per upload. A thin Recommendations module (`Modules/Recommendations/app/Services/RuleBasedGeneticRelevanceReader.php`) matches genotypes to supplement gene interactions by rules, with zero AI calls. It exposes only a DTO contract, which the Supplements page and the Reports module consume. Genetics and Recommendations depend on other modules only through their contracts; boundary tests enforce this. New dependency: smalot/pdfparser (approved).

## Findings
- Review required two fixes, both done. The demo-user seeder now fails closed outside local and testing unless `DEMO_USER_PASSWORD` is set and not "password". Gene-interaction rsID and allele values (for example C677T, RS1801133) are normalised instead of dropped, with an end-to-end upload-to-research test.
- One task needed a fix round: upload parsing got a 1000-row cap on marker lists, and genome CSVs are now treated as raw exports.
- Four edits outside planned scope, all approved: `tests/Feature/Auth/LoginTest.php` (login now redirects to /genetics), `phpunit.xml` (512M memory for module arch tests), and the Supplements and Genetics config files (reaper and gene-limit keys).
- Checks: 461 tests passing plus 3 opt-in skipped at verify (475 after review fixes). 32 functional and 3 non-functional requirements met. The git-ignore part of the demo-files requirement is untested. Secret scan clean; composer and npm audits show 0 vulnerabilities.
- Time: discover 1h 19m, PRD 7m, plan 9m, build 1h 26m, check 3m, review 12m.

## Follow-ups
- No findings were accepted as is. Nothing was dismissed; 82 task and branch review notes were merged into 5 open follow-ups.
- 5 open recommended follow-ups (1 security, 1 tests, 1 quality, 1 ux, 1 docs): run /cairn:suggestions.
- Manual steps before the demo: raise Herd PHP `upload_max_filesize` and `post_max_size` to 25M; after merge, run migrations and `php artisan db:seed` in the main checkout; run `php artisan genetics:demo-files`; re-run research on the demo supplement (methylfolate) so it gains gene interactions; optionally run `php artisan schedule:work` for the reapers.
