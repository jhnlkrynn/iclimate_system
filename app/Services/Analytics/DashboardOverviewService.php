<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\HeatmapArea;
use App\Models\MonthlyWeatherPrediction;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\SystemHealthService;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use App\Support\SampleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Role-specific dashboard overview: at most five primary KPIs followed by a
 * small set of charts. Everything comes from stored records — the RiceProduction
 * table, the Ambulong monthly climate dataset, persisted monthly weather
 * predictions, heat-map risk ratings, advisories and system tables. Nothing is
 * estimated or padded; a missing figure is null and rendered as "No data available".
 *
 * Predicted values are always separate chart datasets (kind = "predicted") so the
 * view can draw them differently from recorded history.
 *
 * KPI shape matches DashboardKpiService (plus optional `progress` 0–100).
 * Chart shape: id, title, subtitle, type (line|bar|doughnut), unit, labels,
 * datasets [{label, data, kind, axis?}], extra?, empty, empty_message.
 */
class DashboardOverviewService
{
    // Long TTL is safe: DashboardData::bump() invalidates on any data write.
    private const CACHE_SECONDS = 300;

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const PREDICTION_RUN_TYPES = ['rice_yield_prediction', 'weather_prediction'];

    /** Shown wherever a figure cannot be backed by a verified record — never a zero. */
    private const NO_DATA = 'No verified data available';

    private const INSUFFICIENT = 'Insufficient verified data';

    /** What kind of data each chart draws, shown as a small label on its card. */
    private const CHART_BASIS = [
        'yield-trend' => 'Historical + Predicted',
        'monthly-rainfall' => 'Historical + Predicted',
        'weather-trend' => 'Historical + Predicted',
        'advisory-status' => 'Calculated',
        'rain-forecast' => 'Forecast',
        'temperature-forecast' => 'Forecast',
        'yield-by-year' => 'Historical',
        'records-by-month' => 'Count',
        'notifications-by-type' => 'Count',
        'advisories-by-severity' => 'Count',
        'production-trend' => 'Historical',
        'yield-by-month' => 'Historical',
        'production-by-barangay' => 'Historical',
        'risk-levels' => 'Calculated',
        'users-by-role' => 'Count',
        'records-by-type' => 'Count',
        'system-activity' => 'Count',
        'predictions-by-month' => 'Count',
    ];

    public function __construct(
        private readonly AmbulongHistoricalClimateDataset $ambulong,
        private readonly DashboardData $data,
        private readonly ProjectYieldDataset $yields,
        private readonly DataProvenanceService $provenance,
    ) {}

    /* ------------------------------------------------------------------
     | Year filter (Farmer + MAO)
     * ------------------------------------------------------------------ */

    /**
     * Years offered by the filter: any year with verified yield history, harvest
     * records or Ambulong weather records, newest first. A year that lacks one
     * of them shows "Insufficient verified data" for that figure — never a zero.
     *
     * @return Collection<int, int>
     */
    public function availableYears(): Collection
    {
        try {
            $weatherYears = $this->ambulong->load()['observations']->pluck('year');
        } catch (Throwable) {
            $weatherYears = collect();
        }

        return $this->rice()->pluck('year')
            ->merge($weatherYears)
            ->merge($this->yields->years())
            ->filter(fn ($year) => $year && $year <= \App\Support\BusinessClock::year())
            ->map(fn ($year) => (int) $year)
            ->unique()->sortDesc()->values();
    }

    /** A requested year if it is offered; otherwise the latest year with verified yield or harvest data. */
    public function resolveYear(?int $requested): ?int
    {
        if ($requested && $this->availableYears()->contains($requested)) {
            return $requested;
        }

        return $this->rice()->pluck('year')->merge($this->yields->years())
            ->filter(fn ($y) => $y && $y <= \App\Support\BusinessClock::year())->map(fn ($y) => (int) $y)->max()
            ?? $this->availableYears()->first();
    }

    /* ------------------------------------------------------------------
     | Farmer — "What should I do?"
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>|null  $prediction  rice-yield model result already computed for the dashboard
     * @param  array<string, mixed>|null  $weather  live weather payload the dashboard already holds
     */
    public function farmer(User $user, ?string $barangay, ?array $prediction, ?array $weather, ?int $requestedYear): array
    {
        $year = $this->resolveYear($requestedYear);

        $data = Cache::remember(
            'dashboard:overview:farmer:v'.DashboardData::version().':'.$user->id.':'.md5((string) $barangay).':'.($year ?? 'none'),
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->farmerStored($user, $barangay, $year),
        );

        return [
            'role' => 'farmer',
            'year' => $year,
            'years' => $this->availableYears()->all(),
            'generated_at' => $data['generated_at'],
            'sources' => $this->provenance->farmer($prediction, $weather),
            'schedule' => $data['schedule'],
            'advisory' => $data['advisory'],
            'kpis' => $this->adaptKpis([
                $this->predictedYieldKpi($prediction),
                $this->currentWeatherKpi($weather),
                $this->plantingAdvisoryKpi($prediction, $barangay),
                $data['alerts'],
                $data['historical'],
            ], [
                fn (): array => $this->irrigationKpi($prediction, $barangay),
                ...($data['alt_kpis'] ?? []),
                fn (): array => $this->rainTodayKpi($weather),
                fn (): array => $this->humidityWindKpi($weather),
            ]),
            'charts' => $this->adaptCharts([
                $this->withPredictedYield($data['yield_trend'], $prediction),
                $data['rainfall'],
                $this->rainForecastChart($weather),
                $data['weather'],
            ], [
                ...($data['alt_charts'] ?? []),
                fn (): array => $this->temperatureForecastChart($weather),
            ]),
        ];
    }

