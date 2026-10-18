<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Risk\HeatMapLayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeatMapController extends Controller
{
    public function __invoke(Request $request, HeatMapLayerService $service): JsonResponse
    {
        return response()->json($service->get((string) $request->query('layer', 'weather_risk'), (string) $request->query('period', 'week'), $request->user()));
    }
}
