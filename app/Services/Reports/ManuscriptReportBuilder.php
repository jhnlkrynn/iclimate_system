<?php

namespace App\Services\Reports;

use App\Models\BarangayProfile;
use App\Models\FarmerProfile;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Climate\WeatherAnalysisService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Support\ProjectYieldDataset;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use App\Support\YieldCorrections;
use Illuminate\Support\Collection;

/**
 * Manuscript – Naicel (2026-10-08), Phase 7: the four report types the manuscript adds — Seasonal Production, Yield
 * Prediction Summary, Climate Trend and Barangay Risk. They are built here in PHP from stored data (small tables) and
 * saved as ordinary Report rows (Draft, versioned), so Reports shows, prints and exports them like the others.
 */
class ManuscriptReportBuilder
{
    public const TYPES = ['seasonal_production', 'yield_prediction', 'climate_trend', 'barangay_risk'];

    /** Which filters each type uses. */
    public const FILTERS = [
        'seasonal_production' => ['year', 'season', 'barangay'],
        'yield_prediction' => ['year', 'season', 'barangay'],
        'climate_trend' => ['year', 'season'],
        'barangay_risk' => ['barangay'],
    ];

    public function __construct(
        private readonly WeatherAnalysisService $weather,
        private readonly ProjectYieldDataset $yields,
    ) {}

