# iClimate Production Workers, Services, Cron & Deploy Guide

This document describes the live production service layout for iClimate (`iclimate-queue`, `iclimate-python`, `iclimate-reverb`), the Nginx WebSocket proxy, the Laravel scheduler cron, and the standard deployment steps.

---

## 1. Systemd Services

### `iclimate-queue.service` (Laravel Queue Worker)
Processes `analytics` and `default` jobs from the `database` queue.

```ini
[Unit]
Description=iClimate Laravel Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/iclimate
ExecStart=/usr/bin/php artisan queue:work database --queue=analytics,default --sleep=3 --tries=3 --max-time=3600 --timeout=300
Restart=always
RestartSec=5
StandardOutput=append:/var/www/iclimate/storage/logs/queue-worker.log
StandardError=append:/var/www/iclimate/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
```

### `iclimate-python.service` (ML & Analytics FastAPI on `:8010`)
Serves rice-yield prediction, monthly weather random forest, drought assessment, and backtest/evaluation endpoints.

```ini
[Unit]
Description=iClimate Python ML Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/iclimate/python_scripts
EnvironmentFile=/var/www/iclimate/.env
ExecStart=/var/www/iclimate/.venv-ml/bin/uvicorn main:app --host 127.0.0.1 --port 8010 --workers 2
Restart=always
RestartSec=5
StandardOutput=append:/var/www/iclimate/storage/logs/python-ml.log
StandardError=append:/var/www/iclimate/storage/logs/python-ml.log

[Install]
WantedBy=multi-user.target
```

> Verify health: `curl -fsS http://127.0.0.1:8010/health`

### `iclimate-reverb.service` (Laravel Reverb WebSockets on `:8082`)
Powers live dashboard updates, notifications, and advisory/weather broadcasts (`BROADCAST_CONNECTION=reverb`).

```ini
[Unit]
Description=iClimate Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/iclimate
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8082
Restart=always
RestartSec=5
StandardOutput=append:/var/www/iclimate/storage/logs/reverb.log
StandardError=append:/var/www/iclimate/storage/logs/reverb.log

[Install]
WantedBy=multi-user.target
```

Required `.env` keys for Reverb:
```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=iclimate
REVERB_APP_KEY=<generated-key>
REVERB_APP_SECRET=<generated-secret>
REVERB_HOST=your-domain.example
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8082
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 2. Nginx `/app/` WebSocket Proxy & `/build/` Immutable Cache

Proxy `/app/` (and `/apps/` if needed by Pusher-compatible clients) to Reverb on `127.0.0.1:8082`, and serve hashed Vite assets under `/build/` with a 1-year immutable `Cache-Control` header:

```nginx
location ^~ /build/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}

location /app/ {
    proxy_pass http://127.0.0.1:8082;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Apply and reload Nginx:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. Cron (`schedule:run`)

Run Laravel's scheduler every minute as `www-data`:

```cron
* * * * * cd /var/www/iclimate && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Key scheduled tasks (defined in `routes/console.php`):
- `iclimate:refresh-barangay-weather` (every 10 min) → also refreshes barangay risk, flood risk, and drought risk.
- `iclimate:warm-farmer-yields` (every 3 hours) → checks `/health` once, warms farmer & barangay yield estimates.
- `iclimate:health --log --restart-ml` (every 5 min) → monitors DB, ML, Reverb, queue, scheduler, and disk.
- `iclimate:prune-logs --days=14` (daily at 03:55) → prunes rotated `.log` files older than 14 days.

---

## 4. Standard Deploy Steps

```bash
cd /var/www/iclimate

# 1. Pull latest code and install dependencies
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
npm ci
NODE_ENV=development npm run build   # avoids PurgeCSS stripping dynamic classes

# 2. Validate environment & run migrations
php artisan iclimate:env-check
php artisan migrate --force

# 3. Rebuild Laravel caches
php artisan optimize:clear
php artisan optimize

# 4. Ensure web/worker ownership and writable storage/bootstrap cache
sudo chown -R www-data:www-data /var/www/iclimate/storage /var/www/iclimate/bootstrap/cache
sudo chmod -R ug+rwX /var/www/iclimate/storage /var/www/iclimate/bootstrap/cache

# 5. Restart services gracefully
php artisan queue:restart
sudo systemctl restart iclimate-queue iclimate-python iclimate-reverb
sudo systemctl reload nginx
```
