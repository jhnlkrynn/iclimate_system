# Real-time audit probes

Browser probes that check what an *open* page does when data changes: real Chromium sessions per role, one
controlled change in a **disposable clone** database, and the time until the open page shows it (without a reload).
Results from 2026-11-11 are in `docs/realtime-status.md`.

Never point these at the active database. `trigger.php` refuses to run unless the app's database name contains
`rt_audit`.

## Setup (isolated stack, nothing shared with the active app)

1. Clone the database: `mysqldump --single-transaction <active_db> | mysql iclimate_rt_audit_<date>` (after `CREATE DATABASE`).
   The clone carries the active `jobs` table. Check what is queued before starting a worker on it.
2. Copy the project to a scratch folder (`rsync` without `storage/app/backups`, `vendor`, `node_modules`; then
   `cp -cR vendor node_modules`). In its `.env`, set:
   `APP_URL=http://127.0.0.1:8100`, `DB_DATABASE=iclimate_rt_audit_<date>`, `CACHE_STORE=database`,
   `REVERB_PORT=8091`, `REVERB_SERVER_PORT=8091`, `MAIL_MAILER=log`, `FARMING_AI_API_URL=http://127.0.0.1:8011`,
   `SESSION_COOKIE=iclimate_rt_audit_session`. Then run `npm run build` there, so the bundle connects to Reverb on 8091.
3. Start the services in the copy: `php artisan serve --port=8100`, `php artisan reverb:start --port=8091`,
   `php artisan queue:work database --queue=analytics,default,broadcasts`, and
   `.venv-ml/bin/python -m uvicorn main:app --app-dir python_scripts --port 8011`.
4. In the clone only, give test accounts a known password and write their emails to `creds.json`:
   `{"farmer": "…", "farmer2": "…", "mao": "…", "it": "…"}`. `farmer` needs a barangay and a farm; `farmer2` needs a
   different barangay.

## Run

```sh
export RT_APP_DIR=/path/to/isolated/copy RT_CREDS=/path/to/creds.json   # RT_FARMER_ID / RT_MAO_ID / RT_FARM_ID if not 1 / 2 / 1
caffeinate -dimsu node scripts/realtime-audit/rt-observe.mjs            # every page × change; or name cases
caffeinate -dimsu node scripts/realtime-audit/rt-flows.mjs              # channel auth, barangay isolation, duplicates, messages
caffeinate -dimsu node scripts/realtime-audit/rt-busy.mjs               # a list never changes under someone typing
caffeinate -dimsu node scripts/realtime-audit/rt-outage.mjs             # stops/restarts the isolated Reverb (RT_REVERB_PORT)
```

`rt-flows.mjs` names channels for users 1 and 2, farm 1 and 2, and the Matabungkay and Kapito barangays. Adjust
those names to match your clone.

Result per case:
- `UPDATED`: the open page showed the change; `delay_ms` runs from just before the commit.
- `NO-UPDATE`: the open page did not show it within 20 s. `afterReload: true` confirms the change is on the page
  after a manual reload, so the page is stale.
- `NOT-VISIBLE-HERE`: the server's own render did not change, so this page doesn't show that change.
