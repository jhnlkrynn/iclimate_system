<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BarangayRiskMapController extends Controller
{
    public function __construct(
        private readonly BarangayRiskMapService $riskMapService,
        private readonly LianBarangayWeatherService $weatherService,
    ) {}

    /** True when this request found the weather due and started a refresh that runs after the response. */
    private bool $weatherRefreshScheduled = false;

    /**
     * GET /api/lian/risk-map
     * Lean list of all 19 barangays unless ?detail=1 is passed.
     */
    public function index(Request $request): JsonResponse
    {
        $refreshQueued = false;
        $weatherRefreshed = $this->refreshWeather($request);

        // Refresh weather & risk: the 19 ratings are made from the new weather right after this response is sent
        // (no queue worker needed); the page asks again shortly for them. Until then the last verified ratings show.
        if ($request->boolean('refresh')) {
            $refreshQueued = $this->riskMapService->refreshAfterResponse();
        }

        try {
            $map = $this->riskMapService->withCurrentWeather($this->riskMapService->riskMap(false));
        } catch (Throwable $exception) {
            return $this->unavailableResponse($exception);
        }

        $detail = $request->boolean('detail');

        $barangays = collect($map['barangays'])->map(function (array $entry) use ($detail): array {
            if ($detail) {
                return $entry;
            }

            return [
                'barangay' => $entry['barangay'],
                'psgc_10d' => $entry['psgc_10d'],
                'risk_level' => $entry['risk_level'],
                'risk_score' => $entry['risk_score'],
                'color' => $entry['color'],
                'status' => $entry['status'],
                'updated_at' => $entry['updated_at'],
                'risk_calculated_at' => $entry['risk_calculated_at'] ?? null,
                'weather' => $entry['weather'],
                'last_recorded_yield' => $entry['last_recorded_yield'],
            ];
        })->values()->all();

        return response()->json([
            'status' => 'ok',
            'generated_at' => $map['generated_at'],
            'stale' => (bool) ($map['stale'] ?? false),
            'stale_count' => (int) ($map['stale_count'] ?? 0),
            'refresh_queued' => $refreshQueued,
            'weather_refreshed' => $weatherRefreshed,
            'weather_refresh_scheduled' => $this->weatherRefreshScheduled,
            'risk_refresh_scheduled' => $refreshQueued,
            'weather_summary' => $map['weather_summary'] ?? null,
            'source_note' => $map['source_note'],
            'legend' => ['Low', 'Moderate', 'High', 'Severe', 'No Data'],
            'barangays' => $barangays,
        ]);
    }

    /**
     * GET /api/lian/risk-map/{barangay}
     * Full detail (risk breakdown + advisory) for one barangay's click panel.
     */
    public function show(Request $request, string $barangay): JsonResponse
    {
        $this->refreshWeather($request);

        if ($request->boolean('refresh')) {
            $this->riskMapService->refreshAfterResponse();
        }

        try {
            // Detail requests must be bounded too; the risk recompute is queued
            // and the current verified entry is returned now.
            $entry = $this->riskMapService->detailFor($barangay, false);
            if ($entry !== null) {
                $entry = $this->riskMapService->withCurrentWeather(['barangays' => [$entry]])['barangays'][0];
            }
        } catch (Throwable $exception) {
            return $this->unavailableResponse($exception);
        }

        if ($entry === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => "'{$barangay}' did not confidently match one of Lian's 19 barangays.",
            ], 404);
        }

        return response()->json(['status' => 'ok'] + $entry);
    }

    /**
     * Refresh Weather (?refresh=1) fetches all 19 now in one batched request (bounded by a short timeout), so
     * the response already carries the new readings. Any other request reads the stored readings only (the
     * scheduler keeps them current). ?priority= names the farmer's own barangay, which is requested first.
     */
    private function refreshWeather(Request $request): bool
    {
        $priority = $request->query('priority');
        $priority = is_string($priority) ? $priority : null;

        try {
            if ($request->boolean('refresh')) {
                $this->weatherService->refreshUnlessJustChecked($priority);

                return true;
            }

            // Any other request only reads: iclimate:refresh-barangay-weather fetches all 19 every 10 minutes.
        } catch (Throwable $exception) {
            Log::warning('Barangay weather refresh could not start.', ['message' => $exception->getMessage()]);
        }

        return false;
    }

    private function unavailableResponse(Throwable $exception): JsonResponse
    {
        Log::error('Barangay risk map endpoint failed.', ['message' => $exception->getMessage()]);

        return response()->json([
            'status' => 'unavailable',
            'message' => 'The barangay heat map is temporarily unavailable. Please try again shortly.',
            'legend' => ['Low', 'Moderate', 'High', 'Severe', 'No Data'],
            'barangays' => [],
        ]);
    }
}
