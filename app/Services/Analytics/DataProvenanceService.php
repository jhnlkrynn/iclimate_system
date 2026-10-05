<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\HeatmapArea;
use App\Models\MonthlyWeatherPrediction;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Support\DashboardData;
use App\Support\ProjectYieldDataset;
use App\Support\SampleDataDetector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answers "where does this number come from?" for each dashboard.
 *
 * Every entry names the source, says what KIND of data it is, and gives the
 * period it covers and when it was last updated:
 *
 *   Observed    – a reading taken by a station/provider
 *   Historical  – recorded history stored in the project
 *   Predicted   – output of an iClimate ML model (never an observation)
 *   Calculated  – derived from the above by iClimate rules/arithmetic
 *   Count       – a live count of rows in the system
 *
 * Entries are descriptions of real records: nothing here is invented, and
 * anything that is missing says so.
 */
class DataProvenanceService
{
    public const OBSERVED = 'Observed';

    public const HISTORICAL = 'Historical';

    public const PREDICTED = 'Predicted';

    public const CALCULATED = 'Calculated';

    public const COUNT = 'Count';

    public function __construct(
        private readonly AmbulongHistoricalClimateDataset $ambulong,
        private readonly ProjectYieldDataset $yields,
        private readonly SampleDataDetector $samples,
    ) {}

    /* ------------------------------------------------------------------ roles */

    /**
     * @param  array<string, mixed>|null  $prediction  the rice-yield model result shown on the dashboard
     * @param  array<string, mixed>|null  $weather  the live weather payload shown on the dashboard
     * @return array<int, array<string, mixed>>
     */
    public function farmer(?array $prediction, ?array $weather): array
    {
        $db = $this->databaseFacts();

        return array_values(array_filter([
            $this->liveWeather($weather),
            $this->riceModel($prediction),
            $this->advisoryRules($prediction),
            $this->yieldHistory(),
            $this->ambulongHistory(),
            $this->monthlyForecast($db),
            $this->notifications($db),
            $this->pagasaAdvisories($db),
        ]));
    }

