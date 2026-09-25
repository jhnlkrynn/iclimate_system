<?php

namespace App\Http\Controllers;

use App\Models\AIChat;
use App\Services\AI\AIOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    public const REDACTED_QUESTION = '[Message not shown: inappropriate language]';

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

        // A message the language filter blocked is never stored or sent back;
        // a neutral placeholder takes its place in the history.
        $question = ($result['question_redacted'] ?? false)
            ? self::REDACTED_QUESTION
            : $validated['question'];
        unset($result['question_redacted']);

        $shouldSave = (bool) ($validated['save_conversation'] ?? true);

        $chat = $shouldSave
            ? AIChat::query()->create([
                'user_id' => $request->user()->id,
                'question' => $question,
                ...$result,
            ])
            : [
                'id' => null,
                'user_id' => $request->user()->id,
                'question' => $question,
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
