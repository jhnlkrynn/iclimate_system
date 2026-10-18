<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One saved current-season rice-yield estimate for a farm or a barangay (App\Services\Prediction\YieldPredictionStore). */
class YieldPrediction extends Model
{
    public const SCOPE_FARM = 'farm';

    public const SCOPE_BARANGAY = 'barangay';

    protected $fillable = [
        'scope', 'subject_key', 'farm_id', 'user_id', 'barangay', 'predicted_yield', 'classification', 'basis',
        'period', 'farms_used', 'area_hectares', 'season_start', 'season', 'season_label', 'target_month', 'model_version',
        'model_input', 'generated_at',
    ];

    /** season_start stays a plain Y-m-d string, so the (subject_key, season_start) lookup matches on every database. */
    protected function casts(): array
    {
        return [
            'predicted_yield' => 'float',
            'area_hectares' => 'float',
            'farms_used' => 'integer',
            'model_input' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
