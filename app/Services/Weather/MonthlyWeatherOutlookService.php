<?php

namespace App\Services\Weather;

use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Carbon\CarbonImmutable;

/**
 * Builds the "Monthly Weather Prediction" panel for the calendar's selected month.
 *
 * Only real, stored or fetched data is used, and each kind stays in its own block:
 *   - model:      the stored monthly Random Forest estimate  (Model prediction)
 *   - daily:      a summary of the real Open-Meteo daily values that fall in the month (Daily forecast)
 *   - historical: Ambulong records for that calendar month     (Historical observation)
 * Nothing is invented: a missing value is left out and listed under "not available".
 * PAGASA seasonal outlooks are not connected to iClimate, and this service says so instead of guessing.
 */
final class MonthlyWeatherOutlookService
{
    /** PAGASA-style "percent of normal" bands, applied by iClimate to its own estimate. */
    private const RAIN_ABOVE = 1.20;

    private const RAIN_BELOW = 0.80;

    /** Degrees Celsius either side of the historical average treated as "near normal". */
    private const TEMP_BAND = 0.5;

    /** Minimum number of past years needed before a comparison with "normal" is made. */
    private const MIN_BASELINE_YEARS = 5;

    private const COMPASS = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];

    public function __construct(private readonly AmbulongHistoricalClimateDataset $dataset)
    {
    }

    /**
     * @param  array<string, mixed>  $calendar  Result of MonthlyWeatherCalendarService::build()
     */
    public function build(array $calendar): array
    {
        /** @var CarbonImmutable $month */
        $month = $calendar['month'];
        $baseline = $this->baseline($month);
        $model = $this->model($calendar, $baseline, $month);
        $weekly = $model === null ? $this->weekly($calendar, $month) : null;

        return [
            'label' => $calendar['label'],
            'period' => $month->format('F j').'–'.$month->endOfMonth()->format('j, Y').' (whole month)',
            'model' => $model,
            'has_model' => $model !== null,
            // Trust order: 1) monthly model estimate, 2) weekly summaries of real daily forecasts, 3) nothing reliable.
            'mode' => $model !== null ? 'monthly' : ($weekly !== null ? 'weekly' : 'none'),
            'weekly' => $weekly,
            'historical' => $baseline,
            'model_message' => $calendar['monthly_prediction_message'] ?? null,
            'pagasa_note' => 'PAGASA monthly outlooks are not shown in iClimate. For official guidance, follow PAGASA. '
                .'The estimate above is iClimate\'s own, not a PAGASA forecast.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function model(array $calendar, ?array $baseline, CarbonImmutable $month): ?array
    {
        $mp = $calendar['monthly_prediction'] ?? null;
        if (! is_array($mp)) {
            return null;
        }

        $outlook = [];
        if (! empty($mp['season'])) {
            $outlook[] = ['label' => 'Season', 'value' => $mp['season'], 'detail' => 'Wet season is May–October; dry season is November–April.'];
        }
        $outlook = array_merge($outlook, $this->comparison($mp, $baseline, $month));

        $wind = null;
        if ($mp['wind_speed_kmh'] !== null || ($mp['wind_direction'] ?? null) !== null) {
            $wind = trim(($mp['wind_speed_kmh'] !== null ? number_format($mp['wind_speed_kmh'], 1).' average' : '')
                .($this->compass($mp['wind_direction'] ?? null) ? ($mp['wind_speed_kmh'] !== null ? ', from ' : 'From ').$this->compass($mp['wind_direction']) : ''));
        }
        $summary = collect($outlook)->map(fn ($o) => match ($o['label']) {
            'Season' => $o['value'].' season',
            'Rainfall Outlook' => $o['value'].' rainfall',
            'Temperature Outlook' => $o['value'].' temperature',
            default => null,
        })->filter()->implode(' · ');

        // Fixed metric slots: a value the source does not provide says so, and is never shown as 0.
        $slot = fn (string $label, ?string $value, string $unit = '') => ['label' => $label, 'value' => $value, 'unit' => $unit, 'available' => $value !== null];
        $rows = [
            $slot('Expected Rainfall', $mp['rainfall_mm'] !== null ? number_format($mp['rainfall_mm'], 1) : null, 'mm (month total)'),
            $slot('Expected Temperature', $mp['temperature_c'] !== null ? number_format($mp['temperature_c'], 1) : null, '°C (monthly average)'),
            $slot('Expected Temperature Range', ($mp['tmax_c'] !== null && $mp['tmin_c'] !== null) ? number_format($mp['tmin_c'], 1).' – '.number_format($mp['tmax_c'], 1) : null, '°C (average low – high)'),
            $slot('Expected Humidity', $mp['humidity_percent'] !== null ? number_format($mp['humidity_percent'], 1) : null, '% (monthly average)'),
            // Model wind has no confirmed unit, so none is claimed.
            $slot('Expected Wind', $wind, $mp['wind_speed_kmh'] !== null ? '(unit not confirmed)' : ''),
            // The monthly model does not estimate these two; they are shown as unavailable rather than guessed.
            $slot('Chance of Rain', null),
            $slot('Overall Weather Outlook', $summary !== '' ? $summary : null),
            $slot('Main Weather', null),
        ];
        $missing = [];
        $updated = null;
        if (! empty($mp['generated_at'])) {
            try {
                $updated = CarbonImmutable::parse($mp['generated_at'])->timezone('Asia/Manila')->format('M j, Y, g:i A').' (Philippine time)';
            } catch (\Throwable) {
                $updated = null;
            }
        }
        $training = $mp['training_from'] && $mp['training_to'] ? $mp['training_from'].' to '.$mp['training_to'] : null;

        return [
            'type' => 'iClimate Monthly Estimate',
            'rows' => $rows,
            'outlook' => $outlook,
            'cards' => $this->cards($mp, $baseline, $month),
            'missing' => $missing,
            'period' => $month->format('F j').'–'.$month->endOfMonth()->format('j, Y').' (the whole month, not a single day)',
            'source' => $mp['source_name'].($training ? " (based on records from {$training})" : ''),
            'updated' => $updated ?? 'Not recorded',
            'uncertainty' => trim(($mp['confidence_label'] ? $mp['confidence_label'].'. ' : '')
                .'This is an estimate for the whole month, not a daily forecast. Real weather can differ, especially for later months.'),
        ];
    }

    /**
     * The three "Seasonal & Outlook" cards for the selected month. Rainfall and temperature are
     * comparisons of the model estimate with HISTORICAL averages for the same calendar month, so
     * they carry the number of years and the year span used. Anything that cannot be computed is
     * marked unavailable instead of being guessed.
     *
     * @return array{season: array<string, mixed>, rainfall: array<string, mixed>, temperature: array<string, mixed>}
     */
    private function cards(array $mp, ?array $baseline, CarbonImmutable $month): array
    {
        $name = $month->format('F');
        $enough = $baseline && $baseline['years'] >= self::MIN_BASELINE_YEARS;
        $why = ! $baseline
            ? 'No past records to compare with.'
            : ($enough ? '' : "Only {$baseline['years']} past ".($baseline['years'] === 1 ? 'year' : 'years')." of {$name} records exist; at least ".self::MIN_BASELINE_YEARS.' are needed for a fair comparison.');
        $years = $enough ? $baseline['years'] : null;
        $span = $enough ? $baseline['span'] : null;

        $rain = ['available' => false, 'reason' => $why ?: 'No rainfall estimate for this month yet.'];
        if ($enough && $mp['rainfall_mm'] !== null && ($baseline['avg']['rainfall'] ?? 0) > 0) {
            $ratio = $mp['rainfall_mm'] / $baseline['avg']['rainfall'];
            $rain = [
                'available' => true,
                'status' => $ratio > self::RAIN_ABOVE ? 'Above Normal' : ($ratio < self::RAIN_BELOW ? 'Below Normal' : 'Near Normal'),
                'value' => round($ratio * 100).'%',
                'caption' => "of the historical {$name} average rainfall",
                'years' => $years, 'span' => $span,
            ];
        }

        $temp = ['available' => false, 'reason' => $why ?: 'No temperature estimate for this month yet.'];
        if ($enough && $mp['temperature_c'] !== null && ($baseline['avg']['temperature'] ?? null) !== null) {
            $diff = round($mp['temperature_c'] - $baseline['avg']['temperature'], 1);
            $temp = [
                'available' => true,
                'status' => $diff > self::TEMP_BAND ? 'Above Normal' : ($diff < -self::TEMP_BAND ? 'Below Normal' : 'Near Normal'),
                'value' => sprintf('%+.1f°C', $diff == 0.0 ? 0.0 : $diff),
                'caption' => "compared with the historical {$name} average temperature",
                'years' => $years, 'span' => $span,
            ];
        }

        // The iClimate season calendar (App\Support\RiceSeason): wet May–October, dry November–April.
        $activeSeason = ! empty($mp['season']) ? ucfirst(strtolower((string) $mp['season'])) : (\App\Models\ClimateRecord::seasonForMonth($month->month) === 'Wet' ? 'Wet' : 'Dry');

        return [
            'season' => ['wet' => 'May–October', 'dry' => 'November–April', 'active' => $activeSeason, 'month' => $name],
            'rainfall' => $rain,
            'temperature' => $temp,
        ];
    }
    /**
     * Compare the model estimate with the average for the same calendar month in past years.
     *
     * @return array<int, array{label: string, value: string, detail: string}>
     */
    private function comparison(array $mp, ?array $baseline, CarbonImmutable $month): array
    {
        if (! $baseline || $baseline['years'] < self::MIN_BASELINE_YEARS) {
            return [];
        }

        $out = [];
        $name = $month->format('F');
        $basis = "the average {$name} from {$baseline['years']} past years ({$baseline['span']})";

        if ($mp['rainfall_mm'] !== null && ($baseline['avg']['rainfall'] ?? 0) > 0) {
            $ratio = $mp['rainfall_mm'] / $baseline['avg']['rainfall'];
            $class = $ratio > self::RAIN_ABOVE ? 'Above Normal' : ($ratio < self::RAIN_BELOW ? 'Below Normal' : 'Near Normal');
            $out[] = ['label' => 'Rainfall Outlook', 'value' => $class, 'detail' => sprintf('Estimate is %d%% of %s.', round($ratio * 100), $basis)];
        }
        if ($mp['temperature_c'] !== null && ($baseline['avg']['temperature'] ?? null) !== null) {
            $diff = $mp['temperature_c'] - $baseline['avg']['temperature'];
            $class = $diff > self::TEMP_BAND ? 'Above Normal' : ($diff < -self::TEMP_BAND ? 'Below Normal' : 'Near Normal');
            $out[] = ['label' => 'Temperature Outlook', 'value' => $class, 'detail' => sprintf('Estimate is %+.1f°C compared with %s.', $diff, $basis)];
        }

        return $out;
    }

    /**
     * Day ranges 1-7, 8-14, 15-21, 22-28 and 29-end. They never overlap, never skip a date,
     * and the last week shrinks to the real month length (28, 29, 30 or 31 days).
     *
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public static function weekRanges(CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $last = $month->endOfMonth()->startOfDay();
        $ranges = [];

        for ($start = $month; $start->lte($last); $start = $start->addDays(7)) {
            $end = $start->addDays(6);
            $ranges[] = ['start' => $start, 'end' => $end->gt($last) ? $last : $end];
        }

        return $ranges;
    }

    /**
     * Weekly summaries built ONLY from real daily forecast values. Days without a forecast are left
     * out (never treated as 0), and a week with no forecast says "No data available".
     *
     * @return array<string, mixed>|null  null when no week in the month has any forecast
     */
    private function weekly(array $calendar, CarbonImmutable $month): ?array
    {
        $byDate = [];
        foreach ($calendar['weeks'] as $week) {
            foreach ($week as $day) {
                if ($day['in_month'] && $day['forecast']) {
                    $byDate[$day['date']] = $day['forecast']; // keyed by date: duplicates cannot occur
                }
            }
        }
        if ($byDate === []) {
            return null;
        }

        $weeks = [];
        $anyData = false;
        foreach (self::weekRanges($month) as $i => $range) {
            $days = [];
            for ($d = $range['start']; $d->lte($range['end']); $d = $d->addDay()) {
                if (isset($byDate[$d->toDateString()])) {
                    $days[$d->toDateString()] = $byDate[$d->toDateString()];
                }
            }
            $length = $range['start']->diffInDays($range['end']) + 1;
            $summary = $this->summarise($days);
            $anyData = $anyData || $summary !== [];
            $single = $range['start']->equalTo($range['end']);

            $weeks[] = [
                'label' => 'Week '.($i + 1),
                'period' => $range['start']->format('F j').($single ? '' : '–'.$range['end']->format('j')).', '.$range['end']->format('Y'),
                'short' => $range['start']->format('M j').($single ? '' : '–'.$range['end']->format('j')),
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'days_in_week' => $length,
                'days_with_data' => count($days),
                'coverage' => count($days).' of '.$length.' days',
                'has_data' => $summary !== [],
                'rows' => $summary['rows'] ?? [],
                'conditions' => $summary['conditions'] ?? [],
            ];
        }
        if (! $anyData) {
            return null;
        }

        return [
            'type' => 'Weekly summary of the daily forecast',
            'weeks' => $weeks,
            'source' => str_replace('Forecast API', 'weather forecast', $calendar['source_name'] ?: 'Open-Meteo Forecast API'),
            'updated' => $this->fetched($calendar['fetched_at'] ?? null),
            'note' => 'Only days with a forecast are counted. Days without one are left out, not counted as zero.',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $days  date => forecast
     * @return array{rows: array<int, array<string, mixed>>, conditions: array<string, int>}|array{}
     */
    private function summarise(array $days): array
    {
        if ($days === []) {
            return [];
        }
        $col = fn (string $key) => array_values(array_filter(array_column($days, $key), fn ($v) => $v !== null && $v !== ''));
        $mean = fn (array $v) => $v === [] ? null : array_sum($v) / count($v);
        $slot = fn (string $label, ?string $value, string $unit = '') => ['label' => $label, 'value' => $value, 'unit' => $unit, 'available' => $value !== null];

        $rain = $col('rainfall');
        $chance = $col('rain_chance');
        $hum = $col('humidity');
        $wind = $col('wind');
        $highs = $col('temperature_max');
        $lows = $col('temperature_min');
        $means = [];
        foreach ($days as $f) {
            if ($f['temperature_max'] !== null && $f['temperature_min'] !== null) {
                $means[] = ($f['temperature_max'] + $f['temperature_min']) / 2;
            } elseif ($f['temperature'] !== null) {
                $means[] = $f['temperature'];
            }
        }
        $conditions = array_count_values(array_map('strval', $col('condition')));
        arsort($conditions);

        $outlook = null;
        if ($conditions) {
            $top = array_key_first($conditions);
            $outlook = $conditions[$top] * 2 > count($days) ? 'Mostly '.$top : 'Mixed conditions (most common: '.$top.')';
        }

        $rows = [
            $slot('Expected Rainfall', $rain ? number_format(array_sum($rain), 1) : null, 'mm (total of covered days)'),
            $slot('Expected Temperature', $means ? number_format($mean($means), 1) : null, '°C (average)'),
            $slot('Temperature Range', ($highs && $lows) ? number_format(min($lows), 1).' – '.number_format(max($highs), 1) : null, '°C (lowest – highest)'),
            $slot('Expected Humidity', $hum ? number_format($mean($hum), 1) : null, '% (average)'),
            $slot('Chance of Rain', $chance ? 'up to '.number_format(max($chance)).'%' : null, $chance ? '(average '.number_format($mean($chance)).'%)' : ''),
            $slot('Strongest Daily Wind', $wind ? number_format(max($wind), 1) : null, 'km/h'),
            $slot('Weather Outlook', $outlook),
        ];

        return ['rows' => $rows, 'conditions' => $conditions];
    }
    /** @return array<string, mixed>|null */
    private function baseline(CarbonImmutable $month): ?array
    {
        try {
            $data = $this->dataset->load();
        } catch (\Throwable) {
            return null;
        }
        if (! ($data['available'] ?? false)) {
            return null;
        }

        $sameMonth = collect($data['observations'])->where('month', $month->month);
        if ($sameMonth->isEmpty()) {
            return null;
        }

        $observed = $sameMonth->firstWhere('year', $month->year);
        $past = $sameMonth->where('year', '!=', $month->year);
        $avg = fn (string $k) => $past->isEmpty() ? null : round($past->avg($k), 2);
        $fmt = fn ($v, int $d = 1) => $v === null ? null : number_format($v, $d);

        $averages = ['rainfall' => $avg('rainfall'), 'temperature' => $avg('temperature'), 'humidity' => $avg('humidity')];

        return [
            'type' => 'Past Weather Records',
            'source' => 'Past weather records from PAGASA Ambulong station',
            'years' => $past->count(),
            'span' => $past->isEmpty() ? '' : $past->min('year').'–'.$past->max('year'),
            'avg' => $averages,
            'average_rows' => $past->isEmpty() ? [] : array_values(array_filter([
                ['label' => 'Average Rainfall', 'value' => $fmt($averages['rainfall']), 'unit' => 'mm'],
                ['label' => 'Average Temperature', 'value' => $fmt($averages['temperature']), 'unit' => '°C'],
                ['label' => 'Average Humidity', 'value' => $fmt($averages['humidity']), 'unit' => '%'],
            ], fn ($r) => $r['value'] !== null)),
            'observed_rows' => $observed ? [
                ['label' => 'Recorded Rainfall', 'value' => number_format($observed['rainfall'], 1), 'unit' => 'mm'],
                ['label' => 'Recorded Temperature', 'value' => number_format($observed['temperature'], 1), 'unit' => '°C'],
                ['label' => 'Recorded Humidity', 'value' => number_format($observed['humidity'], 1), 'unit' => '%'],
            ] : [],
        ];
    }

    private function compass(mixed $degrees): ?string
    {
        if (! is_numeric($degrees) || $degrees < 0 || $degrees > 360) {
            return null;
        }

        return self::COMPASS[((int) round(((float) $degrees) / 22.5)) % 16].' ('.round((float) $degrees).'°)';
    }

    private function fetched(mixed $value): string
    {
        if (! $value) {
            return 'Not recorded';
        }
        try {
            return CarbonImmutable::parse($value)->timezone('Asia/Manila')->format('M j, Y, g:i A').' (Philippine time)';
        } catch (\Throwable) {
            return 'Not recorded';
        }
    }
}
