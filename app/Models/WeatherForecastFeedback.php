<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A farmer's answer to "Was this weather forecast accurate in your area?".
 * Stored data only: nothing here feeds a model, ClimateRecord or the
 * historical dataset. forecast_* columns are an immutable snapshot.
 */
class WeatherForecastFeedback extends Model
{
    use HasFactory;

    protected $table = 'weather_forecast_feedback';

    public const TYPE_MONTHLY = 'monthly';

    // Farmer – Naicel (2026-10): "Tama ba ang taya ng panahon kahapon?" — one answer per farmer per forecast day.
    public const TYPE_DAILY = 'daily';

    public const ACCURATE = 'accurate';

    public const PARTLY = 'partly';

    public const DIFFERENT = 'different';

    public const ACCURACY_VALUES = [self::ACCURATE, self::PARTLY, self::DIFFERENT];

    public const OBSERVED_CONDITIONS = [
        'Sunny', 'Cloudy', 'No Rain', 'Light Rain', 'Moderate Rain', 'Heavy Rain', 'Strong Wind',
    ];

    protected $fillable = [
        'user_id', 'barangay', 'forecast_type', 'forecast_date', 'monthly_weather_prediction_id',
        'forecast_condition', 'forecast_rainfall_mm', 'forecast_temperature_c',
        'forecast_humidity_percent', 'accuracy', 'observed_condition', 'submitted_at',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'forecast_rainfall_mm' => 'decimal:4',
        'forecast_temperature_c' => 'decimal:4',
        'forecast_humidity_percent' => 'decimal:4',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monthlyWeatherPrediction(): BelongsTo
    {
        return $this->belongsTo(MonthlyWeatherPrediction::class);
    }

    public static function accuracyLabels(): array
    {
        return [
            self::ACCURATE => 'Yes, it was accurate',
            self::PARTLY => 'Partly accurate',
            self::DIFFERENT => 'No, it was different',
        ];
    }
}
