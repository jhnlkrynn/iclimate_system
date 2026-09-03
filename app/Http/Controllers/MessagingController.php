<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MessagingController extends Controller
{
    private const ATTACHMENT_RULES = [
        'nullable',
        'file',
        'max:51200',
        'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const TYPING_TTL_SECONDS = 5;

    public function index(Request $request): View
    {
        $conversations = $this->conversationQuery($request->user())
            ->with(['participantOne', 'participantTwo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('messages.index', [
            'conversations' => $conversations,
            'activeConversation' => null,
            'messages' => collect(),
            'recipients' => $this->recipients($request->user()),
            'unreadCounts' => $this->unreadCounts($request->user(), $conversations),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeConversation($request->user(), $conversation);

        $this->markRead($conversation, $request->user());

        $conversations = $this->conversationQuery($request->user())
            ->with(['participantOne', 'participantTwo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('messages.index', [
            'conversations' => $conversations,
            'activeConversation' => $conversation->load(['participantOne', 'participantTwo']),
            'messages' => $conversation->messages()->with('sender')->oldest()->get(),
            'recipients' => $this->recipients($request->user()),
            'unreadCounts' => $this->unreadCounts($request->user(), $conversations),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => self::ATTACHMENT_RULES,
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_id']);
        abort_unless($this->canMessage($request->user(), $recipient), 403);

        $conversation = $this->findOrCreateConversation($request->user(), $recipient);
        $this->createMessage($request, $conversation, $validated['body'] ?? null);

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    /**
     * Open (or create) a conversation with a recipient, with no message required.
     * Used by the conversation search so picking a not-yet-messaged user opens the
     * thread directly instead of routing through a separate compose form.
     */
    public function openConversation(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_id']);
        abort_unless($this->canMessage($request->user(), $recipient), 403);

        $conversation = $this->findOrCreateConversation($request->user(), $recipient);

        if ($request->wantsJson()) {
            return response()->json([
                'conversation_id' => $conversation->id,
                'redirect' => route('messages.show', $conversation),
            ]);
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $validated = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => self::ATTACHMENT_RULES,
        ]);

        $message = $this->createMessage($request, $conversation, $validated['body'] ?? null);

        Cache::forget($this->typingCacheKey($conversation, $request->user()->id));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $this->serializeMessage($message),
            ]);
        }

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * Return messages newer than `after_id`, plus lightweight presence/typing/read
     * state, so the chat window can refresh via polling without a full page reload.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $user = $request->user();
        $afterId = (int) $request->integer('after_id');

        $messages = $conversation->messages()
            ->with('sender')
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->oldest()
            ->get();

        $this->markRead($conversation, $user);

        $ownReadReceipts = $conversation->messages()
            ->where('sender_id', $user->id)
            ->latest()
            ->limit(20)
            ->get(['id', 'read_at'])
            ->map(fn (ConversationMessage $message) => [
                'id' => $message->id,
                'read_at' => $message->read_at?->toIso8601String(),
            ])
            ->values();

        $other = $conversation->otherParticipant($user);

        return response()->json([
            'messages' => $messages->map(fn (ConversationMessage $message) => $this->serializeMessage($message))->values(),
            'own_read_receipts' => $ownReadReceipts,
            'other_online' => (bool) $other?->isOnline(),
            'other_typing' => $other && Cache::has($this->typingCacheKey($conversation, $other->id)),
        ]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        Cache::put(
            $this->typingCacheKey($conversation, $request->user()->id),
            true,
            now()->addSeconds(self::TYPING_TTL_SECONDS)
        );

        return response()->json(['ok' => true]);
    }

    public function searchRecipients(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        $recipients = $this->recipients($request->user(), $query !== '' ? $query : null);

        return response()->json([
            'recipients' => $recipients->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ])->values(),
        ]);
    }

    public function destroyMessage(Request $request, Conversation $conversation, ConversationMessage $message): RedirectResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->sender_id === $request->user()->id, 403);

        DB::transaction(function () use ($conversation, $message): void {
            if ($message->attachment_path) {
                Storage::disk('public')->delete($message->attachment_path);
            }

            $message->delete();

            $conversation->update([
                'last_message_at' => $conversation->messages()->latest()->value('created_at'),
            ]);
        });

        return redirect()->route('messages.show', $conversation)->with('success', 'Message unsent.');
    }

    private function createMessage(Request $request, Conversation $conversation, ?string $body): ConversationMessage
    {
        return DB::transaction(function () use ($request, $conversation, $body): ConversationMessage {
            $attachment = $request->file('attachment');
            $path = $attachment?->store('message-attachments', 'public');

            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $body,
                'attachment_path' => $path,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'attachment_mime' => $attachment?->getMimeType(),
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message;
        });
    }

    private function serializeMessage(ConversationMessage $message): array
    {
        $message->loadMissing('sender');

        return [
            'id' => $message->id,
            'body' => $message->body,
            'attachment_url' => $message->attachment_path ? asset('storage/'.$message->attachment_path) : null,
            'attachment_name' => $message->attachment_name,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'created_at' => $message->created_at?->toIso8601String(),
            'human_time' => $message->humanTimestamp(),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }

    private function markRead(Conversation $conversation, User $user): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function typingCacheKey(Conversation $conversation, int $userId): string
    {
        return "messaging:typing:{$conversation->id}:{$userId}";
    }

    /**
     * @param  Collection<int, Conversation>  $conversations
     * @return Collection<int, int> conversation_id => unread count
     */
    private function unreadCounts(User $user, Collection $conversations): Collection
    {
        if ($conversations->isEmpty()) {
            return collect();
        }

        return ConversationMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->selectRaw('conversation_id, count(*) as unread_count')
            ->groupBy('conversation_id')
            ->pluck('unread_count', 'conversation_id');
    }

    private function findOrCreateConversation(User $sender, User $recipient): Conversation
    {
        [$one, $two] = collect([$sender->id, $recipient->id])->sort()->values()->all();

        return Conversation::query()->firstOrCreate([
            'participant_one_id' => $one,
            'participant_two_id' => $two,
        ], [
            'last_message_at' => now(),
        ]);
    }

    private function conversationQuery(User $user)
    {
        return Conversation::query()
            ->where('participant_one_id', $user->id)
            ->orWhere('participant_two_id', $user->id);
    }

    private function authorizeConversation(User $user, Conversation $conversation): void
    {
        abort_unless(in_array($user->id, [$conversation->participant_one_id, $conversation->participant_two_id], true), 403);
    }

    private function recipients(User $user, ?string $search = null)
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->where('status', User::STATUS_ACTIVE)
            ->when($user->role === User::ROLE_FARMER, fn ($query) => $query->where('role', User::ROLE_MAO))
            ->when($user->role === User::ROLE_MAO, fn ($query) => $query->where('role', User::ROLE_FARMER))
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();
    }

    private function canMessage(User $sender, User $recipient): bool
    {
        if ($sender->role === User::ROLE_IT_EXPERT || $recipient->role === User::ROLE_IT_EXPERT) {
            return true;
        }

        return $sender->role !== $recipient->role;
    }
}
