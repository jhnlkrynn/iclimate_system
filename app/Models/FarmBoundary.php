<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmBoundary extends Model
{
    use HasFactory;

    protected $fillable = [
        'farmer_profile_id',
        'boundary_coordinates',
        'calculated_area_hectares',
        'calculated_perimeter_meters',
    ];

    protected function casts(): array
    {
        return [
            'boundary_coordinates' => 'array',
            'calculated_area_hectares' => 'decimal:4',
            'calculated_perimeter_meters' => 'decimal:2',
        ];
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }
}
