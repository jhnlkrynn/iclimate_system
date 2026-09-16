<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;

class AnalyticsPayloadFactory
{
    public function make(AnalyticsRun $run): array
    {
        return match ($run->type) {
            'weather_prediction' => $this->weather($run->input),
            'rice_yield_prediction' => $this->riceYield($run->input),
            'heatmap_risk' => $this->heatmap($run->input),
            'advisory_ai' => $this->advisory($run->input),
            'report_analytics' => $this->report($run->input),
            'dataset_processing' => $this->dataset($run->input),
            default => throw new \InvalidArgumentException("Unsupported analytics type: {$run->type}"),
        };
    }

    private function weather(array $input): array
    {
        return $input + ['climate_records' => ClimateRecord::query()->orderBy('record_date')->get()->toArray()];
    }

    private function riceYield(array $input): array
    {
        return $input + [
            'climate_records' => ClimateRecord::query()->orderBy('record_date')->get()->toArray(),
            'rice_productions' => RiceProduction::query()->orderBy('year')->get()->toArray(),
        ];
    }

    private function heatmap(array $input): array
    {
        return $input + [
            'areas' => HeatmapArea::query()->get()->toArray(),
            'climate_records' => ClimateRecord::query()->orderBy('record_date')->get()->toArray(),
            'rice_productions' => RiceProduction::query()->orderBy('year')->get()->toArray(),
        ];
    }

    private function advisory(array $input): array
    {
        return $input + [
            'weather_records' => ExternalWeatherData::query()->latest('forecast_date')->limit(32)->get()->toArray(),
        ];
    }

    private function report(array $input): array
    {
        $rows = match ($input['report_type']) {
            'climate_records' => ClimateRecord::query()->get()->toArray(),
            'rice_production' => RiceProduction::query()->get()->toArray(),
            'farmer_registration' => FarmerProfile::query()->get()->toArray(),
            'advisory' => PlantingAdvisory::query()->get()->toArray(),
            'community_feed' => FeedPost::query()->with('author')->get()->toArray(),
        };

        return $input + ['source_rows' => $rows];
    }

    private function dataset(array $input): array
    {
        return $input + [
            'climate_records' => ClimateRecord::query()->orderBy('record_date')->get()->toArray(),
            'rice_productions' => RiceProduction::query()->orderBy('year')->get()->toArray(),
        ];
    }
}
