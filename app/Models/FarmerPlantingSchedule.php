<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FarmerPlantingSchedule extends Model
{
    use HasFactory;

    public const SEASONS = ['Wet', 'Dry'];

    public const FARM_TYPES = ['Irrigated', 'Rainfed'];

    public const MANUAL_STATUSES = ['Planted', 'Harvested', 'Delayed', 'Needs Review'];

    protected $fillable = [
        'user_id', 'planting_date', 'scheduled_harvest_date', 'harvest_month',
        'season', 'farm_type', 'barangay', 'farm_area_hectares', 'notes',
        'progress_status', 'predicted_yield', 'prediction_confidence',
        'prediction_source', 'prediction_unavailable_reason', 'prediction_details',
        'historical_same_month_average', 'yield_difference',
        'comparison_interpretation', 'comparison_note', 'recommended_actions',
        'prediction_generated_at',
    ];

    protected $casts = [
        'planting_date' => 'date',
        'scheduled_harvest_date' => 'date',
        'harvest_month' => 'date',
        'farm_area_hectares' => 'decimal:2',
        'predicted_yield' => 'decimal:2',
        'prediction_confidence' => 'decimal:2',
        'prediction_details' => 'array',
        'historical_same_month_average' => 'decimal:2',
        'yield_difference' => 'decimal:2',
        'prediction_generated_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function harvestDateForPlanning(): ?Carbon
    {
        return $this->scheduled_harvest_date ?? $this->harvest_month?->copy()->endOfMonth();
    }

    public function monitoringStatus(): string
    {
        if (in_array($this->progress_status, self::MANUAL_STATUSES, true)) {
            return strtolower(str_replace(' ', '_', $this->progress_status));
        }

        $today = today();
        $harvest = $this->harvestDateForPlanning();

        if ($this->planting_date?->isFuture()) {
            return 'upcoming_planting';
        }

        if ($harvest?->isPast()) {
            return 'delayed';
        }

        if ($harvest && $today->diffInDays($harvest, false) <= 30) {
            return 'upcoming_harvest';
        }

        return $this->prediction_unavailable_reason || $this->prediction_confidence === null || (float) $this->prediction_confidence < 70
            ? 'needs_review'
            : 'planted';
    }

    public function monitoringStatusLabel(): string
    {
        return str($this->monitoringStatus())->replace('_', ' ')->title()->toString();
    }
}
