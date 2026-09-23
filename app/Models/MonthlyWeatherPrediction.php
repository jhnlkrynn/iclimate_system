<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single persisted MonthlyWeatherRandomForest::predict() result for one
 * (prediction_year, prediction_month, weather_model). Written only by
 * PredictionOrchestrator — every other consumer (BarangayRiskMapService,
 * WeatherPredictionController, RiceYieldShadowComparisonService, the
 * candidate-report command) should read through the orchestrator rather
 * than querying this table directly, so there is exactly one place that
 * decides freshness/expiry and one place that writes.
 */
class MonthlyWeatherPrediction extends Model
{
    public const STATUS_READY = 'ready';

    public const STATUS_UNREADY = 'unready';

    protected $fillable = [
        'prediction_year',
        'prediction_month',
        'rainfall',
        'tmax',
        'tmin',
        'temperature_average',
        'humidity',
        'wind_speed',
        'wind_direction',
        'season',
        'weather_model',
        'training_from',
        'training_to',
        'generated_at',
        'expires_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'prediction_year' => 'integer',
        'prediction_month' => 'integer',
        'rainfall' => 'decimal:4',
        'tmax' => 'decimal:4',
        'tmin' => 'decimal:4',
        'temperature_average' => 'decimal:4',
        'humidity' => 'decimal:4',
        'wind_speed' => 'decimal:4',
        'wind_direction' => 'decimal:4',
        'training_from' => 'date',
        'training_to' => 'date',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function isFresh(): bool
    {
        return $this->status === self::STATUS_READY
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
