<?php

namespace App\Http\Controllers;

use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Services\HeatmapRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HeatmapAreaController extends CrudController
{
    protected string $model = HeatmapArea::class;

    protected string $routeName = 'heatmap-areas';

    protected string $title = 'Heat Map Area';

    protected array $columns = [
        'barangay' => 'Barangay',
        'predicted_yield' => 'Predicted Rice Yield',
        'rainfall_status' => 'Rainfall Status',
        'risk_level' => 'Risk Level',
        'planting_advisory' => 'Planting Advisory',
        'irrigation_recommendation' => 'Irrigation Recommendation',
    ];

    protected array $searchable = ['barangay', 'risk_level', 'risk_type', 'rainfall_status', 'planting_advisory', 'irrigation_recommendation', 'description'];

    protected array $filterable = ['risk_level' => ['Low', 'Moderate', 'High', 'Severe'], 'risk_type' => ['Flood', 'Drought', 'Typhoon', 'Heat']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay'],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => '0.0000001'],
            ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => '0.0000001'],
            ['name' => 'risk_level', 'label' => 'Risk Level', 'type' => 'select', 'options' => ['Low' => 'Low', 'Moderate' => 'Moderate', 'High' => 'High', 'Severe' => 'Severe']],
            ['name' => 'risk_type', 'label' => 'Risk Type', 'type' => 'select', 'options' => ['Flood' => 'Flood', 'Drought' => 'Drought', 'Typhoon' => 'Typhoon', 'Heat' => 'Heat']],
            ['name' => 'risk_score', 'label' => 'Climate Impact Score', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'predicted_yield', 'label' => 'Predicted Rice Yield', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'rainfall_status', 'label' => 'Rainfall Status'],
            ['name' => 'planting_advisory', 'label' => 'Planting Advisory', 'type' => 'textarea'],
            ['name' => 'irrigation_recommendation', 'label' => 'Irrigation Recommendation', 'type' => 'textarea'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $shouldRefresh = $request->boolean('refresh')
            || ! HeatmapArea::query()->whereNotNull('latitude')->whereNotNull('longitude')->exists();

        if ($shouldRefresh) {
            app(HeatmapRiskService::class)->refresh();
            Cache::put('heatmap:last-refresh', now()->toDateTimeString(), now()->addDay());
        }

        $query = $this->baseQuery($request);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('barangay', 'like', "%{$search}%")
                    ->orWhere('risk_level', 'like', "%{$search}%")
                    ->orWhere('risk_type', 'like', "%{$search}%")
                    ->orWhere('rainfall_status', 'like', "%{$search}%")
                    ->orWhere('planting_advisory', 'like', "%{$search}%")
                    ->orWhere('irrigation_recommendation', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        foreach (['risk_level', 'risk_type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        $recordsQuery = clone $query;
        $mapAreasQuery = clone $query;

        $latestPagasaAdvisory = PlantingAdvisory::query()
            ->active()
            ->where('source', 'PAGASA')
            ->latest('valid_from')
            ->first();

        return view('heatmap-areas.index', [
            'records' => $recordsQuery->latest()->paginate(12)->withQueryString(),
            'mapAreas' => $mapAreasQuery
                ->select([
                    'id',
                    'barangay',
                    'latitude',
                    'longitude',
                    'risk_level',
                    'risk_type',
                    'risk_score',
                    'predicted_yield',
                    'rainfall_status',
                    'planting_advisory',
                    'irrigation_recommendation',
                    'description',
                ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('barangay')
                ->get()
                ->unique('barangay')
                ->values(),
            'canManage' => $this->canManage($request),
            'riskLevels' => ['Low', 'Moderate', 'High', 'Severe'],
            'riskTypes' => ['Flood', 'Drought', 'Typhoon', 'Heat'],
            'search' => $search,
            'latestPagasaAdvisory' => $latestPagasaAdvisory,
            'pagasaMapUrl' => 'https://www.pagasa.dost.gov.ph/?vm=r',
            'pagasaRadarUrl' => 'https://www.pagasa.dost.gov.ph/radar',
        ]);
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'risk_level' => ['required', Rule::in(['Low', 'Moderate', 'High', 'Severe'])],
            'risk_type' => ['required', Rule::in(['Flood', 'Drought', 'Typhoon', 'Heat'])],
            'risk_score' => ['nullable', 'numeric', 'between:0,1'],
            'predicted_yield' => ['nullable', 'numeric', 'min:0'],
            'rainfall_status' => ['nullable', 'string', 'max:255'],
            'planting_advisory' => ['nullable', 'string'],
            'irrigation_recommendation' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
