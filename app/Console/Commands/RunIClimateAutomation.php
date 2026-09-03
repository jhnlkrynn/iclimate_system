<?php

namespace App\Console\Commands;

use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\SystemLog;
use App\Services\Advisories\AdvisoryGenerationService;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RunIClimateAutomation extends Command
{
    protected $signature = 'iclimate:automation-run
        {--force-weather : Force a fresh Open-Meteo request}
        {--force-pagasa : Force fresh PAGASA requests}
        {--skip-pagasa : Skip PAGASA online advisory page fetching for this run}';

    protected $description = 'Run the full iClimate automation pipeline for weather, advisories, cleanup, and safety state.';

    public function handle(
        OpenMeteoService $weather,
        PagasaAdvisoryService $pagasa,
        AdvisoryGenerationService $generator,
        TyphoonSafetyService $typhoonSafety,
    ): int {
        if (! $this->hasRequiredTables()) {
            $this->error('Required automation tables are missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $startedAt = now();
        $summary = [
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => null,
            'ok' => true,
            'steps' => [],
            'errors' => [],
        ];

        $this->info('Running iClimate full automation...');

        $summary['steps']['weather'] = $this->runStep('Weather refresh', function () use ($weather) {
            return $weather->fetchForecast((bool) $this->option('force-weather'));
        }, $summary);

        if (! $this->option('skip-pagasa')) {
            $summary['steps']['pagasa'] = $this->runStep('PAGASA online advisory fetch', function () use ($pagasa) {
                return $pagasa->fetchAndStore((bool) $this->option('force-pagasa'));
            }, $summary);
        }

        $summary['steps']['advisory_generation'] = $this->runStep('Advisory generation', function () use ($generator) {
            return $generator->generate(fetchWeather: false, forceWeather: false);
        }, $summary);

        $summary['steps']['expiry_cleanup'] = $this->runStep('Expiry cleanup', function () use ($generator) {
            return ['expired' => $generator->expireOutdated()];
        }, $summary);

        $activeTyphoonEvent = $typhoonSafety->activeEvent();
        $summary['steps']['safety_gate'] = [
            'open' => $activeTyphoonEvent !== null,
            'event' => $activeTyphoonEvent,
        ];

        $summary['snapshot'] = [
            'latest_weather_at' => ExternalWeatherData::query()->latestSuccessful()->value('fetched_at'),
            'active_advisories' => PlantingAdvisory::query()->active()->count(),
            'pending_review_advisories' => PlantingAdvisory::query()->pendingReview()->count(),
            'expired_advisories' => PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_EXPIRED)->count(),
            'pagasa_advisories' => PlantingAdvisory::query()->fromPagasaOnline()->count(),
            'typhoon_safety_open' => $activeTyphoonEvent !== null,
        ];
        $summary['finished_at'] = now()->toDateTimeString();
        $summary['duration_seconds'] = $startedAt->diffInSeconds(now());

        $compactSummary = $this->compactSummary($summary);
        $this->recordSystemLog($compactSummary);
        $this->pruneOldAutomationLogs();
        Log::info('iClimate automation run completed.', $compactSummary);

        $this->table(['Metric', 'Value'], [
            ['Weather records saved', (string) data_get($summary, 'steps.weather.records_saved', 0)],
            ['PAGASA online advisories created', (string) data_get($summary, 'steps.pagasa.advisories_created', 0)],
            ['Generated advisories created', (string) data_get($summary, 'steps.advisory_generation.advisories_created', 0)],
            ['Expired advisories updated', (string) data_get($summary, 'steps.expiry_cleanup.expired', 0)],
            ['Typhoon safety gate', $activeTyphoonEvent ? 'Open' : 'Closed'],
            ['Duration', $summary['duration_seconds'].'s'],
        ]);

        if ($summary['errors'] !== []) {
            foreach ($summary['errors'] as $error) {
                $this->warn($error);
            }

            return self::FAILURE;
        }

        $this->info('Automation completed successfully.');

        return self::SUCCESS;
    }

    private function runStep(string $label, callable $callback, array &$summary): array
    {
        $this->line("-> {$label}");

        try {
            $result = $callback();
            $errors = array_filter((array) ($result['errors'] ?? []));

            foreach ($errors as $error) {
                $summary['errors'][] = "{$label}: {$error}";
            }

            return $result;
        } catch (Throwable $exception) {
            $summary['ok'] = false;
            $summary['errors'][] = "{$label}: {$exception->getMessage()}";

            return [
                'ok' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('external_weather_data')
            && Schema::hasTable('planting_advisories');
    }

    private function compactSummary(array $summary): array
    {
        return [
            'started_at' => $summary['started_at'],
            'finished_at' => $summary['finished_at'],
            'duration_seconds' => $summary['duration_seconds'] ?? null,
            'ok' => $summary['ok'] && $summary['errors'] === [],
            'weather' => [
                'ok' => (bool) data_get($summary, 'steps.weather.ok', false),
                'freshness' => data_get($summary, 'steps.weather.freshness'),
                'records_saved' => data_get($summary, 'steps.weather.records_saved', 0),
                'fetched_at' => data_get($summary, 'steps.weather.fetched_at'),
                'message' => data_get($summary, 'steps.weather.message'),
            ],
            'pagasa' => [
                'ok' => (bool) data_get($summary, 'steps.pagasa.ok', true),
                'sources_checked' => data_get($summary, 'steps.pagasa.sources_checked', 0),
                'created' => data_get($summary, 'steps.pagasa.advisories_created', 0),
                'duplicates' => data_get($summary, 'steps.pagasa.advisories_skipped_as_duplicates', 0),
                'without_lian_batangas_match' => data_get($summary, 'steps.pagasa.sources_without_lian_batangas_match', 0),
            ],
            'advisories' => [
                'created' => data_get($summary, 'steps.advisory_generation.advisories_created', 0),
                'duplicates' => data_get($summary, 'steps.advisory_generation.advisories_skipped_as_duplicates', 0),
                'expired' => data_get($summary, 'steps.expiry_cleanup.expired', 0),
                'weather_freshness' => data_get($summary, 'steps.advisory_generation.weather_freshness'),
            ],
            'safety_gate' => [
                'open' => (bool) data_get($summary, 'steps.safety_gate.open', false),
                'event_title' => data_get($summary, 'steps.safety_gate.event.title'),
                'event_key' => data_get($summary, 'steps.safety_gate.event.key'),
            ],
            'snapshot' => $summary['snapshot'] ?? [],
            'errors' => $summary['errors'],
        ];
    }

    private function recordSystemLog(array $summary): void
    {
        if (! Schema::hasTable('system_logs')) {
            return;
        }

        SystemLog::query()->create([
            'action' => 'iclimate.automation',
            'details' => json_encode($summary, JSON_PRETTY_PRINT),
        ]);
    }

    private function pruneOldAutomationLogs(): void
    {
        if (! Schema::hasTable('system_logs')) {
            return;
        }

        SystemLog::query()
            ->where('action', 'iclimate.automation')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }
}
