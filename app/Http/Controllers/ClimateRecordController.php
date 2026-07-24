<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClimateRecordController extends CrudController
{
    protected string $model = ClimateRecord::class;

    protected string $routeName = 'climate-records';

    protected string $title = 'Climate Record';

    protected array $columns = ['record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source'];

    protected array $searchable = ['record_date', 'season', 'source'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'source' => ['PAGASA']];

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

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'record_date' => ['required', 'date'],
            'rainfall' => ['nullable', 'numeric', 'min:0'],
            'temperature' => ['nullable', 'numeric'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wind_speed' => ['nullable', 'numeric', 'min:0'],
            'season' => ['required', Rule::in(['Wet', 'Dry'])],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}
