<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The climate inputs of the deployed rice-yield model for ONE target month (verification 2026-11-18).
 *
 * Before this, every prediction read "the latest six months on file" whatever month it was for, so all 6,435 stored
 * predictions shared one value for eight of the twelve features. Here every window is anchored on the target month T:
 *
 *   previous_*            month T-1
 *   rainfall_6m           MEAN monthly rainfall of T-1 .. T-6     (model split thresholds 54-311 mm: a mean, not a sum)
 *   temp_3m / temp_6m     mean temperature of T-1 .. T-3 / T-1 .. T-6
 *   seasonal_rainfall     SUM over the WHOLE model season containing T (Wet May-Sep, Dry Oct-Apr): the thresholds
 *                         280-1864 mm match whole-season totals, so the model was trained with them
 *   seasonal_temp         mean temperature over the same whole season
 *   temp_range            TMAX - TMIN of the target month itself (thresholds 5.75-9.45 degC), from the month's weather
 *
 * Lags and trailing windows read only months strictly before T. The whole-season pair is the one exception, decided by
 * the owner on 2026-11-18 to match training: its months before T must be observed; T itself uses the target month's
 * weather; months after T use the observation when that month has passed, otherwise the stored monthly weather
 * forecast (they are listed in seasonal_forecast_months). A month is the entered Climate Record when one exists,
 * otherwise the Ambulong station record. A month that is neither observed nor forecast stays missing: no other month
 * and no invented value takes its place, and the feature is null (the prediction reports it as missing).
 */
final class YieldModelClimateWindow
{
    public const MONTHS = 6;

    /** @var array<string, object>|null station months keyed Y-m, read once per process */
    private static ?array $station = null;

    /**
     * @param  array<string, mixed>  $targetWeather  the target month's weather (needs tmax and tmin for temp_range)
     * @return array{features: array<string, ?float>, months_used: list<string>, missing_months: list<string>}
     */
    public static function features(CarbonImmutable $target, array $targetWeather = []): array
    {
        $history = self::history();
        $first = $target->startOfMonth();
        $months = [];
        $missing = [];
        for ($i = 1; $i <= self::MONTHS; $i++) {
            $key = $first->subMonthsNoOverflow($i)->format('Y-m');
            $row = $history[$key] ?? null;
            $complete = $row !== null && is_numeric($row->rainfall ?? null) && is_numeric($row->temperature ?? null);
            $months[$i] = $complete ? $row : null;
            if (! $complete) {
                $missing[] = $key;
            }
        }

        $num = static fn ($v): ?float => is_numeric($v) ? (float) $v : null;
        $round = static fn (?float $v): ?float => $v === null ? null : round($v, 2);
        $window = static fn (int $n): ?Collection => collect(range(1, $n))->every(fn (int $i): bool => $months[$i] !== null)
            ? collect(range(1, $n))->map(fn (int $i): object => $months[$i])
            : null;

        $six = $window(self::MONTHS);
        $three = $window(3);
        $seasonal = self::wholeSeason($first, $history, $targetWeather);
        $previous = $months[1];
        $tmax = $num($targetWeather['tmax'] ?? null);
        $tmin = $num($targetWeather['tmin'] ?? null);

        return [
            'features' => [
                'temp_range' => $tmax !== null && $tmin !== null && $tmax >= $tmin ? round($tmax - $tmin, 2) : null,
                'previous_rainfall' => $round($num($previous?->rainfall)),
                'previous_temp' => $round($num($previous?->temperature)),
                'previous_humidity' => $round($num($previous?->humidity)),
                'previous_wind_speed' => $round($num($previous?->wind_speed)),
                'rainfall_6m' => $six ? round((float) $six->avg('rainfall'), 2) : null,
                'temp_3m' => $three ? round((float) $three->avg('temperature'), 2) : null,
                'temp_6m' => $six ? round((float) $six->avg('temperature'), 2) : null,
                'seasonal_rainfall' => $seasonal['rainfall'],
                'seasonal_temp' => $seasonal['temperature'],
            ],
            'months_used' => collect($months)->filter()->map(fn (object $m): string => substr((string) $m->record_date, 0, 7))->values()->all(),
            'missing_months' => $missing,
            'seasonal_months' => $seasonal['months'],
            'seasonal_forecast_months' => $seasonal['forecast'],
            'seasonal_missing_months' => $seasonal['missing'],
        ];
    }

