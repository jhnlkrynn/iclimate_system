<x-app-layout>
    <style>
        .feed-wrap { --feed-ink:#0d1f18; --feed-muted:#607468; --feed-line:rgba(153,185,160,.7); --feed-green:#2d6a4f; --feed-mint:#52b788; --feed-blue:#1677b8; --feed-gold:#f4b63f; }
        .feed-hero { border-radius:8px; padding:1.25rem; color:#fff; background:linear-gradient(135deg,#0d1f18,#146b78 52%,#0d6a41); box-shadow:0 1rem 2.3rem rgba(13,31,24,.16); }
        .feed-grid { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:1rem; align-items:start; margin-top:1rem; }
        .feed-card { border:1px solid var(--feed-line); border-radius:8px; background:#fff; box-shadow:0 .7rem 1.6rem rgba(20,32,51,.07); overflow:hidden; }
        .feed-card-body { padding:1rem; }
        .feed-composer { background:linear-gradient(145deg,#fff,#f2f8f3); }
        .feed-author { display:flex; gap:.75rem; align-items:center; }
        .avatar { width:42px; height:42px; border-radius:50%; display:grid; place-items:center; background:#d8f3dc; color:#1f6f4a; font-weight:900; flex:0 0 auto; }
        .feed-title { color:var(--feed-ink); font-weight:900; margin:0; }
        .feed-meta { color:var(--feed-muted); font-size:.82rem; }
        .feed-badge { display:inline-flex; border-radius:999px; padding:.32rem .6rem; background:#d8f3dc; color:#1f6f4a; font-size:.76rem; font-weight:900; }
        .feed-badge.archived { background:#f1f3f2; color:#607468; border:1px solid #d4edda; }
        .post-actions { display:flex; flex-wrap:wrap; gap:.45rem; justify-content:flex-end; align-items:center; }
        .manage-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-start; margin-top:1rem; padding-top:1rem; border-top:1px solid #edf3ee; }
        .manage-panel { flex:0 1 auto; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; overflow:hidden; }
        .manage-panel[open] { flex-basis:100%; }
        .manage-panel summary { display:inline-flex; cursor:pointer; list-style:none; margin:.75rem .85rem; border:1px solid #1f6f4a; border-radius:8px; background:#fff; color:#1f6f4a; padding:.48rem .75rem; font-weight:900; }
        .manage-panel summary::-webkit-details-marker { display:none; }
        .manage-panel-body { padding:.85rem; border-top:1px solid #d4edda; }
        .media-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
        .media-grid img, .media-grid video { width:100%; border-radius:8px; border:1px solid #d4edda; background:#0d1f18; max-height:330px; object-fit:cover; }
        .file-tile { display:flex; align-items:center; gap:.7rem; padding:.85rem; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; color:inherit; text-decoration:none; font-weight:800; }
        .reaction-row { display:flex; flex-wrap:wrap; gap:.45rem; padding:.8rem 1rem; border-top:1px solid #edf3ee; border-bottom:1px solid #edf3ee; background:#fbfdfb; }
        .reaction-row button { border:1px solid #d4edda; border-radius:999px; background:#fff; padding:.35rem .65rem; font-size:.82rem; font-weight:800; color:var(--feed-ink); }
        .comment { display:flex; gap:.65rem; margin-top:.75rem; }
        .comment-bubble { background:#f0f7f4; border:1px solid #d4edda; border-radius:8px; padding:.6rem .75rem; flex:1; }
        .side-panel { border:1px solid var(--feed-line); border-radius:8px; background:linear-gradient(145deg,#f7fbf8,#edf7e7); padding:1rem; }
        @media (max-width:1199.98px) { .feed-grid { grid-template-columns:1fr; } }
        @media (max-width:767.98px) { .media-grid { grid-template-columns:1fr; } }
    </style>

    <div class="feed-wrap">
        <section class="feed-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="small text-white-50 fw-bold text-uppercase mb-1">Community Feed</div>
                    <h1 class="h3 fw-bold mb-1">MAO updates, programs, activities, and farmer discussions</h1>
                    <p class="mb-0 text-white-50">Farmers can react and comment on official MAO posts with photos, videos, and files.</p>
                </div>
                <a href="{{ route('messages.index') }}" class="btn btn-light fw-bold">Open Messages</a>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="feed-grid">
            <main class="d-grid gap-3">
                @if($canPost)
                    <section class="feed-card feed-composer">
                        <div class="feed-card-body">
                            <h2 class="h5 fw-bold mb-3">Create MAO Post</h2>
                            <form method="POST" action="{{ route('community-feed.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-8"><input name="title" class="form-control" placeholder="Post title" required></div>
                                    <div class="col-md-4">
                                        <select name="category" class="form-select" required>
                                            @foreach(['Update','Program','Activity','Training','Advisory','Announcement'] as $category)
                                                <option value="{{ $category }}">{{ $category }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12"><textarea name="body" class="form-control" rows="4" placeholder="Share details, schedules, instructions, or reminders..." required></textarea></div>
                                    <div class="col-md-4"><input name="event_date" type="datetime-local" class="form-control"></div>
                                    <div class="col-md-4">
                                        <select name="visibility" class="form-select" required>
                                            <option value="All Farmers">All Farmers</option>
                                            <option value="All Users">All Users</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4"><input name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx"></div>
                                </div>
                                <div class="mt-3"><button class="btn btn-primary fw-bold" type="submit">Publish Post</button></div>
                            </form>
                        </div>
                    </section>
                @endif

                @forelse($posts as $post)
                    @php
                        $userReaction = $post->reactions->firstWhere('user_id', auth()->id());
                        $counts = $post->reactions->groupBy('type')->map->count();
                        $canManagePost = $canPost && ($post->user_id === auth()->id() || auth()->user()->role === \App\Models\User::ROLE_IT_EXPERT);
                        $isArchived = $post->archived_at !== null;
                    @endphp
                    <article class="feed-card">
                        <div class="feed-card-body">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="feed-author">
                                    <div class="avatar">{{ str($post->author?->name ?? 'M')->substr(0, 1)->upper() }}</div>
                                    <div>
                                        <h2 class="feed-title h5">{{ $post->title }}</h2>
                                        <div class="feed-meta">{{ $post->author?->name ?? 'MAO' }} | {{ $post->created_at?->diffForHumans() }} @if($post->event_date) | Event: {{ $post->event_date->format('M d, Y h:i A') }} @endif</div>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    @if($isArchived)
                                        <span class="feed-badge archived">Archived</span>
                                    @endif
                                    <span class="feed-badge">{{ $post->category }}</span>
                                </div>
                            </div>
                            <p class="mt-3 mb-0" style="white-space:pre-line;">{{ $post->body }}</p>

                            @if($post->media->isNotEmpty())
                                <div class="media-grid">
                                    @foreach($post->media as $media)
                                        @if($media->media_type === 'image')
                                            <img src="{{ asset('storage/'.$media->path) }}" alt="{{ $media->original_name }}">
                                        @elseif($media->media_type === 'video')
                                            <video controls src="{{ asset('storage/'.$media->path) }}"></video>
                                        @else
                                            <a class="file-tile" href="{{ asset('storage/'.$media->path) }}" target="_blank">File: {{ $media->original_name }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if($canManagePost)
                                <div class="manage-actions">
                                    <details class="manage-panel">
                                        <summary>Edit Post</summary>
                                        <div class="manage-panel-body">
                                            <form method="POST" action="{{ route('community-feed.update', $post) }}" enctype="multipart/form-data">
                                                @csrf
                                                @method('PATCH')
                                                <div class="row g-3">
                                                    <div class="col-md-8">
                                                        <label class="form-label fw-semibold">Title</label>
                                                        <input name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Category</label>
                                                        <select name="category" class="form-select" required>
                                                            @foreach(['Update','Program','Activity','Training','Advisory','Announcement'] as $category)
                                                                <option value="{{ $category }}" @selected(old('category', $post->category) === $category)>{{ $category }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label fw-semibold">Post Details</label>
                                                        <textarea name="body" class="form-control" rows="4" required>{{ old('body', $post->body) }}</textarea>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Event Date</label>
                                                        <input name="event_date" type="datetime-local" class="form-control" value="{{ old('event_date', $post->event_date?->format('Y-m-d\TH:i')) }}">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Visibility</label>
                                                        <select name="visibility" class="form-select" required>
                                                            <option value="All Farmers" @selected(old('visibility', $post->visibility) === 'All Farmers')>All Farmers</option>
                                                            <option value="All Users" @selected(old('visibility', $post->visibility) === 'All Users')>All Users</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Add Attachments</label>
                                                        <input name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <button class="btn btn-primary fw-bold" type="submit">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </details>
                                    @if(! $isArchived)
                                        <form method="POST" action="{{ route('community-feed.archive', $post) }}" onsubmit="return confirm('Archive this post? Farmers will no longer see it.');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-outline-secondary fw-bold" type="submit">Archive</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('community-feed.destroy', $post) }}" onsubmit="return confirm('Delete this post permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger fw-bold" type="submit">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @unless($isArchived)
                            <div class="reaction-row">
                                @foreach($reactionTypes as $reaction)
                                    <form method="POST" action="{{ route('community-feed.reactions.store', $post) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $reaction }}">
                                        <button type="submit" class="{{ $userReaction?->type === $reaction ? 'text-success' : '' }}">{{ $reaction }} {{ $counts[$reaction] ?? '' }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endunless

                        <div class="feed-card-body pt-0">
                            @foreach($post->comments as $comment)
                                <div class="comment">
                                    <div class="avatar" style="width:34px;height:34px;font-size:.8rem;">{{ str($comment->user?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                    <div class="comment-bubble">
                                        <div class="fw-bold">{{ $comment->user?->name ?? 'User' }} <span class="feed-meta fw-normal">{{ $comment->created_at?->diffForHumans() }}</span></div>
                                        <div>{{ $comment->body }}</div>
                                    </div>
                                </div>
                            @endforeach
                            @unless($isArchived)
                                <form method="POST" action="{{ route('community-feed.comments.store', $post) }}" class="d-flex gap-2 mt-3">
                                    @csrf
                                    <input name="body" class="form-control" placeholder="Write a comment..." required>
                                    <button class="btn btn-outline-primary fw-bold" type="submit">Comment</button>
                                </form>
                            @else
                                <div class="text-muted small mt-3">This post is archived. Reactions and comments are closed.</div>
                            @endunless
                        </div>
                    </article>
                @empty
                    <div class="feed-card"><div class="feed-card-body text-center text-muted">No community posts yet.</div></div>
                @endforelse

                {{ $posts->links() }}
            </main>

            <aside class="side-panel">
                <h2 class="h5 fw-bold mb-2">What Farmers Can Do</h2>
                <p class="text-muted mb-3">Read MAO updates, react to useful posts, ask questions in comments, and message MAO directly for private concerns.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="{{ route('messages.index') }}">Start Conversation</a>
                    <a class="btn btn-outline-primary fw-bold" href="{{ route('planting-advisories.index') }}">Planting Advisories</a>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
