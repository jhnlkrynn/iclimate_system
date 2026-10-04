<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * The numbers on the Farmer dashboard, calculated from real inputs only. No database or network access: callers pass
 * in the data (daily rain, forecast days, planting date ...), so every calculation can be tested on its own.
 *
 * Rules every method follows:
 *   - missing input gives `value = null` and a `reason` (translated text), never 0. Zero means "measured as zero";
 *   - thresholds come from config/farmer_dashboard.php, never from this file;
 *   - rain is rounded to a whole mm (one decimal under 10), temperature to one decimal, percentages to whole numbers.
 *
 * Result arrays always hold `value` (raw number or null), `display` (text for the farmer, "—" when missing),
 * `unit`, `reason` (null when there is a value) and the extras documented on each method.
 */
final class FarmerKpiService
{
    public const MISSING = '—';

    // ------------------------------------------------------------------ formatting (one place, so a number reads the same everywhere)

    /** Rain: whole mm, or one decimal under 10 mm. */
    public static function formatRain(float $mm): string
    {
        return $mm < 10 ? number_format($mm, 1) : number_format(round($mm), 0);
    }

    public static function formatTemperature(float $celsius): string
    {
        return number_format($celsius, 1);
    }

    public static function formatPercent(float $percent): string
    {
        return number_format(round($percent), 0);
    }

    // ------------------------------------------------------------------ rain

    /**
     * Rain recorded over the last 7 FULL days (today is not finished, so it is left out).
     * A single missing day makes the total unknown: a partial sum would look like a smaller week of rain.
     *
     * @param  array<string, float|int|null>  $dailyMm  rain in mm by Y-m-d date
     * @return array{key: string, value: ?float, display: string, unit: string, reason: ?string, days_found: int, previous: ?float, change_percent: ?float, level: ?string}
     */
    public function rainLast7Days(array $dailyMm, CarbonImmutable $today): array
    {
        $sum = $this->sumDays($dailyMm, $today->subDays(7), 7);
        $previous = $this->sumDays($dailyMm, $today->subDays(14), 7);

        if ($sum['sum'] === null) {
            return $this->missing('rain_past', 'mm', __('farmer.reason.rain_records_missing', ['found' => $sum['found']]), ['days_found' => $sum['found'], 'previous' => null, 'change_percent' => null, 'level' => null]);
        }

        $bands = (array) config('farmer_dashboard.rain_week_bands');
        $level = match (true) {
            $sum['sum'] < 0.1 => 'none',
            $sum['sum'] < (float) $bands['light_below'] => 'light',
            $sum['sum'] >= (float) $bands['heavy_at'] => 'heavy',
            default => 'moderate',
        };

        return $this->found('rain_past', $sum['sum'], self::formatRain($sum['sum']), 'mm', [
            'days_found' => 7,
            'previous' => $previous['sum'],
            'change_percent' => $previous['sum'] !== null && $previous['sum'] > 0 ? ($sum['sum'] - $previous['sum']) / $previous['sum'] * 100 : null,
            'level' => $level,
        ]);
    }

    /**
     * Rain coming: the sum of the forecast daily rain from today on, for up to 7 days. Fewer days are fine
     * ("next 5 days") as long as every counted day has a rain amount.
     *
     * @param  array<int, array{date: CarbonImmutable|string, rain: float|int|null}>  $days
     * @return array{key: string, value: ?float, display: string, unit: string, reason: ?string, days: int, level: ?string}
     */
    public function rainNext7Days(array $days, CarbonImmutable $today): array
    {
        $sum = 0.0;
        $count = 0;
        foreach ($days as $day) {
            $date = $day['date'] instanceof CarbonImmutable ? $day['date'] : CarbonImmutable::parse($day['date'], $today->getTimezone());
            if ($date->startOfDay()->lt($today->startOfDay()) || $date->startOfDay()->gte($today->startOfDay()->addDays(7))) {
                continue;
            }
            if (! is_numeric($day['rain'] ?? null)) {
                return $this->missing('rain_next', 'mm', __('farmer.reason.forecast_rain_missing'), ['days' => 0, 'level' => null]);
            }
            $sum += (float) $day['rain'];
            $count++;
        }

        if ($count === 0) {
            return $this->missing('rain_next', 'mm', __('farmer.reason.forecast_missing'), ['days' => 0, 'level' => null]);
        }

        $bands = (array) config('farmer_dashboard.rain_week_bands');
        // A short run of days is scaled to a week only for the label, never for the number shown.
        $perWeek = $sum / $count * 7;
        $level = match (true) {
            $sum < 0.1 => 'none',
            $perWeek < (float) $bands['light_below'] => 'light',
            $perWeek >= (float) $bands['heavy_at'] => 'heavy',
            default => 'moderate',
        };

        return $this->found('rain_next', $sum, self::formatRain($sum), 'mm', ['days' => $count, 'level' => $level]);
    }

