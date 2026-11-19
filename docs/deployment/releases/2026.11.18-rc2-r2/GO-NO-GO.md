# iClimate RC2: final GO / NO-GO (18 November 2026, Asia/Manila)

**Candidate:** `2026.11.18-rc2` (r2), fingerprint `04c7db9fedf0db9c077521adfebae8621291b1ddcf5e0e371a72a966dcb2d29d`, archive `c26275e0…527ad`, 1,504 files.

**Base:** RC1 `d43cd1aa`, tag `v2026.11.18-rc1`, unchanged.

Nothing has been committed, tagged, pushed, merged or deployed. No ML model was retrained or replaced. No weather data was created. No active database record was changed.

| Area | Decision | Basis |
|---|---|---|
| **Software readiness** | **GO for owner approval** (commit + tag) | Every suite passes on the exact candidate (see Evidence). No known unmitigated release-blocking defect. |
| **ML accuracy** | **NO-GO: ACCURACY NOT VERIFIED** | Exploratory only: 19 records from one year, and overlap with the unknown training data cannot be ruled out. Estimates stay withheld from Farmers. |
| **Private VPS readiness** | **CONDITIONAL GO** for an approved, controlled installation rehearsal | Packaging, installer guards, migrations, backup/restore and rollback were rehearsed on MariaDB 10.11 locally. No real Ubuntu/Hostinger host has been verified. |
| **Public deployment readiness** | **NO-GO** | No VPS, TLS/DNS, real SMTP/recovery rollout, production monitoring or target-environment security acceptance yet. |

## Requirement checklist

1. **Change audit:** 90 paths against RC1 (42 added, 48 modified, 0 deleted; `CHANGES.md`, `changed-files.txt`). The package includes all three:
   - the frozen-input fix;
   - Codex's RC2 improvements;
   - the MariaDB test fix (`PreMigrationCompatibilityTest`).

   Codex's last edit was at 18:05. There were no concurrent edits during preparation.
2. **Model:** `storage/models/rice_yield_model_final.pkl` sha256 is `f3a54e12…b777c`. It is identical to the RC1 commit (`git diff` is empty for all of `storage/models`). The installer pins this checksum.
3. **The 12 features:** the order matches the model's own `feature_names_in_` exactly: RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall, Previous_Temp, Rainfall_6M, Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season. Preprocessing is unchanged (ColumnTransformer → RandomForest).

   | Feature | Definition |
   |---|---|
   | RAINFALL, TEMP_AVG, TEMP_RANGE | Target month (TEMP_RANGE = TMAX − TMIN) |
   | Previous_* | Month T−1 |
   | Rainfall_6M | **Mean** of T−1..T−6 |
   | Temp_3M / Temp_6M | Means of T−1..T−3 / T−1..T−6 |
   | Seasonal_* | Whole-season sum / mean: observed months plus ready forecasts, flagged |
   | Area | Hectares |
   | Season | Wet May–Sep / Dry Oct–Apr, as trained |

   Units are mm and °C.
4. **Gate:**
   - `ICLIMATE_YIELD_GATE=withhold` is the default and is set in both templates.
   - `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`: the default, set in both templates and parsed strictly.
   - The installer now **refuses** an install if the flag is enabled.
5. **Missing weather:**
   - **Behaviour:** an unobserved month stays missing and the model is not called. The Farmer sees "Not enough weather records yet for an estimate." / "Kulang pa ang datos ng panahon para sa tantiya."
   - **Tests:** unit tests cover missing, partial-season, leakage and forecast-flagging cases.
   - **Pipeline check:** every 2025-11 and 2026-11 run reported its missing inputs.
6. **Farmer visibility:** 0 of 180 pipeline runs were visible. The gate withholds every model estimate, including stored history (`showsStored`), until accuracy is verified.
7. **Driver:** production and both templates use `DB_CONNECTION=mysql`. `deploy-release.sh` now refuses any other driver and refuses duplicated keys. Under the `mariadb` driver, 9 MySQL-guarded migration steps would be skipped (40 test failures).
8. **Migrations and rehearsals on MariaDB 10.11.19:** source = the pre-migration audit clone (69 tables, 114,779 rows, 100 migrations).

   | Step | Result |
   |---|---|
   | Backup, with the production script | — |
   | Restore on 10.11 | Columns, row counts, CHECKSUM TABLE and foreign keys identical to the 10.4 source |
   | 7 migrations | Every data-preservation check OK; 26 foreign keys added, none lost |
   | Rollback of all 7 | Byte-identical snapshot to the pre-migration database |
   | Re-migrate | Identical to the first migration |
   | Backup + `restore-isolated-mariadb.sh` of the migrated database | Identical (78 tables) |
   | Health check with a runtime account that cannot change the schema | Database and migrations OK |
