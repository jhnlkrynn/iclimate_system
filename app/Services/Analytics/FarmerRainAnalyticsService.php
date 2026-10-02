<?php

namespace App\Services\Analytics;

use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Carbon\CarbonImmutable;

/**
 * Monthly rain at the Ambulong station (the nearest PAGASA station, in Tanauan) for the latest year on record, next to
 * the long-term average for each month (every other recorded year), plus one plain sentence about the latest month.
 *
 * Reads only the Ambulong monthly dataset. No month is ever filled in: a month without a record stays empty.
 */
class FarmerRainAnalyticsService
{
    /** A long-term average needs at least this many years of records for that month. */
    public const MIN_YEARS_FOR_AVERAGE = 3;

    public function __construct(private readonly AmbulongHistoricalClimateDataset $ambulong) {}

    /**
     * Long-term average rain (mm) for a calendar month, across all recorded years except `$exceptYear`. Null when there
     * are too few years of records.
     */
    public function monthlyNormal(int $month, ?int $exceptYear = null): ?float
    {
        $values = $this->observations()
            ->filter(fn (array $o) => $o['month'] === $month && ($exceptYear === null || $o['year'] !== $exceptYear))
            ->pluck('rainfall');

        return $values->count() >= self::MIN_YEARS_FOR_AVERAGE ? (float) $values->avg() : null;
    }

    /**
     * @return array{year: int, is_current_year: bool, labels: array<int, string>, actual: array<int, ?float>, normal: array<int, ?float>, latest: ?array{month: int, rain: float, normal: ?float, percent: ?float, level: ?string}}|null
     */
    public function monthly(CarbonImmutable $today): ?array
    {
        $observations = $this->observations()->filter(fn (array $o) => $o['year'] <= $today->year);
        $year = $observations->max('year');
        if ($year === null) {
            return null;
        }

        $actual = array_fill(0, 12, null);
        $normal = array_fill(0, 12, null);
        foreach ($observations->where('year', $year) as $o) {
            $actual[$o['month'] - 1] = round((float) $o['rainfall'], 1);
        }
        for ($m = 1; $m <= 12; $m++) {
            $avg = $this->monthlyNormal($m, (int) $year);
            $normal[$m - 1] = $avg !== null ? round($avg, 1) : null;
        }

        $latestMonth = null;
        foreach ($actual as $i => $rain) {
            if ($rain !== null) {
                $latestMonth = $i + 1;
            }
        }
        $latest = null;
        if ($latestMonth !== null) {
            $rain = (float) $actual[$latestMonth - 1];
            $avg = $normal[$latestMonth - 1];
            $percent = $avg !== null && $avg > 0 ? ($rain - $avg) / $avg * 100 : null;
            $band = (float) config('farmer_dashboard.rain_normal_band_percent');
            $latest = [
                'month' => $latestMonth,
                'rain' => $rain,
                'normal' => $avg,
                'percent' => $percent,
                'level' => $percent === null ? null : ($percent > $band ? 'more' : ($percent < -$band ? 'less' : 'normal')),
            ];
        }

        return [
            'year' => (int) $year,
            'is_current_year' => (int) $year === $today->year,
            'labels' => array_map(fn (int $m) => CarbonImmutable::create(2000, $m, 1)->format('M'), range(1, 12)),
            'actual' => $actual,
            'normal' => $normal,
            'latest' => $latest,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{year: int, month: int, rainfall: float}> */
    private function observations(): \Illuminate\Support\Collection
    {
        $data = $this->ambulong->load();

        return collect($data['observations'] ?? [])->filter(fn (array $o) => isset($o['year'], $o['month']) && is_numeric($o['rainfall'] ?? null))->values();
    }
}
