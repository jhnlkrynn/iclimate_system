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
        $records = $weather->all(refresh: $request->boolean('refresh'));

        return response()->json([
            'source' => 'Open-Meteo Forecast API',
            'scope' => 'Lian, Batangas barangay coordinate forecast',
            'refresh_minutes' => max(10, (int) config('services.open_meteo.refresh_minutes', 30)),
            'generated_at' => now()->toDateTimeString(),
            'accuracy_note' => 'Weather values are forecast/model estimates for each barangay coordinate, not official PAGASA rain-gauge observations per barangay.',
            'data' => array_values($records),
        ]);
    }
}
