<?php

namespace App\Http\Controllers;

use App\Models\RiceProduction;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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

    protected array $columns = ['barangay' => 'Barangay', 'production_month' => 'Production Month', 'season' => 'Season', 'irrigation_type' => 'Irrigation', 'yield_per_hectare' => 'Yield per Hectare', 'area_hectares' => 'Area (ha)', 'total_production' => 'Total Harvest', 'year' => 'Year'];

    protected array $searchable = ['barangay', 'season', 'irrigation_type', 'year', 'remarks'];

    protected array $filterable = ['season' => ['Wet', 'Dry'], 'irrigation_type' => ['Rainfed', 'Irrigated']];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'year';

    /** What the dataset rows cover: Lian as a whole, not one barangay. */
    public const DATASET_SCOPE = 'Lian (municipal average)';

    /**
     * MAO and IT see the rice_productions rows together with the project's own yield history
     * (ProjectYieldDataset: the AVERAGE_YIELD column of the modeling dataset the rice-yield model was built from,
     * the same series the dashboards use). Farmers keep their own page.
     */
    public function index(Request $request): View
    {
        if ($this->usesFarmerPages($request)) {
            return parent::index($request);
        }

        $this->authorizeView($request);
        $rows = $this->datasetRows()->concat($this->enteredRows());

        return $this->staffRowsIndex($request, $rows, [
            'season' => $rows->pluck('season')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v])->all(),
            // Only irrigation types that records actually have; the yield dataset does not record one.
            'irrigation_type' => $rows->pluck('irrigation_type')->filter()->unique()->sort()->mapWithKeys(fn ($v) => [$v => $v])->all(),
        ]);
    }

    /**
     * One read-only row per month on file: the municipal average yield (t/ha) and season, exactly as stored. The
     * dataset has no barangay, irrigation type, planted area or production, so those stay missing, never 0.
     * Its rows are municipal monthly averages, so they can never be the same record as an entered barangay row.
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
                'source_note' => 'iClimate yield dataset · read-only',
                'barangay' => self::DATASET_SCOPE,
                'production_month' => $month->format('M Y'),
                'season' => $row['season'] ?? null,
                'irrigation_type' => null,
                'yield_per_hectare' => self::plainNumber($row['yield'], ' t/ha'),
                'area_hectares' => null,
                'total_production' => null,
                'year' => $row['year'],
                'search_text' => mb_strtolower(implode(' ', [self::DATASET_SCOPE, 'lian municipal average yield dataset', $month->format('M Y'), $month->format('F Y'), $month->format('Y-m'), $row['season'] ?? ''])),
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

        return $records->map(fn (RiceProduction $record): object => (object) [
            'id' => $record->id,
            'is_source_dataset' => false,
            'barangay' => $record->barangay,
            'production_month' => $record->production_month?->format('M Y'),
            'season' => $record->season,
            'irrigation_type' => $record->irrigation_type,
            'yield_per_hectare' => self::plainNumber($record->yield_per_hectare, ' t/ha'),
            'area_hectares' => self::plainNumber($record->area_hectares, ' ha'),
            'total_production' => self::plainNumber($record->total_production, ' t'),
            'year' => $record->year,
            'search_text' => mb_strtolower(implode(' ', [$record->barangay, $record->season, $record->irrigation_type, $record->year, $record->production_month?->format('M Y'), $record->remarks])),
            // A record without a harvest month sorts with its year (after the dataset months of that year).
            'sort_key' => ($record->production_month?->format('Y-m') ?? $record->year.'-13').'|2|'.str_pad((string) $record->id, 10, '0', STR_PAD_LEFT),
        ]);
    }

    protected function farmerIndexData(Request $request, Builder $filtered): array
    {
        $stats = (clone $filtered)->toBase()->selectRaw('count(*) as records, sum(total_production) as total_production, avg(yield_per_hectare) as avg_yield, sum(area_hectares) as total_area, max(year) as latest_year')->first();

        return [
            'stats' => $stats,
            'yieldBars' => (clone $filtered)->whereNotNull('yield_per_hectare')->orderBy('year')->orderBy('id')->limit(16)->get(['id', 'barangay', 'season', 'year', 'yield_per_hectare']),
            'farmerBarangay' => $request->user()->barangay,
            'ownBarangayCount' => $request->user()->barangay ? (clone $filtered)->where('barangay', $request->user()->barangay)->count() : 0,
        ];
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
            'production_month' => ['nullable', 'date_format:Y-m'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
