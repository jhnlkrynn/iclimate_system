<?php

namespace App\Services\Prediction;

use Carbon\CarbonImmutable;

/**
 * Derives a within-month "short-term planning outlook" from the existing
 * monthly rice-yield prediction — NOT a true weekly forecast.
 *
 * Why this exists: the app's climate data and trained models are all
 * monthly-resolution (the original training dataset is literally named
 * "Ambulong Monthly Data.csv" — see ModelEvaluationService's source
 * metadata), and there is no realistic near-term path to genuine
 * weekly-resolution PAGASA/MAO records for Lian, Batangas. Farmers still
 * need something more actionable than "here's one number for the whole
 * month" to plan around, so this interpolates a trend line between two
 * real, already-computed values — last month's recorded average
 * (previous_rainfall/previous_temp/etc. in PredictionEngine's model
 * input) and this month's model-predicted average — and buckets that
 * trend into four week-ish segments across the target month.
 *
 * This is a linear approximation of a trend, not a prediction of actual
 * week-to-week weather, which can vary far more than a straight line
 * between two monthly averages. Every value this service returns must be
 * shown next to the disclaimer text it produces — do not present the
 * per-week numbers as if they were independently forecast.
 */
final class ShortTermOutlookService
{
    private const WEEK_COUNT = 4;

    private const STABLE_RAINFALL_THRESHOLD_MM = 15.0;

    private const STABLE_TEMPERATURE_THRESHOLD_C = 0.5;

