<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FarmerProfile extends Model
{
    use HasFactory, \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    public const FARM_TYPE_RAINFED = 'Rainfed';

    public const FARM_TYPE_IRRIGATED = 'Irrigated';

    protected $fillable = [
        'user_id',
        'farmer_organization_id',
        'rsbsa_no',
        'full_name',
        'contact_number',
        'address',
        'barangay',
        'farm_area',
        'farm_type',
    ];

    protected $casts = [
        'farm_area' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The farmer's primary organization (sets the barangay and farm type at sign-up); always one of organizations(). */
    public function farmerOrganization(): BelongsTo
    {
        return $this->belongsTo(FarmerOrganization::class);
    }

    // ------------------------------------------------------------ Panel revision 1.1: many organizations per farmer

    protected static function booted(): void
    {
        // Code that sets only the primary organization (older forms, imports, seeders) keeps the membership list right.
        static::saved(function (self $profile): void {
            $primaryChanged = $profile->wasRecentlyCreated || $profile->wasChanged('farmer_organization_id');
            if ($profile->farmer_organization_id && $primaryChanged && \App\Support\SchemaCache::hasTable('farmer_organization_members')
                && ! $profile->organizations()->whereKey($profile->farmer_organization_id)->exists()) {
                $profile->organizations()->attach($profile->farmer_organization_id, ['date_joined' => now()->toDateString()]);
            }
        });
    }

    /** Every organization the farmer belongs to, with the date they joined. */
    public function organizations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(FarmerOrganization::class, 'farmer_organization_members')
            ->withPivot('date_joined')
            ->withTimestamps()
            ->orderBy('farmer_organizations.name');
    }

    /**
     * Sets the farmer's memberships. The primary organization is kept when it is still listed, otherwise the first one
     * listed becomes primary (none listed: no primary). Dates joined of memberships that stay are kept.
     *
     * @param  array<int, int|string>  $organizationIds
     */
    public function syncOrganizations(array $organizationIds, ?int $primaryId = null): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $organizationIds))));
        if ($primaryId && ! in_array($primaryId, $ids, true)) {
            array_unshift($ids, $primaryId);
        }
        $existing = $this->organizations()->pluck('farmer_organizations.id')->all();
        $today = now()->toDateString();
        $this->organizations()->sync(collect($ids)->mapWithKeys(fn (int $id): array => [
            $id => in_array($id, $existing, true) ? [] : ['date_joined' => $today],
        ])->all());

        $primary = $primaryId ?: (in_array((int) $this->farmer_organization_id, $ids, true) ? (int) $this->farmer_organization_id : ($ids[0] ?? null));
        if ((int) $this->farmer_organization_id !== (int) $primary) {
            $this->forceFill(['farmer_organization_id' => $primary])->saveQuietly();
        }
        $this->unsetRelation('organizations');
    }

    /** @return array<int, int> ids of every organization the farmer belongs to (for the form's ticks). */
    public function getFarmerOrganizationIdsAttribute(): array
    {
        return $this->organizations->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** "Samahan A, Samahan B", or null. */
    public function getOrganizationNamesAttribute(): ?string
    {
        $names = $this->organizations->pluck('name');
        if ($names->isEmpty() && $this->farmerOrganization) {
            $names = collect([$this->farmerOrganization->name]);
        }

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    // ------------------------------------------------------------ end panel revision 1.1

    /** The farmer's most recently saved farm map (each farm now has its own: Farm::boundary()). */
    public function farmBoundary(): HasOne
    {
        return $this->hasOne(FarmBoundary::class)->latestOfMany();
    }

    /** The farmer's farms (by the same user). */
    public function farms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Farm::class, 'user_id', 'user_id');
    }

    /**
     * The farmer's whole farm area for the MAO list ("3.5 ha"): the sum of their farms with a known size, or the area
     * on the profile when no farm has one. Null when neither is known, so the list shows nothing instead of a zero.
     */
    public function getTotalFarmAreaAttribute(): ?string
    {
        $known = array_key_exists('active_known_farm_area', $this->attributes)
            ? (float) ($this->attributes['active_known_farm_area'] ?? 0)
            : $this->farms->whereNull('archived_at')->sum(fn (Farm $farm): float => (float) ($farm->knownHectares() ?? 0));
        $hectares = $known > 0 ? $known : (float) $this->farm_area;

        return $hectares > 0 ? rtrim(rtrim(number_format($hectares, 2), '0'), '.').' ha' : null;
    }

    /** Active / Inactive: the status of the farmer's login account. */
    public function getAccountStatusAttribute(): ?string
    {
        if (array_key_exists('account_status_value', $this->attributes)) {
            return $this->attributes['account_status_value'] !== null
                ? (string) $this->attributes['account_status_value']
                : null;
        }

        return $this->user?->status;
    }
}
