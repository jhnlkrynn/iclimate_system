<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshHeatmapRisk;
use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\HeatmapRiskService;
use App\Services\WeatherApiService;
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
        $heatmapRiskService = app(HeatmapRiskService::class);
        $this->authorizeView($request);
        $shouldRefresh = $request->boolean('refresh')
            || ! HeatmapArea::query()->whereNotNull('latitude')->whereNotNull('longitude')->exists();

        $refreshWindowClaimed = Cache::add(
            'heatmap:lian:risk-refresh-window',
            true,
            now()->addMinutes(10),
        );

        if ($shouldRefresh || $refreshWindowClaimed) {
            Cache::put('heatmap:lian:risk-refresh-pending', true, now()->addMinutes(5));
            RefreshHeatmapRisk::dispatch()
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'));
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
            ->fromPagasaOnline()
            ->latest('valid_from')
            ->first();

        $myFarmBoundary = null;
        if ($request->user()->role === User::ROLE_FARMER) {
            $boundary = $request->user()->farmerProfile?->farmBoundary;
            if ($boundary) {
                $myFarmBoundary = [
                    'coordinates' => $boundary->boundary_coordinates,
                    'area_hectares' => (float) $boundary->calculated_area_hectares,
                    'perimeter_meters' => (float) $boundary->calculated_perimeter_meters,
                ];
            }
        }

        $latestRecordUpdated = HeatmapArea::query()->latest('updated_at')->value('updated_at');
        $lastUpdatedTimestamp = $latestRecordUpdated
            ? $latestRecordUpdated->timezone(config('app.timezone', 'Asia/Manila'))->format('M d, Y h:i A')
            : now()->timezone(config('app.timezone', 'Asia/Manila'))->format('M d, Y h:i A');

        $liveWeather = app(WeatherApiService::class)->forecast();
        $isLiveWeatherFallback = false;
        if (! $liveWeather) {
            $latestClimate = ClimateRecord::query()->latest('record_date')->first();
            if ($latestClimate) {
                $isLiveWeatherFallback = true;
                $liveWeather = [
                    'current' => [
                        'temperature' => (float) $latestClimate->temperature,
                        'relative_humidity' => (int) $latestClimate->humidity,
                        'wind_speed' => (float) $latestClimate->wind_speed,
                        'precipitation' => (float) $latestClimate->rainfall,
                        'weather_code' => 1,
                        'is_day' => 1,
                    ],
                    'daily' => [],
                    'source' => 'Verified Local Climate Database (Offline Fallback)',
                ];
            }
        }

        // Lian has ~19 barangays total, so the full filtered result set is rendered
        // as one linear list (first 10 shown, rest revealed by a "Show more" toggle)
        // rather than paginated — see HeatmapRiskService::DEFAULT_AREAS.
        $records = $recordsQuery->orderBy('barangay')->get();
        $records->each(function (HeatmapArea $record) use ($heatmapRiskService): void {
            $record->setAttribute('risk_breakdown', $heatmapRiskService->riskBreakdownFor($record->barangay));
        });

        return view('heatmap-areas.index', [
            'records' => $records,
            'mapAreas' => $mapAreasQuery
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
            'myFarmBoundary' => $myFarmBoundary,
            'analyticsRun' => null,
            'liveWeather' => $liveWeather,
            'lastUpdatedTimestamp' => $lastUpdatedTimestamp,
            'isLiveWeatherFallback' => $isLiveWeatherFallback,
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
