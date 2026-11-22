<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A weather fetch succeeded. Carries only the real fetch time, so an open page can move its "Updated" time without
 * reading anything again. Sent for every successful fetch, including one whose values did not change (those send
 * no weather.changed, and before 2026-11-12 the farmer's "Updated" time then waited for the 5-minute poll).
 *
 * kind: "current"   the Lian reading (iclimate:refresh-weather-cache, every 3 minutes)
 *       "barangays" the barangay forecasts behind the Heat Map / farm-risk rating, only when every barangay succeeded
 *                   (otherwise the oldest reading on the map is older than this fetch and the stamp must not move)
 */
class WeatherFetched implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $kind, public string $fetchedAt) {}

    /** Never lets a broadcast problem (Reverb down) fail the fetch that just succeeded; pages then catch up by polling. */
    public static function announce(string $kind, \DateTimeInterface|string|null $fetchedAt): void
    {
        $time = $fetchedAt instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($fetchedAt) : (filled($fetchedAt) ? rescue(fn () => \Illuminate\Support\Carbon::parse($fetchedAt), null, fn (\Throwable $e): bool => \App\Support\SilentFailure::log($e, 'WeatherFetched::announce')) : null);
        if ($time === null) {
            return;
        }
        rescue(fn () => self::dispatch($kind, $time->copy()->utc()->toIso8601String()), null, fn (\Throwable $e): bool => \App\Support\SilentFailure::log($e, 'WeatherFetched::announce'));
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard.farmer'), new PrivateChannel('dashboard.mao')];
    }

    public function broadcastAs(): string
    {
        return 'weather.fetched';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'fetched_at' => $this->fetchedAt];
    }
}
