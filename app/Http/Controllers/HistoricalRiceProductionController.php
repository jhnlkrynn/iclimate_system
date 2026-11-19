<?php

namespace App\Http\Controllers;

use App\Models\HistoricalRiceProduction;
use App\Models\User;
use App\Support\LianFarmTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Authorized seasonal benchmark maintenance. Farmers only consume this table; they never write to it. */
class HistoricalRiceProductionController extends CrudController
{
    protected string $model = HistoricalRiceProduction::class;
    protected string $routeName = 'historical-rice-productions';
    protected string $title = 'Historical Rice Yield Benchmark';
    protected array $manageableRoles = [User::ROLE_MAO];
    protected array $columns = [
        'barangay' => 'Barangay', 'season_label' => 'Season', 'area_planted_hectares' => 'Area Planted (ha)',
        'average_yield_t_per_hectare' => 'Average Yield (t/ha)', 'total_production_tons' => 'Total Production (t)',
    ];
    protected array $searchable = ['barangay', 'season', 'season_label', 'season_year', 'source_filename'];
    protected array $filterable = ['season' => ['WS' => 'Wet Season (WS)', 'DS' => 'Dry Season (DS)']];
    protected ?string $barangayColumn = 'barangay';

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => array_combine(LianFarmTypes::riceBarangays(), LianFarmTypes::riceBarangays())],
            ['name' => 'season', 'label' => 'Season code', 'type' => 'select', 'options' => ['WS' => 'Wet Season (WS)', 'DS' => 'Dry Season (DS)']],
            ['name' => 'season_year', 'label' => 'Season start year', 'type' => 'number'],
            ['name' => 'season_label', 'label' => 'Season label', 'type' => 'text'],
            ['name' => 'area_planted_hectares', 'label' => 'Area Planted (ha)', 'type' => 'number', 'step' => '0.0001'],
            ['name' => 'average_yield_t_per_hectare', 'label' => 'Average Yield (t/ha)', 'type' => 'number', 'step' => '0.0001'],
            ['name' => 'total_production_tons', 'label' => 'Total Production (tons)', 'type' => 'number', 'step' => '0.0001'],
            ['name' => 'source_filename', 'label' => 'Source file', 'type' => 'text'],
            ['name' => 'source_sheet', 'label' => 'Source sheet', 'type' => 'text'],
            ['name' => 'source_row', 'label' => 'Source row', 'type' => 'number'],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', Rule::in(LianFarmTypes::riceBarangays())],
            'season' => ['required', Rule::in(['WS', 'DS'])],
            'season_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'season_label' => ['required', 'string', 'max:80'],
            'area_planted_hectares' => ['nullable', 'numeric', 'min:0'],
            'average_yield_t_per_hectare' => ['nullable', 'numeric', 'min:0'],
            'total_production_tons' => ['nullable', 'numeric', 'min:0'],
            'source_filename' => ['nullable', 'string', 'max:255'],
            'source_sheet' => ['nullable', 'string', 'max:255'],
            'source_row' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
