# RC2-R2 approval checkpoints

Each checkpoint needs its own explicit owner approval. Approving one does not approve the next.

**Verified for this candidate on 2026-11-18:**
- the archive, the 1,504-file manifest and the fingerprint verify;
- an independent rebuild is byte-identical;
- the archive contains every fix;
- the model sha256 is `f3a54e12…b777c`, identical to RC1;
- the gate is `withhold` and `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`.

These settings must not change at any checkpoint:
- **ML:** no retraining, no new model, no invented weather records.
- **Farmers:** no yield estimate shown to Farmers (`ICLIMATE_YIELD_GATE=withhold`, `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`).

---

## Checkpoint 1: RC2-R2 commit and tag (local repository only)

| | |
|---|---|
| **Ready?** | **Yes, awaiting approval** |
| **State now** | Branch `release/2026.11.18-rc1`, HEAD `d43cd1aa` (RC1), nothing staged. Tags: only `v2026.11.18-rc1` (annotated, points to `d43cd1aa`). No RC2 tag or branch exists. |
| **What is committed** | Exactly the 1,504 paths in `approved-paths.txt` (90 changes: 42 added, 48 modified, 0 deleted). Rehearsed tree `d823c0146ebc7e0448285b4105918faf6e920862`. |
| **What is left out** | The four excluded locations: research models, external datasets, release metadata, companion readiness report. No other change is in the working tree. |
| **Procedure** | `APPROVAL.md`: creates the `release/2026.11.18-rc2` branch, stages by path list, checks the tree ID before and after the commit, then creates the annotated tag and a bundle. |
| **Not included** | Push to GitHub, merge to `main`. Each is a separate decision. |
| **Approve by saying** | "Commit and tag RC2-R2". |

## Checkpoint 2: private VPS installation

| | |
|---|---|
| **Ready?** | **Conditional.** Needs checkpoint 1 (tag and bundle) plus a VPS the owner has authorised. |
| **Covers** | `VPS-CHECKLIST.md` sections A–H: provisioning, a database restored into a **disposable** copy, the 7-migration rehearsal on that copy, code install, services, TLS, backups and private acceptance. **Production is not migrated.** |
| **Must be done here** | (a) **Node 22:** `npm ci`, `npm run build` and `npm run test:js` (26) on the VPS. Not verified locally; Node 26 passed. (b) **Python audit:** `pip-audit -r python_scripts/requirements.txt` and the 69 Python tests. Not re-run in this session. |
| **Blocks progress if** | Any check fails, any `pip-audit` advisory is unreviewed, or `iclimate:health` is not all OK. |
| **Approve by saying** | "Provision and install RC2-R2 on <VPS>, private access only". |

## Checkpoint 3: production database migration

| | |
|---|---|
| **Ready?** | **No.** Needs checkpoint 2 passed on the VPS. |
| **State now** | Production database (MariaDB 10.4, `mysql` driver): 100 migrations applied, **exactly 7 pending**, checked read-only on 2026-11-18. The pending migrations are `2026_11_16_000001`–`000004`, `2026_11_17_000001`–`000002` and `2026_11_18_000001`. |
| **Evidence** | Local rehearsal on MariaDB 10.11 with a copy of the audit clone: restore identical; migrate OK; rollback of 7 identical; re-migrate identical; backup/restore of the migrated database identical; restricted runtime account OK. |
| **Steps** | 1. Fresh protected backup (`iclimate-backup.sh`), checksum, off-host copy. 2. Before snapshot. 3. `php artisan migrate --force` with the temporary migration account. 4. After snapshot. 5. `compare` must be all OK. 6. Lock the migration account. 7. `queue:restart`. 8. Health check. |
| **Rollback** | `ROLLBACK.md`. After real use, **never roll back more than one step.** Migrations `000002` and `2026_11_17_000002` delete association records and Farmer review history, and `000003` fails on null farm types. Prefer code-only rollback (Level 2) or a restore (Level 4). |
| **Approve by saying** | "Migrate the production database with a fresh backup". |

## Checkpoint 4: public launch

| | |
|---|---|
| **Ready?** | **NO-GO.** |
| **Open blockers** | 1. Checkpoints 2–3 completed and signed off. 2. Off-host backup copies configured and monitored, plus a restore drill on the VPS. 3. Real SMTP delivery and recovery-code operations proven; staff trained for recovery. 4. TLS/DNS on the real hostname; WebSocket over TLS; off-host denial of private paths. 5. Target-environment security check: firewall, ssh hardening, fail2ban, file permissions, `APP_DEBUG=false`. 6. Monitoring and alerting for health, queue, scheduler and backups. 7. MAO enters the observed **2026 monthly weather** (PAGASA Ambulong) in Climate Records. Until then, every current yield card says "Not enough weather records yet". This is correct, but users will see it. |
| **Separate, later decision** | Showing yield estimates to Farmers (`ICLIMATE_YIELD_ACCURACY_VERIFIED=true`) needs an independent validation against observed yields not used for training, with the training rows documented. Today the accuracy is **NOT VERIFIED**: exploratory MAE 0.531 on 19 records from 2024, against a 0.628 mean baseline. The installer refuses the flag as long as it stays in RC2. |
| **Approve by saying** | "Launch publicly", and only after every blocker above has been signed off. |
