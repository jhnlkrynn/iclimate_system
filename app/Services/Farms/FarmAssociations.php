<?php

namespace App\Services\Farms;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which farmers' association each farm belongs to. A farm can only join an association of its own barangay. The farmer
 * answers on Add / Edit Farm: a listed association (member), "my association isn't listed" (pending_other: the MAO
 * links it, adds it, or turns it down) or "not a member". No answer yet: "not declared".
 *
 * farmer_organization_members stays the farmer-level list. Farm::booted() calls reconcile() whenever a farm's link
 * changes or the farm is archived: an association a farm uses is always one of the farmer's memberships, and one that
 * no farm uses any more is dropped only when a farm just let go of it (memberships the MAO entered on the profile, with
 * no farm behind them, are left alone).
 */
final class FarmAssociations
{
    public static function available(): bool
    {
        return SchemaCache::hasColumn('farms', 'association_status');
    }

    /**
     * Every association, for the Add / Edit Farm picker (filtered in the page as the barangay changes).
     *
     * @return Collection<int, array{id: int, name: string, barangay: ?string, farm_type: ?string}>
     */
    public static function all(): Collection
    {
        return FarmerOrganization::query()->orderBy('name')->get(['id', 'name', 'barangay', 'farm_type'])
            ->map(fn (FarmerOrganization $o): array => [
                'id' => (int) $o->id,
                'name' => (string) $o->name,
                'barangay' => LianBarangays::canonicalName($o->barangay),
                'farm_type' => in_array($o->farm_type, ['Irrigated', 'Rainfed'], true) ? $o->farm_type : null,
            ]);
    }

    /**
     * The associations a farm in this barangay can join. With a farm type, only those of that type (or with no type),
     * unless none match: then every association of the barangay.
     *
     * @param  Collection<int, array{id: int, name: string, barangay: ?string, farm_type: ?string}>|null  $all
     * @return Collection<int, array{id: int, name: string, barangay: ?string, farm_type: ?string}>
     */
    public static function optionsFor(?string $barangay, ?string $farmType = null, ?Collection $all = null): Collection
    {
        $canonical = LianBarangays::canonicalName($barangay);
        if ($canonical === null) {
            return collect();
        }
        $here = ($all ?? self::all())->where('barangay', $canonical)->values();
        $typed = $here->filter(fn (array $o): bool => $o['farm_type'] === null || $o['farm_type'] === $farmType)->values();

        return $farmType !== null && $typed->isNotEmpty() ? $typed : $here;
    }

