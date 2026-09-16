<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeatmapAreaForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay',
        'forecast_month',
        'risk_level',
        'risk_type',
        'risk_score',
        'predicted_yield',
        'rainfall_status',
        'planting_advisory',
        'irrigation_recommendation',
        'description',
        'main_risk_reason',
        'suggested_action',
        'computed_at',
    ];

    protected $casts = [
        'forecast_month' => 'date',
        'risk_score' => 'decimal:2',
        'predicted_yield' => 'decimal:2',
        'computed_at' => 'datetime',
    ];
}
