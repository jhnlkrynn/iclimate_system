<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function index(Request $request): View
    {
        $canPost = $this->canPost($request->user());

        return view('community-feed.index', [
            'posts' => FeedPost::query()
                ->with([
                    'author',
                    'media',
                    'comments.user',
                    'reactions' => fn ($query) => $query->where('user_id', $request->user()->id),
                ])
                ->withCount([
                    'reactions as like_reactions_count' => fn ($query) => $query->where('type', 'Like'),
                    'reactions as love_reactions_count' => fn ($query) => $query->where('type', 'Love'),
                    'reactions as care_reactions_count' => fn ($query) => $query->where('type', 'Care'),
                    'reactions as wow_reactions_count' => fn ($query) => $query->where('type', 'Wow'),
                    'reactions as helpful_reactions_count' => fn ($query) => $query->where('type', 'Helpful'),
                ])
                ->when(! $canPost, fn ($query) => $query->whereNull('archived_at'))
                ->latest()
                ->paginate(10),
            'canPost' => $canPost,
            'reactionTypes' => ['Like', 'Love', 'Care', 'Wow', 'Helpful'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canPost($request->user()), 403);

        $validated = $this->validatePost($request);

        $post = FeedPost::query()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'visibility' => $validated['visibility'],
            'user_id' => $request->user()->id,
            'event_date' => $validated['event_date'] ?? null,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $this->storeAttachment($post, $file);
        }

        return redirect()->route('community-feed.index')->with('success', 'Post published to the community feed.');
    }

    public function update(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($this->canManagePost($request->user(), $post), 403);

        $validated = $this->validatePost($request);

        $post->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'visibility' => $validated['visibility'],
            'event_date' => $validated['event_date'] ?? null,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $this->storeAttachment($post, $file);
        }

        return back()->with('success', 'Post updated.');
    }

    public function archive(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($this->canManagePost($request->user(), $post), 403);

        $post->forceFill(['archived_at' => now()])->save();

        return back()->with('success', 'Post archived.');
    }

    public function comment(Request $request, FeedPost $post): RedirectResponse
    {
        abort_if($post->archived_at !== null, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function react(Request $request, FeedPost $post): RedirectResponse
    {
        abort_if($post->archived_at !== null, 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['Like', 'Love', 'Care', 'Wow', 'Helpful'])],
        ]);

        FeedReaction::query()->updateOrCreate(
            ['feed_post_id' => $post->id, 'user_id' => $request->user()->id],
            ['type' => $validated['type']]
        );

        return back()->with('success', 'Reaction saved.');
    }

    public function destroy(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($this->canManagePost($request->user(), $post), 403);

        foreach ($post->media as $media) {
            Storage::disk('public')->delete($media->path);
        }

        $post->delete();

        return back()->with('success', 'Post removed.');
    }

    private function canPost(User $user): bool
    {
        return in_array($user->role, [User::ROLE_MAO, User::ROLE_IT_EXPERT], true);
    }

    private function canManagePost(User $user, FeedPost $post): bool
    {
        return $this->canPost($user);
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', Rule::in(['Update', 'Program', 'Activity', 'Training', 'Advisory', 'Announcement'])],
            'visibility' => ['required', Rule::in(['All Farmers', 'All Users'])],
            'event_date' => ['nullable', 'date'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);
    }

    private function storeAttachment(FeedPost $post, $file): void
    {
        $path = $file->store('community-feed', 'public');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $post->media()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'media_type' => str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'file'),
            'size' => $file->getSize() ?: 0,
        ]);
    }
}
