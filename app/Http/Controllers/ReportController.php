<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends CrudController
{
    protected string $model = Report::class;
    protected string $routeName = 'reports';
    protected string $title = 'Report';
    protected array $columns = ['report_type' => 'Report Type', 'title' => 'Title'];
    protected array $searchable = ['report_type', 'title'];
    protected array $filterable = ['report_type' => ['Climate Records', 'Rice Production', 'Farmer Registration', 'Advisory', 'Announcement']];

    public const REPORT_TYPES = [
        'climate_records' => 'Climate Records Report',
        'rice_production' => 'Rice Production Report',
        'farmer_registration' => 'Farmer Registration Report',
        'advisory' => 'Advisory Report',
        'announcement' => 'Announcement Report',
    ];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'report_type', 'label' => 'Report Type'],
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'payload', 'label' => 'Payload JSON', 'type' => 'textarea'],
        ];
    }

    public function index(Request $request): View
    {
        $this->authorizeManage($request);

        return view('reports.index', [
            'reportTypes' => self::REPORT_TYPES,
            'history' => Report::query()->with('generatedBy')->latest()->paginate(10),
            'barangays' => $this->barangays(),
            'seasons' => ['Wet', 'Dry'],
            'selected' => null,
            'rows' => collect(),
            'columns' => [],
            'filters' => [],
        ]);
    }

    public function generate(Request $request): View
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$rows, $columns] = $this->reportData($data);
        $label = self::REPORT_TYPES[$data['report_type']];

        Report::query()->create([
            'report_type' => $label,
            'title' => $label.' - '.now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->id,
            'payload' => [
                'filters' => $data,
                'row_count' => $rows->count(),
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);

        return view('reports.index', [
            'reportTypes' => self::REPORT_TYPES,
            'history' => Report::query()->with('generatedBy')->latest()->paginate(10),
            'barangays' => $this->barangays(),
            'seasons' => ['Wet', 'Dry'],
            'selected' => $label,
            'rows' => $rows,
            'columns' => $columns,
            'filters' => $data,
        ])->with('success', $label.' generated and saved to report history.');
    }

    public function print(Request $request): View
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$rows, $columns] = $this->reportData($data);

        return view('reports.print', [
            'title' => self::REPORT_TYPES[$data['report_type']],
            'rows' => $rows,
            'columns' => $columns,
            'filters' => $data,
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$rows, $columns] = $this->reportData($data);
        $title = self::REPORT_TYPES[$data['report_type']];

        Report::query()->create([
            'report_type' => $title,
            'title' => $title.' CSV Export - '.now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->id,
            'payload' => ['filters' => $data, 'row_count' => $rows->count(), 'export' => 'csv'],
        ]);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_values($columns));
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($field) => data_get($row, $field), array_keys($columns)));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($title).'-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    protected function prepareData(Request $request, array $data): array
    {
        $data['generated_by'] = $request->user()->id;
        if (isset($data['payload']) && is_string($data['payload'])) {
            $data['payload'] = json_decode($data['payload'], true) ?: ['notes' => $data['payload']];
        }
        return $data;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'report_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'string'],
        ];
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', Rule::in(array_keys(self::REPORT_TYPES))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'season' => ['nullable', Rule::in(['Wet', 'Dry'])],
        ]);
    }

    private function reportData(array $filters): array
    {
        return match ($filters['report_type']) {
            'climate_records' => $this->climateReport($filters),
            'rice_production' => $this->riceReport($filters),
            'farmer_registration' => $this->farmerReport($filters),
            'advisory' => $this->advisoryReport($filters),
            'announcement' => $this->announcementReport($filters),
        };
    }

    private function climateReport(array $filters): array
    {
        $query = ClimateRecord::query();
        $this->applyDateFilters($query, 'record_date', $filters);
        $this->applySeasonFilter($query, $filters);

        return [$query->latest('record_date')->get(), [
            'record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source',
        ]];
    }

    private function riceReport(array $filters): array
    {
        $query = RiceProduction::query();
        $this->applyYearFilter($query, $filters);
        $this->applyBarangayFilter($query, $filters);
        $this->applySeasonFilter($query, $filters);

        return [$query->latest('year')->get(), [
            'barangay' => 'Barangay', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield/Ha', 'area_hectares' => 'Area', 'total_production' => 'Total', 'year' => 'Year',
        ]];
    }

    private function farmerReport(array $filters): array
    {
        $query = FarmerProfile::query();
        $this->applyDateFilters($query, 'created_at', $filters);
        $this->applyBarangayFilter($query, $filters);

        return [$query->latest()->get(), [
            'full_name' => 'Farmer', 'contact_number' => 'Contact', 'barangay' => 'Barangay', 'farm_area' => 'Farm Area', 'farm_type' => 'Farm Type', 'created_at' => 'Registered',
        ]];
    }

    private function advisoryReport(array $filters): array
    {
        $query = PlantingAdvisory::query();
        $this->applyDateFilters($query, 'created_at', $filters);
        if (! empty($filters['barangay'])) {
            $query->where('target_barangay', $filters['barangay']);
        }

        return [$query->latest()->get(), [
            'title' => 'Title', 'type' => 'Type', 'target_barangay' => 'Barangay', 'status' => 'Status', 'created_at' => 'Posted',
        ]];
    }

    private function announcementReport(array $filters): array
    {
        $query = Announcement::query();
        $this->applyDateFilters($query, 'created_at', $filters);

        return [$query->latest()->get(), [
            'title' => 'Title', 'category' => 'Category', 'status' => 'Status', 'created_at' => 'Posted',
        ]];
    }

    private function applyDateFilters(Builder $query, string $field, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($field, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($field, '<=', $filters['date_to']);
        }
    }

    private function applyYearFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
    }

    private function applyBarangayFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['barangay'])) {
            $query->where('barangay', $filters['barangay']);
        }
    }

    private function applySeasonFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['season'])) {
            $query->where('season', $filters['season']);
        }
    }

    private function barangays(): array
    {
        return collect()
            ->merge(FarmerProfile::query()->pluck('barangay'))
            ->merge(RiceProduction::query()->pluck('barangay'))
            ->merge(PlantingAdvisory::query()->pluck('target_barangay'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}