    /** @return array<int, array<string, mixed>> */
    public function mao(): array
    {
        $db = $this->databaseFacts();

        return array_values(array_filter([
            $this->yieldHistory(),
            $this->riceProduction($db),
            $this->weatherFeed($db, false),
            $this->ambulongHistory(),
            $this->monthlyForecast($db),
            $this->riskRatings($db),
            $this->pagasaAdvisories($db),
            $this->predictionRuns($db),
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $systemStatus  the status block already computed for the IT dashboard
     * @return array<int, array<string, mixed>>
     */
    public function it(?array $systemStatus = null): array
    {
        $db = $this->databaseFacts();

        return array_values(array_filter([
            $this->entry('Users', self::COUNT, 'users table (live count)', 'users', null, null, User::count(), null),
            $this->climateRecords($db),
            $this->weatherFeed($db, true),
            $this->ambulongHistory(),
            $this->riceProduction($db),
            $this->predictionRuns($db),
            $this->monthlyForecast($db),
            $this->notifications($db),
            $systemStatus ? $this->entry(
                'System & data status',
                self::CALCULATED,
                'SystemHealthService checks (database, queue, backups, models, scheduler)',
                'app/Services/SystemHealthService.php',
                null,
                $systemStatus['checked_at'] ?? null,
                count($systemStatus['items'] ?? []),
                $systemStatus['headline'] ?? null,
            ) : null,
        ]));
    }

    /* --------------------------------------------------------------- entries */

    /**
     * @return array<string, mixed>
     */
    private function entry(string $label, string $kind, string $source, ?string $reference, ?string $period, ?string $updated, ?int $records, ?string $note, ?string $warning = null): array
    {
        return compact('label', 'kind', 'source', 'reference', 'period', 'updated', 'records', 'note', 'warning');
    }

    private function liveWeather(?array $weather): ?array
    {
        if (! ($weather['success'] ?? false)) {
            return $this->entry('Current weather', self::OBSERVED, 'No weather provider responded', null, null, null, null, 'Shown as "No verified data available".');
        }

        return $this->entry(
            'Current weather',
            self::OBSERVED,
            'Online weather forecast service — not a PAGASA station reading',
            null,
            'Latest reading for Lian, Batangas',
            $weather['fetched_at'] ?? null,
            null,
            'The time shown is the provider\'s own data timestamp; the source itself is not real-time.',
        );
    }

    private function riceModel(?array $prediction): ?array
    {
        if (! is_numeric($prediction['predicted_yield'] ?? null)) {
            return $this->entry('Predicted rice yield', self::PREDICTED, 'iClimate rice-yield model', null, null, null, null, 'The prediction service returned no result.');
        }

        $inputs = collect($prediction['inputs_used'] ?? [])
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v, $k) => $k.' '.(is_float($v) ? round($v, 1) : $v))
            ->take(8)
            ->implode(' · ');

        return $this->entry(
            'Predicted rice yield',
            self::PREDICTED,
            'iClimate rice-yield estimate — a prediction, not a harvest record',
            (string) ($prediction['model_version'] ?? ''),
            ! empty($prediction['target_date']) ? 'Prediction for '.$prediction['target_date'] : null,
            null,
            null,
            $inputs !== '' ? 'Inputs used: '.$inputs.'. Computed on request and kept up to 6 hours.' : 'Computed on request and kept up to 6 hours.',
        );
    }

    private function advisoryRules(?array $prediction): ?array
    {
        if (empty($prediction['planting_advisory']) && empty($prediction['irrigation_recommendation'])) {
            return null;
        }

        return $this->entry(
            'Planting advisory & irrigation',
            self::CALCULATED,
            'iClimate decision rules applied to the prediction and weather inputs above',
            'app/Services/DecisionSupportService.php',
            ! empty($prediction['target_date']) ? 'For '.$prediction['target_date'] : null,
            null,
            null,
            null,
        );
    }

    private function yieldHistory(): array
    {
        if (! $this->yields->available() || $this->yields->rows()->isEmpty()) {
            return $this->entry('Rice yield history', self::HISTORICAL, ProjectYieldDataset::SOURCE_NAME, ProjectYieldDataset::FILE, null, null, 0, 'File not found or empty.', 'Insufficient verified data');
        }

        $d = $this->yields->describe();

        return $this->entry(
            'Rice yield history',
            self::HISTORICAL,
            $d['name'],
            $d['file'],
            $d['first'].' – '.$d['last'],
            $d['updated']?->toIso8601String(),
            $d['records'],
            'Values are used exactly as stored (t/ha). The file has no production tonnage, area or barangay, so those stay unavailable.',
        );
    }

    private function ambulongHistory(): array
    {
        try {
            $data = $this->ambulong->load();
            $obs = $data['observations'];
            $first = $obs->sortBy(fn ($r) => $r['year'] * 100 + $r['month'])->first();
            $last = $obs->sortByDesc(fn ($r) => $r['year'] * 100 + $r['month'])->first();
            $path = $this->ambulong->csvPath();

            return $this->entry(
                'Monthly climate history',
                self::HISTORICAL,
                'Ambulong weather station monthly record (project dataset)',
                'database/data/Ambulong Monthly Data.csv',
                $first ? Carbon::create($first['year'], $first['month'], 1)->format('M Y').' – '.Carbon::create($last['year'], $last['month'], 1)->format('M Y') : null,
                is_file($path) ? Carbon::createFromTimestamp(filemtime($path))->toIso8601String() : null,
                $obs->count(),
                'Recorded values, unchanged. '.$data['excluded_sentinel_count'].' missing-value marker(s) (-999) left out, not zero-filled.',
            );
        } catch (Throwable) {
            return $this->entry('Monthly climate history', self::HISTORICAL, 'Ambulong monthly record', 'database/data/Ambulong Monthly Data.csv', null, null, null, null, 'The file could not be read.');
        }
    }

    /** @param  array<string, mixed>  $db */
    private function weatherFeed(array $db, bool $named): array
    {
        return $this->entry(
            'Live weather feed',
            self::OBSERVED,
            $named ? 'Open-Meteo — a weather-forecast service, not a PAGASA station reading' : 'Online weather forecast service — not a PAGASA station reading',
            $named ? 'https://open-meteo.com/' : null,
            'Lian municipality and 19 barangays',
            $db['weather']['latest'],
            $db['weather']['records'],
            'Stored with its retrieval time. The provider is not real-time, so the time shown is when we last retrieved it (refreshed by the scheduler).',
        );
    }

    /** @param  array<string, mixed>  $db */
    private function monthlyForecast(array $db): ?array
    {
        $f = $db['forecast'];
        if (($f['records'] ?? 0) === 0) {
            return $this->entry('Monthly weather forecast', self::PREDICTED, 'iClimate monthly weather model', null, null, null, 0, 'No forecast has been generated yet.');
        }

        return $this->entry(
            'Monthly weather forecast',
            self::PREDICTED,
            'iClimate monthly weather model ('.$f['model'].'), trained on the Ambulong record '.$f['training'],
            'monthly_weather_predictions',
            $f['period'],
            $f['generated'],
            $f['records'],
            'A forecast, not an observation — drawn as lighter or dashed series.',
        );
    }

    /** @param  array<string, mixed>  $db */
    private function riceProduction(array $db): array
    {
        $s = $db['rice'];

        return $this->entry(
            'Rice production records',
            self::HISTORICAL,
            'rice_productions table (entered by MAO/admin; origin not recorded per row)',
            'rice_productions',
            $s['period'],
            $s['updated'],
            $s['verified'],
            $s['verified'] === 0 ? 'Insufficient verified data — production, area and barangay figures stay unavailable until real records are entered.' : null,
            $this->sampleWarning($s),
        );
    }

    /** @param  array<string, mixed>  $db */
    private function climateRecords(array $db): array
    {
        $s = $db['climate'];

        return $this->entry(
            'Weather records (database)',
            self::HISTORICAL,
            'climate_records table',
            'climate_records',
            $s['period'],
            $s['updated'],
            $s['verified'],
            null,
            $this->sampleWarning($s),
        );
    }

    /** @param  array<string, mixed>  $db */
    private function notifications(array $db): array
    {
        $s = $db['notifications'];

        return $this->entry('Notifications', self::COUNT, 'notifications table (live count)', 'notifications', null, $s['updated'], $s['verified'], null, $this->sampleWarning($s));
    }

    /** @param  array<string, mixed>  $db */
    private function pagasaAdvisories(array $db): array
    {
        return $this->entry(
            'Weather advisories',
            self::OBSERVED,
            'PAGASA online advisory pages, filtered for Lian/Batangas (plus iClimate rule advisories)',
            'https://bagong.pagasa.dost.gov.ph/',
            null,
            $db['advisories']['latest'],
            $db['advisories']['pagasa'],
            $db['advisories']['pagasa'].' PAGASA-sourced advisory record(s) stored; each keeps its source URL and retrieval time. Fetched every 15 minutes.',
        );
    }

    /** @param  array<string, mixed>  $db */
    private function riskRatings(array $db): array
    {
        return $this->entry(
            'Farm risk ratings',
            self::CALCULATED,
            'AgriculturalRiskScorer applied to barangay weather and the last recorded yield',
            'heatmap_areas',
            null,
            $db['risk']['computed'],
            $db['risk']['records'],
            'A planning guide, not an observation.',
        );
    }

    /** @param  array<string, mixed>  $db */
    private function predictionRuns(array $db): array
    {
        return $this->entry(
            'Prediction records',
            self::PREDICTED,
            'analytics_runs (each stores its input and result) and monthly forecasts',
            'analytics_runs',
            null,
            $db['runs']['latest'],
            $db['runs']['records'] + $db['forecast']['records'],
            $db['runs']['records'].' prediction run(s) + '.$db['forecast']['records'].' monthly forecast row(s). Every one is a model output.',
            $db['runs']['invalid'] > 0
                ? $db['runs']['invalid'].' earlier run(s) used a weather model trained on only 6 seeded sample rows and produced impossible values (e.g. thousands of mm of rain in a month); they are not counted.'
                : null,
        );
    }

    /** @param  array{sample: int, implausible: int, total: int}  $s */
    private function sampleWarning(array $s): ?string
    {
        $parts = [];
        if (($s['sample'] ?? 0) > 0) {
            $parts[] = $s['sample'].' of '.$s['total'].' row(s) look like seeded sample data (placeholder text, created by the database seeder)';
        }
        if (($s['implausible'] ?? 0) > 0) {
            $parts[] = $s['implausible'].' impossible reading(s) (future date or out of range)';
        }
        if ($parts === []) {
            return null;
        }

        return implode('; ', $parts).(config('iclimate.exclude_sample_data', true)
            ? ' — excluded from every figure on this dashboard.'
            : ' — currently INCLUDED because exclusion is switched off.');
    }

    /* -------------------------------------------------------------- database */

    /**
     * Everything read from the database in one cached pass. Retired whenever
     * dashboard source data changes (DashboardData::version()).
     *
     * @return array<string, mixed>
     */
    private function databaseFacts(): array
    {
        return \App\Support\StaleCache::remember('provenance:db:v'.DashboardData::version(), 300, function (): array {
            $iso = fn ($v) => $v ? Carbon::parse($v)->toIso8601String() : null;

            $rice = $this->samples->summary('rice_productions');
            $riceRows = DB::table('rice_productions')->whereNotIn('id', $this->samples->excludedIds('rice_productions'))
                ->selectRaw('min(year) a, max(year) b, max(updated_at) u')->first();

            $climate = $this->samples->summary('climate_records');
            $climateRows = DB::table('climate_records')->whereNotIn('id', $this->samples->excludedIds('climate_records'))
                ->selectRaw('min(record_date) a, max(record_date) b, max(updated_at) u')->first();

            $notifications = $this->samples->summary('notifications');
            $notifUpdated = DB::table('notifications')->whereNotIn('id', $this->samples->excludedIds('notifications'))->max('created_at');

            $forecast = MonthlyWeatherPrediction::query()->where('status', 'ready');
            $latestForecast = (clone $forecast)->orderByDesc('generated_at')->first(['weather_model', 'training_from', 'training_to', 'generated_at']);
            $span = (clone $forecast)->selectRaw('min(prediction_year * 100 + prediction_month) a, max(prediction_year * 100 + prediction_month) b')->first();
            $ym = fn ($n) => $n ? Carbon::create((int) floor($n / 100), $n % 100, 1)->format('M Y') : null;

            $pagasa = PlantingAdvisory::query()->fromPagasaOnline();

            return [
                'rice' => $rice + [
                    'period' => $riceRows->a ? $riceRows->a.($riceRows->b != $riceRows->a ? ' – '.$riceRows->b : '') : null,
                    'updated' => $iso($riceRows->u),
                ],
                'climate' => $climate + [
                    'period' => $climateRows->a ? Carbon::parse($climateRows->a)->format('M j, Y').' – '.Carbon::parse($climateRows->b)->format('M j, Y') : null,
                    'updated' => $iso($climateRows->u),
                ],
                'notifications' => $notifications + ['updated' => $iso($notifUpdated)],
                'forecast' => [
                    'records' => (clone $forecast)->count(),
                    'model' => $latestForecast?->weather_model ?? 'n/a',
                    'training' => $latestForecast && $latestForecast->training_from
                        ? Carbon::parse($latestForecast->training_from)->format('M Y').' – '.Carbon::parse($latestForecast->training_to)->format('M Y')
                        : 'n/a',
                    'period' => $span?->a ? $ym($span->a).' – '.$ym($span->b) : null,
                    'generated' => $iso($latestForecast?->generated_at),
                ],
                'weather' => [
                    'records' => DB::table('external_weather_data')->count(),
                    'latest' => $iso(DB::table('external_weather_data')->max('fetched_at')),
                ],
                'advisories' => [
                    'pagasa' => (clone $pagasa)->count(),
                    'latest' => $iso((clone $pagasa)->max('created_at')),
                ],
                'risk' => [
                    'records' => HeatmapArea::query()->count(),
                    'computed' => $iso(HeatmapArea::query()->max('risk_computed_at') ?? HeatmapArea::query()->max('updated_at')),
                ],
                'runs' => [
                    'records' => \App\Support\SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', ['rice_yield_prediction', 'weather_prediction'])->count(),
                    'latest' => $iso(\App\Support\SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', ['rice_yield_prediction', 'weather_prediction'])->max('created_at')),
                    'invalid' => count($this->samples->excludedIds('analytics_runs')),
                ],
            ];
        });
    }
}
