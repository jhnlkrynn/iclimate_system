<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClimateRecordController extends CrudController
{
    protected string $model = ClimateRecord::class;

    protected string $routeName = 'climate-records';

    protected string $title = 'Climate Record';

    protected array $columns = ['record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source'];

    protected array $searchable = ['record_date', 'season', 'source'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'source' => ['PAGASA Historical Climate Record', 'PAGASA Lian Monthly Data', 'PAGASA', 'iClimate Seed Data (synthetic)']];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'record_date';

    protected function farmerIndexData(Request $request, Builder $filtered): array
    {
        $stats = (clone $filtered)->toBase()->selectRaw('count(*) as records, avg(temperature) as avg_temp, max(temperature) as max_temp, min(temperature) as min_temp, sum(rainfall) as total_rain, avg(humidity) as avg_humidity, min(record_date) as first_date, max(record_date) as last_date')->first();

        return [
            'stats' => $stats,
            // Oldest first, so the rainfall bars read left to right in time.
            'rainBars' => (clone $filtered)->whereNotNull('rainfall')->latest('record_date')->limit(12)->get(['id', 'record_date', 'rainfall'])->sortBy('record_date')->values(),
            'sources' => (clone $filtered)->toBase()->distinct()->pluck('source')->filter()->values(),
        ];
    }

    public function __construct()
    {
        $this->fields = [
            ['name' => 'record_date', 'label' => 'Record Date', 'type' => 'date'],
            ['name' => 'rainfall', 'label' => 'Rainfall', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'temperature', 'label' => 'Temperature', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'humidity', 'label' => 'Humidity', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'wind_speed', 'label' => 'Wind Speed', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => ['Wet' => 'Wet', 'Dry' => 'Dry']],
            ['name' => 'source', 'label' => 'Source'],
        ];
    }

    /**
     * Numeric guardrails added by the 2026-09 data-quality fix, after an
     * audit found a manually-entered ClimateRecord row (5000mm rainfall,
     * 100C temperature, 500km/h wind, future-dated) had no upper bound to
     * block it. These are PRAGMATIC DATA-ENTRY GUARDRAILS, not scientifically
     * derived Lian-specific thresholds. The bound constants and their
     * reasoning now live on ClimateRecord itself (single source of truth),
     * so analytics consumers (ClimateRecord::scopePlausible()) can apply the
     * exact same bounds without duplicating them here.
     */
    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            // before_or_equal:today blocks another future-dated bad row like
            // the historical "sir mags" entry.
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'rainfall' => ['nullable', 'numeric', 'min:0', 'max:'.ClimateRecord::RAINFALL_MAX_MM],
            'temperature' => ['nullable', 'numeric', 'min:'.ClimateRecord::TEMPERATURE_MIN_C, 'max:'.ClimateRecord::TEMPERATURE_MAX_C],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wind_speed' => ['nullable', 'numeric', 'min:0', 'max:'.ClimateRecord::WIND_SPEED_MAX],
            'season' => ['required', Rule::in(['Wet', 'Dry'])],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}
