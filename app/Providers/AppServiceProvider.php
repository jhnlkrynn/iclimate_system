<?php

namespace App\Providers;

use App\Models\FarmerProfile;
use Carbon\CarbonInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The risk calculation is replaceable: bind a validated methodology here when one is approved.
        $this->app->bind(\App\Contracts\RiskMethodology::class, \App\Services\Risk\AgriculturalRiskScorer::class);

        // The dashboards share one request-scoped copy of the tables they read (see the class).
        $this->app->singleton(\App\Support\DashboardData::class);

        // One stopwatch and one history read per chat message, shared by every AI service.
        $this->app->singleton(\App\Services\AI\AiTimings::class);
        $this->app->singleton(\App\Services\AI\ConversationContext::class);
        $this->app->singleton(\App\Services\Prediction\PredictionReadMode::class);

        // Postgres connector that passes the schema at connect time (saves a round trip per request).
        $this->app->bind('db.connector.pgsql', fn () => new \App\Database\StartupSearchPathPostgresConnector);

        // A missing Vite build logs a warning instead of breaking every page with a 500.
        $this->app->singleton(\Illuminate\Foundation\Vite::class, \App\Support\SafeVite::class);

        // A stopped Reverb must not turn a saved notification or message into a 500: failed realtime updates are
        // logged and retried from the queue. Registered as an extender so it applies when the deferred broadcasting
        // provider first builds the manager (before any channel is registered on it).
        $this->app->extend(\Illuminate\Broadcasting\BroadcastManager::class, fn ($manager, $app) => $manager instanceof \App\Broadcasting\ResilientBroadcastManager
            ? $manager
            : new \App\Broadcasting\ResilientBroadcastManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CSP nonce for inline <script> blocks (App\Http\Middleware\SecurityHeaders): <script @nonce>. Empty when
        // no nonce was made for this response (console, mail views, the loose fallback policy).
        \Illuminate\Support\Facades\Blade::directive('nonce', fn (): string => '<?php if ($__icNonce = \Illuminate\Support\Facades\Vite::cspNonce()): ?>nonce="<?php echo e($__icNonce); ?>"<?php endif; ?>');

        // Local and production databases stay apart; Supabase is never a runtime database. Checked from
        // configuration only (no connection is opened). iclimate:environment-check reports instead of stopping.
        if (! $this->app->runningUnitTests() && ! in_array('iclimate:environment-check', (array) ($_SERVER['argv'] ?? []), true)) {
            $guard = \App\Support\DatabaseEnvironmentGuard::inspect(config()->all(), (string) $this->app->environment());
            if ($guard['violations'] !== []) {
                throw new \RuntimeException('Unsafe database configuration: '.implode(' ', $guard['violations']));
            }
            foreach ($guard['warnings'] as $warning) {
                \Illuminate\Support\Facades\Log::warning($warning);
            }
        }

        // A running queue worker leaves a heartbeat (each loop, written every 20 s) so scheduled producers can tell a
        // stopped worker apart from a busy one (QueueWorkerHeartbeat).
        \Illuminate\Support\Facades\Queue::looping(static fn () => \App\Support\QueueWorkerHeartbeat::beat());
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobProcessed::class, static fn () => \App\Support\QueueWorkerHeartbeat::beat());

        // A queued account email that has now really gone out: its Last email line says Sent (AccountEmails).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, static function (\Illuminate\Mail\Events\MessageSent $event): void {
            $id = $event->data['accountEmailId'] ?? null;
            if (is_int($id) && \App\Support\SchemaCache::hasTable('account_emails')) {
                \App\Models\AccountEmail::query()->whereKey($id)->where('status', \App\Models\AccountEmail::STATUS_QUEUED)
                    ->update(['status' => \App\Models\AccountEmail::STATUS_SENT, 'sent_at' => now(), 'error' => null]);
            }
        });

        // Farmer – Naicel (2026-10): after a farm or profile is saved anywhere (e.g. the farm form), the farmer's
        // missing-info notifications are checked again on their next page (FarmerMissingInfoNotifier::syncOnPageLoad).
        // The listener returns nothing on purpose: a listener that returns false stops the ones after it.
        foreach ([\App\Models\Farm::class, \App\Models\FarmerProfile::class] as $farmerRecord) {
            $farmerRecord::saved(static function ($record): void {
                if ($record->user_id) {
                    \Illuminate\Support\Facades\Cache::forget(\App\Services\Farmer\FarmerMissingInfoNotifier::pageCheckKey((int) $record->user_id));
                }
            });
        }
        // end Farmer – Naicel

        // MAO/IT – Naicel (2026-10-07): when a climate record, rice production record, advisory or planner activity is
        // saved or deleted, every MAO's "no data yet" bell notifications are checked again after the response
        // (MaoMissingDataNotifier). Void listeners, for the same reason as above.
        foreach ([\App\Models\ClimateRecord::class, \App\Models\RiceProduction::class, \App\Models\PlantingAdvisory::class, \App\Models\MaoActivity::class] as $maoRecord) {
            $maoRecord::saved(static function (): void {
                \App\Services\Mao\MaoMissingDataNotifier::queueSyncAll();
            });
            $maoRecord::deleted(static function (): void {
                \App\Services\Mao\MaoMissingDataNotifier::queueSyncAll();
            });
        }
        // end MAO/IT – Naicel

        // One password rule everywhere a password is chosen (registration, IT-created accounts, change, reset).
        // Manuscript – Naicel (2026-10-08): IT sets the policy on Security settings (defaults: 8, mixed case, numbers, symbols).
        \Illuminate\Validation\Rules\Password::defaults(function () {
            $s = \App\Support\SystemSettings::class;
            $rule = \Illuminate\Validation\Rules\Password::min(max(8, (int) $s::get('password_min_length', 8)));
            $rule = $s::get('password_mixed_case', true) ? $rule->mixedCase() : $rule;
            $rule = $s::get('password_numbers', true) ? $rule->numbers() : $rule;

            return $s::get('password_symbols', true) ? $rule->symbols() : $rule;
        });
        // Manuscript – Naicel: session timeout from Security settings (read from the settings file: no query).
        if (is_numeric($timeout = \App\Support\SystemSettings::get('session_timeout_minutes'))) {
            config(['session.lifetime' => max(5, (int) $timeout)]);
        }
        // Manuscript – Naicel: the system name IT set (System Settings) is the name in every page title.
        if (($name = \App\Support\SystemSettings::get('system_name')) !== \App\Support\SystemSettings::DEFAULTS['system_name']) {
            config(['app.name' => (string) $name]);
        }

        // Same as the built-in "eloquent" user provider, minus the per-request user query.
        \Illuminate\Support\Facades\Auth::provider('cached-eloquent', fn ($app, array $config) => new \App\Auth\CachedEloquentUserProvider($app['hash'], $config['model']));

        $this->guardReusedConnection();

        // How much of a chat request is spent waiting on the database (records nothing outside a chat request).
        \Illuminate\Support\Facades\DB::listen(fn ($query) => app(\App\Services\AI\AiTimings::class)->recordQuery((float) $query->time));

        // Every scheduled run is recorded in scheduled_task_runs (shown on the IT dashboard).
        \App\Models\ScheduledTaskRun::listen();

        Paginator::useBootstrapFive();

        Carbon::macro('shortTime', function () {
            /** @var CarbonInterface $this */
            return $this->minute === 0 ? $this->format('g A') : $this->format('g:i A');
        });

        Carbon::macro('shortDateTime', function (string $dateFormat = 'M d, Y') {
            /** @var CarbonInterface $this */
            return trim($this->format($dateFormat).' '.$this->shortTime());
        });

        $this->registerDataCacheInvalidation();

        // A migration may add or drop a table or column: the cached schema lists (SchemaCache) are read again.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationsEnded::class, static fn () => \App\Support\SchemaCache::forget());

        // A write during a dashboard build: the reads that build remembered (BuildMemo) are made again. The cache and
        // session tables (CACHE_STORE / SESSION_DRIVER=database) hold no figures.
        \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $query): void {
            if (! \App\Support\BuildMemo::active() || preg_match('/^\s*(select|with)\b/i', $query->sql)) {
                return;
            }
            $kept = ['cache', 'cache_locks', 'sessions', config('cache.stores.database.table'), config('cache.stores.database.lock_table'), config('session.table')];
            if (! preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+"?([\w.]+)"?/i', $query->sql, $table)
                || ! in_array(strtolower((string) preg_replace('/^.*\./', '', $table[1])), array_filter($kept), true)) {
                \App\Support\BuildMemo::flush();
            }
        });

        $this->createMysqlDatabaseForMigrations();
    }

    /**
     * A reused (persistent) connection can carry an open transaction over from a request that
     * died mid-way (for example a fatal timeout). Before the first query of a request, roll such
     * a leftover back so it can not swallow this request's writes.
     */
    private function guardReusedConnection(): void
    {
        if (config('database.default') !== 'pgsql' || ! config('database.connections.pgsql.options.'.PDO::ATTR_PERSISTENT)) {
            return;
        }

        $checked = false;

        \Illuminate\Support\Facades\DB::beforeExecuting(function ($query, $bindings, $connection) use (&$checked): void {
            if ($checked) {
                return;
            }

            $checked = true;
            $pdo = $connection->getPdo();

            if ($connection->transactionLevel() === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        });
    }

    private function registerDataCacheInvalidation(): void
    {
        // A real data change retires only the caches that read that data (the map is in CacheScope); rows that belong
        // to one person also retire that person's private pages.
        \App\Support\CacheScope::listen();

        // Prediction pages receive only a status/reference signal. Their existing authorized
        // endpoints remain the source of truth and their polling fallback is retained.
        \App\Models\AnalyticsRun::saved(static function (\App\Models\AnalyticsRun $run): void {
            if ($run->wasRecentlyCreated || $run->wasChanged('status')) {
                \App\Events\PredictionChanged::forAnalyticsRun($run);
            }
        });
        \App\Models\YieldPredictionRequest::saved(static function (\App\Models\YieldPredictionRequest $request): void {
            if ($request->wasRecentlyCreated || $request->wasChanged('status')) {
                \App\Events\PredictionChanged::forYieldRequest($request);
            }
        });

        // Farm pages listen to a private owner/farm signal. CacheScope continues to emit the broader dashboard
        // invalidation for MAO/IT aggregates; this event carries only an opaque farm id and action metadata.
        \App\Models\Farm::saved(static function (\App\Models\Farm $farm): void {
            if (! \App\Support\CacheScope::isMeaningfulChange($farm)) {
                return;
            }

            $action = $farm->wasRecentlyCreated
                ? 'created'
                : ($farm->wasChanged('archived_at')
                    ? ($farm->archived_at === null ? 'restored' : 'deleted')
                    : 'updated');

            \App\Events\FarmChanged::dispatch(
                (int) $farm->getKey(),
                (int) $farm->user_id,
                $action,
                (string) \Illuminate\Support\Str::uuid(),
                now()->toIso8601String(),
            );
        });
        \App\Models\Farm::deleted(static function (\App\Models\Farm $farm): void {
            \App\Events\FarmChanged::dispatch(
                (int) $farm->getKey(),
                (int) $farm->user_id,
                'deleted',
                (string) \Illuminate\Support\Str::uuid(),
                now()->toIso8601String(),
            );
        });

        // A farm's expected yield depends on its size, type and location and on its planting plans, so a change to any of
        // them makes THAT farm's estimate due: the new one is started now (after the response), and the last estimate
        // stays on the card with its time until it is replaced. Another farm, or another farmer, is not touched.
        $estimateAgain = static function (int $userId, ?int $farmId): void {
            \App\Services\Analytics\FarmerDashboardService::estimateInputsChanged($userId, $farmId);
            \App\Jobs\PrepareFarmerYieldEstimate::queueFor($userId, $farmId);
        };

        \App\Models\Farm::saved(static function (\App\Models\Farm $farm) use ($estimateAgain): void {
            // An archived farm is no longer estimated.
            if ($farm->archived_at === null && ($farm->wasRecentlyCreated || $farm->wasChanged(\App\Models\Farm::ESTIMATE_INPUTS))) {
                $estimateAgain((int) $farm->user_id, (int) $farm->getKey());
            }
        });

        $planChanged = static function (\App\Models\FarmerPlantingSchedule $plan) use ($estimateAgain): void {
            if ($plan->user_id) {
                $estimateAgain((int) $plan->user_id, \App\Support\ActiveFarm::available() ? ($plan->farm_id ? (int) $plan->farm_id : null) : null);
            }
        };
        \App\Models\FarmerPlantingSchedule::saved(static function ($plan) use ($planChanged): void {
            if ($plan->wasRecentlyCreated || $plan->wasChanged()) {
                $planChanged($plan);
            }
        });
        \App\Models\FarmerPlantingSchedule::deleted($planChanged);

        // The municipality forecast is invalidated when one of its verified model inputs changes. The next warm
        // command or Predictions page prepares one fresh result; no request trains a model.
        \App\Models\MonthlyWeatherPrediction::saved(static function ($forecast): void {
            \App\Services\Prediction\MunicipalYieldForecastService::invalidateMonth((int) $forecast->prediction_year, (int) $forecast->prediction_month);
        });
        foreach ([\App\Models\RiceProduction::class, \App\Models\ClimateRecord::class] as $municipalInput) {
            $municipalInput::saved(static fn () => \App\Services\Prediction\MunicipalYieldForecastService::invalidateCurrent());
            $municipalInput::deleted(static fn () => \App\Services\Prediction\MunicipalYieldForecastService::invalidateCurrent());
        }
        \App\Models\MlModel::saved(static function ($model): void {
            if ($model->purpose === \App\Models\MlModel::PURPOSE_RICE_YIELD && ($model->wasRecentlyCreated || $model->wasChanged(['status', 'is_active', 'file_path', 'version']))) {
                \App\Services\Prediction\MunicipalYieldForecastService::invalidateCurrent();
            }
        });

        // The farmer profile's farm fields (registration, the mobile app, MAO edits): written to the farmer's primary farm
        // (their first farm is made from them). Before the farms table exists, the profile is still the farm.
        FarmerProfile::saved(static function (FarmerProfile $profile) use ($estimateAgain): void {
            if (! $profile->user_id || ! ($profile->wasRecentlyCreated || $profile->wasChanged(['barangay', 'farm_area', 'farm_type']))) {
                return;
            }
            if (\App\Support\ActiveFarm::available()) {
                app(\App\Services\Farms\FarmService::class)->fromProfile($profile);
            } else {
                $estimateAgain((int) $profile->user_id, null);
            }
        });
        FarmerProfile::deleted(static fn (FarmerProfile $profile) => $profile->user_id && ! \App\Support\ActiveFarm::available()
            ? $estimateAgain((int) $profile->user_id, null) : null);
    }

    private function createMysqlDatabaseForMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $command = $_SERVER['argv'][1] ?? null;

        if (! in_array($command, ['migrate', 'migrate:fresh'], true)
            || in_array('--pretend', $_SERVER['argv'] ?? [], true)) {
            return;
        }

        if (config('database.default') !== 'mysql') {
            return;
        }

        $database = config('database.connections.mysql.database');

        if (! $database) {
            return;
        }

        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', 3306);
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $charset) || ! preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            return;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => max(1, (int) config('database.connections.mysql.options.'.PDO::ATTR_TIMEOUT, 5)),
        ];

        try {
            // If the target database already exists and is accessible (the normal case for
            // scoped staging/production accounts), do not require global CREATE DATABASE privileges.
            new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password, $options);

            return;
        } catch (\PDOException $e) {
            // Only attempt CREATE DATABASE when MySQL/MariaDB specifically reports Unknown database (1049).
            if ((int) ($e->errorInfo[1] ?? 0) !== 1049) {
                return;
            }
        }

        $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, $options);

        $escapedDatabase = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    }
}
