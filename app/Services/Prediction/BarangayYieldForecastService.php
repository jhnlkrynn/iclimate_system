<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Models\Farm;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\LianFarmTypes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Expected rice yield (t/ha) for each official Lian barangay this season, from the trained rice-yield model, for the
 * Heat Map's Expected yield layer:
 *  - a barangay with farm estimates: the area-weighted average of its farms' saved estimates (the same numbers the
 *    farmers see on their dashboards), sum(yield x area) / sum(area);
 *  - a barangay with no farms: one model run built exactly like a farm's (RiceYieldPredictionService::predictForUser:
 *    the monthly weather model's forecast for this month, this season's climate history), with the barangay's usual
 *    farm type (MAO planting records) and the median size of Lian's registered farms;
 *  - neither: no estimate. Nothing is made up.
 *
 * prepare() (iclimate:warm-farmer-yields, after the farms) works them out and saves them (YieldPredictionStore);
 * all() only reads what is saved, so no page waits for the model and a cache clear loses nothing.
 *
 * Periods: season is the farmer dashboard's estimate. week / 2weeks (the Heat Map's This Week / 2 Weeks) are the same
 * runs with this month's rainfall and mean temperature built from the next 7 / 14 days of the barangay's saved
 * forecast plus the monthly weather model's share for the rest of the month (periodWeather); every other input is the
 * farm path's own. preparePeriod() runs each farm and barangay for them.
 */
final class BarangayYieldForecastService
{
    public function __construct(
        private readonly RiceYieldPredictionService $riceYield,
        private readonly YieldPredictionStore $store,
        private readonly SeasonYield $seasonYield,
        private readonly PredictionOrchestrator $orchestrator,
        private readonly LianBarangayWeatherService $weather,
    ) {}

    public const PERIOD_DAYS = ['week' => 7, '2weeks' => 14];

