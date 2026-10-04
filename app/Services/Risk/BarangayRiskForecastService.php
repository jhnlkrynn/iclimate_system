<?php

namespace App\Services\Risk;

use App\Models\FarmerProfile;
use App\Services\DecisionSupportService;
use App\Services\Prediction\PredictionOrchestrator;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forecast risk per barangay for the current month and the next five, for the
 * month selector on the Lian Municipality Risk Map.
 *
 * Nothing here is a new model or formula. Each month's weather is the stored
 * iClimate monthly Random Forest forecast (read through PredictionOrchestrator's
 * read-only batch read, so a page request never trains), scored by the same
 * DecisionSupportService / AgriculturalRiskScorer the live map uses, the same way
 * PredictionEngine feeds that forecast to it. Per-barangay inputs (last recorded
 * yield, dominant farm type) come from BarangayRiskMapService, so a barangay's
 * forecast and its current rating are computed from the same data.
 *
 * The monthly forecast is one estimate for all of Lian; differences between
 * barangays come from farm type, recorded yield and the scorer's exposure rating
 * (a planning placeholder, see AgriculturalRiskScorer::METHODOLOGY_CAVEAT).
 * A month without a ready forecast is left out, never filled in.
 */
final class BarangayRiskForecastService
{
    public const CACHE_KEY = 'geo:lian-risk-forecast:v1';

    private const CACHE_MINUTES = 20;

    /** Current month plus this many following months. */
    public const MONTHS_AHEAD = 5;

    public const LEVELS = ['Low', 'Moderate', 'High', 'Severe', 'No Data'];

    private const LEVEL_RANK = ['Low' => 1, 'Moderate' => 2, 'High' => 3, 'Severe' => 4];

    /**
     * Plain-language wording for DecisionSupportService's stress factors (keyed by
     * its own labels), in the order a farmer should hear about them.
     */
    private const CONCERNS = [
        'Flooding risk' => ['Heavy rainfall may occur', 'Fields may flood, especially in low-lying areas.'],
        'Drought stress' => ['Very little rain is expected', 'Fields may dry out and young rice may suffer, especially on rainfed farms.'],
        'Limited rainfall' => ['Less rain than rice usually needs', 'Rainfed fields may need extra water.'],
        'Wet field risk' => ['More rain than ideal', 'Fields may stay too wet; drainage becomes important.'],
        'Strong wind' => ['Strong winds are possible', 'Rice plants may bend or fall over.'],
        'Disease pressure' => ['Very humid weather', 'Pests and rice diseases may increase.'],
        'Dry-season rainfed exposure' => ['Dry season on rainfed farms', 'Farms without irrigation are more exposed to the dry weather.'],
        'Low yield outlook' => ['Low recorded harvest', 'Past harvests here were below the planning target.'],
        'Average yield outlook' => ['Average recorded harvest', 'Past harvests here were workable but should be watched.'],
    ];

    public function __construct(
        private readonly PredictionOrchestrator $orchestrator,
        private readonly DecisionSupportService $decisionSupport,
        private readonly BarangayRiskMapService $riskMap,
    ) {}

    /**
     * Every forecast month with a ready forecast, oldest first.
     *
     * @return array{generated_at: string, months: list<array<string, mixed>>, source_note: string}
     */
    public function forecast(): array
    {
        $start = BusinessClock::now()->startOfMonth()->toImmutable();

        return Cache::remember(self::CACHE_KEY.':'.$start->format('Y-m'), now()->addMinutes(self::CACHE_MINUTES), function () use ($start): array {
            $yields = $this->riskMap->latestYieldsByBarangay();
            $farmTypes = $this->riskMap->dominantFarmTypes();
            $months = [];

            foreach ($this->monthlyForecasts($start) as $key => $forecast) {
                if ($forecast['ready'] ?? false) {
                    $months[] = $this->scoreMonth(CarbonImmutable::createFromFormat('!Y-m', $key), $forecast, $yields, $farmTypes);
                }
            }

            $this->addTrends($months);

            return [
                'generated_at' => now()->toIso8601String(),
                'months' => $months,
                'source_note' => 'Forecast weather comes from the iClimate monthly forecast model and is one estimate for all of Lian. '
                    .'Differences between barangays come from farm type, recorded harvests and a planning exposure rating. '
                    .AgriculturalRiskScorer::METHODOLOGY_CAVEAT,
            ];
        });
    }

