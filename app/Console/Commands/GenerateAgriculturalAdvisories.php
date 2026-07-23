<?php

namespace App\Console\Commands;

use App\Services\Advisories\AdvisoryGenerationService;
use Illuminate\Console\Command;

class GenerateAgriculturalAdvisories extends Command
{
    protected $signature = 'iclimate:generate-advisories {--fetch-weather : Fetch weather before generating} {--force-weather : Force the weather API call}';

    protected $description = 'Generate agricultural advisories from stored or freshly fetched weather data.';

    public function handle(AdvisoryGenerationService $service): int
    {
        $summary = $service->generate(
            fetchWeather: (bool) $this->option('fetch-weather'),
            forceWeather: (bool) $this->option('force-weather'),
        );

        $this->info('Advisories Generated');
        $this->table(['Metric', 'Value'], [
            ['Weather records saved', $summary['weather_records_saved']],
            ['Advisories created', $summary['advisories_created']],
            ['Duplicates skipped', $summary['advisories_skipped_as_duplicates']],
            ['Expired advisories updated', $summary['advisories_expired']],
            ['Weather freshness', $summary['weather_freshness']],
        ]);

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
