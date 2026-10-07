<?php

namespace App\Services\Prediction;

use App\Jobs\PrepareMunicipalYieldForecast;
use App\Models\FarmerProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\InteractiveRequest;
use App\Services\Prediction\PredictionReadMode;
use Throwable;

/**
 * The municipality-wide expected-yield forecast shown for All of Lian.
 *
 * This deliberately does not read farmer dashboard estimates. It runs the
 * same deployed rice-yield model with municipality-level inputs (including
 * the recorded average area used by PredictionEngine when no farmer is
 * supplied), then stores the successful result for the page and warm command.
 */
final class MunicipalYieldForecastService
{
    private const CACHE_PREFIX = 'predictions:municipal-yield:';
    private const FAILURE_PREFIX = 'predictions:municipal-yield-failure:';
    private const LOCK_PREFIX = 'predictions:municipal-yield-lock:';
    private const FRESH_HOURS = 24;

    public function __construct(
        private readonly RiceYieldPredictionService $riceYield,
        private readonly SeasonYield $seasonYield,
    ) {}

    /** @return array<string, mixed> */
    public function current(CarbonImmutable $now): array
    {
        $target = $now->startOfMonth();
        $key = self::cacheKey($target);
        $base = ['season' => $this->seasonYield->seasonLabel($now), 'source' => 'municipal_model'];
        $cached = Cache::get($key);

        if (is_array($cached) && is_numeric($cached['yield'] ?? null)) {
            $ranAt = isset($cached['ran_at']) ? CarbonImmutable::parse((string) $cached['ran_at']) : null;
            $stale = $ranAt === null || $ranAt->lt($now->subHours(self::FRESH_HOURS));
            if ($stale && ! is_array(Cache::get(self::failureKey($target)))) {
                $this->queueFor($target);
            }

            return $base + $cached + ['stale' => $stale];
        }

        if (is_array(Cache::get(self::failureKey($target)))) {
            return $base + [
                'state' => 'error',
                'reason' => __('farmer.predictions.yield_unavailable'),
            ];
        }

        $this->queueFor($target);

        return $base + [
            'state' => 'loading',
            'reason' => __('farmer.predictions.yield_model_preparing'),
        ];
    }

    /** Run the shared production model and save only a successful result. */
    public function prepare(CarbonImmutable $target): array
    {
        $target = $target->startOfMonth();
        $key = self::cacheKey($target);
        $startedAt = microtime(true);
        $outcome = 'error';

        try {
            $prediction = $this->riceYield->predictForUser(
                $target,
                FarmerProfile::FARM_TYPE_RAINFED,
                $this->riceYield->municipalityArea(),
                null,
                [],
            );

            if (($prediction['success'] ?? false) !== true || ! is_numeric($prediction['yield_tons_per_hectare'] ?? null)) {
                $this->recordFailure($target, $prediction['api_error'] ?? null, $prediction['missing_inputs'] ?? []);

                return ['state' => 'error'];
            }

            $ranAt = CarbonImmutable::now('Asia/Manila');
            $result = [
                'state' => 'ok',
                'yield' => round((float) $prediction['yield_tons_per_hectare'], 2),
                'usual' => $this->seasonYield->usual($target->month),
                'ran_at' => $ranAt->toIso8601String(),
                'model' => $prediction['model'] ?? 'Random Forest',
                'model_source' => $prediction['model_source'] ?? null,
                'model_version' => $prediction['model_version'] ?? null,
                'area_hectares' => $prediction['farm_area_hectares'] ?? null,
                'input_sources' => $prediction['input_sources'] ?? [],
            ];
            $result += $this->comparison($result['yield'], $result['usual'], $target->month);
            Cache::forever($key, $result);
            Cache::forget(self::failureKey($target));
            $outcome = 'ready';

            return $result;
        } catch (Throwable $exception) {
            $this->recordFailure($target, $exception->getMessage(), []);

            return ['state' => 'error'];
        } finally {
            Log::info('Municipal rice-yield forecast timing.', [
                'target_month' => $target->format('Y-m'),
                'outcome' => $outcome,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ]);
            Cache::forget(self::lockKey($target));
        }
    }

