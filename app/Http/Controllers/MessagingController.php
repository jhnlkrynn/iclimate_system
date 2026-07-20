<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessagingController extends Controller
{
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
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeConversation($request->user(), $conversation);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('messages.index', [
            'conversations' => $this->conversationQuery($request->user())
                ->with(['participantOne', 'participantTwo', 'latestMessage'])
                ->orderByDesc('last_message_at')
                ->get(),
            'activeConversation' => $conversation->load(['participantOne', 'participantTwo']),
            'messages' => $conversation->messages()->with('sender')->oldest()->get(),
            'recipients' => $this->recipients($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_id']);
        abort_unless($this->canMessage($request->user(), $recipient), 403);

        $conversation = $this->findOrCreateConversation($request->user(), $recipient);
        $this->createMessage($request, $conversation, $validated['body'] ?? null);

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $validated = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        $this->createMessage($request, $conversation, $validated['body'] ?? null);

        return redirect()->route('messages.show', $conversation);
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

    private function createMessage(Request $request, Conversation $conversation, ?string $body): void
    {
        DB::transaction(function () use ($request, $conversation, $body): void {
            $attachment = $request->file('attachment');
            $path = $attachment?->store('message-attachments', 'public');

            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $body,
                'attachment_path' => $path,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'attachment_mime' => $attachment?->getMimeType(),
            ]);

            $conversation->update(['last_message_at' => now()]);
        });
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

    private function recipients(User $user)
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->where('status', User::STATUS_ACTIVE)
            ->when($user->role === User::ROLE_FARMER, fn ($query) => $query->where('role', User::ROLE_MAO))
            ->when($user->role === User::ROLE_MAO, fn ($query) => $query->where('role', User::ROLE_FARMER))
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
