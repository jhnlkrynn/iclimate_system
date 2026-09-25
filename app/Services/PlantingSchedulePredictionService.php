<?php

namespace App\Services;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Services\Prediction\RiceYieldPredictionService;
use Carbon\CarbonImmutable;
use Throwable;

class PlantingSchedulePredictionService
{
    public function __construct(
        private readonly RiceYieldPredictionService $predictor,
    ) {}

    public function refresh(FarmerPlantingSchedule $schedule): FarmerPlantingSchedule
    {
        $schedule->loadMissing('farmer.farmerProfile');
        $harvestDate = $schedule->harvestDateForPlanning();
        $area = $schedule->farm_area_hectares !== null ? (float) $schedule->farm_area_hectares : null;

        if (! $harvestDate) {
            return $this->unavailable($schedule, 'A scheduled harvest date or month is required.');
        }

        if ($area === null || $area <= 0) {
            return $this->unavailable($schedule, 'Farm area is missing. Add the farm area before generating a yield prediction.');
        }

        try {
            $result = $this->predictor->predictForUser(
                CarbonImmutable::instance($harvestDate),
                $schedule->farm_type,
                $area,
                $schedule->farmer,
                ['season' => $schedule->season],
            );

            $prediction = data_get($result, 'predicted_yield');
            $usesEmptyFallback = (bool) data_get($result, 'fallback', false)
                && (int) data_get($result, 'records_used', 0) === 0;

            if ($prediction === null || $usesEmptyFallback) {
                $reason = $usesEmptyFallback
                    ? 'The trained prediction model is unavailable and no historical production records are available for a data-based fallback.'
                    : ((string) data_get($result, 'api_error') ?: 'The yield prediction service did not return a result.');

                return $this->unavailable($schedule, $reason);
            }

            $schedule->forceFill([
                'predicted_yield' => round((float) $prediction, 2),
                'prediction_confidence' => is_numeric(data_get($result, 'confidence')) ? round((float) data_get($result, 'confidence'), 2) : null,
                'prediction_source' => (string) data_get($result, 'source', 'iClimate Rice Harvest Estimate'),
                'prediction_unavailable_reason' => null,
                'prediction_details' => [
                    'model' => data_get($result, 'model'),
                    'model_version' => data_get($result, 'model_version'),
                    'fallback' => (bool) data_get($result, 'fallback', false),
                    'weather_assessment' => data_get($result, 'weather_assessment'),
                    'risk_level' => data_get($result, 'risk_level'),
                    'rainfall' => data_get($result, 'model_input.rainfall'),
                    'temperature' => data_get($result, 'model_input.temp_avg'),
                    'humidity' => data_get($result, 'model_input.humidity'),
                    'wind_speed' => data_get($result, 'model_input.wind_speed'),
                    'api_error' => data_get($result, 'api_error'),
                ],
                'prediction_generated_at' => now(),
            ])->save();

            return $this->compareWithHistory($schedule->fresh());
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable($schedule, 'The iClimate yield prediction service could not complete this schedule. Try again later or ask MAO to review the required data.');
        }
    }

    private function compareWithHistory(FarmerPlantingSchedule $schedule): FarmerPlantingSchedule
    {
        $harvestDate = $schedule->harvestDateForPlanning();
        $query = RiceProduction::query()
            ->whereNotNull('production_month')
            ->whereNotNull('yield_per_hectare')
            ->whereMonth('production_month', $harvestDate->month)
            ->whereYear('production_month', '<', $harvestDate->year);

        if ($schedule->barangay) {
            $query->where('barangay', $schedule->barangay);
        }

        $query->where('season', $schedule->season)
            ->where('irrigation_type', $schedule->farm_type);

        $average = $query->avg('yield_per_hectare');
        if ($average === null) {
            $schedule->forceFill([
                'historical_same_month_average' => null,
                'yield_difference' => null,
                'comparison_interpretation' => 'insufficient_history',
                'comparison_note' => 'No previous yield history available for comparison. No previous same-month history available.',
                'recommended_actions' => null,
            ])->save();

            return $schedule;
        }

        $average = round((float) $average, 2);
        $difference = round((float) $schedule->predicted_yield - $average, 2);
        $interpretation = abs($difference) < 0.05 ? 'stable' : ($difference > 0 ? 'higher' : 'lower');

        $schedule->forceFill([
            'historical_same_month_average' => $average,
            'yield_difference' => $difference,
            'comparison_interpretation' => $interpretation,
            'comparison_note' => $interpretation === 'lower'
                ? 'Predicted yield is lower than the previous same-month average.'
                : 'Predicted yield is '.($interpretation === 'stable' ? 'stable against' : 'higher than').' the previous same-month average.',
            'recommended_actions' => $interpretation === 'lower' ? $this->recommendedActions($schedule) : null,
        ])->save();

        return $schedule;
    }

    private function recommendedActions(FarmerPlantingSchedule $schedule): string
    {
        $details = $schedule->prediction_details ?? [];
        $rainfall = data_get($details, 'rainfall');
        $temperature = data_get($details, 'temperature');
        $reason = match (true) {
            is_numeric($rainfall) && (float) $rainfall < 80 => 'Low rainfall may reduce crop performance, especially where irrigation is limited.',
            is_numeric($rainfall) && (float) $rainfall > 350 => 'High rainfall may increase flooding and field drainage risk.',
            is_numeric($temperature) && (float) $temperature > 34 => 'High temperature may cause heat stress.',
            $schedule->farm_type === 'Rainfed' && $schedule->season === 'Dry' => 'A rainfed farm in the dry season may have limited water availability.',
            default => 'Available model inputs indicate a lower yield, but the exact field cause needs validation.',
        };

        return $reason.' Recommended MAO actions: validate the field, check irrigation status, review rainfall and pest/disease risk, send an advisory to the farmer, and schedule a follow-up.';
    }

    private function unavailable(FarmerPlantingSchedule $schedule, string $reason): FarmerPlantingSchedule
    {
        $schedule->forceFill([
            'predicted_yield' => null,
            'prediction_confidence' => null,
            'prediction_source' => null,
            'prediction_unavailable_reason' => $reason,
            'prediction_details' => null,
            'historical_same_month_average' => null,
            'yield_difference' => null,
            'comparison_interpretation' => null,
            'comparison_note' => null,
            'recommended_actions' => null,
            'prediction_generated_at' => now(),
        ])->save();

        return $schedule;
    }
}