    /**
     * Stored forecasts for the current month and the next five, keyed 'Y-m', in one read-only
     * query (never trains a model). Months without a ready forecast are absent.
     *
     * @return array<string, array>
     */
    private function monthlyForecasts(CarbonImmutable $start): array
    {
        $months = array_map(fn (int $offset): CarbonImmutable => $start->addMonthsNoOverflow($offset), range(0, self::MONTHS_AHEAD));

        try {
            return $this->orchestrator->preparedMonthlyWeatherForecasts($months);
        } catch (Throwable $exception) {
            Log::warning('Barangay risk forecast: monthly forecast lookup failed.', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, \App\Models\RiceProduction>  $yields
     * @param  array<string, string>  $farmTypes
     */
    private function scoreMonth(CarbonImmutable $month, array $forecast, array $yields, array $farmTypes): array
    {
        $p = $forecast['predictions'] ?? [];
        $number = fn (string $key): ?float => is_numeric($p[$key] ?? null) ? (float) $p[$key] : null;
        $weather = [
            'rainfall_mm' => $number('rainfall') !== null ? (int) round($number('rainfall')) : null,
            'temperature_c' => $number('temperature') !== null ? round($number('temperature'), 1) : null,
            'humidity_percent' => $number('humidity') !== null ? (int) round($number('humidity')) : null,
        ];
        $season = in_array($p['season'] ?? null, ['Wet', 'Dry'], true) ? $p['season'] : null;

        $barangays = [];
        foreach (LianBarangays::all() as $barangay) {
            $yield = ($yields[$barangay] ?? null)?->yield_per_hectare;
            // Same inputs PredictionEngine gives DecisionSupportService for a forecast month; a missing
            // rainfall or temperature makes it return "insufficient data", which is shown as No Data.
            $farmType = $farmTypes[$barangay] ?? FarmerProfile::FARM_TYPE_RAINFED;
            $evaluation = $this->decisionSupport->evaluate([
                'rainfall' => $number('rainfall'),
                'temperature' => $number('temperature'),
                'humidity' => $number('humidity'),
                'wind_speed' => $number('wind_speed'),
                'season' => $season ?? 'Dry',
                'farm_type' => $farmType,
                'barangay' => $barangay,
                'predicted_yield' => $yield !== null ? (float) $yield : null,
            ]);
            // What is known about the barangay itself. The scorer's exposure rating is a planning
            // placeholder (AgriculturalRiskScorer::METHODOLOGY_CAVEAT), so its value is not published here.
            $factors = [
                'farm_type' => $farmType,
                'farm_type_from_records' => isset($farmTypes[$barangay]),
                'yield_record' => $yield !== null ? [
                    'value' => round((float) $yield, 2),
                    'unit' => 't/ha',
                    'year' => $yields[$barangay]->year,
                ] : null,
            ];
            $barangays[] = $this->barangayEntry($barangay, $evaluation) + ['factors' => $factors];
        }

        $counts = array_fill_keys(self::LEVELS, 0);
        foreach ($barangays as $entry) {
            $counts[$entry['risk_level']]++;
        }

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'short_label' => $month->format('M'),
            'season' => $season,
            'weather' => $weather,
            'confidence' => data_get($forecast, 'confidence.label'),
            // "Moderate confidence" -> "Moderate", for the farmer-facing summary.
            'confidence_level' => is_string($label = data_get($forecast, 'confidence.label')) ? trim(preg_replace('/\s*confidence$/i', '', $label)) : null,
            'forecast_generated_at' => $forecast['generated_at'] ?? null,
            'barangays' => $barangays,
            'summary' => $this->summary($barangays, $counts),
        ];
    }

    private function barangayEntry(string $barangay, array $evaluation): array
    {
        $level = $evaluation['risk']['level'] ?? null;
        if (! isset(self::LEVEL_RANK[$level])) {
            return [
                'barangay' => $barangay,
                'risk_level' => 'No Data',
                'risk_score' => null,
                'main_concern' => 'Insufficient Forecast Data',
                'impact' => null,
                'action' => null,
            ];
        }

        $factors = collect($evaluation['stress_factors'] ?? [])->pluck('label')->all();
        $main = collect(array_keys(self::CONCERNS))->first(fn (string $label): bool => in_array($label, $factors, true));
        $planting = $evaluation['planting']['items'] ?? [];
        $actions = array_filter([
            $evaluation['irrigation']['recommendation'] ?? null,
            // The generic "possible with routine monitoring" line adds nothing next to the irrigation advice.
            ...array_filter($planting, fn (string $item): bool => ! str_starts_with($item, 'Planting is possible')),
        ]);

        return [
            'barangay' => $barangay,
            'risk_level' => $level,
            'risk_score' => $evaluation['risk']['score'] ?? null,
            'main_concern' => $main ? self::CONCERNS[$main][0] : 'No major weather concern',
            'impact' => $main ? self::CONCERNS[$main][1] : 'No major weather problem is expected for rice this month.',
            // Two actions at most keeps the popup short; the most specific (irrigation) comes first.
            'action' => $actions !== [] ? implode(' ', array_slice(array_values(array_unique($actions)), 0, 2)) : 'Continue normal farm work and watch PAGASA/local weather advisories.',
        ];
    }

    /** @param  array<string, int>  $counts */
    private function summary(array $barangays, array $counts): array
    {
        $rated = collect($barangays)->where('risk_level', '!=', 'No Data');
        // Most common level; a tie goes to the more serious one.
        $overall = $rated->countBy('risk_level')
            ->sortBy(fn (int $count, string $level): array => [-$count, -self::LEVEL_RANK[$level]])
            ->keys()->first();
        $concern = $rated->where('main_concern', '!=', 'No major weather concern')->countBy('main_concern')->sortDesc()->keys()->first();

        return [
            'monitored' => count($barangays),
            'rated' => $rated->count(),
            'counts' => $counts,
            'overall' => $overall,
            'main_concern' => $concern ?? ($rated->isNotEmpty() ? 'No major weather concern' : null),
            'attention' => $counts['High'] + $counts['Severe'],
        ];
    }

    /**
     * Each barangay's change from the previous forecast month, plus its levels across all months.
     * The first month has no previous forecast, so it gets no trend rather than a guessed one.
     *
     * @param  list<array<string, mixed>>  $months
     */
    private function addTrends(array &$months): void
    {
        $timeline = [];
        foreach ($months as $month) {
            foreach ($month['barangays'] as $entry) {
                $timeline[$entry['barangay']][] = ['month' => $month['short_label'], 'risk_level' => $entry['risk_level']];
            }
        }

        foreach ($months as $index => &$month) {
            $previous = $index > 0 ? collect($months[$index - 1]['barangays'])->keyBy('barangay') : null;
            foreach ($month['barangays'] as &$entry) {
                $entry['timeline'] = $timeline[$entry['barangay']] ?? [];
                $before = ($previous?->get($entry['barangay']) ?? [])['risk_level'] ?? null;
                $entry['trend'] = $this->trend($before, $entry['risk_level']);
            }
            unset($entry);

            $prevSummary = $index > 0 ? $months[$index - 1]['summary'] : null;
            $month['summary']['trend'] = $prevSummary === null ? null : match (true) {
                $month['summary']['attention'] > $prevSummary['attention'] => 'increasing',
                $month['summary']['attention'] < $prevSummary['attention'] => 'decreasing',
                default => 'stable',
            };
            $month['summary']['previous_label'] = $index > 0 ? $months[$index - 1]['label'] : null;
        }
        unset($month);
    }

    private function trend(?string $before, string $now): ?string
    {
        if (! isset(self::LEVEL_RANK[$before], self::LEVEL_RANK[$now])) {
            return null;
        }

        return match (true) {
            self::LEVEL_RANK[$now] > self::LEVEL_RANK[$before] => 'increasing',
            self::LEVEL_RANK[$now] < self::LEVEL_RANK[$before] => 'decreasing',
            default => 'stable',
        };
    }
}
