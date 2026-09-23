<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single "shadow" comparison row: the already-completed production
 * rice-yield prediction for a given month, next to what the experimental
 * candidate model (Ambulong Monthly Weather Random Forest -> Candidate Rice
 * Yield Random Forest) would have predicted for the same month. Written
 * only by RiceYieldShadowComparisonService — never read by, or fed back
 * into, the production prediction pipeline.
 */
class RiceYieldModelComparison extends Model
{
    use HasFactory;

    protected $table = 'rice_yield_model_comparisons';

    protected $fillable = [
        'user_id',
        'prediction_year',
        'prediction_month',
        'production_prediction',
        'production_model',
        'candidate_prediction',
        'candidate_model',
        'candidate_success',
        'candidate_status',
        'difference',
        'absolute_difference',
        'rainfall',
        'tmax',
        'tmin',
        'humidity',
        'wind_speed',
        'wind_direction',
        'weather_basis',
        'weather_model',
        'weather_training_from',
        'weather_training_to',
        'candidate_error',
        'source',
        'metadata',
    ];

    protected $casts = [
        'prediction_year' => 'integer',
        'prediction_month' => 'integer',
        'production_prediction' => 'decimal:4',
        'candidate_prediction' => 'decimal:4',
        'candidate_success' => 'boolean',
        'difference' => 'decimal:4',
        'absolute_difference' => 'decimal:4',
        'rainfall' => 'decimal:4',
        'tmax' => 'decimal:4',
        'tmin' => 'decimal:4',
        'humidity' => 'decimal:4',
        'wind_speed' => 'decimal:4',
        'wind_direction' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