    private function farmerStored(User $user, ?string $barangay, ?int $year): array
    {
        // Yield history comes from the project's own yield dataset (municipal
        // monthly averages). The harvest table holds no verified rows, and this
        // dataset has no barangay breakdown, so the scope is the municipality.
        $scope = 'Lian municipality';
        $trendYears = $this->yields->years()->filter(fn ($y) => ! $year || $y <= $year)->sort()->values();
        $historical = $year ? $this->yields->meanUpTo($year) : null;
        $historicalMonths = $year ? $this->yields->rows()->where('year', '<=', $year)->count() : 0;
        $yieldInfo = $this->yields->describe();

        $unread = SampleData::without(UserNotification::query(), 'notifications')->where('user_id', $user->id)->where('is_read', false);
        $unreadCount = (clone $unread)->count();
        $unreadWarnings = (clone $unread)->where('type', 'Warning')->count();
        $activeAdvisories = PlantingAdvisory::query()->active()->forBarangay($barangay);
        $activeCount = (clone $activeAdvisories)->count();

        $schedule = $this->farmerSchedule($user);
        $weatherYear = $this->monthlyWeather($year)['actual'];
        $rainMonths = array_filter($weatherYear['rainfall'], fn ($v) => $v !== null);
        $tempMonths = array_filter($weatherYear['temperature'], fn ($v) => $v !== null);
        $totalAdvisories = PlantingAdvisory::query()->count();

        $altKpis = [
            $this->kpi('schedules', 'My Planting Schedules', $schedule ? number_format($schedule['active']) : null, $schedule ? 'active' : null,
                $schedule ? ($schedule['next'] ? 'Next harvest '.$schedule['next']['harvest_date'].' · '.$schedule['total'].' on file' : $schedule['total'].' on file, none with a harvest date') : 'No planting schedule added yet',
                route('calendar.index')),
            $this->kpi('active-advisories', 'Active Planting Advisories', $totalAdvisories > 0 ? number_format($activeCount) : null, null,
                $activeCount.' published for your area', route('planting-advisories.index')),
            $this->kpi('ambulong-rainfall', 'Rainfall'.($year ? ' ('.$year.')' : ''), $rainMonths ? number_format(array_sum($rainMonths), 0) : null, 'mm',
                'Historical · Ambulong records, '.count($rainMonths).' '.Str::plural('month', count($rainMonths))),
            $this->kpi('ambulong-temperature', 'Average Temperature'.($year ? ' ('.$year.')' : ''), $tempMonths ? number_format(array_sum($tempMonths) / count($tempMonths), 1) : null, '°C',
                'Historical · Ambulong records, '.count($tempMonths).' '.Str::plural('month', count($tempMonths))),
        ];

        $altCharts = [
            fn (): array => $this->doughnut('advisory-status', 'Active Advisories by Severity',
                $barangay ? 'For '.$barangay.' and municipality-wide' : 'Municipality-wide',
                (clone $activeAdvisories)->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity')
                    ->mapWithKeys(fn ($total, $severity) => [Str::headline((string) $severity) => (int) $total])->all(),
                'No active advisories for your area.'),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'alt_kpis' => $altKpis,
            'alt_charts' => array_map(fn ($make) => $make(), $altCharts),
            'schedule' => $schedule,
            'advisory' => (function () use ($activeAdvisories, $activeCount): array {
                $latest = (clone $activeAdvisories)->latest('id')->first();

                return [
                    'count' => $activeCount,
                    'latest_title' => $latest?->title,
                    'latest_severity' => $latest?->severityLabel(),
                    'latest_id' => $latest?->id,
                ];
            })(),
            'alerts' => [
                'key' => 'alerts',
                'label' => 'Active Alerts',
                'value' => number_format($unreadCount),
                'unit' => null,
                'note' => $activeCount.' active '.Str::plural('advisory', $activeCount).' for your area'
                    .($unreadWarnings > 0 ? ' · '.$unreadWarnings.' unread '.Str::plural('warning', $unreadWarnings) : ''),
                'trend' => null,
                'status' => $unreadWarnings > 0 ? 'warning' : null,
                'status_label' => $unreadWarnings > 0 ? 'Check now' : null,
                'href' => route('notifications.index'),
            ],
            'historical' => [
                'key' => 'historical-yield',
                'label' => 'Average Historical Yield',
                'value' => $historical !== null ? number_format($historical, 2) : self::INSUFFICIENT,
                'unit' => $historical !== null ? 't/ha' : null,
                'note' => $historical !== null
                    ? 'Historical · mean of '.$historicalMonths.' monthly averages, '.$yieldInfo['first'].' to '.($year && $year < (int) substr((string) $yieldInfo['last'], -4) ? $year : $yieldInfo['last']).' · '.$scope
                    : 'No verified yield history on file',
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => null,
            ],
            'yield_trend' => $this->chart('yield-trend', 'Rice Yield Trend', 'Historical yearly mean of monthly averages · '.$scope, 'line', 't/ha',
                $trendYears->map(fn ($y) => (string) $y)->all(),
                [['label' => 'Historical yield', 'data' => $trendYears->map(fn ($y) => round((float) $this->yields->yearlyMean($y), 2))->all(), 'kind' => 'actual']],
                'No verified yield history to chart yet.',
                ['Months on file' => $trendYears->map(fn ($y) => (string) $this->yields->monthsRecorded($y))->all()]),
            'rainfall' => $this->rainfallChart($year),
            'weather' => $this->weatherLine('weather-trend', 'Temperature & Humidity', $year, ['temperature', 'humidity']),
        ];
    }

    /**
     * The farmer's own planting schedules: how many, which harvest is next and how
     * far along it is. Null when the farmer has none, so the card can say so.
     *
     * @return array{total: int, active: int, harvested: int, next: ?array<string, mixed>}|null
     */
    private function farmerSchedule(User $user): ?array
    {
        if (! \App\Support\SchemaCache::hasTable('farmer_planting_schedules')) {
            return null;
        }

        $rows = FarmerPlantingSchedule::query()->where('user_id', $user->id)->orderBy('planting_date')->limit(100)->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $open = $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested');
        $next = $open->filter(fn (FarmerPlantingSchedule $r) => $r->harvestDateForPlanning() !== null)
            ->sortBy(fn (FarmerPlantingSchedule $r) => $r->harvestDateForPlanning()->timestamp)
            ->first();

        $nextData = null;
        if ($next) {
            $harvest = $next->harvestDateForPlanning();
            $planted = $next->planting_date;
            $span = $planted ? $planted->diffInDays($harvest, false) : 0;
            $elapsed = $planted ? $planted->diffInDays(\App\Support\BusinessClock::today(), false) : 0;
            $nextData = [
                'harvest_date' => $harvest->format('M j, Y'),
                'days_left' => (int) \App\Support\BusinessClock::today()->diffInDays($harvest, false),
                'progress' => $span > 0 ? (int) max(0, min(100, round(($elapsed / $span) * 100))) : null,
                'status' => $next->monitoringStatusLabel(),
                'predicted_yield' => $next->predicted_yield !== null ? (float) $next->predicted_yield : null,
            ];
        }

        return [
            'total' => $rows->count(),
            'active' => $open->count(),
            'harvested' => $rows->count() - $open->count(),
            'next' => $nextData,
        ];
    }

    /**
     * Chance of rain for the coming days, from the live forecast the dashboard
     * already holds. Days without a probability are left out, never zero-filled.
     */
    private function rainForecastChart(?array $weather): array
    {
        $days = collect($weather['forecast'] ?? [])
            ->filter(fn ($d) => is_array($d) && is_numeric($d['precipitation_probability'] ?? null))
            ->values();

        $temps = fn (array $d): string => isset($d['temperature_max'], $d['temperature_min'])
            ? round((float) $d['temperature_max']).' / '.round((float) $d['temperature_min']).' °C'
            : 'n/a';

        return $this->chart('rain-forecast', 'Rain Chance This Week', 'Plan spraying, fertilizing and irrigation · online weather forecast', 'bar', '%',
            $days->map(fn (array $d) => (string) ($d['day'] ?? $d['date'] ?? ''))->all(),
            [['label' => 'Chance of rain', 'data' => $days->map(fn (array $d) => round((float) $d['precipitation_probability']))->all(), 'kind' => 'predicted']],
            'Forecast is not available right now.',
            ['High / low' => $days->map($temps)->all()]);
    }

