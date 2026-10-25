# Record: Genetics upload spins forever and complex marker CSVs don't parse

## Links
- Plan: docs/sdlc/plans/2026-10-25-genetics-upload-complex-csv
- Issue: none
- PR: none (repo has no remote); merged locally into main

## Bug
Clicking Upload spun forever and nothing was processed. A rich marker CSV (loose headers, messy genotype cells) would also have gone to the AI and lost half its rows at the 20,000-character cap. Repro: `vendor/bin/pest Modules/Genetics/tests/Feature/ComplexMarkerCsvTest.php`.

## Cause
The `upload` action collided with Livewire's JS `$wire.upload()` helper, which threw in the browser. `MarkerCsvParser` needed exact header names and took the first alias match in file order, so "Marker" beat "rsID".

## Fix
Renamed the action to `uploadResults`, with a guard test against Livewire JS names. Marker CSVs are now read locally with loose headers and messy genotype cells. At ≥80% of rsID rows (`GENETICS_LOCAL_MIN_RATIO`), only leftover rows go to the AI. Below that, or with no header, the AI gets chunked calls. Uploads record the path and read/AI/skipped counts.

## Evidence
- Repro: failing before, passing after. The user's real file reads 73 of 74 rsID rows locally.
- Tests: 261 passed, 1 skipped. Both reviews pass after one fix attempt; 6 follow-ups.
