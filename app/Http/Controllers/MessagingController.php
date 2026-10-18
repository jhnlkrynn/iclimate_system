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
        $conversationPaginator = $this->conversationQuery($request->user())
            ->with(['participantOne', 'participantTwo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->simplePaginate(30, ['*'], 'threads')
            ->withQueryString();
        $conversations = $conversationPaginator->getCollection();

        return view('messages.index', [
            'conversations' => $conversations,
            'conversationPaginator' => $conversationPaginator,
            'activeConversation' => null,
            'messages' => collect(),
            'recipients' => $this->recipients($request->user()),
            'unreadCounts' => $this->unreadCounts($request->user(), $conversations),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $this->markRead($conversation, $request->user());

        $messages = $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->limit(100)
            ->get()
            ->sortBy('id')
            ->values();

        if ($request->wantsJson()) {
            $other = $conversation->otherParticipant($request->user());

            return response()->json([
                'conversation_id' => $conversation->id,
                'other' => [
                    'id' => $other?->id,
                    'name' => $other?->name ?? 'User',
                    'role' => $other?->role ?? 'User',
                    'online' => (bool) $other?->isOnline(),
                ],
                'messages' => $messages->map(fn (ConversationMessage $message) => $this->serializeMessage($message))->values(),
                'last_message_id' => (int) ($messages->max('id') ?? 0),
                'poll_url' => route('messages.poll', $conversation),
                'send_url' => route('messages.reply', $conversation),
                'typing_url' => route('messages.typing', $conversation),
                'unsend_url_base' => route('messages.destroy-message', [$conversation, 0]),
            ]);
        }

        $conversationPaginator = $this->conversationQuery($request->user())
            ->with(['participantOne', 'participantTwo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->simplePaginate(30, ['*'], 'threads')
            ->withQueryString();
        $conversations = $conversationPaginator->getCollection();

        return view('messages.index', [
            'conversations' => $conversations,
            'conversationPaginator' => $conversationPaginator,
            'activeConversation' => $conversation->load(['participantOne', 'participantTwo']),
            'messages' => $messages,
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
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->createMessage($request, $conversation, $validated['body'] ?? null, $validated['client_message_id'] ?? null);

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
            ->oldest('id')
            ->limit(100)
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
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $recipients = $this->peopleQuery($request->user(), $query !== '' ? $query : null)
            ->limit(30)
            ->get(['id', 'name', 'role', 'barangay', 'last_active_at']);

        return response()->json([
            'recipients' => $recipients->map(fn (User $user) => $this->serializePerson($user))->values(),
        ]);
    }

    /** The paginated people directory used by the persistent left-panel list. */
    public function people(Request $request): JsonResponse
    {
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $perPage = min(max((int) $request->integer('per_page', 30), 1), 30);

        $paginator = $this->peopleQuery($request->user(), $query !== '' ? $query : null)
            ->paginate($perPage, ['id', 'name', 'role', 'barangay', 'last_active_at'], 'page')
            ->withQueryString();

        return response()->json([
            'people' => collect($paginator->items())->map(fn (User $user) => $this->serializePerson($user))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function destroyMessage(Request $request, Conversation $conversation, ConversationMessage $message): RedirectResponse|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->sender_id === $request->user()->id, 403);

        DB::transaction(function () use ($conversation, $message): void {
            if ($message->attachment_path) {
                Storage::disk($this->attachmentDisk($message->attachment_path))->delete($message->attachment_path);
            }

            $message->delete();

            $conversation->update([
                'last_message_at' => $conversation->messages()->latest()->value('created_at'),
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['message_id' => $message->id, 'ok' => true]);
        }

        return redirect()->route('messages.show', $conversation)->with('success', 'Message unsent.');
    }

    /** Sends a message's attachment to the two people in the conversation only. */
    public function attachment(Request $request, Conversation $conversation, ConversationMessage $message): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeConversation($request->user(), $conversation);
        abort_unless($message->conversation_id === $conversation->id && $message->attachment_path, 404);

        $disk = Storage::disk($this->attachmentDisk($message->attachment_path));
        abort_unless($disk->exists($message->attachment_path), 404);

        // Shown in the browser for pictures, video and PDF; anything else is downloaded.
        $inline = preg_match('#^(image|video)/|^application/pdf$#', (string) $message->attachment_mime) === 1;

        return $disk->response($message->attachment_path, $message->attachment_name ?: 'attachment', [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ], $inline ? 'inline' : 'attachment');
    }

    /** New attachments are private; ones sent before that change are still in the public folder. */
    private function attachmentDisk(string $path): string
    {
        return Storage::disk('local')->exists($path) ? 'local' : 'public';
    }

    private function createMessage(Request $request, Conversation $conversation, ?string $body, ?string $clientMessageId = null): ConversationMessage
    {
        return DB::transaction(function () use ($request, $conversation, $body, $clientMessageId): ConversationMessage {
            if ($clientMessageId !== null) {
                $existing = $conversation->messages()
                    ->where('sender_id', $request->user()->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existing) {
                    return $existing->load('sender');
                }
            }

            $attachment = $request->file('attachment');
            // Private storage: a message attachment is only ever sent through attachment() below, which checks
            // that the viewer is in the conversation. It is never reachable by a public /storage link.
            $path = $attachment?->store('message-attachments', 'local');

            $message = $clientMessageId === null
                ? $conversation->messages()->create([
                    'sender_id' => $request->user()->id,
                    'body' => $body,
                    'attachment_path' => $path,
                    'attachment_name' => $attachment?->getClientOriginalName(),
                    'attachment_mime' => $attachment?->getMimeType(),
                ])
                : $conversation->messages()->firstOrCreate(
                    [
                        'sender_id' => $request->user()->id,
                        'client_message_id' => $clientMessageId,
                    ],
                    [
                        'body' => $body,
                        'attachment_path' => $path,
                        'attachment_name' => $attachment?->getClientOriginalName(),
                        'attachment_mime' => $attachment?->getMimeType(),
                    ],
                );

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
            'attachment_url' => $message->attachment_path ? route('messages.attachment', [$message->conversation_id, $message]) : null,
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
            ->unreadFor($user)
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
        return $this->peopleQuery($user, $search)
            ->limit($search ? 20 : 50)
            ->get(['id', 'name', 'role', 'last_active_at']);
    }

    private function peopleQuery(User $user, ?string $search = null)
    {
        $query = User::query()
            ->with('farmerProfile:id,user_id,barangay')
            ->where('id', '!=', $user->id)
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('role', [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT])
            ->when($search, function ($query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(barangay, \'\')) LIKE ?', [$term])
                        ->orWhereHas('farmerProfile', fn ($profile) => $profile->whereRaw('LOWER(COALESCE(barangay, \'\')) LIKE ?', [$term]));
                });
            });

        // Keep the server-side result bounded, but put people the user already knows first.
        // This is a correlated EXISTS expression, not an N+1 relationship lookup.
        $conversationOrder = <<<'SQL'
            CASE WHEN EXISTS (
                SELECT 1 FROM conversations
                WHERE (participant_one_id = ? AND participant_two_id = users.id)
                   OR (participant_two_id = ? AND participant_one_id = users.id)
            ) THEN 0 ELSE 1 END
            SQL;

        return $query
            ->orderByRaw($conversationOrder, [$user->id, $user->id])
            ->when($search, function ($query) use ($search): void {
                $term = mb_strtolower($search);
                $query->orderByRaw('CASE WHEN LOWER(name) LIKE ? THEN 0 WHEN LOWER(COALESCE(barangay, \'\')) LIKE ? THEN 1 ELSE 2 END', [$term.'%', $term.'%']);
            })
            ->orderBy('role')
            ->orderBy('name');
    }

    private function serializePerson(User $user): array
    {
        $isFarmer = $user->role === User::ROLE_FARMER;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role,
            'role_label' => $user->role === User::ROLE_IT_EXPERT ? 'IT Support' : $user->roleLabel(),
            'barangay' => $isFarmer ? $user->registeredBarangay() : null,
            'initial' => mb_strtoupper(mb_substr(trim((string) $user->name), 0, 1) ?: 'U'),
            'online' => $user->isOnline(),
        ];
    }

    private function canMessage(User $sender, User $recipient): bool
    {
        return $sender->id !== $recipient->id
            && $recipient->status === User::STATUS_ACTIVE
            && in_array($recipient->role, [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT], true);
    }
}