    /**
     * This season's saved estimate per barangay for the period (empty values when none), with the season estimate
     * beside it ('season_yield') so a week can be compared with the season.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(string $period = 'season'): array
    {
        $period = in_array($period, YieldPredictionStore::PERIODS, true) ? $period : 'season';
        $saved = rescue(fn (): Collection => $this->store->barangays($period), collect(), false);
        $season = $period === 'season' ? $saved : rescue(fn (): Collection => $this->store->barangays(), collect(), false);
        $modelSource = rescue(fn (): string => $this->riceYield->modelSource(), 'configured model', false);

        $results = [];
        foreach (LianBarangays::all() as $barangay) {
            /** @var YieldPrediction|null $row */
            $row = $saved->get($barangay);
            $results[$barangay] = [
                'barangay' => $barangay,
                'predicted_yield' => $row?->predicted_yield,
                'classification' => $row?->classification ?? 'nodata',
                'basis' => $row?->basis,
                'farms_used' => $row?->farms_used ?? 0,
                'area_hectares' => $row?->area_hectares,
                'period' => $period,
                'season_yield' => $season->get($barangay)?->predicted_yield,
                'season' => $row?->season,
                'season_label' => $row?->season_label ?: $this->seasonLabel(),
                'target_month' => $row?->target_month,
                'model' => 'Random Forest',
                'model_source' => $modelSource,
                'model_version' => $row?->model_version,
                'model_input' => $row?->model_input,
                'prediction_timestamp' => $row?->generated_at?->timezone('Asia/Manila')->toIso8601String(),
                'missing_inputs' => [],
                'error' => $row === null ? 'No estimate yet.' : null,
            ];
        }

        return $results;
    }

    /**
     * Works out and saves every barangay's estimate (commands only: the barangay runs call the model). With
     * $runModel false (the model service is down) only the farm averages are saved; the others keep their last run.
     *
     * @return array<string, array<string, mixed>> the saved estimates, as all() reads them
     */
    public function prepare(bool $runModel = true): array
    {
        if (! $this->store->available()) {
            Log::warning('Barangay yield estimates were not saved: the yield_predictions table does not exist yet.');

            return $this->all();
        }

        $this->saveBarangays('season', $runModel, null);

        return $this->all();
    }

    /**
     * The This Week / 2 Weeks estimates (commands only): every farm with a size and an official barangay, then every
     * barangay (farm average first, else its own run), each with that window's weather. Farms whose inputs are the
     * same (barangay, farm type, area) share one model call: the model gives the same answer for the same inputs.
     *
     * @return array{estimates: array<string, array<string, mixed>>, farm_runs: int, model_calls: int, weather: array<string, array<string, mixed>|null>}
     */
    public function preparePeriod(string $period): array
    {
        $days = self::PERIOD_DAYS[$period] ?? null;
        if ($days === null || ! $this->store->available($period)) {
            Log::warning('Barangay '.$period.' yield estimates were not saved: yield_predictions has no period column yet.');

            return ['estimates' => $this->all($period), 'farm_runs' => 0, 'model_calls' => 0, 'weather' => []];
        }

        $now = BusinessClock::now()->toImmutable();
        $monthly = (array) (rescue(fn (): array => $this->orchestrator->monthlyWeatherForecast($now->startOfMonth()), [], false)['predictions'] ?? []);
        $forecasts = rescue(fn (): array => $this->weather->savedForecasts(), [], false);
        $weather = [];
        foreach (LianBarangays::all() as $barangay) {
            $weather[$barangay] = $this->periodWeather($forecasts[$barangay] ?? [], $monthly, $days, $now);
        }

        $label = $this->seasonLabel();
        $memo = [];
        $runs = 0;
        $calls = 0;
        if (ActiveFarm::available()) {
            Farm::query()->active()
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE))
                ->with('user.farmerProfile')->orderBy('id')
                ->each(function (Farm $farm) use ($weather, $now, $label, $period, &$memo, &$runs, &$calls): void {
                    $barangay = LianBarangays::canonicalName($farm->barangay);
                    $area = $farm->modelArea();
                    if ($barangay === null || $area === null || $weather[$barangay] === null) {
                        return;
                    }
                    $farmType = $farm->farm_type ?: ($farm->user?->farmerProfile?->farm_type ?: \App\Models\FarmerProfile::FARM_TYPE_RAINFED);
                    $key = $barangay.'|'.$farmType.'|'.$area;
                    if (! array_key_exists($key, $memo)) {
                        $calls++;
                        $memo[$key] = $this->run($now, $farmType, $area, $farm->user, $weather[$barangay]['overrides']);
                    }
                    if ($memo[$key] !== null) {
                        $this->store->saveFarm($farm, $memo[$key] + ['ran_at' => CarbonImmutable::now('Asia/Manila')->toIso8601String()], $label, $period);
                        $runs++;
                    }
                });
        }

        $calls += $this->saveBarangays($period, true, $weather);

        return ['estimates' => $this->all($period), 'farm_runs' => $runs, 'model_calls' => $calls, 'weather' => $weather];
    }

    /**
     * This month's rainfall and mean temperature as the model reads them, built from the next $days days of the
     * barangay's saved forecast (only the days in this month) plus the monthly weather model's figure for the days
     * the forecast does not cover: rain = forecast sum + monthly x (uncovered / days in month); temperature the same
     * day-weighted mean. Null when the forecast or the monthly figure is missing (no estimate rather than a guess).
     *
     * @param  array<string, mixed>  $forecast
     * @param  array<string, mixed>  $monthly
     * @return array{overrides: array{rainfall: float, temp_avg: float}, forecast_days: int, forecast_rainfall: float}|null
     */
    public function periodWeather(array $forecast, array $monthly, int $days, CarbonImmutable $today): ?array
    {
        if (! is_numeric($monthly['rainfall'] ?? null) || ! is_numeric($monthly['temperature'] ?? null)) {
            return null;
        }
        $from = $today->startOfDay();
        $until = $from->addDays($days - 1)->min($from->endOfMonth());
        $window = collect((array) ($forecast['daily'] ?? []))
            ->filter(fn ($day): bool => is_array($day) && is_string($day['date'] ?? null) && $day['date'] >= $from->toDateString() && $day['date'] <= $until->toDateString());
        $rainDays = $window->filter(fn (array $day): bool => is_numeric($day['rainfall'] ?? $day['precipitation_mm'] ?? null));
        if ($rainDays->isEmpty()) {
            return null;
        }
        $tempDays = $window->filter(fn (array $day): bool => is_numeric($day['temperature_min'] ?? null) && is_numeric($day['temperature_max'] ?? null));
        $month = $from->daysInMonth;
        $rain = (float) $rainDays->sum(fn (array $day): float => (float) ($day['rainfall'] ?? $day['precipitation_mm']));
        $tempSum = (float) $tempDays->sum(fn (array $day): float => ((float) $day['temperature_min'] + (float) $day['temperature_max']) / 2);

        return [
            'overrides' => [
                'rainfall' => round($rain + (float) $monthly['rainfall'] * ($month - $rainDays->count()) / $month, 2),
                'temp_avg' => round(($tempSum + (float) $monthly['temperature'] * ($month - $tempDays->count())) / $month, 2),
            ],
            'forecast_days' => $rainDays->count(),
            'forecast_rainfall' => round($rain, 2),
        ];
    }

    /**
     * Saves every barangay's estimate for the period: its farms' area-weighted average, else (with $runModel) its own
     * run, with the period's weather when $weather is given. Returns how many model calls it made.
     *
     * @param  array<string, array<string, mixed>|null>|null  $weather
     */
    private function saveBarangays(string $period, bool $runModel, ?array $weather): int
    {
        $label = $this->seasonLabel();
        $farms = $this->store->farms($period)->filter(fn (YieldPrediction $row): bool => $row->barangay !== null && $row->area_hectares > 0);
        $byBarangay = $farms->groupBy('barangay');
        // A typical Lian farm for barangays without farms: the median size of the season's farm estimates.
        $typicalArea = $this->median($this->store->farms()->filter(fn (YieldPrediction $row): bool => $row->barangay !== null)->pluck('area_hectares'));
        $calls = 0;

        foreach (LianBarangays::all() as $barangay) {
            $rows = $byBarangay->get($barangay, collect());
            if ($rows->isNotEmpty()) {
                $area = (float) $rows->sum('area_hectares');
                $this->store->saveBarangay($barangay, [
                    'predicted_yield' => round($rows->sum(fn (YieldPrediction $r): float => $r->predicted_yield * $r->area_hectares) / $area, 2),
                    'basis' => 'farm_average',
                    'farms_used' => $rows->count(),
                    'area_hectares' => round($area, 4),
                    'season_label' => $label,
                    'target_month' => $rows->pluck('target_month')->filter()->max(),
                    'model_version' => $rows->pluck('model_version')->filter()->first(),
                    'model_input' => null,
                    'generated_at' => $rows->pluck('generated_at')->filter()->max(),
                ], $period);

                continue;
            }

            $overrides = $weather === null ? [] : ($weather[$barangay]['overrides'] ?? null);
            if ($runModel && $overrides !== null) {
                $calls++;
                $this->runForBarangay($barangay, $typicalArea, $label, $period, $overrides);
            }
        }

        return $calls;
    }

    /**
     * Active farms whose barangay is not one of the 19 (blank, or a name the shared normalizer cannot place): they
     * are left out of every barangay's average, and listed so someone can correct them.
     *
     * @return Collection<int, Farm>
     */
    public function unmatchedFarms(): Collection
    {
        if (! ActiveFarm::available()) {
            return collect();
        }

        return Farm::query()->active()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE))
            ->orderBy('id')
            ->get(['id', 'user_id', 'farm_name', 'barangay'])
            ->filter(fn (Farm $farm): bool => LianBarangays::canonicalName($farm->barangay) === null)
            ->values();
    }

    /**
     * One run through the farm path's own input builder (RiceYieldPredictionService::predictForUser), with $overrides
     * replacing those inputs; null when the model gave no usable estimate (logged).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>|null
     */
    private function run(CarbonImmutable $now, string $farmType, ?float $area, ?User $user, array $overrides): ?array
    {
        try {
            $result = $this->riceYield->predictForUser($now, $farmType, $area, $user, $overrides, PredictionType::RICE_YIELD->value);
        } catch (Throwable $exception) {
            Log::warning('Yield estimate failed.', ['farm_type' => $farmType, 'area' => $area, 'exception' => $exception::class, 'message' => $exception->getMessage()]);

            return null;
        }
        $yield = $result['yield_tons_per_hectare'] ?? $result['predicted_yield'] ?? null;
        if (! ($result['success'] ?? false) || ! is_numeric($yield)) {
            Log::warning('Yield estimate was not produced.', ['farm_type' => $farmType, 'area' => $area, 'reason' => $result['api_error'] ?? null, 'missing_inputs' => $result['missing_inputs'] ?? []]);

            return null;
        }

        return $result;
    }

    /**
     * One model run for a barangay without farms, through the farm path's own input builder.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function runForBarangay(string $barangay, ?float $area, string $label, string $period = 'season', array $overrides = []): void
    {
        $result = $this->run(BusinessClock::now()->toImmutable(), $this->usualFarmType($barangay), $area, null, $overrides);
        if ($result === null) {
            return;
        }
        $yield = $result['yield_tons_per_hectare'] ?? $result['predicted_yield'];

        $this->store->saveBarangay($barangay, [
            'predicted_yield' => round((float) $yield, 2),
            'basis' => 'barangay_model',
            'farms_used' => 0,
            'area_hectares' => $result['model_input']['area'] ?? $area,
            'season_label' => $label,
            'target_month' => $result['target_month'] ?? null,
            'model_version' => $result['model_version'] ?? null,
            'model_input' => is_array($result['model_input'] ?? null) ? $result['model_input'] : null,
            'generated_at' => CarbonImmutable::now('Asia/Manila'),
        ], $period);
    }

    /** The barangay's main farm type in the MAO planting records (by area); Rainfed, the farm path's default, without records. */
    private function usualFarmType(string $barangay): string
    {
        $summary = rescue(fn (): ?array => LianFarmTypes::summaryFor($barangay), null, false);
        if (! is_array($summary)) {
            return \App\Models\FarmerProfile::FARM_TYPE_RAINFED;
        }

        return (float) ($summary['irrigated_area_hectares'] ?? 0) > (float) ($summary['rainfed_area_hectares'] ?? 0)
            ? \App\Models\FarmerProfile::FARM_TYPE_IRRIGATED
            : \App\Models\FarmerProfile::FARM_TYPE_RAINFED;
    }

    /** @param  Collection<int, mixed>  $values */
    private function median(Collection $values): ?float
    {
        $sorted = $values->filter(fn ($v): bool => is_numeric($v) && $v > 0)->map(fn ($v): float => (float) $v)->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return null;   // the engine falls back to Lian's average planted area from the production records
        }
        $middle = intdiv($count, 2);

        return round($count % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
    }

    private function seasonLabel(): string
    {
        return rescue(fn (): string => $this->seasonYield->seasonLabel(CarbonImmutable::now('Asia/Manila')), '', false);
    }
}
