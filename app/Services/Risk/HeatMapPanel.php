<?php

namespace App\Services\Risk;

use Carbon\CarbonImmutable;

/**
 * The words of the Heat Map's side panel: each range's municipal summary and each barangay's risk level, plain
 * explanation ("Ibig sabihin") and recommended action ("Gawin ngayon"), from BarangayWeekRiskService's ratings.
 * Farmers read their saved language; staff read English and also get the forecast numbers.
 */
final class HeatMapPanel
{
    // Panel revision 2.1: Low green, Moderate yellow, High orange, Severe red (the defaults of config/weather_risk.php).
    public const COLORS = ['low' => '#2E7D32', 'moderate' => '#F2C230', 'high' => '#EF6C00', 'severe' => '#C62828', 'nodata' => '#9CA3AF'];

    /** @return array<string, string> level => colour, from config/weather_risk.php (falls back to COLORS) */
    public static function colors(): array
    {
        return array_merge(self::COLORS, (array) config('weather_risk.colors', []));
    }

    private const DISPLAY_NAMES = ['Barangay 1 (Pob.)' => 'Barangay 1 / Bonbon (Poblacion)', 'Barangay 5 (Pob.)' => 'Barangay 5 / Tabok City (Poblacion)'];

    public function __construct(private readonly BarangayWeekRiskService $risk) {}

    /**
     * @return array<string, array<string, mixed>> keyed by range (week, 2weeks)
     */
    public function ranges(string $locale): array
    {
        $ranges = [];
        foreach ($this->risk->ranges() as $range => $rated) {
            $barangays = [];
            foreach ($rated['barangays'] as $name => $b) {
                $barangays[$name] = $this->barangay($b, $rated['days'], $locale);
            }
            $ranges[$range] = [
                'range' => $range,
                'days' => $rated['days'],
                'state' => $rated['state'],
                'updated_at' => $rated['updated_at'],
                'delayed' => $rated['delayed'] ?? false,
                'title' => $this->t($range === 'week' ? 'summary_title' : 'summary_title_2weeks', $locale),
                'counts' => $rated['counts'],
                'count_lines' => $this->countLines($rated['counts'], $locale),
                'outlook' => $this->outlook($rated['barangays'], $locale),
                'barangays' => $barangays,
            ];
        }

        return $ranges;
    }

