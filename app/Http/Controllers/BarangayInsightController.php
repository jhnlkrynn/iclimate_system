<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AI\BarangayInsightService;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GET /heat-map/insight/{barangay}?lang=en|fil — the Barangay AI Insight pop-up (Heat Map and MAO dashboard).
 * Never answers 500: when anything fails the pop-up still shows its verified values from the map.
 */
class BarangayInsightController extends Controller
{
    public function __invoke(Request $request, string $barangay, BarangayInsightService $insights): JsonResponse
    {
        $language = $request->query('lang');
        $language = is_string($language) && in_array($language, BarangayInsightService::LANGUAGES, true)
            ? $language
            : FarmerLanguage::resolve($request);

        try {
            $insight = $insights->forBarangay($barangay, $language);
        } catch (Throwable $exception) {
            Log::error('Barangay AI insight endpoint failed.', ['barangay' => $barangay, 'message' => $exception->getMessage()]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'AI insight is temporarily unavailable.',
            ]);
        }

        if ($insight === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => "'{$barangay}' is not one of Lian's 19 barangays.",
            ], 404);
        }

        // Only whether this is the signed-in farmer's own barangay; nothing else about the farmer is sent.
        $user = $request->user();
        $own = $user?->role === User::ROLE_FARMER ? LianBarangays::canonicalName($user->registeredBarangay()) : null;

        return response()->json(['status' => 'ok', 'is_own_barangay' => $own === $insight['barangay']] + $insight);
    }
}
