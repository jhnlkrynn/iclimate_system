<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeatmapArea extends Model
{
    use HasFactory;

    /**
     * How recent risk_computed_at must be for a row to be treated as
     * "current" (vs. "stale") by chatbot-style consumers (Climora /
     * RoleAssistantService). The scheduled `iclimate:refresh-barangay-risk`
     * command recomputes the whole risk map at least every 4 hours; 6 hours
     * gives a buffer for one missed/delayed scheduler run without being so
     * generous that genuinely outdated data gets presented as current.
     */
    public const CURRENT_WINDOW_HOURS = 6;

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
        'main_risk_reason',
        'suggested_action',
        'risk_computed_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'risk_score' => 'decimal:2',
        'risk_components' => 'array',
        'predicted_yield' => 'decimal:2',
        'risk_computed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $area): void {
            if (! $area->isDirty(['risk_score', 'risk_level', 'risk_type', 'description', 'planting_advisory', 'irrigation_recommendation'])) {
                return;
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('heatmap_areas', 'main_risk_reason')) {
                $area->main_risk_reason = $area->description
                    ?: "{$area->risk_type} risk with a computed score of ".number_format((float) $area->risk_score, 2).'.';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('heatmap_areas', 'suggested_action')) {
                $area->suggested_action = $area->irrigation_recommendation
                    ?: $area->planting_advisory
                    ?: 'Continue routine field monitoring.';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('heatmap_areas', 'risk_computed_at')) {
                $area->risk_computed_at = now();
            }
        });
    }

    /**
     * True when risk_computed_at is within self::CURRENT_WINDOW_HOURS of now.
     * A row with no risk_computed_at at all is never treated as current.
     */
    public function isCurrentlyComputed(): bool
    {
        return $this->risk_computed_at !== null
            && $this->risk_computed_at->greaterThanOrEqualTo(now()->subHours(self::CURRENT_WINDOW_HOURS));
    }
}
