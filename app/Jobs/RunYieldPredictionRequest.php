<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Prediction\PredictionReadMode;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\SeasonYield;
use App\Support\InteractiveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 5: runs one requested rice-yield prediction (the Python model) and stores the
 * result on the request. Like PrepareFarmerYieldEstimate: from a page it runs right after the response (so no page waits
 * for the model) and only reads prepared weather forecasts; elsewhere it goes to the analytics queue.
 */
class RunYieldPredictionRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $requestId) {}

    public static function start(YieldPredictionRequest $request): void
    {
        $id = $request->getKey();
        if (InteractiveRequest::isPageRequest()) {
            $pending = true;
            app()->terminating(static function () use ($id, &$pending): void {
                if (! $pending) {
                    return;
                }
                $pending = false;
                $readOnly = app(PredictionReadMode::class);
                $readOnly->enter();
                try {
                    rescue(fn () => self::dispatchSync($id));
                } finally {
                    $readOnly->leave();
                }
            });

            return;
        }

        self::dispatch($id)
            ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
            ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
            ->afterCommit();
    }

    public function handle(RiceYieldPredictionService $model, SeasonYield $seasonYield): void
    {
        $request = YieldPredictionRequest::query()->find($this->requestId);
        if ($request === null || $request->status !== YieldPredictionRequest::QUEUED) {
            return;
        }

        try {
            $user = $request->scope === YieldPredictionRequest::SCOPE_FARM ? User::query()->with('farmerProfile')->find($request->user_id) : null;
            $target = CarbonImmutable::parse($request->target_month);
            $area = $request->area_hectares !== null ? (float) $request->area_hectares : null;
            $result = $model->predictForUser($target, $request->farm_type, $area, $user);
            $yield = $result['yield_tons_per_hectare'] ?? $result['predicted_yield'] ?? null;

            if (! ($result['success'] ?? false) || ! is_numeric($yield)) {
                $missing = $result['missing_inputs'] ?? [];
                $this->markFailed($request, $missing !== []
                    ? 'Not enough data to predict yet (missing: '.implode(', ', $missing).').'
                    : 'The rice-yield model did not give a result. Please try again later.');

                return;
            }

            $described = $seasonYield->describe((float) $yield, (int) $target->month);
            $request->forceFill([
                'status' => YieldPredictionRequest::DONE,
                'predicted_yield' => round((float) $yield, 2),
                'total_tons' => $area !== null ? round((float) $yield * $area, 2) : null,
                'historical_average' => $described['usual'],
                'impact_level' => match ($described['compare']) {
                    'above' => 'Favorable',
                    'below' => 'At Risk',
                    'typical' => 'Normal',
                    default => null,
                },
                'details' => array_filter([
                    'uncertainty' => $result['uncertainty'] ?? null,
                    'confidence' => $result['confidence'] ?? $result['prediction_confidence'] ?? null,
                    'source' => $result['source_name'] ?? null,
                ], fn ($v) => $v !== null),
                'error' => null,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Requested yield prediction failed.', ['request_id' => $request->getKey(), 'message' => $e->getMessage()]);
            $this->markFailed($request, 'The rice-yield model could not be reached. Please try again later.');
        }
    }

    private function markFailed(YieldPredictionRequest $request, string $reason): void
    {
        $request->forceFill(['status' => YieldPredictionRequest::FAILED, 'error' => $reason, 'completed_at' => now()])->save();
    }
}