    /**
     * The whole model season around the target month (Wet May-Sep, Dry Oct-Apr, as RiceSeason encodes the model).
     *
     * @param  array<string, object>  $history
     * @param  array<string, mixed>  $targetWeather
     * @return array{rainfall: ?float, temperature: ?float, months: list<string>, forecast: list<string>, missing: list<string>}
     */
    private static function wholeSeason(CarbonImmutable $first, array $history, array $targetWeather): array
    {
        $season = RiceSeason::forTrainedYieldModel((int) $first->format('n'));
        $months = [$first];
        for ($m = $first->subMonthNoOverflow(); RiceSeason::forTrainedYieldModel((int) $m->format('n')) === $season; $m = $m->subMonthNoOverflow()) {
            array_unshift($months, $m);
        }
        for ($m = $first->addMonthNoOverflow(); RiceSeason::forTrainedYieldModel((int) $m->format('n')) === $season; $m = $m->addMonthNoOverflow()) {
            $months[] = $m;
        }

        $targetKey = $first->format('Y-m');
        $forecasts = self::forecasts();
        $rain = []; $temp = []; $forecast = []; $missing = [];
        foreach ($months as $m) {
            $key = $m->format('Y-m');
            $observed = $history[$key] ?? null;
            $observed = $observed && is_numeric($observed->rainfall ?? null) && is_numeric($observed->temperature ?? null) ? $observed : null;
            if ($key < $targetKey) {
                $value = $observed ? [(float) $observed->rainfall, (float) $observed->temperature] : null;
            } elseif ($key === $targetKey) {
                $value = is_numeric($targetWeather['rainfall'] ?? null) && is_numeric($targetWeather['temperature'] ?? null)
                    ? [(float) $targetWeather['rainfall'], (float) $targetWeather['temperature']] : null;
            } elseif ($observed) {
                $value = [(float) $observed->rainfall, (float) $observed->temperature];
            } elseif (isset($forecasts[$key])) {
                $value = $forecasts[$key];
                $forecast[] = $key;
            } else {
                $value = null;
            }
            if ($value === null) {
                $missing[] = $key;

                continue;
            }
            $rain[] = $value[0];
            $temp[] = $value[1];
        }
        $complete = $missing === [];

        return [
            'rainfall' => $complete ? round(array_sum($rain), 2) : null,
            'temperature' => $complete ? round(array_sum($temp) / count($temp), 2) : null,
            'months' => array_map(fn (CarbonImmutable $m): string => $m->format('Y-m'), $months),
            'forecast' => $forecast,
            'missing' => $missing,
        ];
    }

    /** @return array<string, array{0: float, 1: float}> the latest ready monthly weather forecast per month ('unready' rows are not forecasts) */
    private static function forecasts(): array
    {
        try {
            if (! SchemaCache::hasTable('monthly_weather_predictions')) {
                return [];
            }
            $out = [];
            foreach (\App\Models\MonthlyWeatherPrediction::query()->where('status', 'ready')->whereNotNull('rainfall')->whereNotNull('temperature_average')
                ->orderBy('generated_at')->get(['prediction_year', 'prediction_month', 'rainfall', 'temperature_average']) as $row) {
                $out[sprintf('%04d-%02d', $row->prediction_year, $row->prediction_month)] = [(float) $row->rainfall, (float) $row->temperature_average];
            }

            return $out;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * Every observed month keyed Y-m: the Ambulong station record, with each entered Climate Record month taking
     * the place of the station month it covers (an entered month never removes other station months).
     *
     * @return array<string, object>
     */
    public static function history(): array
    {
        if (self::$station === null) {
            self::$station = [];
            foreach (ClimateHistory::stationMonths() ?? collect() as $row) {
                self::$station[substr((string) $row->record_date, 0, 7)] = $row;
            }
        }
        $months = self::$station;
        try {
            foreach (ClimateHistory::enteredMonths() as $row) {
                $key = substr((string) $row->record_date, 0, 7);
                // An entered month replaces the station month only when it has the readings the model needs.
                if ((is_numeric($row->rainfall ?? null) && is_numeric($row->temperature ?? null)) || ! isset($months[$key])) {
                    $months[$key] = $row;
                }
            }
        } catch (Throwable $exception) {
            report($exception);   // a database outage keeps the station record
        }

        return $months;
    }

    /** Tests and long-running workers that reload the station file. */
    public static function forget(): void
    {
        self::$station = null;
    }
}
