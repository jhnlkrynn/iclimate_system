<?php

namespace App\Providers;

use App\Models\ClimateRecord;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Same as the built-in "eloquent" user provider, minus the per-request user query.
        \Illuminate\Support\Facades\Auth::provider('cached-eloquent', fn ($app, array $config) => new \App\Auth\CachedEloquentUserProvider($app['hash'], $config['model']));

        $this->guardReusedConnection();

        // How much of a chat request is spent waiting on the database (records nothing outside a chat request).
        \Illuminate\Support\Facades\DB::listen(fn ($query) => app(\App\Services\AI\AiTimings::class)->recordQuery((float) $query->time));

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
        $flush = static function (): void {
            // Everything cached with PageCache::shared() (dashboards, lists, reference data).
            \App\Support\PageCache::flushShared();

            // The MAO / IT dashboards key their cached payload on this version, so a data change retires them at once.
            app(\App\Support\DashboardData::class)->forget();
            \App\Support\DashboardData::bump();

            foreach ([
                'dashboard:shared-stats',
                'dashboard:mao-aggregates',
                'dashboard:admin-aggregates',
                'dashboard:farmer-organizations',
                'dashboard:recent-operational-data',
                'dashboard:high-risk-areas',
                'dashboard:mao-action-items',
                'reports:filter-barangays',
                'reports:source-stats',
            ] as $key) {
                Cache::forget($key);
            }
        };

        foreach ([
            User::class,
            FarmerProfile::class,
            FarmerOrganization::class,
            ClimateRecord::class,
            RiceProduction::class,
            PlantingAdvisory::class,
            FeedPost::class,
            Notification::class,
            Report::class,
            \App\Models\HeatmapArea::class,
            \App\Models\FieldReport::class,
            \App\Models\FarmerPlantingSchedule::class,
            \App\Models\WeatherForecastFeedback::class,
            \App\Models\AdvisoryFeedback::class,
            \App\Models\MonthlyWeatherPrediction::class,
            \App\Models\ExternalWeatherData::class,
            \App\Models\MlModel::class,
            \App\Models\FeedComment::class,
            \App\Models\FeedReaction::class,
            \App\Models\FeedMedia::class,
            \App\Models\TyphoonSafetyResponse::class,
            \App\Models\AnalyticsRun::class,
        ] as $model) {
            // Eloquent fires "saved" even when nothing changed (e.g. the risk-map refresh re-saves 19 identical rows);
            // only a real change should throw the cached pages away.
            $model::saved(static function ($record) use ($flush): void {
                if ($record->wasRecentlyCreated || $record->wasChanged()) {
                    $flush();
                }
            });
            $model::deleted($flush);
        }

        // A farmer's expected yield depends on their farm area, farm type and planting schedule, so it must not outlive an edit
        // to any of them. Both the payload cache and the 6-hour prediction cache are dropped for that one farmer.
        foreach ([FarmerProfile::class, \App\Models\FarmerPlantingSchedule::class] as $model) {
            $forgetFarmer = static function ($record): void {
                if ($record->user_id) {
                    Cache::forget(\App\Services\Analytics\FarmerDashboardService::predictionCacheKey((int) $record->user_id));
                }
            };
            $model::saved(static function ($record) use ($forgetFarmer): void {
                if ($record->wasRecentlyCreated || $record->wasChanged()) {
                    $forgetFarmer($record);
                }
            });
            $model::deleted($forgetFarmer);
        }

        // Private per-person data (notifications, their advisory feedback, their profile): only that person's cache is cleared.
        $flushOwner = static function ($model): void {
            if ($model->user_id ?? null) {
                \App\Support\PageCache::flushUser((int) $model->user_id);
            }
        };

        foreach ([Notification::class, \App\Models\AdvisoryFeedback::class, FarmerProfile::class, \App\Models\FarmBoundary::class] as $model) {
            $model::saved($flushOwner);
            $model::deleted($flushOwner);
        }
    }

    private function createMysqlDatabaseForMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $command = $_SERVER['argv'][1] ?? null;

        if (! in_array($command, ['migrate', 'migrate:fresh'], true)) {
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

        $pdo = new PDO(
            "mysql:host={$host};port={$port}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $escapedDatabase = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    }
}
