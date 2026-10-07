<?php

namespace App\Jobs;

use App\Models\Farm;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\PredictionReadMode;
use App\Support\InteractiveRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Runs one farmer's rice-yield estimate (the Python model) in the background and stores it for the dashboard.
 * Started when a farmer's profile or planting schedule changes, or when the dashboard finds the estimate missing or
 * due, so no page waits for the model. The 3-hourly iclimate:warm-farmer-yields keeps every farmer's estimate current.
 *
 * From a web request it runs in that same process right after the response is sent, so the estimate is ready a moment
 * later even when no queue worker is running; elsewhere (commands, jobs) it goes to the queue.
 */
class PrepareFarmerYieldEstimate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /** One estimate per farmer waits in the queue at a time (held until it has run, at most 10 minutes). */
    public int $uniqueFor = 600;

    /** @param  ?int  $farmId  the farm the estimate is for (null: the farmer's single estimate, before farms existed) */
    public function __construct(public int $userId, public ?int $farmId = null) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.($this->farmId ?? 'farmer');
    }

    /**
     * Starts the estimate unless one is already waiting or running for this farmer (however many pages ask). The farmer's
     * own request runs it right after its response, so their card fills a moment later without a queue worker; a
     * change made by someone else (MAO edits, imports) goes to the queue, so one request never runs many estimates.
     */
    public static function queueFor(int $userId, ?int $farmId = null): void
    {
        if (! Cache::add(self::lockKey($userId, $farmId), true, now()->addMinutes(10))) {
            return;
        }

        if (InteractiveRequest::isPageRequest() && (int) auth()->id() === $userId) {
            $pending = true;
            app()->terminating(static function () use ($userId, $farmId, &$pending): void {
                // Once only: an application that serves several requests calls every terminating callback again.
                if (! $pending) {
                    return;
                }
                $pending = false;
                // Read prepared forecasts only: a page visit never trains the monthly weather model (the scheduled
                // warm-up does); with none prepared yet the run is a short "try again soon", never a training run.
                $readOnly = app(PredictionReadMode::class);
                $readOnly->enter();
                try {
                    // A run that could not start does not hold the lock for its full 10 minutes (handle() releases it otherwise).
                    rescue(fn () => self::dispatchSync($userId, $farmId), fn () => Cache::forget(self::lockKey($userId, $farmId)));
                } finally {
                    $readOnly->leave();
                }
            });

            return;
        }

        self::dispatch($userId, $farmId)
            ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
            ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
            ->afterCommit();
    }

    public static function lockKey(int $userId, ?int $farmId = null): string
    {
        return 'dashboard:farmer-prediction:queued:'.$userId.($farmId ? ':farm:'.$farmId : '');
    }

    public function handle(FarmerDashboardService $dashboard): void
    {
        try {
            $user = User::query()->with('farmerProfile')->find($this->userId);
            // Only the farmer's own farm still in use; an archived or someone else's farm is not estimated.
            $farm = $this->farmId !== null ? Farm::query()->ownedBy($this->userId)->active()->find($this->farmId) : null;
            if ($user !== null && $user->role === User::ROLE_FARMER && ($this->farmId === null || $farm !== null)) {
                $dashboard->prepareYieldEstimate($user, $farm);
            }
        } finally {
            Cache::forget(self::lockKey($this->userId, $this->farmId));
        }
    }
}
