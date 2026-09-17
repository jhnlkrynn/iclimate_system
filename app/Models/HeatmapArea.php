<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeatmapArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay',
        'latitude',
        'longitude',
        'risk_level',
        'risk_type',
        'risk_score',
        'risk_components',
        'predicted_yield',
        'rainfall_status',
        'planting_advisory',
        'irrigation_recommendation',
        'description',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'risk_score' => 'decimal:2',
        'risk_components' => 'array',
        'predicted_yield' => 'decimal:2',
    ];
}
