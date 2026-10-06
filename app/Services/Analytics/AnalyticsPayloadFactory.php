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
use App\Support\ClimateHistory;

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

    // Both predictions read the same climate history as the dashboard forecast (App\Support\ClimateHistory):
    // plausible entered records when they cover six months, otherwise the Ambulong station record.
    private function weather(array $input): array
    {
        return $input + ['climate_records' => ClimateHistory::forModels(), 'prepared_forecast' => $this->preparedForecast($input)];
    }

    /**
     * The month's forecast exactly as the Heat Map, the dashboards and the dashboard yield forecast already
     * show it (PredictionOrchestrator). The Python service uses it as given instead of training its own
     * second model, which used to answer with slightly different numbers for the same month
     * (e.g. 146.6 mm on the Predictions page against 152 mm on the Heat Map). Null when that month has no
     * usable forecast: Python then makes its own from the climate history, as before.
     *
     * @return array<string, mixed>|null
     */
    private function preparedForecast(array $input): ?array
    {
        if (empty($input['target_date'])) {
            return null;
        }

        try {
            $month = \Carbon\CarbonImmutable::parse((string) $input['target_date'])->startOfMonth();
            $forecast = app(\App\Services\Prediction\PredictionOrchestrator::class)->monthlyWeatherForecast($month);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        if (! ($forecast['ready'] ?? false) || ! is_array($forecast['predictions'] ?? null)) {
            return null;
        }

        // Plain JSON values only (dates become strings), the same as it will arrive in Python.
        return json_decode((string) json_encode($forecast), true) ?: null;
    }

    private function riceYield(array $input): array
    {
        return $input + [
            'prepared_forecast' => $this->preparedForecast($input),
            'climate_records' => ClimateHistory::forModels(),
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

        // Stored times are UTC. A report shows, filters and sorts by Philippine time ("Registered", "Posted"),
        // written plainly (2026-10-06 01:25) instead of a raw ISO string; a record date is a calendar day as entered.
        $timezone = (string) config('iclimate.timezone', 'Asia/Manila');
        $rows = array_map(function (array $row) use ($timezone): array {
            foreach (['created_at', 'updated_at'] as $field) {
                if (! empty($row[$field])) {
                    $row[$field] = rescue(fn () => \Illuminate\Support\Carbon::parse($row[$field])->timezone($timezone)->format('Y-m-d H:i'), $row[$field], false);
                }
            }
            if (! empty($row['record_date'])) {
                $row['record_date'] = substr((string) $row['record_date'], 0, 10);
            }

            return $row;
        }, $rows);

        // An empty filter list must reach Python as an object ({}), not a list ([]): every unfiltered report failed.
        return ['filters' => (object) ($input['filters'] ?? [])] + $input + ['source_rows' => $rows];
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
