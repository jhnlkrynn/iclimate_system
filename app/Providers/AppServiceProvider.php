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
        //
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
            foreach ([
                'dashboard:shared-stats',
                'dashboard:mao-aggregates',
                'dashboard:admin-aggregates',
                'dashboard:farmer-organizations',
                'dashboard:recent-operational-data',
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
        ] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
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
