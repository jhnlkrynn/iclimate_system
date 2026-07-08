<?php

namespace App\Http\Controllers;

use App\Models\PlantingAdvisory;
use App\Support\LianBarangays;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantingAdvisoryController extends CrudController
{
    protected string $model = PlantingAdvisory::class;
    protected string $routeName = 'planting-advisories';
    protected string $title = 'Planting Advisory';
    protected array $columns = ['title' => 'Title', 'type' => 'Type', 'target_barangay' => 'Target Barangay', 'status' => 'Status'];
    protected array $searchable = ['title', 'content', 'type', 'target_barangay', 'status'];
    protected array $filterable = ['type' => ['Planting', 'Harvesting', 'Irrigation', 'Climate'], 'status' => ['Draft', 'Published']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'content', 'label' => 'Content', 'type' => 'textarea'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['Planting' => 'Planting', 'Harvesting' => 'Harvesting', 'Irrigation' => 'Irrigation', 'Climate' => 'Climate']],
            ['name' => 'target_barangay', 'label' => 'Target Barangay', 'type' => 'select', 'options' => LianBarangays::options()],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Draft' => 'Draft', 'Published' => 'Published']],
        ];
    }

    protected function prepareData(Request $request, array $data): array
    {
        $data['posted_by'] = $request->user()->id;
        return $data;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', Rule::in(['Planting', 'Harvesting', 'Irrigation', 'Climate'])],
            'target_barangay' => ['nullable', 'string', Rule::in(LianBarangays::all())],
            'status' => ['required', Rule::in(['Draft', 'Published'])],
        ];
    }
}
