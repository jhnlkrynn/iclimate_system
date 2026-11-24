<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\FeedMedia;
use App\Models\FeedReaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->to(route('mao.planner.index').'#announcements');
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
            'show_on_calendar' => $request->boolean('show_on_calendar') && ! empty($validated['event_date']),
        ]);

        $this->storeAttachments($post, $request->file('attachments', []));

        return redirect()->to(route('mao.planner.index').'#announcements')->with('success', 'Post published to the community feed.');
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
            'show_on_calendar' => $request->boolean('show_on_calendar') && ! empty($validated['event_date']),
        ]);

        $attachments = array_values(array_filter($request->file('attachments', [])));
        if ($attachments !== []) {
            // A new upload is an intentional replacement. With no new upload, existing media is untouched.
            $this->storeAttachments($post, $attachments, replaceExisting: true);
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
            $this->deleteAttachment($media);
        }

        $post->delete();

        return back()->with('success', 'Post removed.');
    }

    private function canPost(User $user): bool
    {
        return in_array($user->role, [User::ROLE_MAO], true);
    }

    private function canManagePost(User $user, FeedPost $post): bool
    {
        return $this->canPost($user)
            && ((int) $post->user_id === (int) $user->id);
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', Rule::in(['Update', 'Program', 'Activity', 'Training', 'Advisory', 'Announcement'])],
            'visibility' => ['required', Rule::in(['All Farmers', 'All Users'])],
            'event_date' => ['nullable', 'date'],
            'show_on_calendar' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);
    }

    private function storeAttachments(FeedPost $post, array $files, bool $replaceExisting = false): void
    {
        $stored = [];
        $storedPaths = [];

        try {
            foreach ($files as $file) {
                $path = $file->store('community-feed', 'public');
                $storedPaths[] = $path;
                $mime = $file->getMimeType() ?: 'application/octet-stream';
                $stored[] = $post->media()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'media_type' => str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'file'),
                    'size' => $file->getSize() ?: 0,
                ]);
            }
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            foreach ($stored as $media) {
                $media->delete();
            }

            throw $exception;
        }

        if ($replaceExisting) {
            $post->media()->whereNotIn('id', collect($stored)->pluck('id'))->get()->each(fn (FeedMedia $media) => $this->deleteAttachment($media));
        }
    }

    private function deleteAttachment(FeedMedia $media): void
    {
        $isReferencedElsewhere = FeedMedia::query()
            ->where('path', $media->path)
            ->whereKeyNot($media->getKey())
            ->exists();

        if (! $isReferencedElsewhere) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();
    }
}
