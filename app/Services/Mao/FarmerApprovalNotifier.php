<?php

namespace App\Services\Mao;

use App\Models\Notification;
use App\Models\User;
use App\Support\CacheScope;
use App\Support\MaoLanguage;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;

/**
 * MAO – Naicel (2026-10-09): a new farmer sign-up that waits for approval is a notification in every MAO's bell, in the
 * MAO's language, linking to Farmers → Farmer Approvals.
 *
 * One notification per waiting farmer and MAO account (category "farmer-approval:<farmer id>"), never duplicated, and
 * removed once the sign-up is approved, rejected or otherwise no longer pending, so the bell only lists sign-ups that
 * still need a decision. sync() is safe to run at any time: right after a sign-up, after a decision, and every minute
 * with the dashboard warm-up (which also covers an account whose status IT changed in User Management).
 */
class FarmerApprovalNotifier
{
    public const CATEGORY_PREFIX = 'farmer-approval:';

    public function sync(): void
    {
        if (! SchemaCache::hasColumn('notifications', 'category')) {
            return;
        }

        $pending = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_PENDING)->orderBy('id')->get(['id', 'name', 'barangay'])->keyBy('id');
        $maos = User::query()->where('role', User::ROLE_MAO)->get();
        $existing = Notification::query()->where('category', 'like', self::CATEGORY_PREFIX.'%')->orderBy('id')->get();

        $kept = [];
        $touched = [];
        foreach ($existing as $note) {
            $farmerId = (int) substr((string) $note->category, strlen(self::CATEGORY_PREFIX));
            $key = $note->user_id.':'.$farmerId;
            if (! $pending->has($farmerId) || isset($kept[$key])) {
                $note->delete();                                        // decided since, or a duplicate
                $touched[$note->user_id] = true;

                continue;
            }
            $kept[$key] = true;
        }

        foreach ($maos as $mao) {
            $locale = MaoLanguage::of($mao);
            foreach ($pending as $farmer) {
                if (isset($kept[$mao->id.':'.$farmer->id])) {
                    continue;
                }
                Notification::query()->create([
                    'user_id' => $mao->id,
                    'title' => trans('mao.approval.title', ['name' => $farmer->name], $locale),
                    'message' => trans($farmer->barangay ? 'mao.approval.message' : 'mao.approval.message_no_barangay', ['name' => $farmer->name, 'barangay' => (string) $farmer->barangay], $locale),
                    'type' => 'Announcement',
                    'category' => self::CATEGORY_PREFIX.$farmer->id,
                    'is_read' => false,
                ]);
                $touched[$mao->id] = true;
            }
        }

        if ($touched !== []) {
            CacheScope::changed(Notification::class);
            foreach (array_keys($touched) as $userId) {
                Cache::forget('sidebar:unread-notifications:'.$userId);
            }
        }
    }

    /**
     * Where a sign-up notification leads; null for any other notification.
     *
     * @return ?array{url: string, label: string}
     */
    public static function linkFor(?string $category, ?User $user = null): ?array
    {
        return is_string($category) && str_starts_with($category, self::CATEGORY_PREFIX)
            ? ['url' => route('mao.farmer-approvals.index'), 'label' => trans('mao.approval.open', [], MaoLanguage::of($user))]
            : null;
    }
}
