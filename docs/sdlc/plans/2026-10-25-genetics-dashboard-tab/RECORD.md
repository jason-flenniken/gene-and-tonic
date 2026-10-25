# Record: Genetics page is isolated from the dashboard

## Links
- Plan: docs/sdlc/plans/2026-10-25-genetics-dashboard-tab
- Issue: none
- PR: none (repo has no remote); merged locally into main

## Bug
After signing in, users landed on a standalone `/genetics` page with no way back to the dashboard or other sections. The dashboard Genetics tab only showed a count and a link. Repro: `vendor/bin/pest tests/Feature/DashboardTest.php`.

## Cause
The full genetics UI was its own route (`Modules/Genetics/routes/web.php:5`), the dashboard tab embedded only a summary card, and the header app name was plain text (`resources/views/layouts/app.blade.php:21`).

## Fix
The Genetics tab now renders the full upload, add-SNP and variant list for signed-in users, and a sign-in prompt for guests (`⚡summary.blade.php`). `/genetics` redirects to `/?tab=genetics` and keeps its name and auth, so existing links still work. The header app name links home. Existing page tests now hit the tab URL.

## Evidence
- Repro: 3 failing before, passing after.
- Tests: 256 passed, 1 skipped (`vendor/bin/pest tests/Feature Modules/Genetics/tests Modules/Recommendations/tests`). Pint and PHPStan clean. Code and security reviews passed with no blocking findings, 4 follow-ups logged.
