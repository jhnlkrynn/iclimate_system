<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a farmer's farms (2026-10). Planting / harvest records (farmer_planting_schedules.farm_id) and yield estimates
 * belong to a farm. The farmer's first active farm is also mirrored on farmer_profiles (App\Services\Farmer\FarmService).
 */
class Farm extends Model
{
    use HasFactory;

    public const FARM_TYPES = FarmerPlantingSchedule::FARM_TYPES;

    protected $fillable = ['user_id', 'name', 'barangay', 'area_hectares', 'farm_type', 'archived_at'];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plantingSchedules(): HasMany
    {
        return $this->hasMany(FarmerPlantingSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function hasArea(): bool
    {
        return (float) ($this->area_hectares ?? 0) > 0;
    }
}
