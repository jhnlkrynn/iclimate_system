<?php

namespace App\Http\Controllers;

use App\Models\RiceProduction;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class RiceProductionController extends CrudController
{
    protected string $model = RiceProduction::class;

    protected string $routeName = 'rice-productions';

    protected string $title = 'Rice Production Record';

    protected bool $printable = true;

    protected array $columns = ['barangay' => 'Barangay', 'production_month' => 'Production Month', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield (t/ha)', 'area_hectares' => 'Area (ha)', 'total_production' => 'Total Harvest (t)', 'year' => 'Year'];

    protected array $searchable = ['barangay', 'season', 'irrigation_type', 'year', 'remarks'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'irrigation_type' => ['Rainfed', 'Irrigated']];

    protected bool $farmerPages = true;


    /** What the dataset rows cover: Lian as a whole, not one barangay. */
    public const DATASET_SCOPE = 'Lian Municipal Average';

    /** The plain source name of the dataset rows (the stored file is not shown to farmers). */
    public const DATASET_SOURCE = 'iClimate rice yield records';

    private const FARMER_PER_PAGE = CrudController::PER_PAGE;

    /**
     * Everyone sees the rice_productions rows together with the project's own yield history (ProjectYieldDataset:
     * the AVERAGE_YIELD column of the modeling dataset the rice-yield model was built from, the same series the
     * dashboards use). Farmers get their own page; MAO and IT get the management list. Dataset rows are read-only.
     */
    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $records = $this->records();

        return $this->usesFarmerPages($request)
            ? $this->farmerIndex($request, $records)
            : $this->staffIndex($request, $records);
    }

    /** @param  Collection<int, object>  $records */
    private function staffIndex(Request $request, Collection $records): View
    {
        $rows = $records->map(fn (object $r): object => (object) [
            'id' => $r->id,
            'is_source_dataset' => $r->is_source_dataset,
            'source_note' => 'iClimate yield dataset · read-only',
            'barangay' => $r->barangay,
            'production_month' => $r->production_month?->format('M Y'),
            'season' => $r->season,
            'irrigation_type' => $r->irrigation_type,
            'yield_per_hectare' => self::plainNumber($r->yield_per_hectare, ' t/ha'),
            'area_hectares' => self::plainNumber($r->area_hectares, ' ha'),
            'total_production' => self::plainNumber($r->total_production, ' t'),
            'year' => $r->year,
            'search_text' => $r->search_text,
            'sort_key' => $r->sort_key,
        ]);

        return $this->staffRowsIndex($request, $rows, $this->filterChoices($records));
    }

    /**
     * The farmer page: the same records, search, filters and newest-first order as the staff list, with figures
     * worked out only from values the records really hold. Municipal rows stay visible whatever the farmer's
     * barangay; they are labelled as Lian's average, never as a barangay's.
     *
     * @param  Collection<int, object>  $records
     */
    private function farmerIndex(Request $request, Collection $records): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $needle = mb_strtolower($search);
        $filterable = $this->filterChoices($records);
        $filters = collect(array_keys($filterable))
            ->mapWithKeys(fn (string $field) => [$field => $request->query($field)])
            ->filter(fn ($value) => is_string($value) && $value !== '');

        $filtered = $records
            ->filter(fn (object $r): bool => ($needle === '' || str_contains($r->search_text, $needle))
                && $filters->every(fn (string $value, string $field): bool => (string) ($r->{$field} ?? '') === $value))
            ->sortBy(fn (object $r): string => $r->sort_key, SORT_STRING, true)
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $barangay = $request->user()->barangay;
        // Each figure uses only the records that hold that value; one that none hold stays missing (null), never 0.
        $values = fn (string $field) => $filtered->pluck($field)->filter(fn ($v) => $v !== null);

        return view('farmer-records.rice-productions', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'search' => $search,
            'filterable' => $filterable,
            'canManage' => $this->canManage($request),
            'records' => new LengthAwarePaginator($filtered->forPage($page, self::FARMER_PER_PAGE)->values(), $filtered->count(), self::FARMER_PER_PAGE, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]),
            'stats' => (object) [
                'records' => $filtered->count(),
                'total_production' => $values('total_production')->isNotEmpty() ? $values('total_production')->sum() : null,
                'avg_yield' => $values('yield_per_hectare')->isNotEmpty() ? $values('yield_per_hectare')->avg() : null,
                'total_area' => $values('area_hectares')->isNotEmpty() ? $values('area_hectares')->sum() : null,
                'latest_year' => $filtered->max('year'),
            ],
            // The latest 16 records with a yield, oldest first, so the bars read left to right in time.
            'yieldBars' => $filtered->filter(fn ($r) => $r->yield_per_hectare !== null)->take(16)->reverse()->values(),
            'farmerBarangay' => $barangay,
            'ownBarangayCount' => $barangay ? $filtered->where('is_source_dataset', false)->where('barangay', $barangay)->count() : 0,
            'datasetSource' => self::DATASET_SOURCE,
        ]);
    }

    /**
     * Season and irrigation choices from the records themselves: only irrigation types that records actually
     * have (the yield dataset does not record one, so its rows match only "All").
     *
     * @param  Collection<int, object>  $records
     * @return array<string, array<string, string>>
     */
    private function filterChoices(Collection $records): array
    {
        $choices = fn (string $field) => $records->pluck($field)->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v])->all();

        return ['season' => $choices('season'), 'irrigation_type' => $choices('irrigation_type')];
    }

    /**
     * Every rice record: one read-only row per month of the yield dataset, then every rice_productions row.
     * A dataset row is Lian's municipal monthly average, so it is never the same record as a barangay row for
     * the same month; both are listed.
     *
     * @return Collection<int, object>
     */
    private function records(): Collection
    {
        return $this->datasetRows()->concat($this->enteredRows())->values();
    }

    /**
     * One read-only row per month on file: the municipal average yield (t/ha) and season, exactly as stored. The
     * dataset has no barangay, irrigation type, planted area or production, so those stay missing, never 0.
     *
     * @return Collection<int, object>
     */
    private function datasetRows(): Collection
    {
        try {
            $rows = app(ProjectYieldDataset::class)->rows();
        } catch (Throwable $exception) {
            Log::warning('Rice production records: the yield dataset could not be read.', ['message' => $exception->getMessage()]);

            return collect();
        }

        return $rows->map(function (array $row): object {
            $month = Carbon::create($row['year'], $row['month'], 1);

            return (object) [
                'id' => null,
                'is_source_dataset' => true,
                'barangay' => self::DATASET_SCOPE,
                'production_month' => $month,
                'season' => $row['season'] ?? null,
                'irrigation_type' => null,
                'yield_per_hectare' => (float) $row['yield'],
                'area_hectares' => null,
                'total_production' => null,
                'year' => $row['year'],
                // Short enough for the bar label column (one line, like "2024 Wet · Lumaniag").
                'bar_label' => $month->format('M Y').' · Lian average',
                'search_text' => mb_strtolower(implode(' ', [self::DATASET_SCOPE, 'municipal average dataset', self::DATASET_SOURCE, $month->format('M Y'), $month->format('F Y'), $month->format('Y-m'), $row['season'] ?? ''])),
                'sort_key' => $month->format('Y-m').'|1',
            ];
        });
    }

    /** @return Collection<int, object> */
    private function enteredRows(): Collection
    {
        try {
            $records = RiceProduction::query()->get(['id', 'barangay', 'season', 'irrigation_type', 'yield_per_hectare', 'area_hectares', 'total_production', 'year', 'production_month', 'remarks']);
        } catch (QueryException $exception) {
            Log::warning('Rice production records: rice_productions could not be read.', ['message' => $exception->getMessage()]);

            return collect();
        }

        $number = fn ($value) => is_numeric($value) ? (float) $value : null;

        return $records->map(fn (RiceProduction $record): object => (object) [
            'id' => $record->id,
            'is_source_dataset' => false,
            'barangay' => $record->barangay,
            'production_month' => $record->production_month,
            'season' => $record->season,
            'irrigation_type' => $record->irrigation_type,
            'yield_per_hectare' => $number($record->yield_per_hectare),
            'area_hectares' => $number($record->area_hectares),
            'total_production' => $number($record->total_production),
            'year' => $record->year,
            'bar_label' => trim($record->year.' '.$record->season).' · '.$record->barangay,
            'search_text' => mb_strtolower(implode(' ', [$record->barangay, $record->season, $record->irrigation_type, $record->year, $record->production_month?->format('M Y'), $record->remarks, 'mao record'])),
            // A record without a harvest month sorts with its year (after the dataset months of that year).
            'sort_key' => ($record->production_month?->format('Y-m') ?? $record->year.'-13').'|2|'.str_pad((string) $record->id, 10, '0', STR_PAD_LEFT),
        ]);
    }

    public function __construct()
    {
        $this->fields = [
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::options(false)],
            ['name' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => ['Wet' => 'Wet', 'Dry' => 'Dry']],
            ['name' => 'irrigation_type', 'label' => 'Irrigation Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
            ['name' => 'yield_per_hectare', 'label' => 'Yield Per Hectare', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'area_hectares', 'label' => 'Area Hectares', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'total_production', 'label' => 'Total Production', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'year', 'label' => 'Year', 'type' => 'number'],
            ['name' => 'production_month', 'label' => 'Harvest / Production Month', 'type' => 'month'],
            ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea'],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            'season' => ['required', Rule::in(['Wet', 'Dry'])],
            'irrigation_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
            'yield_per_hectare' => ['nullable', 'numeric', 'min:0'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'total_production' => ['nullable', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            // Required: a harvest record without its month cannot be placed in a season or compared with the weather.
            'production_month' => ['required', 'date_format:Y-m'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