    public static function displayName(string $barangay): string
    {
        return self::DISPLAY_NAMES[$barangay] ?? $barangay;
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function barangay(array $b, int $days, string $locale): array
    {
        $level = (string) $b['level'];
        $reason = (string) $b['reason'];
        $first = $b['reasons'][0] ?? null;
        $key = in_array($reason, ['calm', 'nodata'], true) ? $reason : $reason.'.'.$level;
        $named = ['days' => $this->dayList((array) ($first['dates'] ?? []), $locale)];

        return [
            'barangay' => $b['barangay'],
            'name' => self::displayName((string) $b['barangay']),
            'level' => $level,
            'label' => $this->t('level.'.$level, $locale),
            // Panel revision 2.1: for the map tooltip: the main weather factor and what it can do to the crop.
            'factor' => $this->factorText((array) ($b['factor'] ?? []) + ['facts' => $b], $locale),
            'impact' => $this->t('impact.'.((string) ($b['factor']['key'] ?? ($level === 'nodata' ? 'nodata' : 'calm'))), $locale),
            'meaning' => $this->t('meaning.'.$key, $locale, $named),
            'action' => $this->t('action.'.$key, $locale),
            // A second hazard in the same period is named too, so a windy week is not hidden behind a rainy one.
            'also' => array_values(array_map(
                fn (array $r): string => $this->t('meaning.'.$r['key'].'.'.$r['level'], $locale, ['days' => $this->dayList((array) $r['dates'], $locale)]),
                array_slice((array) $b['reasons'], 1),
            )),
            'partial' => $level !== 'nodata' && $b['days_covered'] < $days
                ? $this->t('days_covered', $locale, ['count' => $b['days_covered'], 'days' => $days])
                : null,
            'numbers' => $level === 'nodata' ? [] : array_filter([
                $this->t('rain_total', $locale) => $b['rain_total_mm'] !== null ? number_format($b['rain_total_mm'], 1).' mm' : null,
                $this->t('wettest_day', $locale) => $b['wettest_day_mm'] !== null ? number_format($b['wettest_day_mm'], 1).' mm' : null,
                $this->t('wind_max', $locale) => $b['wind_max_kmh'] !== null ? number_format($b['wind_max_kmh'], 0).' km/h' : null,
                $this->t('temp_max', $locale) => $b['temp_max_c'] !== null ? number_format($b['temp_max_c'], 1).' °C' : null,
                $this->t('farm_type', $locale) => $b['farm_type'],
            ]),
        ];
    }

    /**
     * Lian's forecast numbers for the period, worked out from the barangays' own saved forecasts (never a separate
     * municipal figure): the spread of rain totals across barangays and the period's wettest day, strongest wind and
     * hottest day in any barangay.
     *
     * @param  array<string, array<string, mixed>>  $barangays
     * @return array<string, string>
     */
    private function outlook(array $barangays, string $locale): array
    {
        $values = fn (string $key): array => array_values(array_filter(
            array_map(fn (array $b) => $b['level'] === 'nodata' ? null : $b[$key], $barangays),
            fn ($value): bool => $value !== null,
        ));
        $rain = $values('rain_total_mm');
        $wettest = $values('wettest_day_mm');
        $wind = $values('wind_max_kmh');
        $temp = $values('temp_max_c');
        $rainRange = $rain === [] ? null : (min($rain) === max($rain)
            ? number_format(min($rain), 1)
            : number_format(min($rain), 1).'–'.number_format(max($rain), 1));

        return array_filter([
            $this->t('rain_range', $locale) => $rainRange !== null ? $rainRange.' mm' : null,
            $this->t('wettest_day', $locale) => $wettest !== [] ? number_format(max($wettest), 1).' mm' : null,
            $this->t('wind_max', $locale) => $wind !== [] ? number_format(max($wind), 0).' km/h' : null,
            $this->t('temp_max', $locale) => $temp !== [] ? number_format(max($temp), 1).' °C' : null,
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array{level: string, text: string}>
     */
    private function countLines(array $counts, string $locale): array
    {
        $lines = [];
        foreach (['severe', 'high', 'moderate', 'low', 'nodata'] as $level) {
            if (in_array($level, ['nodata', 'severe'], true) && ($counts[$level] ?? 0) === 0) {
                continue;
            }
            $lines[] = ['level' => $level, 'text' => trans_choice('farmer.heat_map.count_'.$level, $counts[$level] ?? 0, ['count' => $counts[$level] ?? 0], $locale)];
        }

        return $lines;
    }

    /**
     * "Wed", "Wed and Fri", "Wed, Fri and Sat", or "5 days" for a longer list. A date in the coming week is named by
     * its weekday ("today" for today); a later one by its date, since a 2-week range has each weekday twice.
     *
     * @param  array<int, string>  $dates
     */
    private function dayList(array $dates, string $locale): string
    {
        $dates = array_values(array_filter($dates));
        if (count($dates) > 3) {
            return $this->t('days_count', $locale, ['count' => count($dates)]);
        }
        $today = CarbonImmutable::now(BarangayWeekRiskService::TIMEZONE)->startOfDay();
        $names = (array) trans('farmer.heat_map.day_names', [], $locale);
        $words = array_map(function (string $date) use ($today, $names, $locale): string {
            $day = CarbonImmutable::parse($date, BarangayWeekRiskService::TIMEZONE)->startOfDay();
            $ahead = (int) $today->diffInDays($day, false);

            return match (true) {
                $ahead === 0 => $this->t('today', $locale),
                $ahead > 0 && $ahead < 7 => (string) ($names[$day->dayOfWeek] ?? $day->format('D')),
                default => $day->locale($locale)->translatedFormat('M j'),
            };
        }, $dates);

        if (count($words) <= 1) {
            return $words[0] ?? '';
        }
        $last = array_pop($words);

        return implode(', ', $words).' '.$this->t('and', $locale).' '.$last;
    }

    /**
     * Panel revision 2.1: "Heavy rainfall: 120 mm in one day", "Strong wind: 70 km/h", "Typhoon advisory: …".
     *
     * @param  array{key?: string, value?: ?string}  $factor
     */
    private function factorText(array $factor, string $locale): ?string
    {
        $key = (string) ($factor['key'] ?? '');
        if ($key === '' || $key === 'nodata') {
            return null;
        }
        $label = $this->t('factor.'.$key, $locale);
        $f = (array) ($factor['facts'] ?? []);
        $n = fn ($v, int $d = 0): string => number_format((float) $v, $d);
        // The numbers in the reader's language ("120 mm sa isang araw").
        $value = match ($key) {
            'heavy_rain' => isset($f['wettest_day_mm']) ? $this->t('factor_value.heavy_rain', $locale, ['day' => $n($f['wettest_day_mm']), 'total' => $n($f['rain_total_mm'] ?? $f['wettest_day_mm'])]) : null,
            'strong_wind' => isset($f['wind_max_kmh']) ? $this->t('factor_value.strong_wind', $locale, ['kmh' => $n($f['wind_max_kmh'])]) : null,
            'heat' => isset($f['temp_max_c']) ? $this->t('factor_value.heat', $locale, ['temp' => $n($f['temp_max_c'], 1), 'days' => (int) ($f['hot_days'] ?? 0)]) : null,
            'dry' => $this->t('factor_value.dry', $locale),
            'calm' => isset($f['rain_total_mm']) ? $this->t('factor_value.calm', $locale, ['total' => $n($f['rain_total_mm'])]) : null,
            default => $factor['value'] ?? null,
        };

        return filled($value) ? $label.': '.$value : $label;
    }

    /** @param  array<string, mixed>  $replace */
    private function t(string $key, string $locale, array $replace = []): string
    {
        return (string) trans('farmer.heat_map.'.$key, $replace, $locale);
    }
}
