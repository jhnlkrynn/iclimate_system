<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LianWeatherController extends Controller
{
    public function __invoke(Request $request, LianBarangayWeatherService $weather): JsonResponse
    {
        // The shared records are returned straight from the cache; iclimate:refresh-barangay-weather keeps them current.
        $records = rescue(function () use ($request, $weather): array {
            if ($request->boolean('refresh')) {
                return $weather->refreshUnlessJustChecked();
            }

            return $weather->snapshot();
        }, [], false);

        return response()->json([
            'source' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Online barangay weather data by Open-Meteo; interpreted by iClimate.',
            'scope' => 'Lian, Batangas barangay coordinate weather',
            'refresh_minutes' => (int) config('services.open_meteo.barangay_refresh_minutes', LianBarangayWeatherService::REFRESH_MINUTES),
            'generated_at' => now()->toDateTimeString(),
            'accuracy_note' => 'Weather values are model estimates for each barangay coordinate, not official PAGASA rain-gauge observations per barangay.',
            'summary' => $records === [] ? null : $weather->summary($records),
            'data' => array_values($records),
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
