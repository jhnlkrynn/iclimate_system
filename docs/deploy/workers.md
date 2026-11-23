# Queue Worker & Scheduler Guide

iClimate relies on two background processes:
1. **Queue worker** (`queue:work`) — processes yield prediction requests, Python analytics jobs, and queued broadcasts/notifications (`jobs` table when `QUEUE_CONNECTION=database`).
2. **Scheduler** (`schedule:work` locally, or `schedule:run` every minute in production) — runs periodic weather fetches (`iclimate:refresh-weather-cache`), scheduled advisory publishing (`iclimate:publish-scheduled-alerts`), dashboard cache warm-ups (`iclimate:warm-dashboard-cache`), and database backups (`iclimate:backup-database`).

---

## Local Development (macOS / Linux / XAMPP)

Run each in its own terminal from the repository root (or use a local process manager such as Supervisor):

```bash
# 1. Queue worker (stop gracefully after finishing the current job with Ctrl+C)
php artisan queue:work --tries=3 --timeout=120

# 2. Laravel scheduler (runs scheduled tasks every minute in the foreground)
php artisan schedule:work
```

> **Tip:** Make sure `QUEUE_CONNECTION=database` is set in `.env` and that `iclimate:health` passes before testing features that dispatch jobs.

---

## Production (Linux / Hostinger VPS)

### 1. Queue Worker via `systemd` (Recommended)

Create `/etc/systemd/system/iclimate-queue.service`:

```ini
[Unit]
Description=iClimate Laravel Queue Worker
After=network.target mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/iclimate_system
ExecStart=/usr/bin/php /var/www/iclimate_system/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=120

[Install]
WantedBy=multi-user.target
```

Enable and start it:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now iclimate-queue.service
sudo systemctl status iclimate-queue.service
```

After every deployment, restart workers gracefully so they pick up new code:

```bash
php artisan queue:restart
```

*(Alternatively, if using Supervisor instead of `systemd`, configure `numprocs=1` running `php /var/www/iclimate_system/artisan queue:work database --sleep=3 --tries=3 --max-time=3600`.)*

### 2. Scheduler via Cron (Recommended)

Add a single cron entry for the web/app user (`crontab -u www-data -e`):

```cron
* * * * * cd /var/www/iclimate_system && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Each scheduled command writes its own log to `storage/logs/automation-<command>.log` and records its status in `scheduled_task_runs`.

---

## Health Checks & Failed Jobs

Check whether the queue, scheduler, database, ML service, Reverb, and backups are healthy:

```bash
php artisan iclimate:health
# Machine-readable output for monitoring:
php artisan iclimate:health --json
```

Inspect or clean failed jobs:

```bash
php artisan queue:failed
php artisan queue:retry <id>   # retry a transient failure when the target service is back
php artisan queue:forget <id>  # remove a single stale/obsolete failed job
php artisan queue:flush        # prune all failed jobs
```