    /**
     * Rain this month so far compared with the long-term average for the same days.
     * The long-term record is monthly, so "the same days" is the monthly average spread evenly over the month.
     *
     * @param  array<string, float|int|null>  $dailyMm
     * @param  float|null  $monthlyNormalMm  long-term average rain for this calendar month, in mm
     * @return array{key: string, value: ?float, display: string, unit: string, reason: ?string, label: ?string, level: ?string, month_to_date: ?float, normal_to_date: ?float, days: int}
     */
    public function rainComparedWithNormal(array $dailyMm, ?float $monthlyNormalMm, CarbonImmutable $today): array
    {
        $extras = ['label' => null, 'level' => null, 'month_to_date' => null, 'normal_to_date' => null, 'days' => 0];
        $fullDays = $today->day - 1;

        if ($fullDays < 1) {
            return $this->missing('rain_vs_normal', '%', __('farmer.reason.month_just_started'), $extras);
        }
        if ($monthlyNormalMm === null || $monthlyNormalMm <= 0) {
            return $this->missing('rain_vs_normal', '%', __('farmer.reason.no_normal'), $extras);
        }

        $sum = $this->sumDays($dailyMm, $today->startOfMonth(), $fullDays);
        if ($sum['sum'] === null) {
            return $this->missing('rain_vs_normal', '%', __('farmer.reason.rain_records_missing_month', ['found' => $sum['found'], 'needed' => $fullDays]), $extras);
        }

        $normalToDate = $monthlyNormalMm * $fullDays / $today->daysInMonth;
        $percent = $sum['sum'] / $normalToDate * 100;
        $band = (float) config('farmer_dashboard.rain_normal_band_percent');
        $level = match (true) {
            $percent > 100 + $band => 'more',
            $percent < 100 - $band => 'less',
            default => 'normal',
        };

        return $this->found('rain_vs_normal', $percent, self::formatPercent($percent), '%', [
            'label' => __('farmer.rain_vs_normal.'.$level),
            'level' => $level,
            'month_to_date' => $sum['sum'],
            'normal_to_date' => $normalToDate,
            'days' => $fullDays,
        ]);
    }

    // ------------------------------------------------------------------ heat

    /**
     * Heat index in °C (the US National Weather Service regression PAGASA's classes are built on).
     * Below 27 °C the regression does not apply, so the plain temperature is the "feels like" value.
     */
    public static function heatIndex(float $temperatureC, float $humidityPercent): float
    {
        if ($temperatureC < 27.0) {
            return $temperatureC;
        }

        $t = $temperatureC * 9 / 5 + 32;
        $rh = max(0.0, min(100.0, $humidityPercent));
        $hi = -42.379 + 2.04901523 * $t + 10.14333127 * $rh - 0.22475541 * $t * $rh - 0.00683783 * $t ** 2
            - 0.05481717 * $rh ** 2 + 0.00122874 * $t ** 2 * $rh + 0.00085282 * $t * $rh ** 2 - 0.00000199 * $t ** 2 * $rh ** 2;

        if ($rh < 13 && $t >= 80 && $t <= 112) {
            $hi -= ((13 - $rh) / 4) * sqrt((17 - abs($t - 95)) / 17);
        } elseif ($rh > 85 && $t >= 80 && $t <= 87) {
            $hi += (($rh - 85) / 10) * ((87 - $t) / 5);
        }

        return ($hi - 32) * 5 / 9;
    }

    /**
     * One plain word each for today's temperature, rain and humidity, from the bands in config/farmer_dashboard.php
     * (today_levels). A value that is not known has no word.
     *
     * @return array{temperature: ?string, rain: ?string, humidity: ?string}
     */
    public function todayLevels(?float $temperatureC, ?float $rainMm, ?float $humidityPercent): array
    {
        $bands = (array) config('farmer_dashboard.today_levels');

        return [
            'temperature' => $temperatureC === null ? null : match (true) {
                $temperatureC >= (float) $bands['temperature']['very_hot_at'] => 'very_hot',
                $temperatureC >= (float) $bands['temperature']['hot_at'] => 'hot',
                $temperatureC >= (float) $bands['temperature']['warm_at'] => 'warm',
                $temperatureC >= (float) $bands['temperature']['mild_at'] => 'mild',
                default => 'cool',
            },
            'rain' => $rainMm === null ? null : match (true) {
                $rainMm >= (float) config('farmer_dashboard.heavy_rain_mm') => 'heavy',
                $rainMm >= (float) $bands['rain']['moderate_at'] => 'moderate',
                $rainMm >= (float) $bands['rain']['light_at'] => 'light',
                default => 'none',
            },
            'humidity' => $humidityPercent === null ? null : match (true) {
                $humidityPercent >= (float) config('farmer_dashboard.humid_percent') => 'very_high',
                $humidityPercent >= (float) $bands['humidity']['high_at'] => 'high',
                $humidityPercent >= (float) $bands['humidity']['moderate_at'] => 'moderate',
                default => 'low',
            },
        ];
    }

