<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FarmerProfile extends Model
{
    use HasFactory;

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

    public function farmerOrganization(): BelongsTo
    {
        return $this->belongsTo(FarmerOrganization::class);
    }

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
}
