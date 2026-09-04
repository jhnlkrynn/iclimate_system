<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmerOrganization extends Model
{
    protected $fillable = [
        'name',
        'barangay',
        'farm_type',
        'area_hectares',
        'farmer_count',
    ];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'farmer_count' => 'integer',
    ];

    public function farmerProfiles(): HasMany
    {
        return $this->hasMany(FarmerProfile::class);
    }
}
