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

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
