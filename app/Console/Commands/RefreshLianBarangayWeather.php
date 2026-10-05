<?php

namespace App\Console\Commands;

use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Console\Command;

/**
 * Refreshes the shared current weather for all 19 Lian barangays (one batched Open-Meteo request). Scheduled
 * every 10 minutes; run with --force after a deployment to warm the weather before the first visitor.
 */
class RefreshLianBarangayWeather extends Command
{
    protected $signature = 'iclimate:refresh-barangay-weather
        {--force : Refresh even when every barangay already has a current reading}
        {--priority= : Barangay to request first}';

    protected $aliases = ['iclimate:refresh-lian-weather'];

    protected $description = 'Refresh the shared Open-Meteo current weather for all 19 Lian barangays.';

    public function handle(LianBarangayWeatherService $weather): int
    {
        if (! $this->option('force') && ! $weather->isDue()) {
            $this->info('Barangay weather is already current; nothing to refresh.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $records = $weather->refreshAll($this->option('priority') ?: null, wait: true);
        $seconds = round(microtime(true) - $started, 2);
        $summary = $weather->summary($records);

        $this->table(
            ['Barangay', 'Lat', 'Lon', 'Weather', 'Updated', 'Status'],
            collect($records)->map(fn (array $record): array => [
                $record['barangay'],
                $record['latitude'],
                $record['longitude'],
                $record['temperature_c'] === null ? '—' : $record['temperature_c'].'°C · '.($record['condition'] ?? '—'),
                $record['fetched_at'] ?? '—',
                $record['status'],
            ])->values()->all(),
        );

        $this->info("Latest weather for {$summary['current']} of {$summary['total']} barangays ({$summary['last_refresh_method']} request, {$seconds}s).");

        if ($summary['current'] < $summary['total']) {
            $this->warn(($summary['total'] - $summary['current']).' barangay(s) are using their last available reading or have none.');
        }

        return $summary['current'] > 0 ? self::SUCCESS : self::FAILURE;
    }
}
