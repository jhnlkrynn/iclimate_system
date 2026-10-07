<?php

namespace App\Services\Farmer;

use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Models\YieldPredictionFeedback;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * "Ano ang tunay mong ani?" (2026-10, Farmer – Naicel): after a harvest is recorded the farmer is asked for the actual
 * yield in t/ha, shown next to the predicted one. The answer is saved on the planting record (actual_yield) and, with a
 * snapshot of the prediction, in yield_prediction_feedback, linked to the farm and the season.
 *
 * Stored data only. Nothing here retrains, queues a retraining of, or edits the rice-yield model or its dataset.
 */
class YieldSurveyService
{
    public static function ready(): bool
    {
        return SchemaCache::hasTable('yield_prediction_feedback') && SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield');
    }

    /** The farmer's latest harvested planting that has no actual yield yet; null when there is nothing to ask. */
    public function pending(User $user): ?FarmerPlantingSchedule
    {
        if (! self::ready()) {
            return null;
        }

        return FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            ->whereNull('actual_yield')
            ->where(fn ($q) => $q->whereNotNull('harvested_on')->orWhere('progress_status', 'Harvested'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('yield_prediction_feedback')->whereColumn('yield_prediction_feedback.farmer_planting_schedule_id', 'farmer_planting_schedules.id'))
            ->orderByDesc('harvested_on')->orderByDesc('id')
            ->first();
    }

    /**
     * The yield that was predicted for this planting: the estimate saved on the record, else the farm's saved
     * season estimate. Only read, never computed here.
     *
     * @return array{value: ?float, source: ?string, model_version: ?string, generated_at: ?CarbonImmutable}
     */
    public function predictedFor(FarmerPlantingSchedule $schedule): array
    {
        if (is_numeric($schedule->predicted_yield) && (float) $schedule->predicted_yield > 0) {
            return [
                'value' => (float) $schedule->predicted_yield,
                'source' => $schedule->prediction_source,
                'model_version' => data_get($schedule->prediction_details, 'model_version'),
                'generated_at' => $schedule->prediction_generated_at?->toImmutable(),
            ];
        }

        $stored = $schedule->farm_id ? Cache::get(FarmerDashboardService::farmPredictionCacheKey((int) $schedule->user_id, (int) $schedule->farm_id)) : null;
        $stored = is_array($stored) && ! ($stored['failed'] ?? false) ? $stored : FarmerDashboardService::storedEstimate((int) $schedule->user_id);
        $value = is_array($stored) ? ($stored['yield_tons_per_hectare'] ?? $stored['predicted_yield'] ?? null) : null;
        if (! is_numeric($value) || (float) $value <= 0) {
            return ['value' => null, 'source' => null, 'model_version' => null, 'generated_at' => null];
        }

        return [
            'value' => round((float) $value, 2),
            'source' => (string) ($stored['source'] ?? 'iClimate Rice Harvest Estimate'),
            'model_version' => isset($stored['model_version']) ? (string) $stored['model_version'] : null,
            'generated_at' => ! empty($stored['ran_at']) ? CarbonImmutable::parse($stored['ran_at']) : null,
        ];
    }

    /** Saves the actual yield on the planting record and keeps it with the prediction made for it. */
    public function record(FarmerPlantingSchedule $schedule, float $actual): ?YieldPredictionFeedback
    {
        if (! self::ready()) {
            return null;
        }

        $predicted = $this->predictedFor($schedule);
        $schedule->forceFill(['actual_yield' => round($actual, 2), 'progress_status' => 'Harvested'])->save();

        $existing = YieldPredictionFeedback::query()->where('farmer_planting_schedule_id', $schedule->id)->first();
        $answer = ['actual_yield' => round($actual, 2), 'harvested_on' => $schedule->harvested_on, 'submitted_at' => now()];
        if ($existing) {
            // A corrected answer keeps the prediction it was first compared with.
            $existing->update($answer);

            return $existing;
        }

        $season = $schedule->season ?: RiceSeason::forDate($schedule->planting_date);

        return YieldPredictionFeedback::query()->create($answer + [
            'user_id' => $schedule->user_id,
            'farm_id' => $schedule->farm_id,
            'farmer_planting_schedule_id' => $schedule->id,
            'season' => $season,
            'season_year' => RiceSeason::window($schedule->planting_date)['start']->year,
            'barangay' => $schedule->barangay,
            'farm_type' => $schedule->farm_type,
            'predicted_yield' => $predicted['value'],
            'prediction_source' => $predicted['source'],
            'prediction_model_version' => $predicted['model_version'],
            'prediction_generated_at' => $predicted['generated_at'],
        ]);
    }
}
