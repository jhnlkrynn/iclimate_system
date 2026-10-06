<?php

namespace App\Services\Weather;

use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\PredictionOrchestrator;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The Predictions page's day-by-day weather, from saved data only: [This Week] = days 1–7, [Next 2 Weeks] = days 1–14,
 * day 1 being today. Each day comes from the stored daily forecast — Lian-wide (FarmerDashboardWeatherService) or one
 * barangay (LianBarangayWeatherService) — and a day the provider does not cover is a "Tantiya" (estimate) from the
 * monthly model's prepared forecast for that month. Never calls a provider or trains a model.
 */
final class DailyForecastService
{
    public const TIMEZONE = 'Asia/Manila';

    public function __construct(
        private readonly FarmerDashboardWeatherService $lian,
        private readonly LianBarangayWeatherService $barangays,
        private readonly PredictionOrchestrator $orchestrator,
        private readonly WeatherIconMapper $icons,
    ) {}

    /**
     * @return array{state: string, location: string, updated_at: ?CarbonImmutable, days: array<int, array<string, mixed>>, estimated: int}
     */
    public function forLocation(?string $barangay, int $days): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        [$stored, $updated] = $barangay ? $this->barangayDays($barangay) : $this->lianDays();
        if ($barangay && $stored === []) {
            // No saved forecast for the barangay yet: the Lian-wide one, labelled as Lian (never as the barangay).
            [$stored, $updated] = $this->lianDays();
            $barangay = null;
        }
        $byDate = collect($stored)->keyBy('date');

        $monthly = $this->monthlyEstimates($today, $days);
        $list = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $today->addDays($i);
            $day = $byDate->get($date->toDateString());
            $list[] = is_array($day) ? $this->fromForecast($date, $day) : $this->fromMonthly($date, $monthly[$date->format('Y-m')] ?? null);
        }
        $known = array_filter($list, fn (array $d): bool => $d['kind'] !== 'none');

        return [
            'state' => $known === [] ? 'nodata' : 'ok',
            'location' => $barangay ? $barangay.', Lian' : 'Lian, Batangas',
            'updated_at' => $updated,
            'days' => $list,
            'estimated' => count(array_filter($list, fn (array $d): bool => $d['kind'] === 'estimate')),
        ];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: ?CarbonImmutable} */
    private function lianDays(): array
    {
        try {
            $w = $this->lian->current();
        } catch (Throwable) {
            return [[], null];
        }
        if (! ($w['success'] ?? false)) {
            return [[], null];
        }
        $fetched = data_get($w, 'timestamps.server_fetched_at') ?? ($w['fetched_at'] ?? null);

        return [(array) ($w['forecast_extended'] ?? $w['forecast'] ?? []), $fetched ? CarbonImmutable::parse($fetched)->timezone(self::TIMEZONE) : null];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: ?CarbonImmutable} */
    private function barangayDays(string $barangay): array
    {
        $canonical = LianBarangays::canonicalName($barangay);
        $record = $canonical ? rescue(fn () => $this->barangays->cachedForBarangay($canonical), null, false) : null;
        if (! is_array($record)) {
            return [[], null];
        }

        return [(array) ($record['daily'] ?? []), ! empty($record['fetched_at']) ? CarbonImmutable::parse($record['fetched_at'])->timezone(self::TIMEZONE) : null];
    }

    /** @return array<string, array<string, mixed>> prepared monthly forecasts keyed by 'Y-m' (read only) */
    private function monthlyEstimates(CarbonImmutable $today, int $days): array
    {
        $months = collect(range(0, $days - 1))->map(fn (int $i) => $today->addDays($i)->startOfMonth())->unique(fn ($m) => $m->format('Y-m'))->values()->all();

        return rescue(fn (): array => $this->orchestrator->preparedMonthlyWeatherForecasts($months), [], false);
    }

    /** @return array<string, mixed> */
    private function fromForecast(CarbonImmutable $date, array $day): array
    {
        $rain = is_numeric($day['rainfall'] ?? null) ? (float) $day['rainfall'] : null;
        $chance = is_numeric($day['precipitation_probability'] ?? null) ? (float) $day['precipitation_probability'] : null;
        $wind = is_numeric($day['wind_speed_max'] ?? null) ? (float) $day['wind_speed_max'] : null;
        $high = is_numeric($day['temperature_max'] ?? null) ? (float) $day['temperature_max'] : null;
        $low = is_numeric($day['temperature_min'] ?? null) ? (float) $day['temperature_min'] : null;
        $condition = (string) ($day['condition'] ?? '');
        $rainKey = FarmerDashboardService::rainKey($rain, $chance);
        $words = FarmerDashboardService::dayWords($condition, $rainKey);

        return [
            'kind' => 'forecast',
            'date' => $date,
            'condition' => $words['condition'],
            'icon' => $this->icon($condition, $rainKey),
            'rain_key' => $rainKey,
            'rain_words' => $words['rain'],
            'wind_words' => FarmerDashboardService::windWords($wind),
            'rain_mm' => $rain,
            'chance' => $chance,
            'high' => $high,
            'low' => $low,
            'wind_kmh' => $wind,
        ];
    }

    /**
     * A day the provider does not cover: the monthly model's forecast spread over the month (an estimate, labelled so).
     *
     * @return array<string, mixed>
     */
    private function fromMonthly(CarbonImmutable $date, ?array $month): array
    {
        $p = is_array($month) && ($month['ready'] ?? true) ? (array) ($month['predictions'] ?? []) : [];
        if (! is_numeric($p['rainfall'] ?? null) && ! is_numeric($p['temperature'] ?? null)) {
            return ['kind' => 'none', 'date' => $date];
        }
        $perDay = is_numeric($p['rainfall'] ?? null) ? round((float) $p['rainfall'] / $date->daysInMonth, 1) : null;
        $rainKey = FarmerDashboardService::rainKey($perDay, null);

        return [
            'kind' => 'estimate',
            'date' => $date,
            'condition' => __('farmer.predictions.estimate'),
            'icon' => $rainKey && $rainKey !== 'none' ? 'rain' : 'weather',
            'rain_key' => $rainKey,
            'rain_words' => $rainKey ? __('farmer.home.rain.'.$rainKey) : null,
            'wind_words' => null,
            'rain_mm' => $perDay,
            'chance' => null,
            'high' => is_numeric($p['tmax'] ?? null) ? (float) $p['tmax'] : null,
            'low' => is_numeric($p['tmin'] ?? null) ? (float) $p['tmin'] : null,
            'avg' => is_numeric($p['temperature'] ?? null) ? (float) $p['temperature'] : null,
            'wind_kmh' => null,
        ];
    }

    private function icon(string $condition, ?string $rainKey): string
    {
        $text = strtolower($condition);

        return match (true) {
            str_contains($text, 'thunder') || str_contains($text, 'storm') => 'storm',
            str_contains($text, 'rain') || str_contains($text, 'shower') || str_contains($text, 'drizzle') => 'rain',
            str_contains($text, 'fog') || str_contains($text, 'mist') => 'fog',
            str_contains($text, 'partly') || str_contains($text, 'mostly') => 'cloud-sun',
            str_contains($text, 'cloud') || str_contains($text, 'overcast') => 'weather',
            str_contains($text, 'clear') || str_contains($text, 'sunny') => 'sun',
            $rainKey !== null && $rainKey !== 'none' => 'rain',
            default => 'weather',
        };
    }
}
