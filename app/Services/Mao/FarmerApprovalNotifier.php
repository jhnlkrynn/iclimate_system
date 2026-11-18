<?php

namespace App\Services\Mao;

use App\Models\Notification;
use App\Models\User;
use App\Support\CacheScope;
use App\Support\MaoLanguage;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * MAO – Naicel (2026-10-09): a new farmer sign-up that waits for approval is a notification in every MAO's bell, in the
 * MAO's language, linking to Account Requests.
 *
 * One notification per waiting farmer and MAO account (category "farmer-approval:<farmer id>"), never duplicated, and
 * removed once the sign-up is approved, rejected or otherwise no longer pending, so the bell only lists sign-ups that
 * still need a decision. sync() is safe to run at any time: right after a sign-up, after a decision, and every minute
 * with the dashboard warm-up (which also covers an account whose status changed in User Management).
 */
class FarmerApprovalNotifier
{
    public const CATEGORY_PREFIX = 'farmer-approval:';

    public function sync(): void
    {
        if (! SchemaCache::hasColumn('notifications', 'category')) {
            return;
        }

        // Before 2026_11_17_000002 there is no revision or deduplication column: one alert per MAO and farmer, as before.
        $revisions = SchemaCache::hasColumn('users', 'registration_revision') && SchemaCache::hasColumn('notifications', 'deduplication_key');

        DB::transaction(function () use ($revisions): void {
            $pending = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_PENDING)->orderBy('id')->lockForUpdate()->get($revisions ? ['id', 'name', 'barangay', 'registration_revision'] : ['id', 'name', 'barangay'])->keyBy('id');
            $maos = User::query()->where('role', User::ROLE_MAO)->where('status', User::STATUS_ACTIVE)->get();
            $existing = Notification::query()->where('category', 'like', self::CATEGORY_PREFIX.'%')->orderBy('id')->get();

            $kept = [];
            $touched = [];
            foreach ($existing as $note) {
                $farmerId = (int) substr((string) $note->category, strlen(self::CATEGORY_PREFIX));
                $key = $note->user_id.':'.$farmerId;
                if (! $pending->has($farmerId) || isset($kept[$key])
                    || ($note->deduplication_key !== null && $note->deduplication_key !== self::key($note->user_id, $pending[$farmerId]))
                    || ($note->deduplication_key === null && (int) $pending[$farmerId]->registration_revision > 0)) {
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
                    Notification::query()->firstOrCreate($revisions ? ['deduplication_key' => self::key($mao->id, $farmer)] : ['user_id' => $mao->id, 'category' => self::CATEGORY_PREFIX.$farmer->id], [
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
        }, 3);
    }

    private static function key(int $maoId, User $farmer): string
    {
        return 'farmer-approval:'.$farmer->id.':'.(int) $farmer->registration_revision.':mao:'.$maoId;
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
