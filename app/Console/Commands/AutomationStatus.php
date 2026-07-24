<?php

namespace App\Console\Commands;

use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\SystemLog;
use App\Services\TyphoonSafetyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AutomationStatus extends Command
{
    protected $signature = 'iclimate:automation-status';

    protected $description = 'Show iClimate automation health for weather, advisories, expirations, and typhoon safety responses.';

    public function handle(TyphoonSafetyService $typhoonSafety): int
    {
        $this->info('iClimate Automation Status');

        if (! $this->hasRequiredTables()) {
            $this->error('Required automation tables are missing. Run php artisan migrate.');

            return self::FAILURE;
        }

        $latestWeather = ExternalWeatherData::query()->latestSuccessful()->first();
        $activeTyphoonEvent = $typhoonSafety->activeEvent();
        $latestAutomation = Schema::hasTable('system_logs')
            ? SystemLog::query()->where('action', 'iclimate.automation')->latest()->first()
            : null;
        $latestAutomationDetails = $latestAutomation
            ? json_decode((string) $latestAutomation->details, true)
            : null;

        $this->table(['Check', 'Status'], [
            ['Last automation run', $latestAutomation
                ? $latestAutomation->created_at?->format('M d, Y h:i A').' ('.(data_get($latestAutomationDetails, 'ok') ? 'ok' : 'needs review').')'
                : 'No full automation run logged yet'],
            ['Latest weather fetch', $latestWeather
                ? $latestWeather->fetched_at?->format('M d, Y h:i A').' ('.$latestWeather->freshnessLabel().')'
                : 'No Open-Meteo weather data yet'],
            ['Active published advisories', (string) PlantingAdvisory::query()->active()->count()],
            ['Pending review advisories', (string) PlantingAdvisory::query()->pendingReview()->count()],
            ['Expired advisories', (string) PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_EXPIRED)->count()],
            ['PAGASA advisories stored', (string) PlantingAdvisory::query()->where('source', 'PAGASA')->count()],
            ['Typhoon safety response', $activeTyphoonEvent
                ? 'Open: '.$activeTyphoonEvent['title']
                : 'Closed: no active typhoon advisory'],
            ['Automation log', storage_path('logs/automation.log')],
        ]);

        if (! $latestWeather) {
            $this->warn('Weather automation has not saved any Open-Meteo records yet.');
        } elseif ($latestWeather->isOutdated()) {
            $this->warn('Weather data is outdated. Check the scheduler or Open-Meteo connectivity.');
        }

        $this->line('Run php artisan schedule:work on local/XAMPP, or configure the server cron to run php artisan schedule:run every minute.');

        return self::SUCCESS;
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('external_weather_data')
            && Schema::hasTable('planting_advisories');
    }
}