    /**
     * How an estimated yield reads against the usual yield: within the band either side of it is "typical".
     *
     * @return 'above'|'typical'|'below'
     */
    public function yieldAgainstTypical(float $percentDifference): string
    {
        $band = (float) config('farmer_dashboard.yield_typical_band_percent');

        return match (true) {
            $percentDifference > $band => 'above',
            $percentDifference < -$band => 'below',
            default => 'typical',
        };
    }

    /** PAGASA heat class for a heat index in °C: normal, caution, extreme_caution, danger or extreme_danger. */
    public function heatLevel(float $heatIndexC): string
    {
        $level = 'normal';
        foreach ((array) config('farmer_dashboard.heat_levels') as $key => $from) {
            if (round($heatIndexC) >= (float) $from) {
                $level = (string) $key;
            }
        }

        return $level;
    }

    /**
     * Highest "feels like" temperature expected today. A provider's own value is used when it gives one; otherwise it is
     * worked out from today's highest temperature and the humidity. With neither, it is unknown.
     *
     * @return array{key: string, value: ?float, display: string, unit: string, reason: ?string, level: ?string, label: ?string, basis: ?string}
     */
    public function heatToday(?float $feelsLikeMaxC, ?float $maxTemperatureC, ?float $humidityPercent): array
    {
        $extras = ['level' => null, 'label' => null, 'basis' => null];
        $basis = null;
        $heat = null;

        if ($feelsLikeMaxC !== null) {
            $heat = $feelsLikeMaxC;
            $basis = 'provider';
        } elseif ($maxTemperatureC !== null && $humidityPercent !== null) {
            $heat = self::heatIndex($maxTemperatureC, $humidityPercent);
            $basis = 'calculated';
        }

        if ($heat === null) {
            return $this->missing('heat', '°C', __('farmer.reason.heat_missing'), $extras);
        }

        $level = $this->heatLevel($heat);

        return $this->found('heat', $heat, self::formatTemperature($heat), '°C', [
            'level' => $level,
            'label' => __('farmer.heat.'.$level),
            'basis' => $basis,
        ]);
    }

    // ------------------------------------------------------------------ good work days

    /**
     * Forecast days with a low chance of rain and light wind. A day missing either value cannot be judged and is not
     * counted; with no judgeable day at all the count is unknown (not 0).
     *
     * @param  array<int, array{date: CarbonImmutable|string, chance: float|int|null, wind_kmh: float|int|null}>  $days
     * @return array{key: string, value: ?int, display: string, unit: string, reason: ?string, dates: array<int, string>, judged: int}
     */
    public function goodWorkDays(array $days, CarbonImmutable $today): array
    {
        $maxChance = (float) config('farmer_dashboard.good_work_day.max_rain_chance_percent');
        $maxWind = (float) config('farmer_dashboard.good_work_day.max_wind_kmh');
        $good = [];
        $judged = 0;

        foreach ($days as $day) {
            $date = $day['date'] instanceof CarbonImmutable ? $day['date'] : CarbonImmutable::parse($day['date'], $today->getTimezone());
            if ($date->startOfDay()->lt($today->startOfDay()) || $date->startOfDay()->gte($today->startOfDay()->addDays(7))) {
                continue;
            }
            if (! is_numeric($day['chance'] ?? null) || ! is_numeric($day['wind_kmh'] ?? null)) {
                continue;
            }
            $judged++;
            if ((float) $day['chance'] < $maxChance && (float) $day['wind_kmh'] < $maxWind) {
                $good[] = $date->toDateString();
            }
        }

        if ($judged === 0) {
            return $this->missing('work_days', __('farmer.units.days'), __('farmer.reason.work_days_missing'), ['dates' => [], 'judged' => 0]);
        }

        return $this->found('work_days', count($good), (string) count($good), __('farmer.units.days'), ['dates' => $good, 'judged' => $judged]);
    }

    // ------------------------------------------------------------------ crop

