<?php

namespace App\Services\AI;

use App\Models\AIChat;
use App\Models\User;

/**
 * The little recent history the chatbot actually uses, read once per message.
 *
 * Only the last few chats are loaded, and only the columns needed (question,
 * intent, and the small memory blob with any pending follow-up). Result sets,
 * predictions and explanations stored on older chats are never read. The
 * orchestrator and the prediction service share one read instead of each
 * querying the history.
 */
class ConversationContext
{
    /** @var array<int, array<string, mixed>> per user id, for the current message */
    private array $cache = [];

    /** Forget what was read; called at the start of each message. */
    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * @return array{last_intent: ?string, pending_intent: mixed, recent_questions: list<string>, recent_facts: list<string>}
     */
    public function recent(User $user): array
    {
        return $this->cache[$user->id] ??= $this->load($user);
    }

    private function load(User $user): array
    {
        $latest = AIChat::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(max(1, (int) config('ai.history_messages', 3)))
            ->get(['id', 'intent', 'question', 'memory']);

        $pending = $latest
            ->pluck('memory')
            ->filter(fn ($memory) => is_array($memory) && filled(data_get($memory, 'pending_intent')))
            ->first();

        return [
            'last_intent' => $latest->first()?->intent,
            'pending_intent' => data_get($pending, 'pending_intent'),
            'recent_questions' => $latest->pluck('question')->all(),
            'recent_facts' => $latest->pluck('intent')->filter()->values()->all(),
        ];
    }
}
