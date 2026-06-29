<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiceProduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay',
        'season',
        'irrigation_type',
        'yield_per_hectare',
        'area_hectares',
        'total_production',
        'year',
        'remarks',
    ];

    protected $casts = [
        'yield_per_hectare' => 'decimal:2',
        'area_hectares' => 'decimal:2',
        'total_production' => 'decimal:2',
        'year' => 'integer',
    ];
}