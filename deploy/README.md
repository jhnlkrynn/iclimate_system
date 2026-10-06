# Deploying iClimate

`php artisan serve` is a development server: it answers one request at a time, so one slow page makes every
other visitor wait. For real use, serve the app with Apache or Nginx.

## What must be running

| Part | What it does | Without it |
|---|---|---|
| MySQL / MariaDB | The database | Nothing works |
| Web server + PHP 8.2 or newer | Serves the pages | Nothing works |
| Scheduler (`php artisan schedule:work`, or cron `* * * * * php artisan schedule:run`) | Weather, risk, PAGASA advisories, daily backup, queue clean-up | Weather and risk go stale, no backups |
| Queue worker (`php artisan queue:work --queue=analytics,default`) | Predictions and reports | "Check Weather", "Estimate My Harvest" and reports time out |
| ML service (`uvicorn main:app --app-dir python_scripts --port 8010`) | Rice-yield model and on-demand predictions | Yield forecast unavailable |

## Windows (XAMPP's Apache) — tested

1. In `.env` set `ICLIMATE_WEB_SERVER=apache`.
2. Run `start-services.bat` (or `start-services.bat restart`). It starts the database, the ML service, four PHP
   workers (`php-cgi.exe`), Apache with [`apache/httpd-iclimate.conf`](apache/httpd-iclimate.conf), the scheduler
   and the queue worker, and restarts any of them that stops.
3. Run `start-services.bat install-autostart` once so everything starts when you sign in to Windows.
4. `start-services.bat status` shows what is running. Logs are in `storage/logs`.

The Apache configuration is stand-alone: it does not change XAMPP's own `httpd.conf`, and XAMPP's PHP 8.0 is not
used. Only `public/` is served, so `.env`, `storage/` and the source code cannot be downloaded.

To serve other computers on the office network, set `ICLIMATE_WEB_BIND=0.0.0.0` in `.env` and run
`start-services.bat restart`. People then open `http://<this computer's address>:8000`; links and forms follow
whatever address was typed. Windows Firewall must allow Apache (`httpd.exe` in XAMPP's `apache/bin` folder) on the
network in use; XAMPP normally added that rule when Apache was first started. Set `APP_URL` in `.env` to the same
address so emails and scheduled jobs build correct links.

Logs (all in `storage/logs`): the application log is one file per day (`LOG_STACK=daily`, 14 days kept); Apache's
logs rotate at 10 MB (five files kept); each service log rotates at 5 MB when its service restarts; every scheduled
command has its own `automation-<command>.log`.

## Linux (Apache or Nginx)

- Apache: [`apache/iclimate-vhost.conf`](apache/iclimate-vhost.conf) (virtual host for PHP-FPM).
- Nginx: [`nginx/iclimate.conf`](nginx/iclimate.conf).
- Run the scheduler from cron and the queue worker and ML service from systemd or Supervisor (the same commands as
  in the table above).

## Checklist before real use

`.env`:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=` the real address (https if you have a certificate).
- `LOG_LEVEL=warning` and `LOG_CHANNEL=daily` (one log file per day, old ones removed; the single-file default
  grows without limit).
- `SESSION_SECURE_COOKIE=true` once the site is served over https.
- Real mail settings (`MAIL_MAILER=smtp`, host, user, password). With `MAIL_MAILER=log` no email is sent and the
  forgot-password page tells people to ask MAO/IT for a new password.
- A database user with its own password (not `root` with an empty password).
- `WEATHER_PROVIDER=open_meteo` unless you have a WeatherAPI key.

Commands, after every update of the code:

```
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link        (once)
start-services.bat restart      (Windows)   or   php artisan queue:restart  and reload PHP-FPM (Linux)
```

`php artisan config:cache` freezes `.env`: run it again after changing `.env`. Use `php artisan optimize:clear`
to go back to reading `.env` directly.

Data and accounts:

- `php artisan iclimate:data-audit` should end with "All checks passed".
- Remove or deactivate the demo and test accounts (Users page, IT Personnel).
- Give every staff account its own strong password.
- Copy `database/data/iclimate_farmer_masterlist.csv` onto the server by hand if the farmer import is needed; it is
  not in git because it holds personal data.
- Check that a backup appears in `storage/app/backups` each morning, and copy backups to another disk regularly.

PHP (`php.ini`): `expose_php = Off`, OPcache on, and `upload_max_filesize = 50M`, `post_max_size = 55M`,
`memory_limit = 256M` (the app accepts attachments up to 50 MB; PHP's defaults of 2 MB / 8 MB reject anything larger).
On Windows `start-services.bat` passes these to the PHP workers for you.
