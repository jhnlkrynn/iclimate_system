<?php

namespace App\Models;

use App\Services\Farms\FarmAssociations;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One farm a farmer owns or manages. A farmer may have many, each with its own barangay, size, type, map and plans;
 * the "current farm" they picked in the My Farm selector is the one farmer pages show (App\Support\ActiveFarm).
 *
 * The size keeps how it is known: an exact entry and a size measured from the farm map are numbers
 * (farm_size_hectares); an estimate keeps the range the farmer picked (farm_size_estimate_range) and is always shown
 * as "Estimated"; "unknown" has neither. See modelArea() for what the rice-yield estimate uses for each.
 */
class Farm extends Model
{
    use HasFactory;

    public const SIZE_EXACT = 'exact';

    public const SIZE_ESTIMATED = 'estimated';

    public const SIZE_MAPPED = 'mapped';

    public const SIZE_UNKNOWN = 'unknown';

    public const SIZE_METHODS = [self::SIZE_EXACT, self::SIZE_ESTIMATED, self::SIZE_MAPPED, self::SIZE_UNKNOWN];

    /** The estimate choices, as [from, to] hectares (to = null: "more than"). */
    public const ESTIMATE_RANGES = [
        'lt_0_5' => [0.0, 0.5],
        '0_5_1' => [0.5, 1.0],
        '1_2' => [1.0, 2.0],
        '2_3' => [2.0, 3.0],
        'gt_3' => [3.0, null],
    ];

    public const UNIT_HECTARES = 'hectares';

    public const UNIT_SQUARE_METERS = 'square_meters';

    public const LOCATION_BARANGAY = 'barangay';

    public const LOCATION_PIN = 'pin';

    public const LOCATION_BOUNDARY = 'boundary';

    /** The farm's farmers' association (App\Services\Farms\FarmAssociations); null: not declared yet. */
    public const ASSOCIATION_MEMBER = 'member';

    public const ASSOCIATION_NOT_MEMBER = 'not_member';

    /** "My association isn't listed": the name waits for the MAO (Association Requests). */
    public const ASSOCIATION_PENDING = 'pending_other';

    public const ASSOCIATION_ANSWERS = [self::ASSOCIATION_MEMBER, self::ASSOCIATION_PENDING, self::ASSOCIATION_NOT_MEMBER];

    /** Set when this save carries the farmer's (or the MAO's) association answer, so a barangay change keeps it. */
    public bool $associationAnswered = false;

    /** Room for every real farm; only stops a runaway form. */
    public const MAX_PER_FARMER = 50;

    protected $fillable = [
        'user_id', 'farm_name', 'barangay', 'location_description', 'location_method', 'latitude', 'longitude',
        'farm_size_hectares', 'farm_size_method', 'farm_size_estimate_range', 'farm_size_unit', 'farm_type',
        'is_primary', 'archived_at',
    ];

    protected static function booted(): void
    {
        // A farm that moved to another barangay (form, farm map, MAO profile edit) answers the association question
        // again: the old association may not be where the farm is now.
        static::saving(function (self $farm): void {
            if ($farm->exists && $farm->isDirty('barangay') && ! $farm->associationAnswered && FarmAssociations::available()
                && LianBarangays::canonicalName($farm->getOriginal('barangay')) !== LianBarangays::canonicalName($farm->barangay)) {
                FarmAssociations::clear($farm);
            }
        });

        // The farmer's memberships follow the farm's link (original values are still readable in "saved").
        static::saved(function (self $farm): void {
            $before = $farm->wasRecentlyCreated ? null : $farm->getOriginal('farmer_organization_id');
            if (($farm->farmer_organization_id || $before) && ($farm->wasRecentlyCreated || $farm->wasChanged(['farmer_organization_id', 'archived_at']))) {
                FarmAssociations::reconcile((int) $farm->user_id, [$before, $farm->archived_at !== null ? $farm->farmer_organization_id : null]);
            }
            $farm->associationAnswered = false;
        });
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'farm_size_hectares' => 'decimal:4',
            'is_primary' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);   // history keeps a deleted user's name
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(FarmerOrganization::class, 'farmer_organization_id');
    }

    /** "member" with its association, "not_member", "pending_other", or "not_declared" (no answer, or a removed association). */
    public function associationState(): string
    {
        return match (true) {
            $this->association_status === self::ASSOCIATION_MEMBER && $this->farmer_organization_id !== null => self::ASSOCIATION_MEMBER,
            in_array($this->association_status, [self::ASSOCIATION_NOT_MEMBER, self::ASSOCIATION_PENDING], true) => $this->association_status,
            default => 'not_declared',
        };
    }

    public function boundary(): HasOne
    {
        return $this->hasOne(FarmBoundary::class);
    }

    public function plantingSchedules(): HasMany
    {
        return $this->hasMany(FarmerPlantingSchedule::class);
    }

    /** Farms in use (not archived). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /** A known size in hectares: an exact entry or the measured map area; null for an estimate or no size. */
    public function knownHectares(): ?float
    {
        return in_array($this->farm_size_method, [self::SIZE_EXACT, self::SIZE_MAPPED], true) && (float) $this->farm_size_hectares > 0
            ? (float) $this->farm_size_hectares
            : null;
    }

    /** The estimate's [from, to] in hectares, or null when the size is not an estimate. */
    public function estimateRange(): ?array
    {
        return $this->farm_size_method === self::SIZE_ESTIMATED ? (self::ESTIMATE_RANGES[$this->farm_size_estimate_range] ?? null) : null;
    }

    public function hasSize(): bool
    {
        return $this->knownHectares() !== null || $this->estimateRange() !== null;
    }

    /**
     * The area the rice-yield model is given (it needs one number). A known size is used as it is. An estimate uses
     * the middle of the range the farmer picked ("more than 3 ha": 3 ha), and the stored estimate records that it was
     * based on an estimated size, so the card says so; the range itself is never shown as a measured size. No size:
     * null, and no estimate is made.
     */
    public function modelArea(): ?float
    {
        if (($known = $this->knownHectares()) !== null) {
            return $known;
        }
        $range = $this->estimateRange();
        if ($range === null) {
            return null;
        }

        return $range[1] === null ? $range[0] : round(($range[0] + $range[1]) / 2, 2);
    }

    /** What the farm's yield estimate depends on, so a change to any of it makes the estimate due again. */
    public const ESTIMATE_INPUTS = ['barangay', 'farm_size_hectares', 'farm_size_method', 'farm_size_estimate_range', 'farm_type', 'archived_at'];
}
