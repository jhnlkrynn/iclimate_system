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

            if (\App\Support\SchemaCache::hasColumn('heatmap_areas', 'main_risk_reason')) {
                $area->main_risk_reason = $area->description
                    ?: ($area->risk_type ?: 'Climate')." risk with a computed score of ".number_format((float) $area->risk_score, 2).'.';
            }
            if (\App\Support\SchemaCache::hasColumn('heatmap_areas', 'suggested_action')) {
                $area->suggested_action = $area->irrigation_recommendation
                    ?: $area->planting_advisory
                    ?: 'Continue routine field monitoring.';
            }
            // A caller that set the calculation time (the risk map, one time for all 19) keeps it.
            if (! $area->isDirty('risk_computed_at') && \App\Support\SchemaCache::hasColumn('heatmap_areas', 'risk_computed_at')) {
                $area->risk_computed_at = now();
            }
        });
    }

    /** rainfall_status of the install migration's "Moderate" placeholders. */
    public const SEED_RAINFALL_STATUS = 'Pending climate update';

    /** description the 2026_06_30 install migration gave every row it seeded (placeholders and hand-written demo levels). */
    public const SEED_DESCRIPTION = 'Barangay agricultural risk score generated from rainfall, temperature, season, and yield indicators.';

    /**
     * The 2026_06_30 install migration seeded every barangay with a rating nobody computed: "Moderate / Pending climate
     * update" placeholders and a few hand-written demo levels. Until the risk map has computed that barangay, the row
     * is not a rating and must not be shown or counted as one. BarangayRiskMapService::persist() always rewrites
     * rainfall_status and description, so a computed row never matches.
     */
    public function isSeedPlaceholder(): bool
    {
        return $this->rainfall_status === self::SEED_RAINFALL_STATUS || $this->description === self::SEED_DESCRIPTION;
    }

    /** Rows holding a real computed rating: the query form of ! isSeedPlaceholder(). */
    public function scopeComputed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('rainfall_status')->orWhere('rainfall_status', '!=', self::SEED_RAINFALL_STATUS))
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', '!=', self::SEED_DESCRIPTION));
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
