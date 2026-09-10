<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Services\SystemAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends CrudController
{
    protected string $model = Report::class;

    protected string $routeName = 'reports';

    protected string $title = 'Report';

    protected array $columns = ['report_type' => 'Report Type', 'title' => 'Title'];

    protected array $searchable = ['report_type', 'title'];

    protected array $filterable = ['report_type' => ['Climate Records', 'Rice Production', 'Farmer Registration', 'Advisory', 'Community Feed']];

    public const REPORT_TYPES = [
        'climate_records' => 'Climate Records Report',
        'rice_production' => 'Rice Production Report',
        'farmer_registration' => 'Farmer Registration Report',
        'advisory' => 'Advisory Report',
        'community_feed' => 'Community Feed Report',
    ];

    /**
     * On-screen results are paginated so a filtered report with years of
     * imported PAGASA/MAO history doesn't render thousands of rows into one
     * HTML table (see docs/phase2_gap_analysis.md — this table is expected
     * to grow substantially once real historical data is imported).
     */
    private const RESULTS_PER_PAGE = 50;

    /**
     * Print view renders everything on one page (that's the point of
     * printing a report), so it can't be paginated the same way — instead,
     * cap how many rows it will render at once and ask the user to narrow
     * filters beyond this, rather than silently trying to lay out
     * thousands of rows in a browser print view.
     */
    private const PRINT_ROW_CAP = 2000;

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
            'history' => $this->reportHistory()->paginate(10),
            'barangays' => $this->barangays(),
            'seasons' => ['Wet', 'Dry'],
            'sourceStats' => $this->sourceStats(),
            'selected' => null,
            'rows' => collect(),
            'columns' => [],
            'filters' => [],
        ]);
    }

    /**
     * Validates filters, logs a Report history row, then redirects (GET) to
     * results() — a standard POST/redirect/GET flow. This also means
     * reloading the results page (or clicking a pagination link) no longer
     * resubmits the form and creates a duplicate history row each time,
     * which the previous single-action version did.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$query] = $this->reportQuery($data);
        $label = self::REPORT_TYPES[$data['report_type']];
        $rowCount = (clone $query)->count();

        $report = Report::query()->create([
            'report_type' => $label,
            'title' => $label.' - '.now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->id,
            'payload' => [
                'filters' => $data,
                'row_count' => $rowCount,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
        SystemAuditLogger::forModel('generated', $report, $request, [
            'report_type' => $label,
            'row_count' => $rowCount,
            'filters' => $data,
        ]);

        return redirect()->route('reports.results', $data)
            ->with('success', $label.' generated and saved to report history.');
    }

    /**
     * Renders the paginated results for an already-generated report. This
     * is the GET target pagination links point at (report_type + filters
     * carried as query string via withQueryString()).
     */
    public function results(Request $request): View
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$query, $columns] = $this->reportQuery($data);
        $label = self::REPORT_TYPES[$data['report_type']];

        return view('reports.index', [
            'reportTypes' => self::REPORT_TYPES,
            'history' => $this->reportHistory()->paginate(10),
            'barangays' => $this->barangays(),
            'seasons' => ['Wet', 'Dry'],
            'sourceStats' => $this->sourceStats(),
            'selected' => $label,
            'rows' => $query->paginate(self::RESULTS_PER_PAGE)->withQueryString(),
            'columns' => $columns,
            'filters' => $data,
        ]);
    }

    public function print(Request $request): View
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$query, $columns] = $this->reportQuery($data);
        $totalRows = (clone $query)->count();
        $tooManyRows = $totalRows > self::PRINT_ROW_CAP;

        return view('reports.print', [
            'title' => self::REPORT_TYPES[$data['report_type']],
            'rows' => $tooManyRows ? collect() : $query->get(),
            'columns' => $columns,
            'filters' => $data,
            'totalRows' => $totalRows,
            'tooManyRows' => $tooManyRows,
            'rowCap' => self::PRINT_ROW_CAP,
        ]);
    }

    /**
     * Streams the CSV instead of buffering every row in memory before
     * writing the response, so exporting a genuinely large filtered report
     * (that's the point of CSV export — unlike print, it isn't row-capped)
     * doesn't hold the whole result set in PHP memory at once.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        [$query, $columns] = $this->reportQuery($data);
        $title = self::REPORT_TYPES[$data['report_type']];
        $rowCount = (clone $query)->count();

        $report = Report::query()->create([
            'report_type' => $title,
            'title' => $title.' CSV Export - '.now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->id,
            'payload' => ['filters' => $data, 'row_count' => $rowCount, 'export' => 'csv'],
        ]);
        SystemAuditLogger::forModel('exported CSV for', $report, $request, [
            'report_type' => $title,
            'row_count' => $rowCount,
            'filters' => $data,
        ]);

        $filename = Str::slug($title).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($columns));

            $query->chunk(500, function ($rows) use ($handle, $columns) {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn ($field) => data_get($row, $field), array_keys($columns)));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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

    /**
     * Builds the filtered query for a report type without executing it, so
     * callers can paginate, count, stream-chunk, or cap it as appropriate
     * instead of every code path being forced through one unbounded get().
     *
     * @return array{0: Builder, 1: array<string, string>}
     */
    private function reportQuery(array $filters): array
    {
        return match ($filters['report_type']) {
            'climate_records' => $this->climateReportQuery($filters),
            'rice_production' => $this->riceReportQuery($filters),
            'farmer_registration' => $this->farmerReportQuery($filters),
            'advisory' => $this->advisoryReportQuery($filters),
            'community_feed' => $this->communityFeedReportQuery($filters),
        };
    }

    private function climateReportQuery(array $filters): array
    {
        $query = ClimateRecord::query();
        $this->applyDateFilters($query, 'record_date', $filters);
        $this->applySeasonFilter($query, $filters);
        $query->latest('record_date');

        return [$query, [
            'record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source',
        ]];
    }

    private function riceReportQuery(array $filters): array
    {
        $query = RiceProduction::query();
        $this->applyYearFilter($query, $filters);
        $this->applyBarangayFilter($query, $filters);
        $this->applySeasonFilter($query, $filters);
        $query->latest('year');

        return [$query, [
            'barangay' => 'Barangay', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield/Ha', 'area_hectares' => 'Area', 'total_production' => 'Total', 'year' => 'Year',
        ]];
    }

    private function farmerReportQuery(array $filters): array
    {
        $query = FarmerProfile::query();
        $this->applyDateFilters($query, 'created_at', $filters);
        $this->applyBarangayFilter($query, $filters);
        $query->latest();

        return [$query, [
            'full_name' => 'Farmer', 'contact_number' => 'Contact', 'barangay' => 'Barangay', 'farm_area' => 'Farm Area', 'farm_type' => 'Farm Type', 'created_at' => 'Registered',
        ]];
    }

    private function advisoryReportQuery(array $filters): array
    {
        $query = PlantingAdvisory::query();
        $this->applyDateFilters($query, 'created_at', $filters);
        if (! empty($filters['barangay'])) {
            $query->where('target_barangay', $filters['barangay']);
        }
        $query->latest();

        return [$query, [
            'title' => 'Title', 'type' => 'Type', 'target_barangay' => 'Barangay', 'status' => 'Status', 'created_at' => 'Posted',
        ]];
    }

    private function communityFeedReportQuery(array $filters): array
    {
        $query = FeedPost::query()->with('author');
        $this->applyDateFilters($query, 'created_at', $filters);
        $query->latest();

        return [$query, [
            'title' => 'Title', 'category' => 'Category', 'visibility' => 'Visibility', 'author.name' => 'Posted By', 'created_at' => 'Posted',
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

    private function reportHistory(): Builder
    {
        return Report::query()
            ->with('generatedBy')
            ->whereIn('report_type', array_values(self::REPORT_TYPES))
            ->latest();
    }

    private function sourceStats(): array
    {
        return [
            'climate_records' => ClimateRecord::query()->count(),
            'rice_production' => RiceProduction::query()->count(),
            'farmers' => FarmerProfile::query()->count(),
            'advisories' => PlantingAdvisory::query()->count(),
            'community_posts' => FeedPost::query()->count(),
            'last_refreshed' => now(),
        ];
    }
}