    public static function handles(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function create(string $type, array $filters, User $author): Report
    {
        $filters = collect($filters)->only(self::FILTERS[$type])->reject(fn ($v) => blank($v))->all();
        [$columns, $records, $summary] = match ($type) {
            'seasonal_production' => $this->seasonalProduction($filters),
            'yield_prediction' => $this->yieldPrediction($filters),
            'climate_trend' => $this->climateTrend($filters),
            'barangay_risk' => $this->barangayRisk($filters),
        };
        $label = Report::TYPES[$type];
        $now = now();

        return Report::query()->create([
            'report_type' => $label,
            'title' => $label.' - '.$now->copy()->timezone(\App\Support\BusinessClock::timezone())->format('Y-m-d H:i'),
            'generated_by' => $author->getKey(),
            'is_generated' => true,
            'payload' => [
                'report_type' => $type,
                'filters' => $filters,
                'columns' => $columns,
                'records' => array_values($records),
                'row_count' => count($records),
                'summary' => $summary,
                'generated_at' => $now->toDateTimeString(),
                'format' => 'screen',
                'processor' => 'php',
            ],
        ]);
    }

    /** Area planted and harvested, production and yield per year and season (and barangay), from the MAO's records. */
    private function seasonalProduction(array $f): array
    {
        $rows = RiceProduction::query()
            ->when($f['year'] ?? null, fn ($q, $y) => $q->where('year', $y))
            ->when($f['season'] ?? null, fn ($q, $s) => $q->where('season', $s))
            ->when($f['barangay'] ?? null, fn ($q, $b) => $q->where('barangay', $b))
            ->get();

        $records = $rows->groupBy(fn ($r) => $r->year.'|'.$r->season.'|'.$r->barangay)
            ->map(function (Collection $group) {
                $first = $group->first();
                $planted = $group->sum(fn ($r) => (float) $r->area_hectares);
                $production = $group->sum(fn ($r) => (float) $r->total_production);

                return [
                    'year' => $first->year,
                    'season' => $first->season,
                    'barangay' => $first->barangay,
                    'records' => $group->count(),
                    'area_planted' => round($planted, 2),
                    'area_harvested' => round($group->sum(fn ($r) => (float) $r->area_harvested_hectares), 2),
                    'production_mt' => round($production, 2),
                    'yield_t_ha' => $group->whereNotNull('yield_per_hectare')->isEmpty() ? null : round($group->whereNotNull('yield_per_hectare')->avg(fn ($r) => (float) $r->yield_per_hectare), 2),
                ];
            })
            ->sortBy(fn ($r) => sprintf('%04d|%s|%s', $r['year'], $r['season'], $r['barangay']))->values()->all();

        // The municipal average yield per season from the project's yield record, as context.
        $municipal = $this->yields->rows()
            ->when($f['year'] ?? null, fn ($c, $y) => $c->where('year', (int) $y))
            ->when($f['season'] ?? null, fn ($c, $s) => $c->where('season', $s))
            ->groupBy(fn ($r) => $r['year'].' '.($r['season'] ?? RiceSeason::forMonth((int) $r['month'])))
            ->map(fn ($g) => round($g->avg('yield'), 2));

        $summary = [
            ['label' => 'Total production', 'value' => number_format(collect($records)->sum('production_mt'), 2).' t'],
            ['label' => 'Area planted / harvested', 'value' => number_format(collect($records)->sum('area_planted'), 2).' / '.number_format(collect($records)->sum('area_harvested'), 2).' ha'],
            ['label' => 'Average yield (records)', 'value' => collect($records)->whereNotNull('yield_t_ha')->isEmpty() ? 'Not available' : number_format(collect($records)->whereNotNull('yield_t_ha')->avg('yield_t_ha'), 2).' t/ha'],
        ];
        foreach ($municipal->sortKeys()->take(-6) as $season => $avg) {
            $summary[] = ['label' => 'Lian average yield, '.$season, 'value' => number_format($avg, 2).' t/ha', 'source' => 'iClimate yield record'];
        }

        return [
            ['year' => 'Year', 'season' => 'Season', 'barangay' => 'Barangay', 'records' => 'Records', 'area_planted' => 'Area Planted (ha)', 'area_harvested' => 'Area Harvested (ha)', 'production_mt' => 'Production (t)', 'yield_t_ha' => 'Yield (t/ha)'],
            $records,
            $summary,
        ];
    }

    /** Yield predictions farmers requested and MAO forecasts, with predicted vs actual from farmers' harvests. */
    private function yieldPrediction(array $f): array
    {
        $rows = SchemaCache::hasTable('yield_prediction_requests')
            ? YieldPredictionRequest::query()->where('status', YieldPredictionRequest::DONE)
                ->when($f['season'] ?? null, fn ($q, $s) => $q->where('season', $s))
                ->when($f['barangay'] ?? null, fn ($q, $b) => $q->where('barangay', $b))
                ->when($f['year'] ?? null, fn ($q, $y) => $q->whereYear('target_month', $y))
                ->latest()->limit(2000)->get()
            : collect();

        $records = $rows->map(fn (YieldPredictionRequest $r) => [
            'requested' => $r->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('Y-m-d'),
            'for' => ['farm' => 'Farmer', 'barangay' => 'Barangay', 'municipal' => 'All of Lian'][$r->scope] ?? $r->scope,
            'barangay' => $r->barangay,
            'season' => $r->season.' '.$r->target_month?->format('M Y'),
            'farm_type' => $r->farm_type,
            'area' => $r->area_hectares !== null ? (float) $r->area_hectares : null,
            'predicted' => (float) $r->predicted_yield,
            'total' => $r->total_tons !== null ? (float) $r->total_tons : null,
            'usual' => $r->historical_average !== null ? (float) $r->historical_average : null,
            'outlook' => $r->impact_level,
        ])->all();

        $corrections = rescue(fn () => YieldCorrections::summary(), ['count' => 0], false);
        $outlooks = $rows->countBy('impact_level');

        return [
            ['requested' => 'Requested', 'for' => 'For', 'barangay' => 'Barangay', 'season' => 'Season', 'farm_type' => 'Irrigation', 'area' => 'Area (ha)', 'predicted' => 'Predicted (t/ha)', 'total' => 'Total (t)', 'usual' => 'Usual (t/ha)', 'outlook' => 'Outlook'],
            $records,
            array_values(array_filter([
                ['label' => 'Predictions', 'value' => (string) $rows->count()],
                ['label' => 'Average predicted yield', 'value' => $rows->isEmpty() ? 'Not available' : number_format($rows->avg(fn ($r) => (float) $r->predicted_yield), 2).' t/ha'],
                ['label' => 'Favorable / Normal / At Risk', 'value' => ($outlooks['Favorable'] ?? 0).' / '.($outlooks['Normal'] ?? 0).' / '.($outlooks['At Risk'] ?? 0)],
                ($corrections['count'] ?? 0) > 0 ? ['label' => 'Predicted vs actual (farmers\' harvests)', 'value' => $corrections['count'].' harvests, average miss '.number_format((float) $corrections['error'], 2).' t/ha'] : null,
            ])),
        ];
    }

    /** Monthly rainfall, temperature, humidity and wind, with yearly totals and season averages. */
    private function climateTrend(array $f): array
    {
        $months = $this->weather->filter($this->weather->months(), isset($f['year']) ? (int) $f['year'] : null, $f['season'] ?? null, null, isset($f['year']) ? null : 2020);
        $records = $months->map(fn ($m) => [
            'month' => sprintf('%04d-%02d', $m->year, $m->month),
            'season' => $m->season,
            'rainfall' => $m->rainfall,
            'temperature' => $m->temperature,
            'humidity' => $m->humidity,
            'wind_speed' => $m->wind_speed,
        ])->all();

        $summary = [];
        foreach ($this->weather->yearly($months) as $year) {
            $summary[] = ['label' => $year['year'].' total rainfall / average temperature', 'value' => ($year['rain_total'] === null ? '—' : number_format($year['rain_total'], 1).' mm').' / '.($year['temperature'] === null ? '—' : number_format($year['temperature'], 1).'°C')];
        }
        foreach ($this->weather->seasonComparison($months) as $season => $s) {
            if ($s['months'] > 0) {
                $summary[] = ['label' => $season.' season average rainfall', 'value' => number_format((float) $s['rainfall'], 1).' mm per month ('.$s['months'].' months)'];
            }
        }
        $signals = $this->weather->indicators($months);
        $summary[] = ['label' => 'Heavy-rain / dry / hot months', 'value' => count($signals['heavy_rain']).' / '.count($signals['dry']).' / '.count($signals['hot'])];

        return [
            ['month' => 'Month', 'season' => 'Season', 'rainfall' => 'Rainfall (mm)', 'temperature' => 'Temperature (°C)', 'humidity' => 'Humidity (%)', 'wind_speed' => 'Wind (station reading)'],
            $records,
            $summary,
        ];
    }

    /** This week's risk per barangay beside the MAO's classification, flood/drought marks and registered farmers. */
    private function barangayRisk(array $f): array
    {
        $week = rescue(fn () => app(BarangayWeekRiskService::class)->forRange('week'), ['barangays' => [], 'state' => 'nodata'], false);
        $profiles = SchemaCache::hasTable('barangay_profiles') ? BarangayProfile::query()->get()->keyBy('barangay') : collect();
        $farmers = FarmerProfile::query()->selectRaw('barangay, count(*) as total')->groupBy('barangay')->pluck('total', 'barangay');

        $records = collect(\App\Support\LianBarangays::all())
            ->when($f['barangay'] ?? null, fn ($c, $b) => $c->filter(fn ($name) => $name === $b))
            ->map(fn (string $name) => [
                'barangay' => $name,
                'weekly_risk' => ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'][$week['barangays'][$name]['level'] ?? ''] ?? 'No data',
                'mao_classification' => $profiles[$name]->risk_classification ?? null,
                'flood_prone' => ($profiles[$name]->flood_prone ?? false) ? 'Yes' : 'No',
                'drought_prone' => ($profiles[$name]->drought_prone ?? false) ? 'Yes' : 'No',
                'rice_area' => isset($profiles[$name]) && $profiles[$name]->farm_area_hectares !== null ? (float) $profiles[$name]->farm_area_hectares : null,
                'farmers' => (int) ($farmers[$name] ?? 0),
            ])->values()->all();

        $levels = collect($records)->countBy('weekly_risk');

        return [
            ['barangay' => 'Barangay', 'weekly_risk' => 'This Week\'s Risk', 'mao_classification' => 'MAO Classification', 'flood_prone' => 'Flood-prone', 'drought_prone' => 'Drought-prone', 'rice_area' => 'Rice Area (ha)', 'farmers' => 'Farmers'],
            $records,
            [
                ['label' => 'High / Moderate / Low this week', 'value' => ($levels['High'] ?? 0).' / '.($levels['Moderate'] ?? 0).' / '.($levels['Low'] ?? 0)],
                ['label' => 'Flood-prone / drought-prone barangays', 'value' => collect($records)->where('flood_prone', 'Yes')->count().' / '.collect($records)->where('drought_prone', 'Yes')->count()],
                ['label' => 'Farmers in high-risk barangays this week', 'value' => (string) collect($records)->where('weekly_risk', 'High')->sum('farmers')],
            ],
        ];
    }
}
