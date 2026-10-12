<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FarmerPlantingSchedule extends Model
{
    use HasFactory, \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    public const SEASONS = ['Wet', 'Dry'];

    public const FARM_TYPES = ['Irrigated', 'Rainfed'];

    public const MANUAL_STATUSES = ['Planted', 'Harvested', 'Delayed', 'Needs Review'];

    protected $fillable = [
        'user_id', 'farm_id', 'planting_date', 'scheduled_harvest_date', 'harvest_month',
        'season', 'farm_type', 'barangay', 'farm_area_hectares', 'actual_yield', 'harvested_on', 'notes',
        'progress_status', 'predicted_yield', 'prediction_confidence',
        'prediction_source', 'prediction_unavailable_reason', 'prediction_details',
        'historical_same_month_average', 'yield_difference',
        'comparison_interpretation', 'comparison_note', 'recommended_actions',
        'prediction_generated_at',
        // Panel revision 1.3
        'harvest_timing', 'harvest_timing_reason', 'harvest_timing_reason_other',
    ];

    protected $casts = [
        'planting_date' => 'date',
        'scheduled_harvest_date' => 'date',
        'harvest_month' => 'date',
        'farm_area_hectares' => 'decimal:2',
        'actual_yield' => 'decimal:2',
        'harvested_on' => 'date',
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

    // ------------------------------------------------------------ Panel revision 1.3: harvest timing

    protected static function booted(): void
    {
        // Whatever path records a harvest date (Activity Calendar, the planting form, an import), its timing is kept
        // right. The reason itself is asked for by the forms (App\Support\HarvestTiming::attributes()).
        static::saving(function (self $schedule): void {
            if (! \App\Support\SchemaCache::hasColumn($schedule->getTable(), 'harvest_timing')) {
                return;
            }
            if ($schedule->isDirty(['harvested_on', 'scheduled_harvest_date', 'harvest_month']) || ($schedule->harvested_on && ! $schedule->harvest_timing)) {
                $schedule->harvest_timing = $schedule->harvested_on
                    ? \App\Support\HarvestTiming::classify($schedule->harvestDateForPlanning(), $schedule->harvested_on)
                    : null;
                if (! \App\Support\HarvestTiming::needsReason($schedule->harvest_timing)) {
                    $schedule->harvest_timing_reason = null;
                    $schedule->harvest_timing_reason_other = null;
                }
            }
        });
    }

    /** "Early (12 days) – Heavy rain / flooding", "On time", or null before the harvest is recorded. */
    public function harvestTimingSummary(?string $locale = null): ?string
    {
        return \App\Support\HarvestTiming::summary($this->harvest_timing, $this->harvestDateForPlanning(), $this->harvested_on,
            $this->harvest_timing_reason, $this->harvest_timing_reason_other, $locale);
    }

    // ------------------------------------------------------------ end panel revision 1.3

    /** Which of the farmer's farms this plan is for. */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
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

        // Compare calendar dates in Manila time. The date columns are stored as UTC midnight,
        // so comparing them with the current instant is wrong for the first 8 hours of a Manila day.
        $today = \App\Support\BusinessClock::today();
        $todayDate = $today->toDateString();
        $harvest = $this->harvestDateForPlanning();

        if ($this->planting_date && $this->planting_date->toDateString() > $todayDate) {
            return 'upcoming_planting';
        }

        if ($harvest && $harvest->toDateString() < $todayDate) {
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
