<?php

namespace App\Http\Controllers;

use App\Models\RiceProduction;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiceProductionController extends CrudController
{
    protected string $model = RiceProduction::class;

    protected string $routeName = 'rice-productions';

    protected string $title = 'Rice Production Record';

    protected array $columns = ['barangay' => 'Barangay', 'production_month' => 'Production Month', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield per Hectare', 'area_hectares' => 'Area (ha)', 'total_production' => 'Total Harvest', 'year' => 'Year'];

    protected array $searchable = ['barangay', 'season', 'irrigation_type', 'year', 'remarks'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'irrigation_type' => ['Rainfed', 'Irrigated']];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'year';

    protected function farmerIndexData(Request $request, Builder $filtered): array
    {
        $stats = (clone $filtered)->toBase()->selectRaw('count(*) as records, sum(total_production) as total_production, avg(yield_per_hectare) as avg_yield, sum(area_hectares) as total_area, max(year) as latest_year')->first();

        return [
            'stats' => $stats,
            'yieldBars' => (clone $filtered)->whereNotNull('yield_per_hectare')->orderBy('year')->orderBy('id')->limit(16)->get(['id', 'barangay', 'season', 'year', 'yield_per_hectare']),
            'farmerBarangay' => $request->user()->barangay,
            'ownBarangayCount' => $request->user()->barangay ? (clone $filtered)->where('barangay', $request->user()->barangay)->count() : 0,
        ];
    }

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::options(false)],
            ['name' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => ['Wet' => 'Wet', 'Dry' => 'Dry']],
            ['name' => 'irrigation_type', 'label' => 'Irrigation Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
            ['name' => 'yield_per_hectare', 'label' => 'Yield Per Hectare', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'area_hectares', 'label' => 'Area Hectares', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'total_production', 'label' => 'Total Production', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'year', 'label' => 'Year', 'type' => 'number'],
            ['name' => 'production_month', 'label' => 'Harvest / Production Month', 'type' => 'month'],
            ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea'],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            'season' => ['required', Rule::in(['Wet', 'Dry'])],
            'irrigation_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
            'yield_per_hectare' => ['nullable', 'numeric', 'min:0'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'total_production' => ['nullable', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'production_month' => ['nullable', 'date_format:Y-m'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
