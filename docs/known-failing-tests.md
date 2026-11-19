# Known failing tests

**There are none.** The 37 failures this file used to list (main at `d57a05ee`, 2026-10-17) were all fixed; the
list was removed on 2026-11-20. Its history is in Git.

## Latest full run (after restoring the 19 barangays and the Model Evaluation page)

2026-11-20, Windows 11, PHP 8.5.8, SQLite in memory: **1,708 tests, 13,496 assertions, 12 skipped.** Two tests still
expected 9 barangays (`Phase7ReportsTest`, `MaoRiskCountsPlaceholderTest`); both were updated to 19 and pass. Python
ML tests: 71 passed. JavaScript tests: 26 passed; `npm run build` passed.

## Earlier full run

2026-11-20, `main` at `bafe5701` plus the housekeeping change, Windows 11, PHP 8.5.8, SQLite in memory:

- **1,702 tests, 13,384 assertions: 1,701 passed, 12 skipped, 1 failed.**
- The one failure, `FarmerAccountReviewConcurrencyTest`, was a timing problem on that machine: the test starts two
  PHP worker processes and gives them 10 seconds to start. Run on its own it passed (1 test, 29 assertions).
  On a slow or busy machine, re-run it alone before treating it as a real failure.
- The skipped tests need MariaDB (for example `TimestampRefreshMigrationTest`) and are skipped on SQLite.

Other suites on the same day: Python ML tests 69 passed; JavaScript tests and `npm run build` passed.
