<?php

namespace App\Http\Controllers;

use App\Models\AIChat;
use App\Models\User;
use App\Services\AI\AIOrchestratorService;
use App\Services\AI\AiTimings;
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
            'language' => ['sometimes', 'nullable', 'in:en,tl'],
        ]);

        if ($request->user()->role === User::ROLE_FARMER && filled($validated['language'] ?? null)) {
            $request->user()->forceFill(['ai_language' => $validated['language']])->save();
        }

        // A chat answer must finish well inside the browser's wait; if anything stalls, PHP stops it.
        // Web requests only: in the console (tests, queue) set_time_limit() would stay in force for
        // the whole process and kill unrelated work that legitimately takes longer.
        if (! app()->runningInConsole()) {
            @set_time_limit((int) ceil((float) config('ai.response_budget_seconds', 10)) + 8);
        }

        $timings = app(AiTimings::class);
        $timings->begin();

        $result = $orchestrator->answer($request->user(), $validated['question']);

        // A message the language filter blocked is never stored or sent back;
        // a neutral placeholder takes its place in the history.
        $question = ($result['question_redacted'] ?? false)
            ? self::REDACTED_QUESTION
            : $validated['question'];
        unset($result['question_redacted']);

        $shouldSave = (bool) ($validated['save_conversation'] ?? true);

        $chat = $shouldSave
            ? $timings->time('insert', fn () => AIChat::query()->create([
                'user_id' => $request->user()->id,
                'question' => $question,
                ...$result,
            ]))
            : [
                'id' => null,
                'user_id' => $request->user()->id,
                'question' => $question,
                'created_at' => now()->toISOString(),
                'saved' => false,
                ...$result,
            ];

        $timings->report();

        return response()->json([
            'chat' => $chat,
            'saved' => $shouldSave,
        ]);
    }

    public function setLanguage(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_FARMER, 403);

        $validated = $request->validate([
            'language' => ['required', 'in:en,tl'],
        ]);

        $request->user()->forceFill(['ai_language' => $validated['language']])->save();

        return response()->json(['language' => $validated['language']]);
    }

    public function clear(Request $request): RedirectResponse
    {
        AIChat::query()->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Climora AI conversation cleared.');
    }
}
