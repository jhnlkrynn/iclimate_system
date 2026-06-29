<?php

namespace App\Http\Controllers;

use App\Models\RiceProduction;
use Illuminate\Http\Request;

class RiceProductionController extends CrudController
{
    protected string $model = RiceProduction::class;
    protected string $routeName = 'rice-productions';
    protected string $title = 'Rice Production Record';
    protected array $columns = ['barangay' => 'Barangay', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield/Ha', 'area_hectares' => 'Area', 'total_production' => 'Total', 'year' => 'Year'];
    protected array $searchable = ['barangay', 'season', 'irrigation_type', 'year', 'remarks'];
    protected array $filterable = ['season' => ['Wet', 'Dry'], 'irrigation_type' => ['Rainfed', 'Irrigated']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay'],
            ['name' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => ['Wet' => 'Wet', 'Dry' => 'Dry']],
            ['name' => 'irrigation_type', 'label' => 'Irrigation Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
            ['name' => 'yield_per_hectare', 'label' => 'Yield Per Hectare', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'area_hectares', 'label' => 'Area Hectares', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'total_production', 'label' => 'Total Production', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'year', 'label' => 'Year', 'type' => 'number'],
            ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea'],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string', 'max:255'],
            'irrigation_type' => ['required', 'string', 'max:255'],
            'yield_per_hectare' => ['nullable', 'numeric', 'min:0'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'total_production' => ['nullable', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}