    public function queueFor(CarbonImmutable $target): void
    {
        $target = $target->startOfMonth();
        $lock = self::lockKey($target);
        if (! Cache::add($lock, true, now()->addMinutes(10))) {
            return;
        }

        if (InteractiveRequest::isPageRequest()) {
            $pending = true;
            app()->terminating(function () use ($target, $lock, &$pending): void {
                if (! $pending) {
                    return;
                }
                $pending = false;
                $readOnly = app(PredictionReadMode::class);
                $readOnly->enter();
                try {
                    rescue(fn () => $this->prepare($target), fn () => null);
                } finally {
                    $readOnly->leave();
                    Cache::forget($lock);
                }
            });

            return;
        }

        try {
            PrepareMunicipalYieldForecast::dispatch($target->toDateString())
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'));
        } catch (Throwable $exception) {
            Cache::forget($lock);
            throw $exception;
        }
    }

    /** Read-only response for the lightweight card endpoint. Never queues or runs inference. */
    public function status(CarbonImmutable $now): array
    {
        $target = $now->startOfMonth();
        $cached = Cache::get(self::cacheKey($target));
        if (is_array($cached) && is_numeric($cached['yield'] ?? null)) {
            $ranAt = isset($cached['ran_at']) ? CarbonImmutable::parse((string) $cached['ran_at']) : null;
            $stale = $ranAt === null || $ranAt->lt($now->subHours(self::FRESH_HOURS));
            $refreshFailed = is_array(Cache::get(self::failureKey($target)));

            return [
                'status' => 'ready',
                'yield' => (float) $cached['yield'],
                'unit' => 't/ha',
                'generated_at' => $cached['ran_at'] ?? null,
                'stale' => $stale,
                'refreshing' => $stale && ! $refreshFailed,
            ];
        }

        if (is_array(Cache::get(self::failureKey($target)))) {
            return ['status' => 'unavailable', 'message' => __('farmer.predictions.yield_unavailable')];
        }

        return ['status' => 'preparing', 'message' => __('farmer.predictions.yield_model_preparing')];
    }

    /** @return array<string, mixed>|null */
    public function cached(CarbonImmutable $target): ?array
    {
        $value = Cache::get(self::cacheKey($target->startOfMonth()));

        return is_array($value) ? $value : null;
    }

    public static function invalidateMonth(int $year, int $month): void
    {
        $target = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Manila');
        Cache::forget(self::cacheKey($target));
        Cache::forget(self::failureKey($target));
    }

    public static function invalidateCurrent(): void
    {
        $now = CarbonImmutable::now('Asia/Manila');
        self::invalidateMonth($now->year, $now->month);
    }

    private static function cacheKey(CarbonImmutable $target): string
    {
        return self::CACHE_PREFIX.$target->format('Y-m');
    }

    private static function failureKey(CarbonImmutable $target): string
    {
        return self::FAILURE_PREFIX.$target->format('Y-m');
    }

    private static function lockKey(CarbonImmutable $target): string
    {
        return self::LOCK_PREFIX.$target->format('Y-m');
    }

    /** @return array{compare: ?string, compare_label: ?string} */
    private function comparison(float $yield, ?float $usual, int $month): array
    {
        if ($usual === null || $usual <= 0) {
            return ['compare' => null, 'compare_label' => null];
        }

        $described = $this->seasonYield->describe($yield, $month);

        return [
            'compare' => $described['compare'],
            'compare_label' => $described['compare_label'],
        ];
    }

    private function recordFailure(CarbonImmutable $target, mixed $error, array $missing): void
    {
        Log::warning('Municipal rice-yield forecast was not produced.', [
            'target_month' => $target->format('Y-m'),
            'reason' => $error,
            'missing_inputs' => $missing,
        ]);
        // A failure is short-lived so a later data/model recovery is retried.
        Cache::put(self::failureKey($target), ['at' => now()->toIso8601String()], now()->addMinutes(30));
    }
}
