<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Runs one farmer's rice-yield estimate (the Python model) in the background and stores it for the dashboard.
 * Queued when a farmer's profile or planting schedule changes, or when the dashboard finds no stored estimate,
 * so no page waits for the model. The 3-hourly iclimate:warm-farmer-yields keeps every farmer's estimate current.
 */
class PrepareFarmerYieldEstimate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /** One estimate per farmer waits in the queue at a time (held until it has run, at most an hour). */
    public int $uniqueFor = 3600;

    public function __construct(public int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    /** Queues the estimate unless one is already waiting for this farmer. */
    public static function queueFor(int $userId): void
    {
        if (Cache::add(self::lockKey($userId), true, now()->addMinutes(10))) {
            self::dispatch($userId)
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
                ->afterCommit();
        }
    }

    public static function lockKey(int $userId): string
    {
        return 'dashboard:farmer-prediction:queued:'.$userId;
    }

    public function handle(FarmerDashboardService $dashboard): void
    {
        try {
            $user = User::query()->with('farmerProfile')->find($this->userId);
            if ($user !== null && $user->role === User::ROLE_FARMER) {
                $dashboard->prepareYieldEstimate($user);
            }
        } finally {
            Cache::forget(self::lockKey($this->userId));
        }
    }
}
