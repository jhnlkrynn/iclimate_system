<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ClimateRecordController extends CrudController
{
    protected string $model = ClimateRecord::class;

    protected string $routeName = 'climate-records';

    protected string $title = 'Climate Record';

    protected array $columns = ['record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source'];

    protected array $searchable = ['record_date', 'season', 'source'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'source' => ['PAGASA Historical Climate Record', 'PAGASA Lian Monthly Data', 'PAGASA', 'iClimate Seed Data (synthetic)']];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'record_date';

    /** How the Ambulong monthly record is labelled; the station is in Tanauan, the nearest PAGASA station to Lian. */
    public const AMBULONG_SOURCE = 'PAGASA Ambulong Station (Tanauan, Batangas)';

    /**
     * Farmer-facing names for the Ambulong rows: the page presents them as the climate reference used for Lian,
     * while the stored source above stays the true one (filters still match it) and the page notes the station.
     */
    public const AMBULONG_ROW_LABEL = 'Lian Climate Reference';

    public const AMBULONG_FILTER_LABEL = 'Lian Past Weather Records';

    private const FARMER_PER_PAGE = 20;

    /**
     * Farmers see the stored climate history: the Ambulong monthly record (database/data/Ambulong Monthly Data.csv,
     * read through the same loader as the forecast model) plus any plausible climate_records rows. Nothing is
     * written or invented; staff keep the climate_records management list.
     */
    public function index(Request $request): View
    {
        if (! $this->usesFarmerPages($request)) {
            return parent::index($request);
        }

        $this->authorizeView($request);

        [$all, $unavailable] = $this->farmerRecords();
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
     * Every stored record a farmer may see, newest data source first. A part that cannot be read is named in
     * $unavailable instead of being reported as "no records".
     *
     * @return array{0: Collection<int, object>, 1: list<string>}
     */
    private function farmerRecords(): array
    {
        $rows = collect();
        $unavailable = [];

        try {
            $dataset = app(AmbulongHistoricalClimateDataset::class)->load();
            if (! ($dataset['available'] ?? false)) {
                throw new \RuntimeException((string) ($dataset['message'] ?? 'Ambulong dataset is not available.'));
            }
            foreach ($dataset['observations'] as $obs) {
                $date = Carbon::create((int) $obs['year'], (int) $obs['month'], 1)->startOfDay();
                $rows->push($this->farmerRow(null, $date, 'month', $obs['rainfall'] ?? null, $obs['temperature'] ?? null, $obs['humidity'] ?? null, $obs['season'] ?? null, self::AMBULONG_SOURCE));
            }
        } catch (Throwable $exception) {
            Log::warning('Climate records: Ambulong monthly record could not be read.', ['message' => $exception->getMessage()]);
            $unavailable[] = 'the Ambulong station record';
        }

        try {
            ClimateRecord::query()->plausible()->get(['id', 'record_date', 'rainfall', 'temperature', 'humidity', 'season', 'source'])
                ->each(fn (ClimateRecord $record) => $rows->push($this->farmerRow($record->id, $record->record_date, 'day', $record->rainfall, $record->temperature, $record->humidity, $record->season, $record->source)));
        } catch (QueryException $exception) {
            Log::warning('Climate records: climate_records could not be read.', ['message' => $exception->getMessage()]);
            $unavailable[] = 'entered climate records';
        }

        return [$rows, $unavailable];
    }

    private function farmerRow(?int $id, ?Carbon $date, string $period, mixed $rainfall, mixed $temperature, mixed $humidity, ?string $season, ?string $source): object
    {
        $date ??= Carbon::create(1970);
        $label = $period === 'month' ? $date->format('M Y') : $date->format('M j, Y');
        // Missing stays missing (null), never 0.
        $number = fn ($value) => is_numeric($value) ? (float) $value : null;

        return (object) [
            'id' => $id,
            'record_date' => $date,
            'date_label' => $label,
            'period' => $period,
            'rainfall' => $number($rainfall),
            'temperature' => $number($temperature),
            'humidity' => $number($humidity),
            'season' => $season ?: null,
            'source' => $source ?: null,
            'source_label' => $source === self::AMBULONG_SOURCE ? self::AMBULONG_ROW_LABEL : ($source ?: null),
            'search_text' => mb_strtolower(implode(' ', [$label, $date->format('F Y'), $date->format('Y-m'), $date->format('Y-m-d'), $season, $source, $source === self::AMBULONG_SOURCE ? self::AMBULONG_ROW_LABEL.' '.self::AMBULONG_FILTER_LABEL : null])),
        ];
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
