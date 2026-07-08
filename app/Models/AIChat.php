<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'intent',
        'source_type',
        'source_name',
        'source_url',
        'language',
        'memory',
        'prediction_result',
        'weather_prediction',
        'rice_yield_prediction',
        'planting_recommendation',
        'irrigation_recommendation',
        'warnings',
        'explanation',
        'confidence_score',
        'response_time_ms',
    ];

    protected $casts = [
        'prediction_result' => 'array',
        'weather_prediction' => 'array',
        'rice_yield_prediction' => 'array',
        'planting_recommendation' => 'array',
        'irrigation_recommendation' => 'array',
        'warnings' => 'array',
        'memory' => 'array',
        'confidence_score' => 'decimal:2',
        'response_time_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
