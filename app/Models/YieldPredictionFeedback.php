<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A farmer's actual harvest (t/ha) next to the yield predicted for it, linked to the farm, the season and the planting
 * record. Stored data only: kept for a future retraining of the rice-yield model, never used to retrain automatically.
 */
class YieldPredictionFeedback extends Model
{
    protected $table = 'yield_prediction_feedback';

    protected $fillable = [
        'user_id', 'farm_id', 'farmer_planting_schedule_id', 'season', 'season_year', 'barangay', 'farm_type',
        'predicted_yield', 'prediction_source', 'prediction_model_version', 'prediction_generated_at',
        'actual_yield', 'harvested_on', 'submitted_at',
    ];

    protected $casts = [
        'season_year' => 'integer',
        'predicted_yield' => 'decimal:2',
        'actual_yield' => 'decimal:2',
        'prediction_generated_at' => 'datetime',
        'harvested_on' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function plantingSchedule(): BelongsTo
    {
        return $this->belongsTo(FarmerPlantingSchedule::class, 'farmer_planting_schedule_id');
    }
}
