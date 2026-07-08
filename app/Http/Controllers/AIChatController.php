<?php

namespace App\Http\Controllers;

use App\Models\AIChat;
use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->dashboardRoute());
    }

    public function message(Request $request, PredictionService $predictionService): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $result = $predictionService->answer($request->user(), $validated['question']);

        $chat = AIChat::query()->create([
            'user_id' => $request->user()->id,
            'question' => $validated['question'],
            ...$result,
        ]);

        return response()->json([
            'chat' => $chat,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        AIChat::query()->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'AI Farming Assistant conversation cleared.');
    }
}