    /**
     * Association names for these farms, by id (one query).
     *
     * @param  iterable<int, Farm>  $farms
     * @return Collection<int, string>
     */
    public static function namesFor(iterable $farms): Collection
    {
        $ids = collect($farms)->pluck('farmer_organization_id')->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : FarmerOrganization::query()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /** The farm's association in words for MAO / IT pages ("Association: Samahan A", "Not an association member", …). */
    public static function staffLabel(Farm $farm, Collection $names): string
    {
        return match ($farm->associationState()) {
            Farm::ASSOCIATION_MEMBER => 'Association: '.$names->get($farm->farmer_organization_id, 'Unknown'),
            Farm::ASSOCIATION_NOT_MEMBER => 'Not an association member',
            Farm::ASSOCIATION_PENDING => 'Association waiting for MAO review: '.$farm->association_other_name,
            default => 'Association not declared',
        };
    }

    /** True when the association is in this barangay (the only ones a farm there can join). */
    public static function inBarangay(int $organizationId, ?string $barangay): bool
    {
        $canonical = LianBarangays::canonicalName($barangay);
        $organization = FarmerOrganization::query()->find($organizationId, ['id', 'barangay']);

        return $canonical !== null && $organization !== null && LianBarangays::canonicalName($organization->barangay) === $canonical;
    }

    /**
     * The farmer's answer from the farm form, set on the farm (saved by the caller). An unchanged answer keeps the
     * MAO's note; a new one clears it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fill(Farm $farm, array $data): void
    {
        $status = $data['association_status'] ?? null;
        if (! self::available() || ! in_array($status, Farm::ASSOCIATION_ANSWERS, true)) {
            return;
        }

        $answer = [
            'association_status' => $status,
            'farmer_organization_id' => $status === Farm::ASSOCIATION_MEMBER ? (int) $data['farmer_organization_id'] : null,
            'association_other_name' => $status === Farm::ASSOCIATION_PENDING ? trim((string) $data['association_other_name']) : null,
        ];
        $same = $farm->association_status === $answer['association_status']
            && (int) $farm->farmer_organization_id === (int) $answer['farmer_organization_id']
            && (string) $farm->association_other_name === (string) $answer['association_other_name'];

        $farm->forceFill($answer + ($same ? [] : ['association_note' => null]));
        $farm->associationAnswered = true;
    }

    /** Back to "not declared" (the farm moved to another barangay: the question is asked again). */
    public static function clear(Farm $farm): void
    {
        $farm->forceFill(['farmer_organization_id' => null, 'association_status' => null, 'association_other_name' => null, 'association_note' => null]
            + (SchemaCache::hasColumn('farms', 'association_joined_on') ? ['association_joined_on' => null] : []));
    }

    /**
     * Keeps the farmer's memberships in step with their farms in use: every association a farm uses is a membership
     * (joined today, or on the date a restored farm kept), and each of $released that no farm uses any more is dropped. The primary organization moves to
     * another membership when it was dropped, and is set when the farmer had none.
     *
     * @param  array<int, int|string|null>  $released  associations a farm just let go of (unlinked, changed, archived)
     */
    public static function reconcile(int $userId, array $released = []): void
    {
        if (! self::available() || ! SchemaCache::hasTable('farmer_organization_members')) {
            return;
        }

        $inUse = Farm::query()->ownedBy($userId)->active()->whereNotNull('farmer_organization_id')
            ->distinct()->pluck('farmer_organization_id')->map(fn ($id): int => (int) $id)->all();
        $released = array_values(array_diff(array_unique(array_map('intval', array_filter($released))), $inUse));
        $profile = FarmerProfile::query()->where('user_id', $userId)->orderBy('id')->first();
        if ($profile === null) {
            if ($inUse === []) {
                return;
            }
            $user = \App\Models\User::query()->find($userId);
            if ($user === null) {
                return;
            }
            // A farmer without a profile yet gets one from their linked farm (as saving a farm map does).
            $farm = Farm::query()->ownedBy($userId)->active()->whereNotNull('farmer_organization_id')->orderByDesc('is_primary')->orderBy('id')->first();
            $profile = FarmerProfile::query()->create([
                'user_id' => $userId, 'full_name' => $user->name, 'contact_number' => $user->contact_number, 'address' => $user->address,
                'barangay' => LianBarangays::canonicalName($farm?->barangay) ?? LianBarangays::canonicalName($user->barangay) ?? (string) $farm?->barangay,
                'farm_type' => in_array($farm?->farm_type, ['Irrigated', 'Rainfed'], true) ? $farm->farm_type : FarmerProfile::FARM_TYPE_RAINFED,
            ]);
        }

        $members = $profile->organizations()->pluck('farmer_organizations.id')->map(fn ($id): int => (int) $id)->all();
        $today = now()->toDateString();
        $keepsDate = SchemaCache::hasColumn('farms', 'association_joined_on');
        $attach = array_values(array_diff($inUse, $members));
        if ($attach !== []) {
            // A restored farm brings back the date joined it kept while archived; otherwise the farmer joins today.
            $kept = $keepsDate
                ? Farm::query()->ownedBy($userId)->active()->whereIn('farmer_organization_id', $attach)->whereNotNull('association_joined_on')
                    ->get(['farmer_organization_id', 'association_joined_on'])->mapWithKeys(fn (Farm $f): array => [(int) $f->farmer_organization_id => substr((string) $f->getRawOriginal('association_joined_on'), 0, 10)])
                : collect();
            foreach ($attach as $id) {
                $profile->organizations()->attach($id, ['date_joined' => $kept->get($id, $today)]);
            }
            if ($kept->isNotEmpty()) {
                Farm::query()->ownedBy($userId)->active()->whereIn('farmer_organization_id', $kept->keys())->update(['association_joined_on' => null]);
            }
        }
        $dropped = array_values(array_intersect($released, $members));
        if ($dropped !== []) {
            // An archived farm that still names the association keeps the date joined, for when it is restored.
            if ($keepsDate) {
                $joined = DB::table('farmer_organization_members')->where('farmer_profile_id', $profile->id)->whereIn('farmer_organization_id', $dropped)
                    ->whereNotNull('date_joined')->pluck('date_joined', 'farmer_organization_id');
                foreach ($joined as $id => $date) {
                    Farm::query()->ownedBy($userId)->whereNotNull('archived_at')->where('farmer_organization_id', $id)
                        ->update(['association_joined_on' => substr((string) $date, 0, 10)]);
                }
            }
            $profile->organizations()->detach($dropped);
        }

        $remaining = array_values(array_diff(array_unique(array_merge($members, $inUse)), $dropped));
        $primary = (int) $profile->farmer_organization_id;
        if ($primary === 0 || in_array($primary, $dropped, true)) {
            $next = $inUse[0] ?? ($remaining[0] ?? null);
            if ((int) $next !== $primary) {
                $profile->forceFill(['farmer_organization_id' => $next])->saveQuietly();
            }
        }
        $profile->unsetRelation('organizations');
    }

    /** Notifications about one farm's association request: "farm-association:<farm id>". */
    public const NOTIFICATION_PREFIX = 'farm-association:';

    /**
     * Tells the farmer what the MAO decided on their "isn't listed" request (as Farmer Approvals tells them about their
     * account), in the farmer's language. $decision: linked, created or rejected.
     *
     * @param  array<string, string|null>  $words  association, name (what the farmer typed), note
     */
    public static function notifyFarmer(Farm $farm, string $decision, array $words): void
    {
        $farmer = $farm->user;
        if ($farmer === null) {
            return;
        }
        $saved = $farmer->getAttribute('preferred_language');
        $locale = \App\Support\FarmerLanguage::isSupported($saved) ? $saved : \App\Support\FarmerLanguage::DEFAULT;
        $replace = ['farm' => $farm->farm_name] + array_map(fn ($value): string => (string) $value, $words);
        // The note sits mid-message, so it ends as a sentence.
        if (isset($replace['note']) && $replace['note'] !== '' && ! preg_match('/[.!?]$/u', $replace['note'])) {
            $replace['note'] .= '.';
        }

        \App\Models\Notification::query()->create([
            'user_id' => $farmer->getKey(),
            'title' => trans('farms.association.notify.'.$decision.'.title', $replace, $locale),
            'message' => trans('farms.association.notify.'.$decision.'.message', $replace, $locale),
            'type' => 'Announcement',
            'category' => self::NOTIFICATION_PREFIX.$farm->getKey(),
            'is_read' => false,
        ]);
    }

    /**
     * Where an association notification leads: the farm's own page (Edit Farm, where the answer can be changed), or My
     * Farms when the farm is no longer in the farmer's list. Null for any other notification.
     *
     * @return ?array{url: string, label: string}
     */
    public static function notificationLink(?string $category, string $locale, ?\App\Models\User $user = null): ?array
    {
        if (! is_string($category) || ! str_starts_with($category, self::NOTIFICATION_PREFIX)) {
            return null;
        }
        $id = (int) substr($category, strlen(self::NOTIFICATION_PREFIX));
        $open = $user !== null && $id > 0 && Farm::query()->ownedBy($user)->active()->whereKey($id)->exists();

        return [
            'url' => $open ? route('farmer.farms.edit', $id) : route('farmer.my-farm'),
            'label' => trans('farms.association.notify.open', [], $locale),
        ];
    }

    /**
     * Existing farms without an answer: linked to the farmer's primary organization when it is in the farm's barangay
     * (and made sure to be one of their memberships). The rest stay "not declared". Safe to run again.
     *
     * @return array{linked: int, not_declared: int, no_organization: int, other_barangay: int}
     */
    public static function backfill(): array
    {
        $counts = ['linked' => 0, 'not_declared' => 0, 'no_organization' => 0, 'other_barangay' => 0];
        $organizations = DB::table('farmer_organizations')->pluck('barangay', 'id')
            ->map(fn ($barangay): ?string => LianBarangays::canonicalName($barangay));
        // Read straight from the schema: this also runs inside the migration that adds the columns.
        $hasMembers = \Illuminate\Support\Facades\Schema::hasTable('farmer_organization_members');

        DB::table('farms')
            ->leftJoin('farmer_profiles', 'farmer_profiles.user_id', '=', 'farms.user_id')
            ->whereNull('farms.association_status')
            ->whereNull('farms.farmer_organization_id')
            ->select(['farms.id', 'farms.barangay', 'farmer_profiles.id as profile_id', 'farmer_profiles.farmer_organization_id as primary_id'])
            // By id, not by page: a linked farm leaves the "not answered" rows this reads.
            ->chunkById(500, function (Collection $farms) use (&$counts, $organizations, $hasMembers): void {
                $now = now();
                foreach ($farms->unique('id') as $farm) {
                    $primary = $farm->primary_id !== null ? (int) $farm->primary_id : null;
                    if ($primary === null || ! $organizations->has($primary)) {
                        $counts['no_organization']++;
                        $counts['not_declared']++;

                        continue;
                    }
                    $farmBarangay = LianBarangays::canonicalName($farm->barangay);
                    if ($farmBarangay === null || $organizations->get($primary) !== $farmBarangay) {
                        $counts['other_barangay']++;
                        $counts['not_declared']++;

                        continue;
                    }

                    DB::table('farms')->where('id', $farm->id)->update([
                        'farmer_organization_id' => $primary,
                        'association_status' => Farm::ASSOCIATION_MEMBER,
                        'updated_at' => $now,
                    ]);
                    if ($hasMembers) {
                        DB::table('farmer_organization_members')->insertOrIgnore([
                            'farmer_profile_id' => $farm->profile_id, 'farmer_organization_id' => $primary,
                            'date_joined' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                    $counts['linked']++;
                }
            }, 'farms.id', 'id');

        return $counts;
    }
}