    private function predictedYieldKpi(?array $prediction): array
    {
        $value = $prediction['predicted_yield'] ?? $prediction['predicted_yield_tons_per_hectare'] ?? null;
        $has = is_numeric($value);
        $period = ! empty($prediction['target_month']) ? Carbon::parse($prediction['target_month'].'-01')->format('F Y') : null;

        return [
            'key' => 'predicted-yield',
            'label' => 'Predicted Rice Yield',
            'value' => $has ? number_format((float) $value, 2) : self::NO_DATA,
            'unit' => $has ? 't/ha' : null,
            'note' => $has
                ? 'Estimate, not a harvest record'.($period ? ' · '.$period : '')
                : 'The prediction service is not available right now',
            'trend' => null,
            'status' => null,
            'status_label' => $has ? 'Predicted' : null,
            'href' => null,
        ];
    }

    private function currentWeatherKpi(?array $weather): array
    {
        $current = $weather['current'] ?? null;
        $temperature = $current['temperature_c'] ?? $current['temperature'] ?? null;
        $has = ($weather['success'] ?? false) && is_numeric($temperature);

        $parts = [];
        if ($has) {
            $rain = $weather['today']['rainfall_mm'] ?? null;
            $humidity = $current['humidity_percent'] ?? $current['humidity'] ?? null;
            $wind = $current['wind_kph'] ?? $current['wind_speed'] ?? null;
            $parts[] = is_numeric($rain) ? 'Rain today '.number_format((float) $rain, 1).' mm' : null;
            $parts[] = is_numeric($humidity) ? 'Humidity '.round((float) $humidity).'%' : null;
            $parts[] = is_numeric($wind) ? 'Wind '.round((float) $wind).' km/h' : null;
        }

        return [
            'key' => 'current-weather',
            'label' => 'Current Weather',
            'value' => $has ? number_format((float) $temperature, 1) : self::NO_DATA,
            'unit' => $has ? '°C' : null,
            'note' => $has
                ? implode(' · ', array_filter($parts)).' · Online weather service'
                    .(! empty($weather['fetched_at']) ? ' · updated '.Carbon::parse($weather['fetched_at'])->timezone(config('services.weather.timezone', 'Asia/Manila'))->format('g:i A') : '')
                : 'Live weather is not available right now',
            'trend' => null,
            'status' => null,
            'status_label' => null,
            'href' => null,
        ];
    }

    /**
     * Recommended / Monitor / Not recommended — taken from the model's own
     * planting advisory text and risk level, falling back to the barangay's
     * stored heat-map rating. Irrigation guidance rides along in the note.
     */
    private function plantingAdvisoryKpi(?array $prediction, ?string $barangay): array
    {
        $text = $prediction['planting_advisory'] ?? null;
        $irrigation = $prediction['irrigation_recommendation'] ?? null;
        $risk = $prediction['risk_level'] ?? null;

        if (! $text && $barangay) {
            $area = HeatmapArea::query()->where('barangay', $barangay)->latest('updated_at')->first();
            $text = $area?->planting_advisory;
            $irrigation ??= $area?->irrigation_recommendation;
            $risk ??= $area?->risk_level;
        }

        if (! $text && ! $risk) {
            return [
                'key' => 'planting-advisory', 'label' => 'Planting Advisory', 'value' => self::NO_DATA, 'unit' => null,
                'note' => $barangay ? 'No rating for '.$barangay.' yet' : 'Add your barangay to your profile',
                'trend' => null, 'status' => null, 'status_label' => null, 'href' => route('planting-advisories.index'),
            ];
        }

        $lower = Str::lower((string) $text);
        $level = match (true) {
            Str::contains($lower, ['not recommended', 'avoid', 'delay', 'postpone', 'do not']) => 'Not recommended',
            Str::contains($lower, ['ideal', 'suitable', 'good', 'favorable', 'favourable']) => 'Recommended',
            default => 'Monitor',
        };
        if ($risk === 'Severe') {
            $level = 'Not recommended';
        } elseif ($risk === 'High' && $level === 'Recommended') {
            $level = 'Monitor';
        }

        return [
            'key' => 'planting-advisory',
            'label' => 'Planting Advisory',
            'value' => $level,
            'unit' => null,
            'note' => trim(($text ? rtrim((string) $text, '.').'.' : '').($irrigation ? ' Irrigation: '.lcfirst(rtrim((string) $irrigation, '.')).'.' : '')),
            'trend' => null,
            'status' => match ($level) {'Recommended' => 'good', 'Monitor' => 'warning', default => 'critical'},
            'status_label' => null,
            'href' => route('planting-advisories.index'),
        ];
    }

    /** Adds the model's yield estimate to the yield chart as its own (predicted) dataset. */
    private function withPredictedYield(array $chart, ?array $prediction): array
    {
        $value = $prediction['predicted_yield'] ?? null;
        if (! is_numeric($value) || empty($prediction['target_month'])) {
            return $chart;
        }

        $target = Carbon::parse($prediction['target_month'].'-01');
        $label = (string) $target->year;
        $recorded = array_combine($chart['labels'], $chart['datasets'][0]['data']);
        $labels = array_values(array_unique(array_merge($chart['labels'], [$label])));
        sort($labels);

        $extra = [];
        foreach ($chart['extra'] as $name => $values) {
            $byLabel = array_combine($chart['labels'], $values);
            $extra[$name] = array_map(fn (string $l) => $byLabel[$l] ?? 'not on file', $labels);
        }

        $chart['labels'] = $labels;
        $chart['extra'] = $extra;
        $chart['datasets'][0]['data'] = array_map(fn (string $l) => $recorded[$l] ?? null, $labels);
        $chart['datasets'][] = [
            'label' => 'Predicted ('.$target->format('M Y').')',
            'kind' => 'predicted',
            'data' => array_map(fn (string $l) => $l === $label ? round((float) $value, 2) : null, $labels),
        ];
        $chart['empty'] = false;
        $chart['subtitle'] .= ' · the separate marker is a model prediction';

        return $chart;
    }

    /* ------------------------------------------------------------------
     | MAO — "What is happening in agriculture?"
     * ------------------------------------------------------------------ */

    public function mao(?int $requestedYear, ?string $weatherWarningLabel = null): array
    {
        $year = $this->resolveYear($requestedYear);

        $data = \App\Support\StaleCache::remember(
            'dashboard:overview:mao:v'.DashboardData::version().':'.($year ?? 'none'),
            self::CACHE_SECONDS,
            fn (): array => $this->maoStored($year),
        );

        foreach ($data['charts'] as $i => $chart) {
            if ($chart['id'] === 'risk-levels' && $weatherWarningLabel) {
                $data['charts'][$i]['subtitle'] = trim($chart['subtitle'].' · Weather status: '.$weatherWarningLabel);
            }
        }

        return ['role' => 'mao', 'year' => $year, 'years' => $this->availableYears()->all(), 'sources' => $this->provenance->mao()] + $data;
    }

