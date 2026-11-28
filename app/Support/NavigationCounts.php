<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The navigation shell shows counts from two tables. Read them together once per request so a page does not spend
 * separate database round trips on the notification bell and the MAO/IT pending-account badge.
 */
final class NavigationCounts
{
    /** @return array{unread: int, pending: int, pending_farms: int} */
    public static function for(User $user): array
    {
        $request = app()->bound('request') ? request() : null;
        $key = 'navigation-counts.'.$user->getKey();
        if ($request?->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $unread = SampleData::without(Notification::query(), 'notifications')
            ->where('user_id', $user->getKey())
            ->where('is_read', false)
            ->selectRaw('COUNT(*)');

        $query = DB::query()->selectSub($unread, 'unread');
        $hasPendingFarms = $user->role === User::ROLE_MAO
            && SchemaCache::hasTable('farms')
            && SchemaCache::hasColumn('farms', 'verification_status');
        if ($user->role === User::ROLE_MAO) {
            $pending = User::query()->where('status', User::STATUS_PENDING)->selectRaw('COUNT(*)');
            $query->selectSub($pending, 'pending');
        }
        if ($hasPendingFarms) {
            $scopedBarangays = MaoScope::barangays($user);
            $pendingFarmsQuery = Farm::query()
                ->active()
                ->where('verification_status', Farm::VERIFICATION_PENDING)
                ->when($scopedBarangays !== null, fn ($q) => $q->whereIn('barangay', $scopedBarangays))
                ->selectRaw('COUNT(*)');
            $query->selectSub($pendingFarmsQuery, 'pending_farms');
        }

        $hasUnreadAdvisories = $user->role === User::ROLE_FARMER
            && SchemaCache::hasColumn('notifications', 'planting_advisory_id');
        if ($hasUnreadAdvisories) {
            $unreadAdvisories = SampleData::without(Notification::query(), 'notifications')
                ->where('user_id', $user->getKey())
                ->where('is_read', false)
                ->whereNotNull('planting_advisory_id')
                ->selectRaw('COUNT(*)');
            $query->selectSub($unreadAdvisories, 'unread_advisories');
        }

        $row = $query->first();

        $counts = [
            'unread' => (int) ($row?->unread ?? 0),
            'unread_advisories' => (int) ($row?->unread_advisories ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'pending_farms' => (int) ($row?->pending_farms ?? 0),
        ];
        $request?->attributes->set($key, $counts);

        return $counts;
    }
}
