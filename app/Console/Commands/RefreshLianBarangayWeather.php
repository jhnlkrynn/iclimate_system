<?php

namespace App\Console\Commands;

use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Console\Command;

/**
 * Refreshes the shared current weather for all 19 Lian barangays (one batched Open-Meteo request), then rates the
 * barangays from that weather with the existing risk engine, so the risk always follows the weather without a queue
 * worker. Scheduled every 10 minutes; run with --force after a deployment to warm weather and risk together.
 */
class RefreshLianBarangayWeather extends Command
{
    protected $signature = 'iclimate:refresh-barangay-weather
        {--force : Refresh even when every barangay already has a current reading}
        {--priority= : Barangay to request first}';

    protected $aliases = ['iclimate:refresh-lian-weather'];

    protected $description = 'Refresh the shared Open-Meteo current weather for all 19 Lian barangays.';

    public function handle(LianBarangayWeatherService $weather, BarangayRiskMapService $risk): int
    {
        if (! $this->option('force') && ! $weather->isDue()) {
            $this->info('Barangay weather is already current; nothing to refresh.');
            $this->rateRisk($risk);

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

        if ($summary['current'] > 0) {
            $this->rateRisk($risk, (bool) $this->option('force'));
        }

        return $summary['current'] > 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Rate the barangays from this weather (skipped when these exact readings are already rated). */
    private function rateRisk(BarangayRiskMapService $risk, bool $force = false): void
    {
        $started = microtime(true);
        $map = $risk->refreshForCurrentWeather($force);
        $seconds = round(microtime(true) - $started, 2);

        if ($map === null) {
            $this->line('Risk: already rated from this weather (or another process is rating it).');

            return;
        }

        $levels = collect($map['barangays'] ?? [])->countBy('risk_level')->sortKeys()->map(fn (int $count, string $level): string => "{$level} {$count}")->implode(', ');
        $this->info("Risk rated from this weather in {$seconds}s: {$levels}.");
    }
}
