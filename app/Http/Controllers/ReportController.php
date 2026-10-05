<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsRun;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Climate\ClimateRecordHistory;
use App\Services\SystemAuditLogger;
use App\Support\ReportTypeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
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

    public const REPORT_TYPES = Report::TYPES;

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
        $analyticsRun = $this->requestedRun($request, 'report_analytics');
        $reportId = data_get($analyticsRun?->result, 'report_id');
        $report = $reportId
            ? $this->reportHistory()->find($reportId)
            : $this->reportHistory()->first();

        return $this->reportView($request, $report, $analyticsRun);
    }

    /**
     * Validates filters, logs a Report history row, then redirects (GET) to
     * results() — a standard POST/redirect/GET flow. This also means
     * reloading the results page (or clicking a pagination link) no longer
     * resubmits the form and creates a duplicate history row each time,
     * which the previous single-action version did.
     */
    public function generate(Request $request, AnalyticsDispatcher $analytics): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validatedFilters($request);
        $label = self::REPORT_TYPES[$data['report_type']];
        $run = $analytics->dispatch('report_analytics', [
            'report_type' => $data['report_type'],
            'filters' => $this->appliedFilters($data),
        ], $request->user());
        SystemAuditLogger::record('Queued Python report analytics', $request, [
            'report_type' => $label,
            'analytics_run_uuid' => $run->uuid,
            'filters' => $this->appliedFilters($data),
        ]);

        return redirect()->route('reports.index', ['run' => $run->uuid])
            ->with('success', $label.' was queued for Python analytics processing.');
    }

    /**
     * Renders the paginated results for an already-generated report. This
     * is the GET target pagination links point at (report_type + filters
     * carried as query string via withQueryString()).
     */
    public function results(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        return redirect()->route('reports.index');
    }

    public function show(Request $request, int $id): View
    {
        $this->authorizeManage($request);
        $report = $this->reportHistory()->findOrFail($id);

        return $this->reportView($request, $report, null);
    }

    public function print(Request $request): View
    {
        $this->authorizeManage($request);

        abort_unless($request->filled('report'), 404);
        $report = $this->reportHistory()->findOrFail($request->integer('report'));
        $rows = collect($report->savedRecords());
        $totalRows = $rows->count();
        $tooManyRows = $totalRows > self::PRINT_ROW_CAP;

        return view('reports.print', [
            'title' => $report->title,
            'rows' => $tooManyRows ? collect() : $rows,
            'columns' => $report->savedColumns(),
            'filters' => $report->savedFilters(),
            'generatedAt' => $report->created_at,
            'totalRows' => $totalRows,
            'tooManyRows' => $tooManyRows,
            'rowCap' => self::PRINT_ROW_CAP,
            'reportTypeInfo' => ReportTypeCatalog::describe($report->typeKey()),
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

        abort_unless($request->filled('report'), 404);
        $report = $this->reportHistory()->findOrFail($request->integer('report'));
        $columns = $report->savedColumns();
        $records = $report->savedRecords();
        $filename = Str::slug($report->title).'.csv';

        return response()->streamDownload(function () use ($records, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($columns));
            foreach ($records as $row) {
                fputcsv($handle, array_map(fn ($field) => data_get($row, $field), array_keys($columns)));
            }
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

    private function barangays(): array
    {
        return Cache::remember('reports:filter-barangays', now()->addMinutes(10), fn (): array => collect()
            ->merge(FarmerProfile::query()->pluck('barangay'))
            ->merge(RiceProduction::query()->pluck('barangay'))
            ->merge(PlantingAdvisory::query()->pluck('target_barangay'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all());
    }

    private function reportHistory(): Builder
    {
        return Report::query()
            ->realGenerated()
            ->with('generatedBy')
            ->latest('created_at')
            ->latest('id');
    }

    private function reportView(Request $request, ?Report $report, ?AnalyticsRun $analyticsRun): View
    {
        $records = $report?->savedRecords() ?? [];
        $filters = $report
            ? ['report_type' => $report->typeKey()] + $report->savedFilters()
            : [];

        return view('reports.index', [
            'reportTypes' => self::REPORT_TYPES,
            'barangays' => $this->barangays(),
            'seasons' => ['Wet', 'Dry'],
            'sourceStats' => $this->sourceStats(),
            'selected' => $report?->report_type,
            'selectedReport' => $report,
            'generatedAt' => $report?->created_at,
            'rows' => $this->paginateSnapshot($records, $request),
            'columns' => $report?->savedColumns() ?? [],
            'filters' => $filters,
            'analyticsRun' => $analyticsRun,
            'reportTypeInfo' => $report ? ReportTypeCatalog::describe($report->typeKey()) : null,
            'reportSummary' => $report?->summary() ?? [],
            'weatherBasis' => $report ? $this->weatherBasis($report) : null,
        ]);
    }

    /**
     * For non-climate report types with an explicit date range filter, shows the weather during that same period as
     * supporting context — the "what was the weather doing" behind the report. It uses the same records as Climate
     * Records, in two separate parts: the historical PAGASA Ambulong months and the entered climate records. Their
     * figures are never averaged together (a month's rainfall total is not one day's reading).
     */
    private function weatherBasis(Report $report): ?array
    {
        if ($report->typeKey() === 'climate_records') {
            return null;
        }

        $filters = $report->savedFilters();
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        if (! $from && ! $to && isset($filters['year'])) {
            $from = $filters['year'].'-01-01';
            $to = $filters['year'].'-12-31';
        }

        if (! $from && ! $to) {
            return null;
        }

        [$rows] = app(ClimateRecordHistory::class)->records(plausibleOnly: false);
        $inRange = ClimateRecordHistory::inRange($rows, $from, $to);

        return [
            'range_label' => $this->dateRangeLabel($from, $to),
            'historical_source' => ClimateRecordHistory::AMBULONG_SOURCE,
            'historical' => ClimateRecordHistory::historicalSummary($inRange->where('group', ClimateRecordHistory::HISTORICAL)),
            'entered' => ClimateRecordHistory::enteredSummary($inRange->where('group', ClimateRecordHistory::ENTERED)),
        ];
    }

    private function dateRangeLabel(?string $from, ?string $to): string
    {
        return match (true) {
            $from && $to => $from.' to '.$to,
            (bool) $from => 'from '.$from,
            (bool) $to => 'through '.$to,
            default => 'the selected period',
        };
    }

    private function requestedRun(Request $request, string $type): ?AnalyticsRun
    {
        if (! $request->filled('run')) {
            return null;
        }

        return AnalyticsRun::query()
            ->where('uuid', $request->query('run'))
            ->where('type', $type)
            ->where('requested_by', $request->user()->id)
            ->firstOrFail();
    }

    private function paginateSnapshot(array $records, Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer('results_page', 1));
        $items = array_slice($records, ($page - 1) * self::RESULTS_PER_PAGE, self::RESULTS_PER_PAGE);

        return (new LengthAwarePaginator(
            $items,
            count($records),
            self::RESULTS_PER_PAGE,
            $page,
            ['path' => $request->url(), 'pageName' => 'results_page']
        ))->withQueryString();
    }

    private function appliedFilters(array $data): array
    {
        $filterKeys = match ($data['report_type']) {
            'climate_records' => ['date_from', 'date_to', 'season'],
            'rice_production' => ['year', 'barangay', 'season'],
            'farmer_registration' => ['date_from', 'date_to', 'barangay'],
            'advisory' => ['date_from', 'date_to', 'barangay'],
            'community_feed' => ['date_from', 'date_to'],
        };

        return collect($data)
            ->only($filterKeys)
            ->reject(fn ($value): bool => blank($value))
            ->all();
    }

    private function sourceStats(): array
    {
        return Cache::remember('reports:source-stats', now()->addMinutes(5), fn (): array => [
            // The same records Climate Records lists: the historical station months plus the entered records.
            'climate_records' => app(ClimateRecordHistory::class)->records(plausibleOnly: false)[0]->count(),
            'rice_production' => RiceProduction::query()->count(),
            'farmers' => FarmerProfile::query()->count(),
            'advisories' => PlantingAdvisory::query()->count(),
            'community_posts' => FeedPost::query()->count(),
            'last_refreshed' => now(),
        ]);
    }
}
