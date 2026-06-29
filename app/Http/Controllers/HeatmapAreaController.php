<?php

namespace App\Http\Controllers;

use App\Models\HeatmapArea;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HeatmapAreaController extends CrudController
{
    protected string $model = HeatmapArea::class;
    protected string $routeName = 'heatmap-areas';
    protected string $title = 'Heat Map Area';
    protected array $columns = ['barangay' => 'Barangay', 'risk_level' => 'Risk Level', 'risk_type' => 'Risk Type', 'description' => 'Description'];
    protected array $searchable = ['barangay', 'risk_level', 'risk_type', 'description'];
    protected array $filterable = ['risk_level' => ['Low', 'Moderate', 'High', 'Severe'], 'risk_type' => ['Flood', 'Drought', 'Typhoon', 'Heat']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay'],
            ['name' => 'risk_level', 'label' => 'Risk Level', 'type' => 'select', 'options' => ['Low' => 'Low', 'Moderate' => 'Moderate', 'High' => 'High', 'Severe' => 'Severe']],
            ['name' => 'risk_type', 'label' => 'Risk Type', 'type' => 'select', 'options' => ['Flood' => 'Flood', 'Drought' => 'Drought', 'Typhoon' => 'Typhoon', 'Heat' => 'Heat']],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $query = $this->baseQuery($request);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('barangay', 'like', "%{$search}%")
                    ->orWhere('risk_level', 'like', "%{$search}%")
                    ->orWhere('risk_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        foreach (['risk_level', 'risk_type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        return view('heatmap-areas.index', [
            'records' => $query->latest()->paginate(12)->withQueryString(),
            'canManage' => $this->canManage($request),
            'riskLevels' => ['Low', 'Moderate', 'High', 'Severe'],
            'riskTypes' => ['Flood', 'Drought', 'Typhoon', 'Heat'],
            'search' => $search,
        ]);
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', 'string', 'max:255'],
            'risk_level' => ['required', Rule::in(['Low', 'Moderate', 'High', 'Severe'])],
            'risk_type' => ['required', Rule::in(['Flood', 'Drought', 'Typhoon', 'Heat'])],
            'description' => ['nullable', 'string'],
        ];
    }
}