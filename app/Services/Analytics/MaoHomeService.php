<?php

namespace App\Services\Analytics;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\TyphoonSafetyService;
use App\Support\ProjectYieldDataset;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * MAO Dashboard (2026-10): one screen, one purpose. Four KPI tiles (registered farmers, area planted this season,
 * expected yield against the last recorded season, high-risk barangays), the "Kailangan ng Aksyon" list (advisories to
 * approve, unresolved field reports, "Needs Help" check-ins), a small Heat Map preview and one bar chart of rice yield
 * by year. Everything is read from saved data: no provider is called and no model is run.
 *
 * A figure that cannot be worked out is null and shown as "Walang datos pa", never as zero.
 */
class MaoHomeService
{
    public const TIMEZONE = 'Asia/Manila';

    /** The years of the "Rice Yield by Year" chart. */
    public const CHART_FROM_YEAR = 2020;

    public const CHART_TO_YEAR = 2025;

    public function __construct(
        private readonly BarangayWeekRiskService $weekRisk,
        private readonly ProjectYieldDataset $yields,
        private readonly TyphoonSafetyService $typhoon,
    ) {}

    /** @return array<string, mixed> shared by every MAO user (cacheable) */
    public function payload(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $risk = rescue(fn (): array => $this->weekRisk->forRange('week'), ['state' => 'nodata', 'counts' => [], 'barangays' => [], 'updated_at' => null], false);

        return [
            'generated_at' => $now,
            'season' => rescue(fn (): string => $this->seasonLabel($now), '', false),
            'farmers' => rescue(fn (): int => FarmerProfile::query()->count(), null, false),
            'area' => $this->areaPlanted($now),
            'yield' => $this->expectedYield($now),
            'risk' => [
                'state' => $risk['state'],
                'high' => $risk['state'] === 'ok' ? (int) ($risk['counts']['high'] ?? 0) : null,
                'names' => collect($risk['barangays'])->where('level', 'high')->keys()->all(),
                'levels' => collect($risk['barangays'])->map(fn (array $b): string => (string) $b['level'])->all(),
                'updated_at' => $risk['updated_at'],
            ],
            'actions' => $this->actions(),
            'chart' => $this->yieldByYear(),
        ];
    }

    /** "Wet season 2026" / "Dry season 2026–2027". */
    private function seasonLabel(CarbonImmutable $now): string
    {
        $season = RiceSeason::window($now);

        return $season['season'].' season '.($season['season'] === RiceSeason::WET ? $season['start']->year : $season['start']->year.'–'.$season['end']->year);
    }

    /**
     * Hectares in farmers' planting plans whose planting date falls in the current season; null when there are none.
     *
     * @return array{hectares: ?float, plans: int}
     */
    private function areaPlanted(CarbonImmutable $now): array
    {
        try {
            if (! SchemaCache::hasTable('farmer_planting_schedules')) {
                return ['hectares' => null, 'plans' => 0];
            }
            $season = RiceSeason::window($now);
            $plans = FarmerPlantingSchedule::query()
                ->whereBetween('planting_date', [$season['start']->toDateString(), $season['end']->toDateString()])
                ->get(['farm_area_hectares']);
        } catch (Throwable) {
            return ['hectares' => null, 'plans' => 0];
        }

        return ['hectares' => $plans->isEmpty() ? null : round((float) $plans->sum('farm_area_hectares'), 2), 'plans' => $plans->count()];
    }

    /**
     * This season's expected yield (the average of farmers' saved estimates) beside the last recorded season's yield.
     *
     * @return array{expected: ?float, count: int, last: ?float, last_label: ?string, change_percent: ?float}
     */
    private function expectedYield(CarbonImmutable $now): array
    {
        // One saved estimate per farm (a farmer's farms are estimated separately).
        $estimates = rescue(fn () => FarmerDashboardService::storedEstimates(), collect(), false);
        $expected = $estimates->isEmpty() ? null : round((float) $estimates->avg(fn (array $e): float => (float) ($e['yield_tons_per_hectare'] ?? $e['predicted_yield'] ?? 0)), 2);
        $last = rescue(fn (): ?array => $this->lastRecordedSeason($now), null, false);

        return [
            'expected' => $expected,
            'count' => $estimates->count(),
            'last' => $last['yield'] ?? null,
            'last_label' => $last['label'] ?? null,
            'change_percent' => $expected !== null && ! empty($last['yield']) ? round(($expected - $last['yield']) / $last['yield'] * 100, 1) : null,
        ];
    }