    /**
     * @param  array<string, mixed>  $modelInput  the same model_input PredictionEngine::predict() already computed
     * @return array{weeks: array<int, array<string, mixed>>, rainfall_trend: array<string, mixed>, temperature_trend: array<string, mixed>, planning_tip: string, disclaimer: string}|null  null when a real start or end value is missing
     */
    public function build(array $modelInput, CarbonImmutable $targetMonth): ?array
    {
        // The trend needs a real "last month" and a real "this month" value for each variable.
        // A missing one is never replaced by the other value or by 0: no outlook is produced.
        $keys = ['previous_rainfall', 'rainfall', 'previous_temp', 'temp_avg'];
        foreach ($keys as $key) {
            if (! is_numeric($modelInput[$key] ?? null)) {
                return null;
            }
        }

        $rainfallStart = (float) $modelInput['previous_rainfall'];
        $rainfallEnd = (float) $modelInput['rainfall'];
        $temperatureStart = (float) $modelInput['previous_temp'];
        $temperatureEnd = (float) $modelInput['temp_avg'];
        // Humidity and wind are shown only when both ends are real values.
        $hasHumidity = is_numeric($modelInput['previous_humidity'] ?? null) && is_numeric($modelInput['humidity'] ?? null);
        $hasWind = is_numeric($modelInput['previous_wind_speed'] ?? null) && is_numeric($modelInput['wind_speed'] ?? null);

        $dateRanges = $this->weekDateRanges($targetMonth);
        $weeks = [];

        foreach ($dateRanges as $index => [$start, $end]) {
            $midpointFraction = ($index + 0.5) / self::WEEK_COUNT;

            $rainfall = $this->interpolate($rainfallStart, $rainfallEnd, $midpointFraction);
            $temperature = $this->interpolate($temperatureStart, $temperatureEnd, $midpointFraction);
            $humidity = $hasHumidity ? $this->interpolate((float) $modelInput['previous_humidity'], (float) $modelInput['humidity'], $midpointFraction) : null;
            $wind = $hasWind ? $this->interpolate((float) $modelInput['previous_wind_speed'], (float) $modelInput['wind_speed'], $midpointFraction) : null;

            $rainfallLabel = WeatherLabeler::fromRainfall($rainfall);
            $temperatureLabel = WeatherLabeler::fromTemperature($temperature);

            $weeks[] = [
                'week' => $index + 1,
                'label' => 'Week '.($index + 1),
                'date_range_label' => $start->equalTo($end)
                    ? $start->format('M j')
                    : $start->format('M j').' - '.$end->format('j'),
                'rainfall' => round($rainfall, 1),
                'temperature' => round($temperature, 1),
                'humidity' => $humidity !== null ? round($humidity, 1) : null,
                'wind_speed' => $wind !== null ? round($wind, 1) : null,
                'rainfall_label' => $rainfallLabel,
                'temperature_label' => $temperatureLabel,
                'advisory' => $this->advisoryFor($rainfallLabel, $temperatureLabel),
            ];
        }

        $rainfallTrend = $this->trend($rainfallStart, $rainfallEnd, self::STABLE_RAINFALL_THRESHOLD_MM, 'mm');
        $temperatureTrend = $this->trend($temperatureStart, $temperatureEnd, self::STABLE_TEMPERATURE_THRESHOLD_C, '°C');

        return [
            'weeks' => $weeks,
            'rainfall_trend' => $rainfallTrend,
            'temperature_trend' => $temperatureTrend,
            'planning_tip' => $this->planningTip($rainfallTrend, $temperatureTrend),
            'disclaimer' => 'Approximate week-by-week trend, interpolated between last month\'s recorded average and this month\'s forecast — not an independent weekly prediction. Actual week-to-week weather can vary more than this trend line shows; use it for general planning direction, not a precise weekly forecast.',
        ];
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function weekDateRanges(CarbonImmutable $targetMonth): array
    {
        $start = $targetMonth->startOfMonth();
        $daysInMonth = $start->daysInMonth;
        $baseChunk = intdiv($daysInMonth, self::WEEK_COUNT);
        $remainder = $daysInMonth % self::WEEK_COUNT;

        $ranges = [];
        $cursor = 1;

        for ($week = 0; $week < self::WEEK_COUNT; $week++) {
            $chunkLength = $baseChunk + ($week < $remainder ? 1 : 0);
            $rangeStart = $start->addDays($cursor - 1);
            $rangeEnd = $start->addDays($cursor - 1 + max(0, $chunkLength - 1));
            $ranges[] = [$rangeStart, $rangeEnd];
            $cursor += $chunkLength;
        }

        return $ranges;
    }

    private function interpolate(float $start, float $end, float $fraction): float
    {
        return $start + (($end - $start) * $fraction);
    }

    private function advisoryFor(string $rainfallLabel, string $temperatureLabel): string
    {
        $rainfallClause = match ($rainfallLabel) {
            'Heavy Rain' => 'Rainfall trending heavy — watch drainage and flooding.',
            'Rain' => 'Rainfall trending within a workable range.',
            'Dry' => 'Rainfall trending low — water availability may be limited.',
            default => 'Rainfall trending moderate.',
        };

        $temperatureClause = match ($temperatureLabel) {
            'Hot' => 'Temperature trending hot — monitor for heat stress.',
            'Cool' => 'Temperature trending cool — growth may slow.',
            default => 'Temperature trending favorable.',
        };

        return $rainfallClause.' '.$temperatureClause;
    }

    /**
     * @return array{direction: string, delta: float, unit: string, start: float, end: float}
     */
    private function trend(float $start, float $end, float $stableThreshold, string $unit): array
    {
        $delta = $end - $start;

        $direction = match (true) {
            abs($delta) < $stableThreshold => 'Stable',
            $delta > 0 => 'Increasing',
            default => 'Decreasing',
        };

        return [
            'direction' => $direction,
            'delta' => round($delta, 1),
            'unit' => $unit,
            'start' => round($start, 1),
            'end' => round($end, 1),
        ];
    }

    /**
     * @param  array{direction: string, delta: float, unit: string}  $rainfallTrend
     * @param  array{direction: string, delta: float, unit: string}  $temperatureTrend
     */
    private function planningTip(array $rainfallTrend, array $temperatureTrend): string
    {
        // Rainfall leads: it's the strongest single driver of rice yield
        // (see FeatureCatalog.php), so a moving rainfall trend is more
        // decision-relevant than a moving temperature trend and should be
        // the headline tip whenever both are changing.
        if ($rainfallTrend['direction'] === 'Increasing') {
            return 'Conditions look to be trending wetter across the month — later weeks may offer better water availability than earlier ones.';
        }

        if ($rainfallTrend['direction'] === 'Decreasing') {
            return 'Conditions look to be trending drier across the month — earlier weeks may offer better water availability than later ones.';
        }

        if ($temperatureTrend['direction'] === 'Increasing') {
            return 'Rainfall looks stable, but temperature is trending upward across the month — plan heat-sensitive activities for the earlier weeks where possible.';
        }

        if ($temperatureTrend['direction'] === 'Decreasing') {
            return 'Rainfall looks stable, and temperature is trending downward across the month — growth may be slightly slower in the later weeks.';
        }

        return 'Conditions look relatively stable across the month based on this trend — timing within the month matters less than usual.';
    }
}
