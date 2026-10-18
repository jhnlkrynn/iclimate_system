#!/bin/sh
# Starts the site for the Playwright browser tests on its own throwaway SQLite database, so they never touch the
# shared database in .env. Cache is per request (array): a shared file cache would leak test data into the real site.
set -e
cd "$(dirname "$0")/../.."

DB_FILE="${TMPDIR:-/tmp}/iclimate-browser-tests.sqlite"
rm -f "$DB_FILE"
touch "$DB_FILE"

export APP_ENV=local
export APP_DEBUG=true
export APP_URL=http://127.0.0.1:8123
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"
export DB_URL=
export CACHE_STORE=array
export SESSION_DRIVER=file
export QUEUE_CONNECTION=sync
export MAIL_MAILER=log
export BROADCAST_CONNECTION=null
export ICLIMATE_EXCLUDE_SAMPLE_DATA=true
# Dashboards opened by the login tests call outside services; keep those calls short (or off) and run several
# workers, so a slow call cannot hold up the landing page requests. The ML service gets an unroutable port, as in phpunit.xml.
export PHP_CLI_SERVER_WORKERS=8
export FARMING_AI_API_URL=http://127.0.0.1:19999
export OPEN_METEO_TIMEOUT=4
export WEATHERAPI_TIMEOUT=4
export WEATHER_API_TIMEOUT=4
export PAGASA_ADVISORIES_ENABLED=false
export GROQ_ENABLED=false

php artisan config:clear >/dev/null
php artisan migrate:fresh --force >/dev/null
php tests/Browser/seed-landing.php

# Laravel's router script resolves index.php from the working directory, as `artisan serve` does.
cd public
exec php -S 127.0.0.1:8123 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
