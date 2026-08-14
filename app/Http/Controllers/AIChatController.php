<?php

namespace App\Http\Controllers;

use App\Models\AIChat;
use App\Services\AI\AIOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->dashboardRoute());
    }

    public function message(Request $request, AIOrchestratorService $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'save_conversation' => ['sometimes', 'boolean'],
        ]);

        $result = $orchestrator->answer($request->user(), $validated['question']);

        $shouldSave = (bool) ($validated['save_conversation'] ?? true);

        $chat = $shouldSave
            ? AIChat::query()->create([
                'user_id' => $request->user()->id,
                'question' => $validated['question'],
                ...$result,
            ])
            : [
                'id' => null,
                'user_id' => $request->user()->id,
                'question' => $validated['question'],
                'created_at' => now()->toISOString(),
                'saved' => false,
                ...$result,
            ];

        return response()->json([
            'chat' => $chat,
            'saved' => $shouldSave,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        AIChat::query()->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Climora AI conversation cleared.');
    }
}
