<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\User;
use App\Services\Climate\ClimateRecordHistory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClimateRecordController extends CrudController
{
    protected string $model = ClimateRecord::class;

    protected string $routeName = 'climate-records';

    protected string $title = 'Climate Record';

    /** Climate data is the MAO's to enter and correct; IT views it (2026-10). */
    protected array $manageableRoles = [User::ROLE_MAO];

    protected bool $printable = true;

    // Manuscript – Naicel (2026-10-08): Validated (verified_at, set by RecordValidationController).
    protected array $columns = ['record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'season' => 'Season', 'source' => 'Source', 'verification' => 'Validated'];

    protected array $searchable = ['record_date', 'season', 'source'];

    // Season and source choices come from the records themselves (see index()), so no filter names a source that has no records.
    protected array $filterable = ['season' => [], 'source' => []];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'record_date';

    /** The Ambulong station source and its farmer-facing labels; defined once on ClimateRecordHistory. */
    public const AMBULONG_SOURCE = ClimateRecordHistory::AMBULONG_SOURCE;

    public const AMBULONG_ROW_LABEL = ClimateRecordHistory::AMBULONG_ROW_LABEL;

    public const AMBULONG_FILTER_LABEL = ClimateRecordHistory::AMBULONG_FILTER_LABEL;

    private const FARMER_PER_PAGE = CrudController::PER_PAGE;

    /** Manuscript – Naicel (2026-10-08): the Validated filter, shared with RiceProductionController. */
    public const VERIFICATION_CHOICES = ['Validated' => 'Validated', 'Not validated' => 'Not validated', 'Published dataset' => 'Published dataset'];

    /**
     * Everyone sees the stored climate history: the Ambulong monthly record (database/data/Ambulong Monthly Data.csv,
     * read through the same loader as the forecast model) plus the climate_records rows. Nothing is written or
     * invented. Farmers get their own page and only plausible entered rows; MAO and IT get the management list,
     * where the Ambulong rows are read-only and entered rows keep View / Edit / Delete.
     */
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        if (! $this->usesFarmerPages($request)) {
            return $this->staffIndex($request);
        }

        [$all, $unavailable] = app(ClimateRecordHistory::class)->records(plausibleOnly: true);
        $search = trim((string) $request->query('search', ''));
        $season = (string) $request->query('season', '');
        $source = (string) $request->query('source', '');
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';
        $needle = mb_strtolower($search);

        $filtered = $all
            ->filter(fn (object $row): bool => ($needle === '' || str_contains($row->search_text, $needle))
                && ($season === '' || $row->season === $season)
                && ($source === '' || $row->source === $source))
            ->sortBy(fn (object $row): string => $row->record_date->format('Y-m-d').'|'.$row->source, SORT_STRING, $sort === 'newest')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $records = new LengthAwarePaginator(
            $filtered->forPage($page, self::FARMER_PER_PAGE)->values(),
            $filtered->count(),
            self::FARMER_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('farmer-records.climate-records', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'search' => $search,
            'sort' => $sort,
            // Filter choices come from the records themselves, in their own terms.
            'filterable' => [
                'season' => $all->pluck('season')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v])->all(),
                'source' => $all->pluck('source')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v === self::AMBULONG_SOURCE ? self::AMBULONG_FILTER_LABEL : $v])->all(),
            ],
            'records' => $records,
            'stats' => $this->farmerStats($filtered),
            // Latest 12 with rainfall, oldest first, so the bars read left to right in time.
            'rainBars' => $filtered->filter(fn ($row) => $row->rainfall !== null)->sortByDesc(fn ($row) => $row->record_date->format('Y-m-d'))->take(12)->sortBy(fn ($row) => $row->record_date->format('Y-m-d'))->values(),
            'sources' => $filtered->pluck('source')->filter()->unique()->values(),
            'totalRecords' => $all->count(),
            'unavailable' => $unavailable,
        ]);
    }

    /**
     * The staff list: Ambulong rows (read-only, with their true station name as the source) and every entered
     * climate_records row, including implausible ones staff need to correct.
     */
    private function staffIndex(Request $request): View
    {
        [$rows] = app(ClimateRecordHistory::class)->records(plausibleOnly: false);
        $isIt = $request->user()?->role === User::ROLE_IT_EXPERT;
        // Manuscript – Naicel (2026-10-08): which entered rows the MAO validated; the station dataset is a published source.
        $verified = rescue(fn () => ClimateRecord::query()->whereNotNull('verified_at')->pluck('verified_at', 'id'), collect(), false);

        $display = $rows->map(fn (object $row): object => (object) [
            'id' => $row->id,
            'is_source_dataset' => $row->id === null,
            'source_note' => 'iClimate weather reference for Lian · read-only',
            'record_date' => $row->date_label,
            'rainfall' => self::plainNumber($row->rainfall, ' mm'),
            'temperature' => self::plainNumber($row->temperature, ' °C'),
            'humidity' => self::plainNumber($row->humidity, '%'),
            // The station's wind figure has no confirmed unit: IT sees it marked as such, MAO is told why it is absent.
            'wind_speed' => $row->id === null && $row->wind_speed !== null
                ? ($isIt ? self::plainNumber($row->wind_speed).' (unit not confirmed)' : 'Unit not confirmed')
                : self::plainNumber($row->wind_speed),
            'season' => $row->season,
            'source' => $row->source,
            // Manuscript – Naicel (2026-10-08): validation status, and the year and month filters.
            'verification' => $row->id === null ? 'Published dataset' : ($verified->has($row->id) ? 'Validated' : 'Not validated'),
            'year' => $row->record_date->format('Y'),
            'month' => $row->record_date->format('m'),
            'search_text' => $row->search_text,
            'sort_key' => $row->record_date->format('Y-m-d').'|'.$row->source,
        ]);

        return $this->staffRowsIndex($request, $display, [
            'season' => $rows->pluck('season')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v])->all(),
            'source' => $rows->pluck('source')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => ClimateRecordHistory::displaySource($v)])->all(),
            // Manuscript – Naicel
            'year' => $display->pluck('year')->unique()->sortDesc()->mapWithKeys(fn ($v) => [$v => $v])->all(),
            'month' => collect(range(1, 12))->mapWithKeys(fn (int $m) => [sprintf('%02d', $m) => date('F', mktime(0, 0, 0, $m, 1))])->all(),
            'verification' => self::VERIFICATION_CHOICES,
        ]);
    }

    /** Summary of exactly the records listed; each average or total skips records without that value. */
    private function farmerStats(Collection $rows): object
    {
        $values = fn (string $field) => $rows->pluck($field)->filter(fn ($value) => $value !== null);
        $temperature = $values('temperature');
        $rainfall = $values('rainfall');
        $humidity = $values('humidity');

        return (object) [
            'records' => $rows->count(),
            'avg_temp' => $temperature->isNotEmpty() ? $temperature->avg() : null,
            'max_temp' => $temperature->max(),
            'min_temp' => $temperature->min(),
            'total_rain' => $rainfall->isNotEmpty() ? $rainfall->sum() : null,
            'avg_humidity' => $humidity->isNotEmpty() ? $humidity->avg() : null,
            'first_date' => $rows->min(fn ($row) => $row->record_date),
            'last_date' => $rows->max(fn ($row) => $row->record_date),
            'first_label' => $rows->sortBy(fn ($row) => $row->record_date->format('Y-m-d'))->first()?->date_label,
            'last_label' => $rows->sortByDesc(fn ($row) => $row->record_date->format('Y-m-d'))->first()?->date_label,
        ];
    }

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

    /**
     * Numeric guardrails added by the 2026-09 data-quality fix, after an
     * audit found a manually-entered ClimateRecord row (5000mm rainfall,
     * 100C temperature, 500km/h wind, future-dated) had no upper bound to
     * block it. These are PRAGMATIC DATA-ENTRY GUARDRAILS, not scientifically
     * derived Lian-specific thresholds. The bound constants and their
     * reasoning now live on ClimateRecord itself (single source of truth),
     * so analytics consumers (ClimateRecord::scopePlausible()) can apply the
     * exact same bounds without duplicating them here.
     */
    /** Manuscript – Naicel (2026-10-08): the same checks for each row of a CSV import (RecordImportController). */
    public function importRules(): array
    {
        return $this->rules(request());
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            // before_or_equal:today blocks another future-dated bad row like
            // the historical "sir mags" entry.
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'rainfall' => ['nullable', 'numeric', 'min:0', 'max:'.ClimateRecord::RAINFALL_MAX_MM],
            'temperature' => ['nullable', 'numeric', 'min:'.ClimateRecord::TEMPERATURE_MIN_C, 'max:'.ClimateRecord::TEMPERATURE_MAX_C],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wind_speed' => ['nullable', 'numeric', 'min:0', 'max:'.ClimateRecord::WIND_SPEED_MAX],
            'season' => ['required', Rule::in(['Wet', 'Dry'])],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}
