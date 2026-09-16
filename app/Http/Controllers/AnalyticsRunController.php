<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsRun;
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

        return response()->json([
            'uuid' => $analyticsRun->uuid,
            'type' => $analyticsRun->type,
            'status' => $analyticsRun->status,
            'result' => $analyticsRun->status === AnalyticsRun::STATUS_COMPLETED ? $analyticsRun->result : null,
            'error' => $analyticsRun->status === AnalyticsRun::STATUS_FAILED ? $analyticsRun->error : null,
            'completed_at' => $analyticsRun->completed_at?->toISOString(),
        ]);
    }
}
