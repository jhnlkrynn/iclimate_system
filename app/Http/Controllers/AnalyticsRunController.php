<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsRun;
use App\Models\User;
use App\Support\PredictionWording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsRunController extends Controller
{
    public function show(Request $request, AnalyticsRun $analyticsRun): JsonResponse
    {
        abort_unless(
            $analyticsRun->requested_by === null || $analyticsRun->requested_by === $request->user()->id,
            403,
        );

        // The waiting page asks here every few seconds; a run nothing picked up in time is closed so it stops waiting.
        $analyticsRun->expireIfStale();

        return response()->json([
            'uuid' => $analyticsRun->uuid,
            'type' => $analyticsRun->type,
            'status' => $analyticsRun->status,
            // The stored result holds model inputs, source/model names and file
            // paths. Page scripts only need `status` (they reload to render the
            // result server-side), so only server operator accounts get the raw result.
            'result' => null,
            // Raw failure text is for server operator accounts only; everyone else
            // gets a generic plain-language title (the raw text stays in the
            // database / logs).
            'error' => $analyticsRun->status === AnalyticsRun::STATUS_FAILED
                ? PredictionWording::friendlyFailure($analyticsRun->error)['title']
                : null,
            'completed_at' => $analyticsRun->completed_at?->toISOString(),
        ]);
    }
}
