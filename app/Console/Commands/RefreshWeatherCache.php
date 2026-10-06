<?php

namespace App\Console\Commands;

use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Keeps the stored Lian weather (current reading and forecast) fresh, so a
 * page or the chatbot reads it instantly instead of waiting on a weather
 * provider. Runs every few minutes from the scheduler; pages fall back to the
 * last good reading (up to 10 minutes old for the current reading, an hour for
 * the forecast) if this has not run.
 */
class RefreshWeatherCache extends Command
{
    protected $signature = 'iclimate:refresh-weather-cache';

    protected $description = 'Refresh the stored Lian weather reading and forecast so pages never wait on a weather provider.';

    public function handle(FarmerDashboardWeatherService $current, WeatherApiService $forecast): int
    {
        $failed = 0;

        try {
            $reading = $current->current(force: true);
            $this->line('Current reading: '.(($reading['success'] ?? false) ? 'refreshed' : 'provider unavailable, kept the last good reading'));
        } catch (Throwable) {
            $failed++;
            $this->warn('Current reading could not be refreshed.');
        }

        try {
            $forecast->forecast(refresh: true);
            $this->line('Forecast: refreshed');
        } catch (Throwable) {
            $failed++;
            $this->warn('Forecast could not be refreshed.');
        }

        // The Predictions page shows each person the forecast for their own barangay. Fetch the ones that are not
        // stored (or have expired) here, so the first visitor after an expiry does not wait several seconds on the
        // weather provider. A stored forecast is left alone, so this adds at most one request per barangay per expiry.
        try {
            $barangays = \App\Models\FarmerProfile::query()->whereNotNull('barangay')->distinct()->pluck('barangay')
                ->merge(\App\Models\User::query()->whereNotNull('barangay')->distinct()->pluck('barangay'))
                ->map(fn ($name) => \App\Support\LianBarangays::canonicalName((string) $name))->filter()->unique();
            $warmed = 0;

            foreach ($barangays as $barangay) {
                $point = \App\Support\LianBarangays::coordinateFor($barangay);
                if ($point) {
                    $forecast->forecast(latitude: $point[0], longitude: $point[1], location: $barangay.', Lian, Batangas');
                    $warmed++;
                }
            }
            $this->line("Barangay forecasts ready: {$warmed}");
        } catch (Throwable) {
            // Optional warm-up: the page still fetches its own forecast when this could not.
            $this->warn('Barangay forecasts could not be prepared.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
