# Local approval steps (prepared 2026-11-10)

Each step below was rehearsed on isolated copies and is waiting for approval. Run them in this order, from
`/Users/johnlukereynon/iclimate_system`. Never paste a password or key into chat. Prompts read values without echoing.

## 0. Before steps 3 and 4: a quiet, verified backup

```bash
bash scripts/start-iclimate-local.sh stop
php artisan iclimate:backup-database && php artisan iclimate:verify-backup
```

Keep the stack stopped until step 4 is finished.

## 1. Bind XAMPP MariaDB to this Mac only

Port 3306 currently answers on the LAN address. The only live connections come from 127.0.0.1, `htdocs` holds only
XAMPP's default pages, and phpMyAdmin uses the socket.

```bash
bash scripts/xampp-bind-localhost.sh            # dry run: shows exposure and the one-line change
bash scripts/xampp-bind-localhost.sh --apply    # sudo: backs up my.cnf, adds bind-address=127.0.0.1, restarts, verifies
```

To undo: copy the printed `my.cnf.before-bind-*` back and run `sudo /Applications/XAMPP/xamppfiles/xampp restartmysql`.

## 2. Credentials exposed in public Git history (commit `afa3be2f`)

| Name | Local use | Action |
|---|---|---|
| `APP_KEY` | session cookies, cached user entries (no DB column) | `bash scripts/rotate-app-key-local.sh --apply`; after 120 min `--finalize` |
| `GROQ_API_KEY` | Climora AI answers | Create a new key in the Groq console, revoke the old one, put the new one in `.env` |
| `MAIL_PASSWORD` | SMTP mail | Create a new app password at the mail provider, revoke the old one |
| `WEATHERAPI_KEY` | configured but inactive (provider is Open-Meteo) | Revoke at WeatherAPI; delete the line from `.env` unless you switch providers |
| `OPENWEATHER_API_KEY` | not used by the code | Revoke; delete the line |
| `WINDY_API_KEY` | not used by the code | Revoke; delete the line |
| `VITE_GOOGLE_MAPS_API_KEY` | not used by the code | Revoke in Google Cloud; delete the line |
| `REDIS_PASSWORD` | not used (no Redis server; cache, queue and session use file/database) | Delete the line |

`rotate-app-key-local.sh --rehearse` proved that data encrypted with the current key still decrypts after rotation
through `APP_PREVIOUS_KEYS`, and that it is rejected without it. `--apply` keeps a private copy of `.env`, restores it
automatically if verification fails, and restarts the local services.

## 3. Apply the three pending migrations

000005, 000006 and 2026_11_10_000001 were rehearsed on a byte-identical copy of today's data. Every row of every table
stayed identical; the only schema change is the removed JSON check on `planting_advisories.metadata`; timestamps were
unchanged. `iclimate_app` cannot change the schema, so run this with an administrator account (XAMPP `root`):

```bash
read -r -s -p "MariaDB admin password (empty if none): " ICLIMATE_ADMIN_PW; echo
DB_USERNAME=root DB_PASSWORD="$ICLIMATE_ADMIN_PW" php artisan migrate --force
unset ICLIMATE_ADMIN_PW
php artisan migrate:status | grep -c Pending      # expect 0
```

Recovery if anything looks wrong: restore the step-0 backup into a **new** database first and compare. Never restore
over the active database without checking.

## 4. Import the Supabase-only records

```bash
read -r -s -p "Supabase (read-only source) password: " SOURCE_DB_PASSWORD; echo; export SOURCE_DB_PASSWORD
read -r -s -p "iclimate_app password: " TARGET_DB_PASSWORD; echo; export TARGET_DB_PASSWORD
php scripts/reconcile-supabase-only-records.php --target-database=iclimate_staging_20261103_fix3 --target-user=iclimate_app \
  --archive="$(ls ~/.iclimate-private/supabase-archive/*.dump)" --apply --approved-active-target \
  --report=storage/app/migration-reports/supabase-only-$(date -u +%Y%m%dT%H%M%SZ).json
unset SOURCE_DB_PASSWORD TARGET_DB_PASSWORD
bash scripts/start-iclimate-local.sh start
```

Expected: 4 inserts (Supabase `system_logs` 541/542 and `backup_verifications` 31/32 get **new** IDs) and 2 forward
merges of user 10's login times. A second run inserts nothing. Rehearsal: all 107,278 Supabase rows were accounted
for, with 0 missing and 0 unexplained.

## 5. Later, separately: delete Supabase

Only after steps 1–4 are applied and verified. The archive in `~/.iclimate-private/supabase-archive/` (with its
`.sha256`) stays as the permanent record.
