<?php

namespace App\Services\Prediction;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\AnalyticsRun;
use App\Models\MonthlyWeatherPrediction;
use App\Models\RiceYieldModelComparison;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Single, centralized entry point for every module that needs a monthly
 * weather forecast or a rice-yield forecast — the barangay risk map, the
 * weather-predictions page, the shadow candidate comparison, dashboards,
 * reports, advisories, etc. should all read through here instead of each
 * independently calling MonthlyWeatherRandomForest::predict() (a full
 * Random Forest retrain) or querying AnalyticsRun/RiceYieldModelComparison
 * directly.
 *
 * Flow this class implements:
 *   Historical data -> MonthlyWeatherRandomForest -> persisted row
 *   (monthly_weather_predictions) -> short-lived cache -> callers read.
 *
 * A cache miss with no fresh persisted row falls back to computing inline
 * (never invents/zero-fills a value) so existing synchronous callers keep
 * working exactly as before; the *scheduled* path (see
 * app/Console/Commands/RefreshIclimatePredictions.php) is what keeps rows
 * pre-generated so that inline fallback is rarely actually hit by a real
 * request.
 */
class PredictionOrchestrator
{
    private const CACHE_PREFIX = 'iclimate:weather:monthly';

    private const DISPATCH_LOCK_PREFIX = 'iclimate:weather:monthly:dispatch-lock';

