<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A small invalidation signal, not a dashboard data dump. Clients fetch their own authorized dashboard payload.
 */
class DashboardChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    // The areas each role's pages show. Since 2026-11-11 also the areas of the remaining list and admin pages
    // (barangays … system-health); the dashboards ignore those list-only areas (resources/js/dashboard-realtime.js).
    private const FARMER_AREAS = ['advisories', 'risk', 'weather', 'predictions', 'community-posts', 'rice', 'climate', 'typhoon',
        'community-activity', 'barangays', 'historical-rice', 'yield', 'weather-analysis'];

    private const MAO_AREAS = ['users', 'farmers', 'planting', 'climate', 'rice', 'advisories', 'advisory-feedback', 'field-reports', 'reports', 'risk', 'weather', 'predictions', 'typhoon',
        'community-posts', 'community-activity', 'notification-reads', 'barangays', 'planner', 'mao-activity', 'varieties', 'historical-rice', 'yield', 'weather-analysis', 'yield-requests', 'model-feedback'];

    private const ROLE_CHANNELS = [
        User::ROLE_FARMER => 'farmer',
        User::ROLE_MAO => 'mao',
    ];

    public function __construct(
        public array $recipientIds,
        public array $areas,
        public string $changeId,
        public array $roles = [],
    ) {}

    /** Called by CacheScope so model and bulk-write invalidations use the same after-commit signal. */
    public static function dispatchFor(array $areas, iterable $ownerIds = []): void
    {
        $areas = array_values(array_unique(array_map('strval', $areas)));
        $recipients = collect($ownerIds)->map(fn ($id): int => (int) $id)->filter()->values();

        $roles = [];
        if (array_intersect(self::FARMER_AREAS, $areas) !== []) {
            $roles[] = User::ROLE_FARMER;
        }
        if (array_intersect(self::MAO_AREAS, $areas) !== []) {
            $roles[] = User::ROLE_MAO;
        }

        $recipients = $recipients->unique()->values()->all();
        if ($recipients === [] && $roles === []) {
            return;
        }

        self::dispatch($recipients, $areas, (string) Str::uuid(), array_values(array_unique($roles)));
    }

    public function broadcastOn(): array
    {
        return array_merge(
            array_map(fn (int $id): PrivateChannel => new PrivateChannel("user.{$id}"), $this->recipientIds),
            array_map(fn (string $role): PrivateChannel => new PrivateChannel('dashboard.'.self::ROLE_CHANNELS[$role]), array_values(array_intersect(array_map(User::canonicalRole(...), $this->roles), array_keys(self::ROLE_CHANNELS)))),
        );
    }

    public function broadcastAs(): string
    {
        return 'dashboard.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'change_id' => $this->changeId,
            'areas' => $this->areas,
        ];
    }
}
