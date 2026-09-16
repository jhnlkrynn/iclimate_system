<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\HeatmapAreaForecast;
use App\Support\HeatmapCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LianHeatmapController extends Controller
{
    private const LAYERS = ['impact', 'rainfall', 'radar', 'temperature', 'yield', 'farm_type', 'irrigation'];

    private const FORECAST_HORIZON_MONTHS = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $startedAt = hrtime(true);
        $layer = in_array($request->query('layer'), self::LAYERS, true)
            ? (string) $request->query('layer')
            : 'impact';
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $requestedMonth = $this->resolveForecastMonth($request->query('month'), $currentMonth);
        $isForecast = $requestedMonth !== null;
        $filters = array_filter([
            'search' => mb_substr(trim((string) $request->query('search')), 0, 80),
            'risk_level' => in_array($request->query('risk_level'), ['Low', 'Moderate', 'High', 'Severe'], true)
                ? (string) $request->query('risk_level')
                : '',
            'risk_type' => in_array($request->query('risk_type'), ['Flood', 'Drought', 'Typhoon', 'Heat'], true)
                ? (string) $request->query('risk_type')
                : '',
            'month' => $requestedMonth?->format('Y-m') ?? '',
        ], static fn (string $value): bool => $value !== '');
        $cacheKey = HeatmapCache::key($layer, $filters);
        $forceFresh = $request->boolean('fresh');

        if (! $forceFresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $this->respond($cached, 'HIT', $startedAt, 0.0);
            }
        }

        $profileSummary = collect();
        $dbStartedAt = hrtime(true);
        if ($this->shouldLogPerformance()) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $coordinates = HeatmapArea::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->unique('barangay')
            ->keyBy('barangay');

        $records = $isForecast
            ? HeatmapAreaForecast::query()
                ->where('forecast_month', $requestedMonth->toDateString())
                ->whereIn('barangay', $coordinates->keys())
                ->get()
                ->unique('barangay')
            : $coordinates->values();

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $records = $records->filter(function ($area) use ($search): bool {
                $reason = $area->main_risk_reason ?? '';

                return str_contains(mb_strtolower($area->barangay), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $area->risk_type), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $area->description), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $reason), mb_strtolower($search));
            });
        }
        if (isset($filters['risk_level'])) {
            $records = $records->where('risk_level', $filters['risk_level']);
        }
        if (isset($filters['risk_type'])) {
            $records = $records->where('risk_type', $filters['risk_type']);
        }

        $areas = $records->sortBy('barangay')->values();

        if ($layer === 'farm_type' && $areas->isNotEmpty()) {
            $profileSummary = FarmerProfile::query()
                ->select('barangay', 'farm_type', DB::raw('COUNT(*) as profile_count'))
                ->whereIn('barangay', $areas->pluck('barangay'))
                ->groupBy('barangay', 'farm_type')
                ->get()
                ->groupBy(fn (FarmerProfile $profile): string => $this->normalizeBarangay($profile->barangay));
        }

        $items = $areas->map(function (HeatmapArea|HeatmapAreaForecast $area) use ($layer, $profileSummary, $coordinates): ?array {
            $coordinate = $coordinates->get($area->barangay);

            if (! $coordinate) {
                return null;
            }

            [$reason, $action] = $this->layerText(
                $area,
                $layer,
                $profileSummary->get($this->normalizeBarangay($area->barangay), collect()),
            );

            return [
                'barangay' => $area->barangay,
                'polygon_id' => $this->normalizeBarangay($area->barangay),
                'coordinates' => [(float) $coordinate->latitude, (float) $coordinate->longitude],
                'risk_score' => (float) $area->risk_score,
                'risk_level' => $area->risk_level,
                'risk_type' => $area->risk_type,
                'color' => $this->riskColor($area->risk_level),
                'reason' => $reason,
                'suggested_action' => $action,
                'updated_at' => ($area->risk_computed_at ?? $area->computed_at ?? $area->updated_at)?->toISOString(),
            ];
        })->filter()->values()->all();

        $dbMs = $this->shouldLogPerformance()
            ? collect(DB::getQueryLog())->sum('time')
            : (hrtime(true) - $dbStartedAt) / 1_000_000;
        if ($this->shouldLogPerformance()) {
            DB::disableQueryLog();
        }

        $payload = [
            'layer' => $layer,
            'generated_at' => now()->toISOString(),
            'is_forecast' => $isForecast,
            'month' => $requestedMonth?->format('Y-m') ?? $currentMonth->format('Y-m'),
            'areas' => $items,
        ];

        Cache::put($cacheKey, $payload, now()->addMinutes(HeatmapCache::TTL_MINUTES));

        return $this->respond($payload, $forceFresh ? 'REFRESH' : 'MISS', $startedAt, (float) $dbMs);
    }

    /**
     * Parses the `month` query param (Y-m) into a future-month boundary, or
     * null when it's missing/invalid/not a future month within the
     * precomputed forecast horizon — in which case the caller falls back to
     * the current-state HeatmapArea data instead of a forecast.
     */
    private function resolveForecastMonth(mixed $rawMonth, CarbonImmutable $currentMonth): ?CarbonImmutable
    {
        if (! is_string($rawMonth) || $rawMonth === '') {
            return null;
        }

        try {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $rawMonth.'-01')->startOfMonth();
        } catch (\Throwable) {
            return null;
        }

        if (! $month->greaterThan($currentMonth) || $month->diffInMonths($currentMonth) > self::FORECAST_HORIZON_MONTHS) {
            return null;
        }

        return $month;
    }

    private function layerText(HeatmapArea|HeatmapAreaForecast $area, string $layer, mixed $profiles): array
    {
        $defaultReason = $area->main_risk_reason
            ?: $area->description
            ?: "{$area->risk_type} is the main computed risk for this barangay.";
        $defaultAction = $area->suggested_action
            ?: $area->irrigation_recommendation
            ?: $area->planting_advisory
            ?: 'Continue routine field monitoring.';

        if ($layer === 'temperature') {
            return [
                $area->weather_condition ? "Current weather is {$area->weather_condition}. Lian temperature is monitored in real-time." : "Live real-time weather and temperature monitoring for Lian, Batangas.",
                "Ensure proper field water level to protect crops during heat spikes above 35°C.",
            ];
        }

        if ($layer === 'yield') {
            return [
                $area->predicted_yield !== null ? "Model forecast yield is {$area->predicted_yield} t/ha." : "Harvest productivity and rice yield monitoring.",
                $area->planting_advisory ?: "Follow recommended seed variety and nutrient management for optimal harvest.",
            ];
        }

        if ($layer === 'radar') {
            return [
                "Live RainViewer weather radar scan for Lian, Batangas. Real-time precipitation and cloud reflection.",
                "Review immediate field drainage and cover harvested grains if heavy radar echoes approach.",
            ];
        }

        if ($layer === 'rainfall') {
            return [
                $area->rainfall_status ?: $defaultReason,
                $area->irrigation_recommendation ?: $defaultAction,
            ];
        }

        if ($layer === 'farm_type') {
            $count = collect($profiles)->sum('profile_count');
            $types = collect($profiles)->pluck('farm_type')->filter()->unique()->implode(' and ');

            return [
                $count > 0
                    ? "{$count} registered farmer profile(s); farm type: ".($types ?: 'not specified').'.'
                    : 'No registered farmer profile is available for this barangay yet.',
                $defaultAction,
            ];
        }

        if ($layer === 'irrigation') {
            return [
                $area->irrigation_recommendation ?: $defaultReason,
                $area->suggested_action ?: $area->irrigation_recommendation ?: $defaultAction,
            ];
        }

        return [$defaultReason, $defaultAction];
    }

    private function riskColor(string $riskLevel): string
    {
        return match ($riskLevel) {
            'Low' => '#22c55e',
            'Moderate' => '#facc15',
            'High' => '#f97316',
            'Severe' => '#ef4444',
            default => '#9ca3af',
        };
    }

    private function respond(array $payload, string $cacheStatus, int $startedAt, float $dbMs): JsonResponse
    {
        $responseMs = (hrtime(true) - $startedAt) / 1_000_000;
        $payload['refreshing'] = Cache::has('heatmap:lian:risk-refresh-pending')
            || Cache::has('heatmap:lian:risk-refreshing');

        if ($this->shouldLogPerformance()) {
            Log::debug('Heatmap API performance', [
                'response_ms' => round($responseMs, 2),
                'db_ms' => round($dbMs, 2),
                'cache' => $cacheStatus,
                'layer' => $payload['layer'] ?? 'impact',
                'area_count' => count($payload['areas'] ?? []),
            ]);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=300')
            ->header('X-Heatmap-Cache', $cacheStatus)
            ->header('Server-Timing', sprintf('db;dur=%.2f, app;dur=%.2f', $dbMs, $responseMs));
    }

    private function shouldLogPerformance(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    private function normalizeBarangay(string $barangay): string
    {
        $value = strtolower($barangay);
        $value = str_replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
