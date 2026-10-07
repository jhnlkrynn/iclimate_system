<?php

namespace App\Services\Climate;

use App\Support\ClimateHistory;
use App\Support\ProjectYieldDataset;
use App\Support\RiceSeason;
use Illuminate\Support\Collection;

/**
 * Manuscript – Naicel (2026-10-08), Phase 4: the numbers behind Weather Analysis and Weather Impact Analysis.
 * It reads the one monthly climate history the models use (ClimateHistory::months()) and the project's monthly rice
 * yield record (ProjectYieldDataset). Nothing is fetched from a provider and nothing is invented: a month without a
 * reading stays out of an average.
 */
class WeatherAnalysisService
{
    public const VARIABLES = ['rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind speed'];

    public const UNITS = ['rainfall' => 'mm', 'temperature' => '°C', 'humidity' => '%', 'wind_speed' => ''];

    /** Monthly climate risk signals (planning thresholds, not official PAGASA warnings). */
    public const HEAVY_RAIN_MM = 300.0;

    public const DRY_MONTH_MM = 50.0;

    public const HOT_MONTH_C = 29.0;

    /** |r| at or above these is a High / Moderate impact. */
    public const HIGH_IMPACT_R = 0.5;

    public const MODERATE_IMPACT_R = 0.3;

    /** Fewer paired months than this and no impact is given. */
    public const MIN_PAIRS = 8;

    public function __construct(private readonly ProjectYieldDataset $yields) {}

    /** The thresholds above, or what IT saved on System Settings (Phase 8). */
    public static function heavyRainMm(): float
    {
        return (float) \App\Support\SystemSettings::get('heavy_rain_month_mm', self::HEAVY_RAIN_MM);
    }

    public static function dryMonthMm(): float
    {
        return (float) \App\Support\SystemSettings::get('dry_month_mm', self::DRY_MONTH_MM);
    }

    public static function hotMonthC(): float
    {
        return (float) \App\Support\SystemSettings::get('hot_month_c', self::HOT_MONTH_C);
    }

    /**
     * Every month on record, oldest first: date, year, month, season and the four readings.
     *
     * @return Collection<int, object>
     */
    public function months(): Collection
    {
        return rescue(fn () => ClimateHistory::months(), collect(), false)
            ->map(function (object $m): object {
                $year = (int) substr($m->record_date, 0, 4);
                $month = (int) substr($m->record_date, 5, 2);

                return (object) [
                    'key' => sprintf('%04d-%02d', $year, $month),
                    'year' => $year,
                    'month' => $month,
                    'season' => RiceSeason::forMonth($month),
                    'rainfall' => is_numeric($m->rainfall) ? (float) $m->rainfall : null,
                    'temperature' => is_numeric($m->temperature) ? (float) $m->temperature : null,
                    'humidity' => is_numeric($m->humidity) ? (float) $m->humidity : null,
                    'wind_speed' => is_numeric($m->wind_speed) ? (float) $m->wind_speed : null,
                    'source' => $m->source ?? null,
                ];
            })
            ->sortBy('key')->values();
    }

    /** @return Collection<int, object> */
    public function filter(Collection $months, ?int $year, ?string $season, ?int $month, ?int $fromYear = null, ?int $toYear = null): Collection
    {
        return $months
            ->when($year, fn ($c) => $c->where('year', $year))
            ->when($fromYear, fn ($c) => $c->where('year', '>=', $fromYear))
            ->when($toYear, fn ($c) => $c->where('year', '<=', $toYear))
            ->when($season, fn ($c) => $c->where('season', $season))
            ->when($month, fn ($c) => $c->where('month', $month))
            ->values();
    }

    /** Average of each reading per season (Wet / Dry) over the given months. */
    public function seasonComparison(Collection $months): array
    {
        $out = [];
        foreach ([RiceSeason::WET, RiceSeason::DRY] as $season) {
            $rows = $months->where('season', $season);
            $out[$season] = ['months' => $rows->count()] + $this->averages($rows);
        }

        return $out;
    }

    /** Per year: total rainfall and the average of the other readings. */
    public function yearly(Collection $months): array
    {
        return $months->groupBy('year')->map(function (Collection $rows, int $year): array {
            $rain = $rows->pluck('rainfall')->filter(fn ($v) => $v !== null);

            return ['year' => $year, 'months' => $rows->count(), 'rain_total' => $rain->isEmpty() ? null : round($rain->sum(), 1)] + $this->averages($rows);
        })->sortKeys()->values()->all();
    }

    /** Months that crossed the heavy-rain, dry or hot planning thresholds. */
    public function indicators(Collection $months): array
    {
        $label = fn (object $m) => date('M Y', mktime(0, 0, 0, $m->month, 1, $m->year));

        return [
            'heavy_rain' => $months->filter(fn ($m) => $m->rainfall !== null && $m->rainfall >= self::heavyRainMm())->map($label)->values()->all(),
            'dry' => $months->filter(fn ($m) => $m->rainfall !== null && $m->rainfall < self::dryMonthMm())->map($label)->values()->all(),
            'hot' => $months->filter(fn ($m) => $m->temperature !== null && $m->temperature >= self::hotMonthC())->map($label)->values()->all(),
        ];
    }

