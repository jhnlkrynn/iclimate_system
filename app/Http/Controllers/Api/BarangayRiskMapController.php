<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BarangayRiskMapController extends Controller
{
    public function __construct(private readonly BarangayRiskMapService $riskMapService) {}

    /**
     * GET /api/lian/risk-map
     * Lean list of all 19 barangays unless ?detail=1 is passed.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $map = $this->riskMapService->riskMap($request->boolean('refresh'));
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
                'weather' => $entry['weather'],
                'last_recorded_yield' => $entry['last_recorded_yield'],
            ];
        })->values()->all();

        return response()->json([
            'status' => 'ok',
            'generated_at' => $map['generated_at'],
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
        try {
            $entry = $this->riskMapService->detailFor($barangay, $request->boolean('refresh'));
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

    private function unavailableResponse(Throwable $exception): JsonResponse
    {
        Log::error('Barangay risk map endpoint failed.', ['message' => $exception->getMessage()]);

        return response()->json([
            'status' => 'unavailable',
            'message' => 'The barangay risk map is temporarily unavailable. Please try again shortly.',
            'legend' => ['Low', 'Moderate', 'High', 'Severe', 'No Data'],
            'barangays' => [],
        ]);
    }
}