    /**
     * Monthly rainfall for one year: recorded months and (separately) model-forecast months. Null when there is nothing
     * to draw, so the caller can leave the chart out instead of showing an empty one.
     *
     * @return array{year: int, chart: array<string, mixed>, has_forecast: bool}|null
     */
    public function rainfallTrend(?int $requestedYear = null): ?array
    {
        $year = $this->resolveYear($requestedYear);
        if (! $year) {
            return null;
        }

        $chart = $this->rainfallChart($year);
        if ($chart['empty']) {
            return null;
        }

        $forecast = collect($chart['datasets'])->firstWhere('kind', 'predicted');

        return [
            'year' => $year,
            'chart' => $chart,
            'has_forecast' => $forecast !== null && collect($forecast['data'])->contains(fn ($v) => $v !== null),
        ];
    }
    /**
     * Monthly Ambulong rainfall + temperature for one year (recorded = solid, model forecast = dashed), for the MAO
     * overview. Null when there is nothing to draw. The annual total is the sum of the months ACTUALLY recorded, and
     * says how many those are, so a part-year is never presented as a full year.
     *
     * @return array{year: int, chart: array<string, mixed>, annual_rainfall: ?float, rainfall_months: int, temperature_avg: ?float}|null
     */
    public function climateSnapshot(?int $requestedYear = null): ?array
    {
        $year = $this->resolveYear($requestedYear);
        if (! $year) {
            return null;
        }

        $chart = $this->weatherLine('weather-trend', 'Rainfall & Temperature', $year, ['rainfall', 'temperature']);
        if ($chart['empty']) {
            return null;
        }

        $actual = $this->monthlyWeather($year)['actual'];
        $rain = array_values(array_filter($actual['rainfall'], fn ($v) => $v !== null));
        $temp = array_values(array_filter($actual['temperature'], fn ($v) => $v !== null));

        return [
            'year' => $year,
            'chart' => $chart,
            'annual_rainfall' => $rain ? round(array_sum($rain), 0) : null,
            'rainfall_months' => count($rain),
            'temperature_avg' => $temp ? round(array_sum($temp) / count($temp), 1) : null,
        ];
    }
    private function maoStored(?int $year): array
    {
        $totalBarangays = count(LianBarangays::coordinates());
        $current = $year ? $this->rice()->where('year', $year) : collect();
        $area = (float) $current->sum('area_hectares');
        $production = (float) $current->sum('total_production');
        $compare = $year ? $this->compareWithPrevious($year) : null;

        // Yield is the project's historical yield dataset (municipal monthly averages);
        // production tonnage needs real harvest records and has none.
        $yield = $year ? $this->yields->yearlyMean($year) : null;
        $yieldCompare = $year ? $this->yields->compareWithPrevious($year) : null;
        $yieldMonths = $year ? $this->yields->monthsRecorded($year) : 0;

        $areas = $this->data->currentRiskRatings();
        $withRecords = $current->pluck('barangay')->filter()->unique()->count();
        $activeAdvisories = PlantingAdvisory::query()->active()->count();
        $totalAdvisories = PlantingAdvisory::query()->count();
        $pending = PlantingAdvisory::query()->pendingReview()->count();

        $completedRuns = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->where('type', 'rice_yield_prediction')->where('status', 'completed')->count();
        $flaggedRuns = count(app(\App\Support\SampleDataDetector::class)->excludedIds('analytics_runs'));
        $schedulePredictions = \App\Support\SchemaCache::hasTable('farmer_planting_schedules')
            ? FarmerPlantingSchedule::query()->whereNotNull('predicted_yield')->count()
            : 0;

        $kpis = [
            [
                'key' => 'production', 'label' => 'Total Rice Production'.($year ? ' ('.$year.')' : ''),
                'value' => $year && $current->isNotEmpty() ? number_format($production, 1) : self::INSUFFICIENT,
                'unit' => $year && $current->isNotEmpty() ? 'tons' : null,
                'note' => $compare
                    ? 'Production change compares the same '.$compare['barangays'].' '.Str::plural('barangay', $compare['barangays']).' with '.$compare['previous_year']
                    : ($current->isNotEmpty() ? $withRecords.' of '.$totalBarangays.' barangays reported · no earlier year to compare' : 'No verified harvest records for this year'),
                'trend' => $compare && $compare['production_change'] !== null ? ['delta' => $compare['production_change'], 'label' => 'vs '.$compare['previous_year'], 'good' => 'up'] : null,
                'status' => null, 'status_label' => null, 'href' => route('rice-productions.index'),
            ],
            [
                'key' => 'yield', 'label' => 'Average Rice Yield'.($year ? ' ('.$year.')' : ''),
                'value' => $yield !== null ? number_format($yield, 2) : self::INSUFFICIENT,
                'unit' => $yield !== null ? 't/ha' : null,
                'note' => $yield !== null
                    ? 'Historical · mean of '.$yieldMonths.' monthly averages'.($yieldCompare ? ' · change uses the same '.$yieldCompare['months'].' months as '.$yieldCompare['previous_year'] : '')
                    : 'No verified yield history for this year',
                'trend' => $yieldCompare ? ['delta' => $yieldCompare['change'], 'label' => 'vs '.$yieldCompare['previous_year'], 'good' => 'up'] : null,
                'status' => null, 'status_label' => null, 'href' => null,
            ],
            [
                'key' => 'monitored', 'label' => 'Monitored Barangays',
                'value' => $areas->isEmpty() ? self::NO_DATA : number_format($areas->count()),
                'unit' => $areas->isEmpty() ? null : 'of '.$totalBarangays,
                'note' => $areas->isEmpty() ? 'No risk ratings yet' : 'Calculated risk ratings · '.$withRecords.' with verified harvest records in '.($year ?? 'the latest year'),
                'progress' => $areas->isEmpty() ? null : round(($areas->count() / max(1, $totalBarangays)) * 100),
                'trend' => null, 'status' => null, 'status_label' => null, 'href' => '#barangayRiskMap',
            ],
            [
                'key' => 'advisories', 'label' => 'Active Planting Advisories',
                'value' => $totalAdvisories > 0 ? number_format($activeAdvisories) : self::NO_DATA, 'unit' => null,
                'note' => $totalAdvisories === 0 ? 'No advisories have been created yet' : ($pending > 0 ? $pending.' waiting for review' : 'Nothing waiting for review'),
                'trend' => null, 'status' => $pending > 0 ? 'warning' : null, 'status_label' => $pending > 0 ? 'Review' : null,
                'href' => route('planting-advisories.index'),
            ],
            [
                'key' => 'predictions', 'label' => 'Yield Predictions',
                'value' => $completedRuns + $schedulePredictions > 0 ? number_format($completedRuns + $schedulePredictions) : self::NO_DATA, 'unit' => null,
                'note' => $completedRuns.' valid prediction '.Str::plural('run', $completedRuns).' · '.$schedulePredictions.' farm '.Str::plural('schedule', $schedulePredictions).' with an estimate'.($flaggedRuns > 0 ? ' · '.$flaggedRuns.' invalid run(s) not counted' : ''),
                'trend' => null, 'status' => null, 'status_label' => 'Predicted', 'href' => null,
            ],
        ];

        $weatherYear = $this->monthlyWeather($year)['actual'];
        $rainMonths = array_filter($weatherYear['rainfall'], fn ($v) => $v !== null);
        $tempMonths = array_filter($weatherYear['temperature'], fn ($v) => $v !== null);
        $riceAll = $this->rice();
        $profiles = \App\Models\FarmerProfile::query()->count();

        $kpis = $this->adaptKpis($kpis, [
            $this->kpi('production-records', 'Production Records', $riceAll->isNotEmpty() ? number_format($riceAll->count()) : null, null,
                $riceAll->pluck('year')->unique()->count().' '.Str::plural('year', $riceAll->pluck('year')->unique()->count()).' of verified harvest records', route('rice-productions.index')),
            $this->kpi('weather-records', 'Weather Records'.($year ? ' ('.$year.')' : ''), $rainMonths ? number_format(count($rainMonths)) : null, 'months',
                'Ambulong monthly records for '.($year ?? 'the latest year'), route('climate-records.index')),
            $this->kpi('annual-rainfall', 'Rainfall'.($year ? ' ('.$year.')' : ''), $rainMonths ? number_format(array_sum($rainMonths), 0) : null, 'mm',
                'Historical · Ambulong records'.($tempMonths ? ' · average '.number_format(array_sum($tempMonths) / count($tempMonths), 1).' °C' : '')),
            $this->kpi('farmer-profiles', 'Farmer Profiles', $profiles > 0 ? number_format($profiles) : null, null, 'Registered farm records', route('farmer-profiles.index')),
        ]);

        $altCharts = [
            fn (): array => $this->yieldByYearChart(),
            fn (): array => $this->doughnut('advisory-status', 'Active Advisories by Severity', 'Municipality-wide',
                PlantingAdvisory::query()->active()->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity')
                    ->mapWithKeys(fn ($total, $severity) => [Str::headline((string) $severity) => (int) $total])->all(),
                'No active advisories.'),
            fn (): array => $this->predictionsByMonthChart(),
        ];

        $byYear = $this->yieldSeries(null, $year);
        $byBarangay = $current->groupBy('barangay')->map(fn (Collection $rows, $barangay) => [
            'barangay' => (string) $barangay,
            'production' => (float) $rows->sum('total_production'),
            'area' => (float) $rows->sum('area_hectares'),
        ])->sortByDesc('production')->values();
        $risk = collect(['Low', 'Moderate', 'High', 'Severe'])
            ->mapWithKeys(fn (string $level) => [$level => $areas->where('risk_level', $level)->count()])
            ->filter()->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'kpis' => $kpis,
            'charts' => $this->adaptCharts([
                $this->chart('production-trend', 'Rice Production Trend', 'Tons reported per year (barangay coverage varies by year)', 'line', 'tons',
                    $byYear->pluck('year')->map(fn ($y) => (string) $y)->all(),
                    [['label' => 'Recorded production', 'data' => $byYear->pluck('production')->map(fn ($v) => round($v, 1))->all(), 'kind' => 'actual']],
                    'Insufficient verified data — no harvest records with production figures.'),
                $this->yieldByMonthChart($year),
                $this->chart('production-by-barangay', 'Production by Barangay', $year ? 'Tons in '.$year : 'Tons', 'bar', 'tons',
                    $byBarangay->pluck('barangay')->all(),
                    [['label' => 'Recorded production', 'data' => $byBarangay->pluck('production')->map(fn ($v) => round($v, 1))->all(), 'kind' => 'actual']],
                    'Insufficient verified data — no harvest records for this year.',
                    ['Yield' => $byBarangay->map(fn (array $r) => $r['area'] > 0 ? number_format($r['production'] / $r['area'], 2).' t/ha' : 'n/a')->all()],
                    horizontal: true),
                $this->doughnut('risk-levels', 'Barangays by Farm Risk Level', 'Current rating, one per barangay', $risk, 'No risk ratings yet.'),
                $this->weatherLine('weather-trend', 'Rainfall & Temperature', $year, ['rainfall', 'temperature']),
            ], $altCharts),
        ];
    }

    /**
     * Production and yield change versus the closest earlier year, using only
     * barangays that reported in both — otherwise a change in who reported
     * would show up as a fake swing.
     *
     * @return array{previous_year: int, barangays: int, production_change: ?float, yield_change: ?float}|null
     */
    private function compareWithPrevious(int $year): ?array
    {
        $previousYear = $this->rice()->pluck('year')->filter(fn ($y) => $y < $year)->max();
        if (! $previousYear) {
            return null;
        }

        $now = $this->rice()->where('year', $year)->groupBy('barangay');
        $before = $this->rice()->where('year', $previousYear)->groupBy('barangay');
        $common = $now->keys()->intersect($before->keys());
        if ($common->isEmpty()) {
            return null;
        }

        $sum = fn (Collection $grouped, string $column): float => (float) $common->sum(fn ($b) => $grouped[$b]->sum($column));
        $prodNow = $sum($now, 'total_production');
        $prodBefore = $sum($before, 'total_production');
        $areaNow = $sum($now, 'area_hectares');
        $areaBefore = $sum($before, 'area_hectares');
        $pct = fn (float $a, float $b): ?float => $b > 0 ? round((($a - $b) / $b) * 100, 1) : null;

        return [
            'previous_year' => (int) $previousYear,
            'barangays' => $common->count(),
            'production_change' => $pct($prodNow, $prodBefore),
            'yield_change' => ($areaNow > 0 && $areaBefore > 0) ? $pct($prodNow / $areaNow, $prodBefore / $areaBefore) : null,
        ];
    }

    private function yieldByMonthChart(?int $year): array
    {
        $data = $year ? $this->yields->monthly($year) : array_fill(0, 12, null);

        return $this->chart('yield-by-month', 'Average Yield by Month', ($year ? $year.' · ' : '').'Municipal monthly average, iClimate modeling dataset', 'bar', 't/ha', self::MONTHS,
            [['label' => 'Historical yield', 'data' => $data, 'kind' => 'actual']],
            'No verified monthly yield on file for this year.');
    }

    private function monthNumber(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return (int) $value->format('n');
        }
        if (is_numeric($value)) {
            $m = (int) $value;

            return $m >= 1 && $m <= 12 ? $m : 0;
        }
        $ts = strtotime('1 '.$value);

        return $ts ? (int) date('n', $ts) : 0;
    }

    /* ------------------------------------------------------------------
     | IT expert — "Is the system and data working properly?"
     * ------------------------------------------------------------------ */

    public function it(): array
    {
        $data = \App\Support\StaleCache::remember('dashboard:overview:it:v'.DashboardData::version(), self::CACHE_SECONDS, fn (): array => $this->itStored());

        $data['sources'] = $this->provenance->it($data['status']);

        return ['role' => 'it', 'year' => null, 'years' => []] + $data;
    }

    private function itStored(): array
    {
        $users = User::query()->selectRaw('role, status, COUNT(*) as total')->groupBy('role', 'status')->get();
        $totalUsers = (int) $users->sum('total');
        $activeUsers = (int) $users->where('status', User::STATUS_ACTIVE)->sum('total');
        $byRole = $users->groupBy('role')->map(fn (Collection $rows) => (int) $rows->sum('total'));

        $weatherRecords = SampleData::without(ClimateRecord::query(), 'climate_records')->count();
        $ambulongRows = $this->ambulongRows();
        $runs = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', self::PREDICTION_RUN_TYPES)->count();
        $forecasts = MonthlyWeatherPrediction::count();
        $riceRecords = SampleData::without(RiceProduction::query(), 'rice_productions')->count();
        $excludedClimate = count(app(\App\Support\SampleDataDetector::class)->excludedIds('climate_records'));
        $excludedRice = count(app(\App\Support\SampleDataDetector::class)->excludedIds('rice_productions'));
        $unread = SampleData::without(UserNotification::query(), 'notifications')->where('is_read', false)->count();
        $allNotifications = SampleData::without(UserNotification::query(), 'notifications')->count();

        $counts = [
            'Climate records' => $weatherRecords,
            'Rice production' => $riceRecords,
            'Monthly forecasts' => $forecasts,
            'Prediction runs' => $runs,
            'Advisories' => PlantingAdvisory::count(),
            'Reports' => Report::query()->realGenerated()->count(),
            'Community posts' => FeedPost::query()->whereNull('archived_at')->count(),
            'Notifications' => $allNotifications,
        ];

        $status = $this->systemStatus();

        $logTotal = SystemLog::query()->count();
        $reportCount = Report::query()->realGenerated()->count();
        $advisoryTotal = PlantingAdvisory::count();
        $postCount = FeedPost::query()->whereNull('archived_at')->count();
        $altKpis = [
            $this->kpi('log-events', 'System Log Events', $logTotal > 0 ? number_format(SystemLog::query()->where('created_at', '>=', \App\Support\BusinessClock::startOfDayUtc(29))->count()) : null, null,
                'Audit events in the last 30 days · '.number_format($logTotal).' on file', route('system-logs.index')),
            $this->kpi('active-rate', 'Active Accounts', $totalUsers > 0 ? number_format(round(($activeUsers / $totalUsers) * 100)) : null, '%',
                $activeUsers.' of '.$totalUsers.' accounts are active', route('users.index')),
            $this->kpi('advisories', 'Planting Advisories', $advisoryTotal > 0 ? number_format($advisoryTotal) : null, null, 'Advisories created on the platform', route('planting-advisories.index')),
            $this->kpi('reports', 'Reports Generated', $reportCount > 0 ? number_format($reportCount) : null, null, 'Reports stored', route('reports.index')),
            $this->kpi('community-posts', 'Community Posts', $postCount > 0 ? number_format($postCount) : null, null, 'Active posts in the community feed'),
        ];
        $altCharts = [
            fn (): array => $this->recordsByMonthChart(),
            fn (): array => $this->doughnut('notifications-by-type', 'Notifications by Type', number_format($allNotifications).' sent',
                SampleData::without(UserNotification::query(), 'notifications')->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type')
                    ->mapWithKeys(fn ($total, $type) => [(string) $type => (int) $total])->all(), 'No notifications sent yet.'),
            fn (): array => $this->doughnut('advisories-by-severity', 'Advisories by Severity', $advisoryTotal.' advisories',
                PlantingAdvisory::query()->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity')
                    ->mapWithKeys(fn ($total, $severity) => [Str::headline((string) $severity) => (int) $total])->all(), 'No advisories yet.'),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'status' => $status,
            'kpis' => $this->adaptKpis([
                ['key' => 'users', 'label' => 'Total Users', 'value' => number_format($totalUsers), 'unit' => null,
                    'note' => $activeUsers.' active · '.($totalUsers - $activeUsers).' inactive',
                    'trend' => null, 'status' => null, 'status_label' => null, 'href' => route('users.index')],
                ['key' => 'weather-records', 'label' => 'Weather Records', 'value' => $weatherRecords + $ambulongRows > 0 ? number_format($weatherRecords + $ambulongRows) : self::NO_DATA, 'unit' => null,
                    'note' => $weatherRecords.' verified database records + '.$ambulongRows.' Ambulong monthly rows (separate sources)'.($excludedClimate > 0 ? ' · '.$excludedClimate.' excluded (sample/impossible)' : ''),
                    'trend' => null, 'status' => null, 'status_label' => null, 'href' => route('climate-records.index')],
                ['key' => 'prediction-records', 'label' => 'Prediction Records', 'value' => $runs + $forecasts > 0 ? number_format($runs + $forecasts) : self::NO_DATA, 'unit' => null,
                    'note' => $runs.' prediction runs · '.$forecasts.' monthly weather forecasts',
                    'trend' => null, 'status' => null, 'status_label' => null, 'href' => null],
                ['key' => 'rice-records', 'label' => 'Rice Production Records', 'value' => $riceRecords > 0 ? number_format($riceRecords) : self::INSUFFICIENT, 'unit' => null,
                    'note' => $riceRecords > 0 ? 'Verified harvest records across all years' : 'No verified harvest records'.($excludedRice > 0 ? ' · '.$excludedRice.' seeded sample row(s) excluded' : ''),
                    'trend' => null, 'status' => null, 'status_label' => null, 'href' => route('rice-productions.index')],
                ['key' => 'notifications', 'label' => 'Active Notifications', 'value' => $allNotifications > 0 ? number_format($unread) : self::NO_DATA, 'unit' => null,
                    'note' => 'Unread of '.number_format($allNotifications).' sent',
                    'trend' => null, 'status' => null, 'status_label' => null, 'href' => route('notifications.index')],
            ], $altKpis),
            'charts' => $this->adaptCharts([
                $this->doughnut('users-by-role', 'Users by Role', $totalUsers.' accounts',
                    $byRole->mapWithKeys(fn ($total, $role) => [(string) $role => $total])->all(), 'No users yet.'),
                $this->chart('records-by-type', 'Records by Data Type', 'Stored rows per table', 'bar', 'records',
                    array_keys($counts), [['label' => 'Records', 'data' => array_values($counts), 'kind' => 'actual']], 'No records stored yet.', horizontal: true),
                $this->activityChart(),
                $this->predictionsByMonthChart(),
            ], $altCharts),
        ];
    }

    private function activityChart(): array
    {
        $from = \App\Support\BusinessClock::today()->subDays(29);
        $perDay = SystemLog::query()
            ->where('created_at', '>=', $from->copy()->utc())
            ->selectRaw('' . \App\Support\BusinessClock::sqlDate('created_at') . ' as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('M j');
            $data[] = (int) ($perDay[$day->toDateString()] ?? 0);
        }

        return $this->chart('system-activity', 'System Activity', 'Audit log events per day, last 30 days', 'line', 'events', $labels,
            [['label' => 'Log events', 'data' => $data, 'kind' => 'actual']], 'No system log entries in the last 30 days.');
    }

    private function predictionsByMonthChart(): array
    {
        $start = \App\Support\BusinessClock::today()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn (int $i) => $start->copy()->addMonths($i));

        $runs = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', self::PREDICTION_RUN_TYPES)->where('created_at', '>=', $start)->pluck('created_at');
        $forecasts = MonthlyWeatherPrediction::query()->whereNotNull('generated_at')->where('generated_at', '>=', $start)->pluck('generated_at');
        $bucket = fn (Collection $dates): array => $months->map(fn (Carbon $m) => $dates->filter(fn ($d) => Carbon::parse($d)->isSameMonth($m))->count())->all();

        return $this->chart('predictions-by-month', 'Predictions by Month', 'Runs and forecasts generated, last 12 months', 'bar', 'records',
            $months->map(fn (Carbon $m) => $m->format('M Y'))->all(),
            [
                ['label' => 'Prediction runs', 'data' => $bucket($runs), 'kind' => 'actual'],
                ['label' => 'Weather forecasts generated', 'data' => $bucket($forecasts), 'kind' => 'actual'],
            ], 'No predictions generated in the last 12 months.');
    }

    /** @return array{headline: string, healthy: int, needs_attention: int, unavailable: int, items: array<int, array{title: string, status: string}>} */
    private function systemStatus(): array
    {
        // The checks touch the database, queue, model files and a backup lookup
        // (several seconds against a remote database), so they get a longer TTL.
        try {
            return Cache::remember('dashboard:overview:it-status', now()->addMinutes(5), function (): array {
                $health = app(SystemHealthService::class);
                $checks = $health->checks();

                return ['checked_at' => now()->toIso8601String()] + $health->summarize($checks) + ['items' => collect($checks)->map(fn (array $c) => ['title' => $c['title'], 'status' => strtolower($c['status'])])->all()];
            });
        } catch (Throwable) {
            return ['headline' => 'System checks are unavailable.', 'healthy' => 0, 'needs_attention' => 0, 'unavailable' => 0, 'items' => []];
        }
    }

    private function ambulongRows(): int
    {
        try {
            return $this->ambulong->load()['observations']->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /* ------------------------------------------------------------------
     | Adaptive selection: the preferred KPI/chart when it has data,
     | otherwise the next available alternate for the same role.
     * ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function kpi(string $key, string $label, ?string $value, ?string $unit, string $note, ?string $href = null): array
    {
        return [
            'key' => $key, 'label' => $label,
            'value' => $value ?? self::NO_DATA, 'unit' => $value !== null ? $unit : null,
            'note' => $note, 'trend' => null, 'status' => null, 'status_label' => null, 'href' => $href,
        ];
    }

    private function kpiEmpty(array $kpi): bool
    {
        return in_array($kpi['value'] ?? null, [self::NO_DATA, self::INSUFFICIENT], true);
    }

    /**
     * Keeps every primary KPI that has data; each one without data is replaced by
     * the next alternate that has data. With no alternate left it stays as a clean
     * "no data" card.
     *
     * @param  array<int, array<string, mixed>>  $primary
     * @param  array<int, array<string, mixed>|callable>  $alternates  built lazily, in order of relevance
     * @return array<int, array<string, mixed>>
     */
    private function adaptKpis(array $primary, array $alternates): array
    {
        $taken = array_column($primary, 'key');
        $out = [];

        foreach ($primary as $kpi) {
            if (! $this->kpiEmpty($kpi)) {
                $out[] = $kpi;

                continue;
            }

            $replacement = null;
            while ($alternates !== [] && $replacement === null) {
                $candidate = array_shift($alternates);
                $candidate = is_callable($candidate) ? $candidate() : $candidate;
                if (is_array($candidate) && ! in_array($candidate['key'], $taken, true) && ! $this->kpiEmpty($candidate)) {
                    $replacement = $candidate;
                    $taken[] = $candidate['key'];
                }
            }
            $out[] = $replacement ?? $kpi;
        }

        return $out;
    }

    /**
     * Charts without data are swapped for alternates that have data, or dropped.
     * If nothing has data, the first preferred chart stays as the "no data" state.
     *
     * @param  array<int, array<string, mixed>>  $primary
     * @param  array<int, array<string, mixed>|callable>  $alternates
     * @return array<int, array<string, mixed>>
     */
    private function adaptCharts(array $primary, array $alternates): array
    {
        $taken = array_column($primary, 'id');
        $out = [];

        foreach ($primary as $chart) {
            if (! $chart['empty']) {
                $out[] = $chart;

                continue;
            }

            while ($alternates !== []) {
                $candidate = array_shift($alternates);
                $candidate = is_callable($candidate) ? $candidate() : $candidate;
                if (is_array($candidate) && ! in_array($candidate['id'], $taken, true) && ! $candidate['empty']) {
                    $out[] = $candidate;
                    $taken[] = $candidate['id'];

                    break;
                }
            }
        }

        return $out !== [] ? $out : array_slice($primary, 0, 1);
    }

    private function irrigationKpi(?array $prediction, ?string $barangay): array
    {
        $text = $prediction['irrigation_recommendation'] ?? null;
        if (! $text && $barangay) {
            $text = HeatmapArea::query()->where('barangay', $barangay)->latest('updated_at')->first()?->irrigation_recommendation;
        }

        return $this->kpi('irrigation', 'Irrigation Recommendation', $text ? Str::limit(Str::of((string) $text)->before('.')->trim()->ucfirst()->toString(), 40) : null, null,
            $text ? rtrim((string) $text, '.').'.' : 'No irrigation guidance available', route('planting-advisories.index'));
    }

    private function rainTodayKpi(?array $weather): array
    {
        $rain = ($weather['success'] ?? false) ? ($weather['today']['rainfall_mm'] ?? null) : null;

        return $this->kpi('rain-today', 'Rain Today', is_numeric($rain) ? number_format((float) $rain, 1) : null, 'mm', 'Online weather service · today so far');
    }

    private function humidityWindKpi(?array $weather): array
    {
        $current = ($weather['success'] ?? false) ? ($weather['current'] ?? null) : null;
        $humidity = $current['humidity_percent'] ?? $current['humidity'] ?? null;
        $wind = $current['wind_kph'] ?? $current['wind_speed'] ?? null;

        return $this->kpi('humidity-wind', 'Humidity', is_numeric($humidity) ? (string) round((float) $humidity) : null, '%',
            is_numeric($wind) ? 'Wind '.round((float) $wind).' km/h · online weather service' : 'Online weather service');
    }

    private function temperatureForecastChart(?array $weather): array
    {
        $days = collect($weather['forecast'] ?? [])->filter(fn ($d) => is_array($d) && is_numeric($d['temperature_max'] ?? null) && is_numeric($d['temperature_min'] ?? null))->values();

        return $this->chart('temperature-forecast', 'Temperature This Week', 'Daily high and low · online weather forecast', 'line', '°C',
            $days->map(fn (array $d) => (string) ($d['day'] ?? $d['date'] ?? ''))->all(), [
                ['label' => 'High', 'data' => $days->map(fn (array $d) => round((float) $d['temperature_max'], 1))->all(), 'kind' => 'predicted'],
                ['label' => 'Low', 'data' => $days->map(fn (array $d) => round((float) $d['temperature_min'], 1))->all(), 'kind' => 'predicted'],
            ], 'Forecast is not available right now.');
    }

    private function yieldByYearChart(): array
    {
        $years = $this->yields->years()->sort()->values();

        return $this->chart('yield-by-year', 'Average Yield by Year', 'Yearly mean of municipal monthly averages', 'line', 't/ha',
            $years->map(fn ($y) => (string) $y)->all(),
            [['label' => 'Historical yield', 'data' => $years->map(fn ($y) => round((float) $this->yields->yearlyMean($y), 2))->all(), 'kind' => 'actual']],
            'No verified yield history to chart yet.');
    }

    private function recordsByMonthChart(): array
    {
        $start = \App\Support\BusinessClock::today()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn (int $i) => $start->copy()->addMonths($i));
        $bucket = fn (Collection $dates): array => $months->map(fn (Carbon $m) => $dates->filter(fn ($d) => Carbon::parse($d)->isSameMonth($m))->count())->all();

        $sets = [
            'Climate records' => SampleData::without(ClimateRecord::query(), 'climate_records')->where('created_at', '>=', $start)->limit(50000)->pluck('created_at'),
            'Rice production' => SampleData::without(RiceProduction::query(), 'rice_productions')->where('created_at', '>=', $start)->limit(50000)->pluck('created_at'),
            'Advisories' => PlantingAdvisory::query()->where('created_at', '>=', $start)->pluck('created_at'),
            'Reports' => Report::query()->realGenerated()->where('created_at', '>=', $start)->pluck('created_at'),
        ];

        return $this->chart('records-by-month', 'Records Added by Month', 'Rows created per month, last 12 months', 'bar', 'records',
            $months->map(fn (Carbon $m) => $m->format('M Y'))->all(),
            collect($sets)->map(fn (Collection $dates, string $label) => ['label' => $label, 'data' => $bucket($dates), 'kind' => 'actual'])->filter(fn (array $d) => array_sum($d['data']) > 0)->values()->all(),
            'No records were added in the last 12 months.');
    }

    /* ------------------------------------------------------------------
     | Shared builders
     * ------------------------------------------------------------------ */

    /** @return Collection<int, RiceProduction> */
    private function rice(): Collection
    {
        return $this->data->riceRows()->whereNotNull('year');
    }

    /**
     * Area-weighted yield per year up to (and including) the selected year.
     *
     * @return Collection<int, array{year: int, yield: float, production: float}>
     */
    private function yieldSeries(?string $barangay, ?int $upToYear): Collection
    {
        return $this->rice()
            ->when($barangay, fn (Collection $rows) => $rows->where('barangay', $barangay))
            ->when($upToYear, fn (Collection $rows) => $rows->where('year', '<=', $upToYear))
            ->groupBy('year')
            ->map(function (Collection $rows, $year): ?array {
                $area = (float) $rows->sum('area_hectares');

                return $area > 0 ? ['year' => (int) $year, 'yield' => (float) $rows->sum('total_production') / $area, 'production' => (float) $rows->sum('total_production')] : null;
            })
            ->filter()
            ->sortBy('year')
            ->values();
    }

    /**
     * Twelve months of Ambulong records for a year, with the persisted model
     * forecast filling months that have no recorded value (kept separate so
     * predicted values are never drawn as history).
     *
     * @return array{actual: array<string, array<int, ?float>>, predicted: array<string, array<int, ?float>>}
     */
    private function monthlyWeather(?int $year): array
    {
        $metrics = ['rainfall' => 'rainfall', 'temperature' => 'temperature', 'humidity' => 'humidity'];
        $actual = array_fill_keys(array_keys($metrics), array_fill(0, 12, null));
        $predicted = $actual;

        if (! $year) {
            return compact('actual', 'predicted');
        }

        try {
            $observations = $this->ambulong->load()['observations']->where('year', $year);
        } catch (Throwable) {
            $observations = collect();
        }
        foreach ($observations as $row) {
            foreach ($metrics as $key => $field) {
                $actual[$key][$row['month'] - 1] = isset($row[$field]) ? round((float) $row[$field], 1) : null;
            }
        }

        $forecasts = MonthlyWeatherPrediction::query()->where('prediction_year', $year)->where('status', 'ready')->get();
        foreach ($forecasts as $row) {
            $i = $row->prediction_month - 1;
            if ($i < 0 || $i > 11 || $actual['rainfall'][$i] !== null) {
                continue;
            }
            $predicted['rainfall'][$i] = $row->rainfall !== null ? round((float) $row->rainfall, 1) : null;
            $predicted['temperature'][$i] = $row->temperature_average !== null ? round((float) $row->temperature_average, 1) : null;
            $predicted['humidity'][$i] = $row->humidity !== null ? round((float) $row->humidity, 1) : null;
        }

        return compact('actual', 'predicted');
    }

    private function rainfallChart(?int $year): array
    {
        $w = $this->monthlyWeather($year);

        return $this->chart('monthly-rainfall', 'Monthly Rainfall', ($year ? $year.' · ' : '').'Ambulong records; lighter bars are model forecasts', 'bar', 'mm', self::MONTHS, [
            ['label' => 'Recorded rainfall', 'data' => $w['actual']['rainfall'], 'kind' => 'actual'],
            ['label' => 'Forecast rainfall', 'data' => $w['predicted']['rainfall'], 'kind' => 'predicted'],
        ], 'No Ambulong rainfall data for this year.');
    }

    /** @param  array<int, 'rainfall'|'temperature'|'humidity'>  $metrics  first uses the left axis, second the right axis */
    private function weatherLine(string $id, string $title, ?int $year, array $metrics): array
    {
        $w = $this->monthlyWeather($year);
        $names = ['rainfall' => 'Rainfall (mm)', 'temperature' => 'Temperature (°C)', 'humidity' => 'Humidity (%)'];
        $datasets = [];
        foreach ($metrics as $i => $metric) {
            $axis = $i === 0 ? 'y' : 'y2';
            $datasets[] = ['label' => $names[$metric], 'data' => $w['actual'][$metric], 'kind' => 'actual', 'axis' => $axis, 'series' => $i];
            $datasets[] = ['label' => $names[$metric].' — forecast', 'data' => $w['predicted'][$metric], 'kind' => 'predicted', 'axis' => $axis, 'series' => $i];
        }

        return $this->chart($id, $title, ($year ? $year.' · ' : '').'Monthly Ambulong records; dashed = model forecast', 'line', '', self::MONTHS, $datasets,
            'No Ambulong weather data for this year.', axisTitles: [$names[$metrics[0]], $names[$metrics[1]] ?? null]);
    }

    private function doughnut(string $id, string $title, string $subtitle, array $slices, string $emptyMessage): array
    {
        $slices = array_filter($slices, fn ($v) => $v > 0);

        return $this->chart($id, $title, $subtitle, 'doughnut', '', array_keys($slices),
            [['label' => $title, 'data' => array_values($slices), 'kind' => 'actual']], $emptyMessage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $datasets
     * @param  array<string, array<int, string>>  $extra  extra tooltip lines keyed by name, aligned with labels
     */
    private function chart(string $id, string $title, string $subtitle, string $type, string $unit, array $labels, array $datasets, string $emptyMessage, array $extra = [], bool $horizontal = false, array $axisTitles = []): array
    {
        $hasData = collect($datasets)->contains(fn (array $d) => collect($d['data'])->contains(fn ($v) => $v !== null));

        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'type' => $type,
            'unit' => $unit,
            'labels' => array_values($labels),
            'datasets' => array_values($datasets),
            'extra' => $extra,
            'horizontal' => $horizontal,
            'axis_titles' => $axisTitles,
            'basis' => self::CHART_BASIS[$id] ?? null,
            'empty' => ! $hasData || $labels === [],
            'empty_message' => $emptyMessage,
        ];
    }
}