    /**
     * Months that have both a yield record and climate readings: the yield beside the same month's readings and the
     * average of the three months up to it (the growing period before harvest).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function yieldPairs(Collection $months): Collection
    {
        $byKey = $months->keyBy('key');

        return rescue(fn () => $this->yields->rows(), collect(), false)
            ->filter(fn (array $row) => (float) ($row['yield'] ?? 0) > 0)
            ->map(function (array $row) use ($byKey): ?array {
                $key = sprintf('%04d-%02d', $row['year'], $row['month']);
                if (! $byKey->has($key)) {
                    return null;
                }
                $window = collect(range(0, 2))->map(fn (int $back) => $byKey->get(date('Y-m', mktime(0, 0, 0, $row['month'] - $back, 1, $row['year']))))->filter();
                $pair = ['key' => $key, 'year' => (int) $row['year'], 'month' => (int) $row['month'], 'season' => RiceSeason::forMonth((int) $row['month']), 'yield' => round((float) $row['yield'], 3)];
                foreach (array_keys(self::VARIABLES) as $variable) {
                    $pair[$variable] = $byKey[$key]->{$variable};
                    $values = $window->pluck($variable)->filter(fn ($v) => $v !== null);
                    $pair[$variable.'_3m'] = $window->count() === 3 && $values->count() === 3 ? round($values->avg(), 2) : null;
                }

                return $pair;
            })
            ->filter()->values();
    }

    /**
     * Weather Impact Analysis: Pearson correlation of each climate variable with rice yield, for the same month and for
     * the three-month growing window, ranked by strength, with an impact level.
     *
     * @return array{pairs: int, variables: array<int, array<string, mixed>>, strongest: ?array<string, mixed>, years: array<int, int>}
     */
    public function impact(?string $season = null, ?int $fromYear = null, ?int $toYear = null): array
    {
        $pairs = $this->yieldPairs($this->months())
            ->when($season, fn ($c) => $c->where('season', $season))
            ->when($fromYear, fn ($c) => $c->where('year', '>=', $fromYear))
            ->when($toYear, fn ($c) => $c->where('year', '<=', $toYear))
            ->values();

        $variables = [];
        foreach (self::VARIABLES as $variable => $label) {
            foreach (['same' => $variable, 'window' => $variable.'_3m'] as $basis => $column) {
                $points = $pairs->filter(fn (array $p) => $p[$column] !== null)->map(fn (array $p) => [$p[$column], $p['yield']])->values();
                $r = $points->count() >= self::MIN_PAIRS ? self::pearson($points) : null;
                $variables[] = [
                    'variable' => $variable,
                    'label' => $label,
                    'basis' => $basis,
                    'basis_label' => $basis === 'same' ? 'Same month' : '3 months up to harvest',
                    'pairs' => $points->count(),
                    'r' => $r === null ? null : round($r, 3),
                    'impact' => self::impactLevel($r),
                    'direction' => $r === null ? null : ($r >= 0 ? 'higher' : 'lower'),
                    'sentence' => self::sentence($label, $basis, $r),
                ];
            }
        }
        usort($variables, fn ($a, $b) => abs($b['r'] ?? 0) <=> abs($a['r'] ?? 0));

        return [
            'pairs' => $pairs->count(),
            'variables' => $variables,
            'strongest' => collect($variables)->first(fn ($v) => $v['r'] !== null),
            'years' => $pairs->pluck('year')->unique()->sort()->values()->all(),
        ];
    }

    public static function impactLevel(?float $r): ?string
    {
        return match (true) {
            $r === null => null,
            abs($r) >= self::HIGH_IMPACT_R => 'High',
            abs($r) >= self::MODERATE_IMPACT_R => 'Moderate',
            default => 'Low',
        };
    }

    private static function sentence(string $label, string $basis, ?float $r): string
    {
        if ($r === null) {
            return 'Not enough months with both readings to judge.';
        }
        $when = $basis === 'same' ? 'in the harvest month' : 'in the three months before harvest';
        $strength = ['High' => 'strongly', 'Moderate' => 'somewhat', 'Low' => 'barely'][self::impactLevel($r)];

        return "Months with more {$label} {$when} {$strength} went with ".($r >= 0 ? 'higher' : 'lower').' yield.';
    }

    /** @param  Collection<int, array{0: float, 1: float}>  $points */
    public static function pearson(Collection $points): ?float
    {
        $n = $points->count();
        if ($n < 2) {
            return null;
        }
        $mx = $points->avg(0);
        $my = $points->avg(1);
        $sxy = $points->sum(fn ($p) => ($p[0] - $mx) * ($p[1] - $my));
        $sx = sqrt($points->sum(fn ($p) => ($p[0] - $mx) ** 2));
        $sy = sqrt($points->sum(fn ($p) => ($p[1] - $my) ** 2));

        return $sx > 0 && $sy > 0 ? $sxy / ($sx * $sy) : null;
    }

    private function averages(Collection $rows): array
    {
        $out = [];
        foreach (array_keys(self::VARIABLES) as $variable) {
            $values = $rows->pluck($variable)->filter(fn ($v) => $v !== null);
            $out[$variable] = $values->isEmpty() ? null : round($values->avg(), 1);
        }

        return $out;
    }
}