    public function __construct(
        private readonly MonthlyWeatherRandomForest $weatherModel,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Monthly Weather Forecast
    |--------------------------------------------------------------------------
    */

    /**
     * Read-only: returns the persisted/cached forecast for $month if one
     * already exists and is still fresh, WITHOUT ever training a model or
     * writing anything. Returns null if nothing usable is available yet.
     */
    public function cachedMonthlyWeatherForecast(CarbonImmutable $month): ?array
    {
        $month = $month->startOfMonth();
        $cacheKey = $this->cacheKey($month);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $row = $this->freshRow($month);
        if (! $row) {
            return null;
        }

        $payload = $this->payloadFromRow($row);
        Cache::put($cacheKey, $payload, $this->ttl());

        return $payload;
    }

    /**
     * Guaranteed: returns a fresh persisted forecast for $month, computing
     * and persisting it inline if (and only if) nothing fresh already
     * exists. Pass $forceRefresh=true to always recompute (used by the
     * refresh command / candidate reports / IT Expert force-refresh).
     */
    public function monthlyWeatherForecast(CarbonImmutable $month, bool $forceRefresh = false): array
    {
        $month = $month->startOfMonth();

        if (! $forceRefresh) {
            $existing = $this->cachedMonthlyWeatherForecast($month);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->generateAndPersist($month);
    }

    /**
     * Dispatches background generation for $month if (and only if) nothing
     * fresh is persisted yet and nothing is already in flight. Returns true
     * if a job was actually queued. Never blocks, never trains inline.
     */
    public function ensureMonthlyWeatherForecastQueued(CarbonImmutable $month): bool
    {
        $month = $month->startOfMonth();

        if ($this->freshRow($month)) {
            return false;
        }

        $lockKey = $this->dispatchLockKey($month);
        if (Cache::has($lockKey)) {
            return false;
        }

        // Locked before dispatch (not after) so two near-simultaneous
        // requests for the same month can never both queue a job.
        Cache::put($lockKey, true, now()->addMinutes(10));

        GenerateMonthlyWeatherForecast::dispatch($month->format('Y-m-d'))
            ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
            ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
            ->afterCommit();

        return true;
    }

    /**
     * Called by GenerateMonthlyWeatherForecast (and available for direct
     * inline use) — runs the model and upserts the persisted row + cache.
     */
    public function generateAndPersist(CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();

        $result = $this->weatherModel->predict($month);
        $row = $this->persistResult($month, $result);
        $payload = $this->payloadFromRow($row);

        Cache::put($this->cacheKey($month), $payload, $this->ttl());
        Cache::forget($this->dispatchLockKey($month));

        return $payload;
    }

    private function freshRow(CarbonImmutable $month): ?MonthlyWeatherPrediction
    {
        $row = MonthlyWeatherPrediction::query()
            ->where('prediction_year', $month->year)
            ->where('prediction_month', $month->month)
            ->where('weather_model', MonthlyWeatherRandomForest::MODEL_VERSION)
            ->where('status', MonthlyWeatherPrediction::STATUS_READY)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $row) {
            return null;
        }

        // The row is also considered stale (and regenerated) if the
        // underlying Ambulong CSV changed since it was generated — even
        // though its expires_at TTL hasn't elapsed yet. This never fires
        // for unrelated application data changes: AmbulongHistoricalClimateDataset::version()
        // only reflects the source CSV file itself.
        $storedDatasetVersion = data_get($row->metadata, 'dataset_version');
        if ($storedDatasetVersion !== null && $storedDatasetVersion !== $this->weatherModel->datasetVersion()) {
            return null;
        }

        return $row;
    }

    private function persistResult(CarbonImmutable $month, array $result): MonthlyWeatherPrediction
    {
        $ready = (bool) ($result['ready'] ?? false);
        $predictions = (array) ($result['predictions'] ?? []);
        $trainingFrom = data_get($result, 'training_period.from');
        $trainingTo = data_get($result, 'training_period.to');

        return MonthlyWeatherPrediction::query()->updateOrCreate(
            [
                'prediction_year' => $month->year,
                'prediction_month' => $month->month,
                'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
            ],
            [
                'rainfall' => $predictions['rainfall'] ?? null,
                'tmax' => $predictions['tmax'] ?? null,
                'tmin' => $predictions['tmin'] ?? null,
                'temperature_average' => $predictions['temperature'] ?? null,
                'humidity' => $predictions['humidity'] ?? null,
                'wind_speed' => $predictions['wind_speed'] ?? null,
                'wind_direction' => $predictions['wind_direction'] ?? null,
                'season' => $predictions['season'] ?? null,
                'training_from' => $trainingFrom ? $trainingFrom.'-01' : null,
                'training_to' => $trainingTo ? $trainingTo.'-01' : null,
                'generated_at' => now(),
                // Ready rows are trusted for this operational window; unready
                // rows (not enough history yet) expire almost immediately so
                // the very next request tries again rather than being stuck
                // showing "unavailable" for hours.
                'expires_at' => $ready ? now()->addMinutes($this->ttlMinutes()) : now()->addMinutes(5),
                'status' => $ready ? MonthlyWeatherPrediction::STATUS_READY : MonthlyWeatherPrediction::STATUS_UNREADY,
                'metadata' => [
                    'source_name' => $result['source_name'] ?? null,
                    'source_note' => $result['source_note'] ?? null,
                    'message' => $result['message'] ?? null,
                    'months_available' => $result['months_available'] ?? null,
                    'confidence' => $result['confidence'] ?? null,
                    // Lets freshRow() detect "the source CSV changed since
                    // this row was generated" without re-reading the file.
                    'dataset_version' => $this->weatherModel->datasetVersion(),
                ],
            ]
        );
    }

    /**
     * Reconstructs the same {ready, source_name, predictions{...},
     * confidence, ...} shape MonthlyWeatherRandomForest::predict() returns,
     * so every existing caller (MonthlyWeatherCalendarService, etc.) keeps
     * working unchanged whether the payload came from a fresh model run or
     * a persisted row.
     */
    private function payloadFromRow(MonthlyWeatherPrediction $row): array
    {
        if ($row->status !== MonthlyWeatherPrediction::STATUS_READY) {
            return [
                'ready' => false,
                'message' => data_get($row->metadata, 'message') ?? 'Not enough monthly climate history is available yet to compute this forecast.',
                'source_name' => data_get($row->metadata, 'source_name'),
                'predictions' => [],
                'confidence' => null,
                'generated_at' => $row->generated_at?->toIso8601String(),
                'weather_model' => $row->weather_model,
            ];
        }

        return [
            'ready' => true,
            'message' => data_get($row->metadata, 'message') ?? 'Prediction generated using the improved Ambulong historical monthly Random Forest model.',
            'source_type' => 'Trained Model',
            'source_name' => data_get($row->metadata, 'source_name') ?? 'iClimate Ambulong Monthly Random Forest',
            'source_note' => data_get($row->metadata, 'source_note'),
            'training_period' => [
                'from' => $row->training_from?->format('Y-m'),
                'to' => $row->training_to?->format('Y-m'),
            ],
            'months_available' => data_get($row->metadata, 'months_available'),
            'predictions' => [
                'rainfall' => $row->rainfall !== null ? (float) $row->rainfall : null,
                'tmax' => $row->tmax !== null ? (float) $row->tmax : null,
                'tmin' => $row->tmin !== null ? (float) $row->tmin : null,
                'temperature' => $row->temperature_average !== null ? (float) $row->temperature_average : null,
                'humidity' => $row->humidity !== null ? (float) $row->humidity : null,
                'wind_speed' => $row->wind_speed !== null ? (float) $row->wind_speed : null,
                'wind_direction' => $row->wind_direction !== null ? (float) $row->wind_direction : null,
                'season' => $row->season,
            ],
            'confidence' => data_get($row->metadata, 'confidence'),
            'generated_at' => $row->generated_at?->toIso8601String(),
            'weather_model' => $row->weather_model,
        ];
    }

    private function cacheKey(CarbonImmutable $month): string
    {
        // Includes the dataset version (same pattern MonthlyWeatherRandomForest's
        // own internal cache key already uses) so a changed Ambulong CSV
        // naturally produces a cache miss instead of serving a stale cached
        // payload for the rest of its TTL.
        return implode(':', [
            self::CACHE_PREFIX,
            $month->format('Y'),
            $month->format('n'),
            MonthlyWeatherRandomForest::MODEL_VERSION,
            $this->weatherModel->datasetVersion(),
        ]);
    }

    private function dispatchLockKey(CarbonImmutable $month): string
    {
        return implode(':', [
            self::DISPATCH_LOCK_PREFIX,
            $month->format('Y-m'),
            MonthlyWeatherRandomForest::MODEL_VERSION,
        ]);
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addMinutes($this->ttlMinutes());
    }

    private function ttlMinutes(): int
    {
        return (int) config('services.prediction.weather_cache_minutes', 360);
    }

    /*
    |--------------------------------------------------------------------------
    | Rice-Yield Forecasts (read-only lookups over existing systems)
    |--------------------------------------------------------------------------
    |
    | Rice-yield predictions are user/date/area-specific (they need a
    | prediction_date + farm area from the requesting farmer), so — per the
    | existing, already-working AnalyticsRun architecture — they are still
    | generated on demand via AnalyticsDispatcher/RunPythonAnalytics, not
    | pre-generated here. These two methods are read-only lookups over that
    | existing data, standardized for other modules (dashboards, reports,
    | analytics workspace) that just want "the latest known prediction for
    | this month," without duplicating a second competing prediction system.
    */

    /**
     * Latest completed production rice-yield AnalyticsRun result for a
     * given target year/month (and optionally farm type), or null if none
     * exists yet. Never dispatches a new prediction — production yield
     * predictions remain explicitly user-initiated (they require a
     * farmer-supplied date + area).
     */
    public function latestProductionYieldForecast(int $year, int $month, ?string $farmType = null): ?array
    {
        $prefix = sprintf('%04d-%02d', $year, $month);

        $run = AnalyticsRun::query()
            ->where('type', 'rice_yield_prediction')
            ->where('status', AnalyticsRun::STATUS_COMPLETED)
            ->where('input->target_date', 'like', $prefix.'%')
            ->when($farmType, fn ($query) => $query->where('input->farm_type', $farmType))
            ->latest('completed_at')
            ->first();

        if (! $run || ! is_array($run->result)) {
            return null;
        }

        $predictedYield = $run->result['predicted_yield'] ?? null;
        if (! is_numeric($predictedYield)) {
            return null;
        }

        return [
            'status' => 'ready',
            'label' => 'Production',
            'prediction_year' => $year,
            'prediction_month' => $month,
            'farm_type' => data_get($run->input, 'farm_type'),
            'predicted_yield' => (float) $predictedYield,
            'unit' => $run->result['unit'] ?? 'tons/hectare',
            'model_source' => $run->result['source'] ?? $run->result['source_name'] ?? 'Current Production Model',
            'generated_at' => $run->completed_at?->toIso8601String(),
            'analytics_run_id' => $run->id,
        ];
    }

    /**
     * Latest recorded shadow candidate comparison for a given target
     * year/month, or null if none has run yet. Testing-only — never
     * presented as the production result.
     */
    public function latestCandidateYieldForecast(int $year, int $month): ?array
    {
        $comparison = RiceYieldModelComparison::query()
            ->where('prediction_year', $year)
            ->where('prediction_month', $month)
            ->latest('id')
            ->first();

        if (! $comparison) {
            return null;
        }

        return [
            'status' => $comparison->candidate_success ? 'ready' : 'unavailable',
            'label' => 'Candidate / Testing',
            'prediction_year' => $year,
            'prediction_month' => $month,
            'predicted_yield' => $comparison->candidate_success && $comparison->candidate_prediction !== null
                ? (float) $comparison->candidate_prediction
                : null,
            'production_prediction' => (float) $comparison->production_prediction,
            'difference' => $comparison->difference !== null ? (float) $comparison->difference : null,
            'model_source' => $comparison->candidate_model,
            'candidate_error' => $comparison->candidate_error,
            'generated_at' => $comparison->created_at?->toIso8601String(),
        ];
    }
}