    /**
     * How old the crop is and which stage it is in, from the planting date and the season length.
     *
     * @return array{key: string, value: ?int, display: string, unit: string, reason: ?string, duration: int, stage: ?string, stage_label: ?string, stages: array<int, array<string, mixed>>}
     */
    public function cropAge(?CarbonImmutable $plantingDate, CarbonImmutable $today, ?int $durationDays = null): array
    {
        $duration = $durationDays !== null && $durationDays > 0 ? $durationDays : (int) config('farmer_dashboard.crop.default_duration_days');
        $extras = ['duration' => $duration, 'stage' => null, 'stage_label' => null, 'stages' => []];

        if ($plantingDate === null) {
            return $this->missing('crop_age', __('farmer.units.days'), __('farmer.reason.no_planting_date'), $extras);
        }

        $planting = $plantingDate->startOfDay();
        $day = (int) $planting->diffInDays($today->startOfDay(), false);
        $stages = $this->stageWindows($planting, $duration, $day);
        $current = collect($stages)->firstWhere('state', 'current');
        $stage = $current['key'] ?? ($day < 0 ? 'land_prep' : 'harvest');
        $extras = ['duration' => $duration, 'stage' => $stage, 'stage_label' => __('farmer.stages.'.$stage), 'stages' => $stages];

        if ($day < 0) {
            return $this->missing('crop_age', __('farmer.units.days'), __('farmer.reason.not_planted_yet', ['days' => abs($day)]), $extras);
        }

        return $this->found('crop_age', $day, (string) $day, __('farmer.units.days'), $extras);
    }

    /**
     * The six stages with a state (done / current / upcoming) for a crop `$day` days after planting.
     *
     * @return array<int, array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable, state: string}>
     */
    public function stageWindows(CarbonImmutable $planting, int $duration, int $day): array
    {
        $crop = (array) config('farmer_dashboard.crop');
        $plantingEnd = (int) $crop['planting_days'];
        $growingEnd = (int) round($duration * (float) $crop['growing_until']);
        $floweringEnd = (int) round($duration * (float) $crop['flowering_until']);
        $ripeningEnd = (int) round($duration * (float) $crop['ripening_until']);

        // [key, first day, last day] counted from the planting date. Land preparation is the two weeks before planting.
        $windows = [
            ['land_prep', -14, -1],
            ['planting', 0, $plantingEnd - 1],
            ['growing', $plantingEnd, $growingEnd - 1],
            ['flowering', $growingEnd, $floweringEnd - 1],
            ['ripening', $floweringEnd, $ripeningEnd - 1],
            ['harvest', $ripeningEnd, $duration],
        ];

        return array_map(fn (array $w): array => [
            'key' => $w[0],
            'label' => __('farmer.stages.'.$w[0]),
            'start' => $planting->addDays($w[1]),
            'end' => $planting->addDays($w[2]),
            'state' => match (true) {
                $day > $w[2] && $w[0] !== 'harvest' => 'done',
                $day >= $w[1] && ($day <= $w[2] || $w[0] === 'harvest') => 'current',
                default => 'upcoming',
            },
        ], $windows);
    }

    // ------------------------------------------------------------------ expected harvest

    /**
     * Typical range around the model's yield, from the model's own uncertainty (the spread of its trees, in t/ha).
     * Without an uncertainty there is no range: it is never made up.
     *
     * @return array{low: float, high: float}|null
     */
    public function harvestRange(float $yieldTonsPerHectare, ?float $uncertainty): ?array
    {
        if ($uncertainty === null || $uncertainty <= 0) {
            return null;
        }

        return ['low' => max(0.0, round($yieldTonsPerHectare - $uncertainty, 1)), 'high' => round($yieldTonsPerHectare + $uncertainty, 1)];
    }

    // ------------------------------------------------------------------ alerts

    /** @return array{key: string, value: int, display: string, unit: string, reason: ?string} */
    public function activeAlerts(int $count): array
    {
        return $this->found('alerts', $count, (string) $count, '', []);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Sum of `$count` days of rain starting at `$from`. `sum` is null when any day has no reading.
     *
     * @param  array<string, float|int|null>  $dailyMm
     * @return array{sum: ?float, found: int}
     */
    private function sumDays(array $dailyMm, CarbonImmutable $from, int $count): array
    {
        $sum = 0.0;
        $found = 0;
        for ($i = 0; $i < $count; $i++) {
            $mm = $dailyMm[$from->addDays($i)->toDateString()] ?? null;
            if (is_numeric($mm)) {
                $sum += (float) $mm;
                $found++;
            }
        }

        return ['sum' => $found === $count ? $sum : null, 'found' => $found];
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function found(string $key, float|int $value, string $display, string $unit, array $extras): array
    {
        return ['key' => $key, 'value' => $value, 'display' => $display, 'unit' => $unit, 'reason' => null] + $extras;
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function missing(string $key, string $unit, string $reason, array $extras): array
    {
        return ['key' => $key, 'value' => null, 'display' => self::MISSING, 'unit' => $unit, 'reason' => $reason] + $extras;
    }
}