9. **Regression suites:** see Evidence.
10. **Security, dependencies and packaging:**

    | Check | Result |
    |---|---|
    | Composer | 0 advisories |
    | npm, production dependencies | 0 |
    | npm, dev/build dependencies | 12 high entries from 1 advisory (`picomatch` GHSA-fc5q-39xc-vjpf). The hash-verified mitigation is enforced by `npm run build` and verified on all 3 copies after a clean `npm ci` from the archive. |
    | Python audit | Not re-run: the audit tool download was not permitted. The previous candidate reported 0 advisories on 26 pins. |
    | Secret scan of all 1,504 release files | 0 real secrets |
    | Packager | Refuses `.env`, keys, vendor, dumps and symlinks; research data is excluded |
11. **Release archive:** reproducible. A second build is byte-identical. The checksums verify. The extracted archive installs from `composer.lock` and `package-lock.json`, builds, and passes PHP, JS and Python tests. Rollback steps are in `ROLLBACK.md`; approval commands are in `APPROVAL.md`.

## Evidence (exact candidate)

| Suite | Result |
|---|---|
| PHPUnit, PHP 8.4.27, SQLite | 1,695 tests: 1,690 passed, **0 failed**, 5 MariaDB-only skips |
| PHPUnit, PHP 8.4.27, **MariaDB 10.11.19**, `mysql` driver | **1,695 / 1,695 passed**, 0 skipped |
| PHPUnit from the extracted archive (fresh `composer install`) | 0 failed. 7 skips: the 5 above, plus 2 ML integration tests that need a project `.venv`. With the ML venv linked, both pass from the archive (300 assertions). |
| Python | 69 passed (also 69 from the archive) |
| Deployment packaging and safety | 25 passed, including 3 new refusal tests |
| JavaScript (Node 26.10) | 26 passed (also 26 from the archive) |
| Playwright desktop/mobile | 178 passed, 0 failed (18 viewport skips) |
| Playwright desktop/tablet/mobile critical verification | 18 passed |
| Direct model vs ML API vs Laravel (RC2 code, migrated MariaDB 10.11 copy) | 80 distinct inputs, 0 mismatches |
| ML service vs backup CLI vs Laravel | 40 inputs, 0 mismatches; 8/8 invalid inputs rejected identically |

**Defects found and fixed during this preparation** (all re-tested):
1. The installer checked the **last** value of a duplicated env key, while Laravel uses the **first**. Duplicates are now refused.
2. The installer did not enforce the `mysql` driver or the accuracy flag. Both are now required.
3. `(bool)` parsing of the accuracy flag treated "no" as true. It now uses `FILTER_VALIDATE_BOOL`.

## Before-and-after prediction comparison (sample: 30 farmers × 6 months)

| Target | RC1 (frozen inputs) | RC2 |
|---|---|---|
| 2024-03 | 4.17 | 4.03 |
| 2024-08 | 4.05 | 3.34 |
| 2025-01 | 4.19 | 4.11 |
| 2025-06 | 4.02 | 3.82–3.83 |
| 2025-11 | 4.17 | missing: season data unavailable |
| 2026-11 | 4.17 | missing: no observed 2026 weather |

All values are withheld from Farmers. Exploratory accuracy on 19 MAO records, **NOT VERIFIED**:

| Inputs | MAE | R² | r |
|---|---|---|---|
| RC2 inputs | 0.531 | 0.127 | 0.475 |
| RC1 frozen inputs | 0.779 | −0.80 | 0.38 |
| Mean baseline | 0.628 | — | — |

## Remaining limitations and owner actions

1. **Before go-live, the MAO must enter the observed 2026 monthly weather** (PAGASA Ambulong). Until then every current estimate is "missing data"; this is correct, but it is visible.
2. Only the current and next month can ever be estimated, and farms in the same month get nearly the same estimate. These are model and data limits.
3. Accuracy validation against independent observed yields, with the training rows documented, is required before anyone considers `ICLIMATE_YIELD_ACCURACY_VERIFIED`.
4. **Not verified here:**
   - Node 22, which the VPS uses, was not available locally; the previous candidate passed on Node 22.
   - The Python dependency audit was not re-run.
   - No real VPS, TLS, SMTP or WebSocket-over-TLS acceptance has been done.
5. **Owner decisions needed:**
   - approve the commit and tag (`APPROVAL.md`);
   - authorise the VPS rehearsal (runbook RC2);
   - approve the production migration with a fresh backup.
