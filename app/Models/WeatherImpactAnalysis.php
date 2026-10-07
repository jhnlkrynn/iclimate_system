<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manuscript – Naicel (2026-10-08), Phase 4: one saved Weather Impact Analysis (WeatherAnalysisService::impact()). */
class WeatherImpactAnalysis extends Model
{
    protected $fillable = ['title', 'filters', 'results', 'sample_size', 'strongest_variable', 'impact_level', 'notes', 'created_by'];

    protected $casts = ['filters' => 'array', 'results' => 'array'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
