<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Risk\BarangayRiskForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class BarangayRiskForecastController extends Controller
{
    /**
     * GET /api/lian/risk-forecast
     * Forecast risk for all 19 barangays, for each month (current + next five) that has a ready forecast.
     */
    public function __invoke(BarangayRiskForecastService $forecast): JsonResponse
    {
        try {
            $result = $forecast->forecast();
        } catch (Throwable $exception) {
            Log::error('Barangay risk forecast endpoint failed.', ['message' => $exception->getMessage()]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Climate forecast information is currently unavailable.',
                'legend' => BarangayRiskForecastService::LEVELS,
                'months' => [],
            ]);
        }

        return response()->json([
            'status' => $result['months'] === [] ? 'unavailable' : 'ok',
            'message' => $result['months'] === [] ? 'Climate forecast information for this period is currently unavailable.' : null,
            'generated_at' => $result['generated_at'],
            'source_note' => $result['source_note'],
            'legend' => BarangayRiskForecastService::LEVELS,
            'months' => $result['months'],
        ]);
    }
}
