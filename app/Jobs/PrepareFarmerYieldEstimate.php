<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Runs one farmer's rice-yield estimate (the Python model) in the background and stores it for the dashboard.
 * Queued when a farmer's profile or planting schedule changes, or when the dashboard finds no stored estimate,
 * so no page waits for the model. The 3-hourly iclimate:warm-farmer-yields keeps every farmer's estimate current.
 */
class PrepareFarmerYieldEstimate implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /** $farmId: one of the farmer's other farms (2026-10); null is the primary farm, the one on the farmer's profile. */
    public function __construct(public int $userId, public ?int $farmId = null) {}

    /** Queues the estimate unless one is already waiting for this farmer (and farm). */
    public static function queueFor(int $userId, ?int $farmId = null): void
    {
        if (Cache::add(self::lockKey($userId, $farmId), true, now()->addMinutes(10))) {
            self::dispatch($userId, $farmId)
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
                ->afterCommit();
        }
    }

    public static function lockKey(int $userId, ?int $farmId = null): string
    {
        return 'dashboard:farmer-prediction:queued:'.$userId.($farmId !== null ? ':farm:'.$farmId : '');
    }

    public function handle(FarmerDashboardService $dashboard): void
    {
        try {
            $user = User::query()->with('farmerProfile')->find($this->userId);
            if ($user !== null && $user->role === User::ROLE_FARMER) {
                $dashboard->prepareYieldEstimate($user, $this->farmId);
            }
        } finally {
            Cache::forget(self::lockKey($this->userId, $this->farmId));
        }
    }
}