    /**
     * The most recent season before the current one that has yield records, with its average t/ha.
     *
     * @return array{label: string, yield: float}|null
     */
    private function lastRecordedSeason(CarbonImmutable $now): ?array
    {
        $current = RiceSeason::window($now)['start'];

        return $this->yields->rows()
            ->filter(fn ($row): bool => (float) ($row['yield'] ?? 0) > 0)
            ->groupBy(fn ($row): string => RiceSeason::window(CarbonImmutable::create((int) $row['year'], (int) $row['month'], 1, 0, 0, 0, self::TIMEZONE))['start']->toDateString())
            ->filter(fn ($rows, string $start): bool => $start < $current->toDateString())
            ->sortKeysDesc()
            ->map(function ($rows, string $start): array {
                $window = RiceSeason::window(CarbonImmutable::parse($start, self::TIMEZONE));

                return [
                    'label' => $window['season'].' season '.($window['season'] === RiceSeason::WET ? $window['start']->year : $window['start']->year.'–'.$window['end']->year),
                    'yield' => round((float) $rows->avg('yield'), 2),
                ];
            })
            ->first();
    }

    /**
     * "Kailangan ng Aksyon": the three things that wait for the MAO, each with a count and the page that handles it.
     * A count that cannot be read is null (shown as "—"), never zero.
     *
     * @return array<int, array{key: string, label: string, count: ?int, href: string, action: string}>
     */
    private function actions(): array
    {
        $event = rescue(fn () => $this->typhoon->activeEvent(), null, false);

        return [
            [
                'key' => 'advisories',
                'label' => 'Advisories to approve',
                'count' => rescue(fn (): int => PlantingAdvisory::query()->pendingReview()->count(), null, false),
                'href' => route('planting-advisories.index', ['show' => 'pending']),
                'action' => 'Review',
            ],
            [
                'key' => 'field-reports',
                'label' => 'Unresolved field reports',
                'count' => rescue(fn (): int => FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)->count(), null, false),
                'href' => route('field-reports.index', ['unresolved' => 1]),
                'action' => 'Open',
            ],
            [
                'key' => 'check-ins',
                'label' => '"Needs Help" check-ins',
                'count' => rescue(fn (): int => $event && SchemaCache::hasTable('typhoon_safety_responses')
                    ? TyphoonSafetyResponse::query()->where('event_key', $event['key'])->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->count()
                    : 0, null, false),
                'href' => route('planting-advisories.index').'#check-ins',
                'action' => 'Open',
            ],
        ];
    }

    /**
     * Average rice yield (t/ha) for each year from CHART_FROM_YEAR to CHART_TO_YEAR; a year without records is null.
     *
     * @return array{state: string, labels: array<int, string>, values: array<int, ?float>, months: array<int, int>, source: string}
     */
    private function yieldByYear(): array
    {
        $years = range(self::CHART_FROM_YEAR, self::CHART_TO_YEAR);
        try {
            $values = array_map(function (int $year): ?float {
                $mean = $this->yields->yearlyMean($year);

                return $mean && $mean > 0 ? round((float) $mean, 2) : null;
            }, $years);
            $months = array_map(fn (int $year): int => $this->yields->monthsRecorded($year), $years);
        } catch (Throwable) {
            $values = array_fill(0, count($years), null);
            $months = array_fill(0, count($years), 0);
        }

        return [
            'state' => array_filter($values, fn ($v) => $v !== null) === [] ? 'nodata' : 'ok',
            'labels' => array_map('strval', $years),
            'values' => $values,
            'months' => $months,
            'source' => ProjectYieldDataset::SOURCE_NAME,
        ];
    }
}
