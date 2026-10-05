<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Services\Climate\ClimateRecordHistory;

class AnalyticsPayloadFactory
{
    public function make(AnalyticsRun $run): array
    {
        return match ($run->type) {
            'weather_prediction' => $this->weather($run->input),
            'rice_yield_prediction' => $this->riceYield($run->input),
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

    private function advisory(array $input): array
    {
        return $input + [
            'weather_records' => ExternalWeatherData::query()->latest('forecast_date')->limit(32)->get()->toArray(),
        ];
    }

    private function report(array $input): array
    {
        $rows = match ($input['report_type']) {
            'climate_records' => $this->climateReportRows(),
            'rice_production' => RiceProduction::query()->get()->toArray(),
            'farmer_registration' => FarmerProfile::query()->get()->toArray(),
            'advisory' => PlantingAdvisory::query()->get()->toArray(),
            'community_feed' => FeedPost::query()->with('author')->get()->toArray(),
        };

        return $input + ['source_rows' => $rows];
    }

    /**
     * The climate report lists the same records as Climate Records, each marked with its group so the report never
     * mixes them: historical PAGASA Ambulong months (date "2024-01", rainfall = the month's total) and entered
     * records (one date each).
     *
     * @return list<array<string, mixed>>
     */
    private function climateReportRows(): array
    {
        [$rows] = app(ClimateRecordHistory::class)->records(plausibleOnly: false);

        return $rows->map(fn (object $row): array => [
            'record_type' => $row->group === ClimateRecordHistory::HISTORICAL ? Report::CLIMATE_HISTORICAL : Report::CLIMATE_ENTERED,
            'record_date' => $row->period === 'month' ? $row->record_date->format('Y-m') : $row->record_date->format('Y-m-d'),
            'period' => $row->period,
            'rainfall' => $row->rainfall,
            'temperature' => $row->temperature,
            'humidity' => $row->humidity,
            // The station's wind figure has no confirmed unit, so the report does not give it as a number.
            'wind_speed' => $row->group === ClimateRecordHistory::HISTORICAL ? ($row->wind_speed !== null ? 'Unit not confirmed' : null) : $row->wind_speed,
            'season' => $row->season,
            'source' => $row->source,
        ])->values()->all();
    }

    private function dataset(array $input): array
    {
        return $input + [
            'climate_records' => ClimateRecord::query()->orderBy('record_date')->get()->toArray(),
            'rice_productions' => RiceProduction::query()->orderBy('year')->get()->toArray(),
        ];
    }
}
