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

        // One stopwatch and one history read per chat message, shared by every AI service.
        $this->app->singleton(\App\Services\AI\AiTimings::class);
        $this->app->singleton(\App\Services\AI\ConversationContext::class);

        // Saves a network round trip on every new PostgreSQL connection (see the class).
        $this->app->singleton(\App\Support\DashboardData::class);
        $this->app->bind('db.connector.pgsql', fn () => new \App\Database\FastPostgresConnector);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

    private function registerDataCacheInvalidation(): void
    {
        $flush = static function (): void {
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
            \App\Models\AdvisoryFeedback::class,
            \App\Models\FieldReport::class,
            \App\Models\TyphoonSafetyResponse::class,
            \App\Models\FarmerPlantingSchedule::class,
            \App\Models\AnalyticsRun::class,
            \App\Models\MonthlyWeatherPrediction::class,
        ] as $model) {
            // Eloquent fires "saved" even when nothing changed (e.g. the risk-map
            // refresh re-saves 19 identical rows); only real changes should
            // throw the cached dashboards away.
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
