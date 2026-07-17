<x-app-layout>
    <style>
        .feed-wrap { --feed-ink:#0d1f18; --feed-muted:#6b8f71; --feed-line:#e8e0d0; --feed-green:#2d6a4f; --feed-mint:#52b788; --feed-blue:#2f6f8f; --feed-gold:#e8a73d; }
        .feed-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.75rem 1.85rem; color:#fff; background:linear-gradient(145deg,#0d1f18 0%,#1a3a2a 62%,#163324 100%); box-shadow:0 1rem 2.3rem rgba(13,31,24,.16); }
        .feed-hero::before {
            content:"";
            position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                radial-gradient(ellipse at 88% -10%, rgba(82,183,136,.16) 0%, transparent 60%);
            pointer-events:none;
        }
        .feed-hero::after { content:""; position:absolute; left:0; right:0; bottom:0; height:7px; background:linear-gradient(90deg,#e8a73d,#52b788,#2f6f8f); }
        .feed-hero > * { position:relative; z-index:1; }
        .feed-hero h1 { font-family:'DM Serif Display', Georgia, serif; font-weight:400; letter-spacing:-0.01em; color:#fff; }
        .feed-eyebrow { display:inline-flex; align-items:center; gap:8px; font-family:'DM Mono', monospace; font-size:.7rem; font-weight:500; text-transform:uppercase; letter-spacing:.12em; color:#74c69d; margin-bottom:.4rem; }
        .feed-eyebrow::before { content:''; display:block; width:18px; height:1px; background:#74c69d; }
        .feed-chip { display:inline-flex; align-items:center; gap:.5rem; border:1px solid rgba(255,255,255,.18); border-radius:999px; padding:.5rem .85rem; background:rgba(255,255,255,.06); color:rgba(255,255,255,.85); font-family:'DM Mono', monospace; font-size:.76rem; font-weight:500; letter-spacing:.02em; }
        .feed-pulse { width:8px; height:8px; border-radius:999px; background:#74c69d; box-shadow:0 0 0 5px rgba(116,198,157,.2); flex-shrink:0; }
        .feed-grid { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:1rem; align-items:start; margin-top:1rem; }
        .feed-card { border:1.5px solid #e8e0d0; border-radius:18px; background:#fff; box-shadow:0 .7rem 1.6rem rgba(20,32,51,.07); overflow:hidden; }
        .feed-card-body { padding:1rem; }
        .feed-composer { background:linear-gradient(135deg,#f5f0e8,#edf7e7); }
        .feed-composer .feed-card-body { background:linear-gradient(145deg,#fff,#f2f8f3); border-radius:16px; margin:.15rem; }
        .field-box { background:#fff; border:1px solid #e8e0d0; border-radius:10px; padding:.6rem .75rem .5rem; height:100%; }
        .field-box-label { display:block; font-family:'DM Mono', monospace; font-size:.64rem; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:#6b8f71; margin-bottom:.35rem; }
        .field-box .form-control, .field-box .form-select { border:0; padding:0; background:transparent; }
        .field-box .form-control:focus, .field-box .form-select:focus { box-shadow:none; }
        .feed-author { display:flex; gap:.75rem; align-items:center; }
        .avatar { width:42px; height:42px; border-radius:50%; display:grid; place-items:center; background:#f0f7f4; border:1px solid #d8f3dc; color:#2d6a4f; font-family:'DM Mono', monospace; font-weight:700; flex:0 0 auto; }
        .feed-title { color:var(--feed-ink); margin:0; }
        .feed-meta { color:var(--feed-muted); font-family:'DM Mono', monospace; font-size:.72rem; letter-spacing:.02em; }
        .feed-badge { display:inline-flex; border-radius:999px; padding:.32rem .62rem; background:#d8f3dc; color:#2d6a4f; font-family:'DM Mono', monospace; font-size:.64rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
        .feed-badge.archived { background:#f1f3f2; color:#6b8f71; border:1px solid #e8e0d0; }
        .post-actions { display:flex; flex-wrap:wrap; gap:.45rem; justify-content:flex-end; align-items:center; }
        .manage-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-start; margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(153,185,160,.4); }
        .manage-panel { flex:0 1 auto; border:1px solid rgba(153,185,160,.55); border-radius:10px; background:#f7fbf8; overflow:hidden; }
        .manage-panel[open] { flex-basis:100%; }
        .manage-panel summary { display:inline-flex; cursor:pointer; list-style:none; margin:.75rem .85rem; border:1.5px solid #2d6a4f; border-radius:999px; background:#fff; color:#2d6a4f; padding:.48rem .85rem; font-family:'DM Mono', monospace; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; transition:transform .18s ease, box-shadow .18s ease; }
        .manage-panel summary:hover { transform:translateY(-1px); box-shadow:0 .5rem 1rem rgba(45,106,79,.18); }
        .manage-panel summary::-webkit-details-marker { display:none; }
        .manage-panel-body { padding:.85rem; border-top:1px solid rgba(153,185,160,.55); }
        .media-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
        .media-grid img, .media-grid video { width:100%; border-radius:8px; border:1px solid rgba(153,185,160,.55); background:#0d1f18; max-height:330px; object-fit:cover; }
        .file-tile { display:flex; align-items:center; gap:.7rem; padding:.85rem; border:1px solid rgba(153,185,160,.55); border-radius:8px; background:#f7fbf8; color:inherit; text-decoration:none; font-weight:800; }
        .reaction-row { display:flex; flex-wrap:wrap; gap:.45rem; padding:.8rem 1rem; border-top:1px solid rgba(153,185,160,.4); border-bottom:1px solid rgba(153,185,160,.4); background:#fbfdfb; }
        .reaction-row button { border:1px solid rgba(153,185,160,.55); border-radius:999px; background:#fff; padding:.35rem .65rem; font-size:.82rem; font-weight:800; color:var(--feed-ink); }
        .reaction-row button.text-success { color:#2d6a4f !important; border-color:#b7e4c7; background:#d8f3dc; }
        .manage-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:1rem; padding-top:1rem; border-top:1px solid #edf3ee; }
        .manage-actions > form { margin:0; }
        .manage-panel { flex:0 0 auto; border:0; border-radius:8px; background:transparent; overflow:visible; }
        .manage-panel[open] { flex-basis:100%; }
        .manage-panel summary { display:inline-flex; align-items:center; justify-content:center; min-height:40px; cursor:pointer; list-style:none; margin:0; border:1px solid #1f6f4a; border-radius:8px; background:#fff; color:#1f6f4a; padding:.48rem .75rem; font-weight:900; line-height:1.2; }
        .manage-panel summary:hover, .manage-panel[open] summary { background:#f0f7f4; }
        .manage-panel summary::-webkit-details-marker { display:none; }
        .manage-panel-body { margin-top:.75rem; padding:.85rem; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; }
        .media-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
        .media-grid img, .media-grid video { width:100%; border-radius:8px; border:1px solid #d4edda; background:#0d1f18; max-height:330px; object-fit:cover; }
        .file-tile { display:flex; align-items:center; gap:.7rem; padding:.85rem; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; color:inherit; text-decoration:none; font-weight:800; }
        .reaction-row { display:flex; flex-wrap:wrap; gap:.45rem; padding:.8rem 1rem; border-top:1px solid #edf3ee; border-bottom:1px solid #edf3ee; background:#fbfdfb; }
        .reaction-picker { position:relative; display:inline-flex; align-items:center; }
        .reaction-trigger { border:1px solid #d4edda; border-radius:999px; background:#fff; color:var(--feed-ink); padding:.42rem .8rem; font-size:.86rem; font-weight:900; min-width:86px; }
        .reaction-trigger.active { color:#1f6f4a; background:#f0f7f4; border-color:#95d5b2; }
        .reaction-menu {
            position:absolute;
            left:0;
            bottom:calc(100% + .5rem);
            display:flex;
            gap:.42rem;
            padding:.42rem .5rem;
            border:1px solid rgba(13,31,24,.35);
            border-radius:999px;
            background:#23272d;
            box-shadow:0 .75rem 1.7rem rgba(13,31,24,.28);
            opacity:0;
            visibility:hidden;
            transform:translateY(.35rem) scale(.96);
            transform-origin:bottom left;
            transition:opacity .15s ease, transform .15s ease, visibility .15s ease;
            z-index:25;
            white-space:nowrap;
        }
        .reaction-picker:hover .reaction-menu,
        .reaction-picker:focus-within .reaction-menu {
            opacity:1;
            visibility:visible;
            transform:translateY(0) scale(1);
        }
        .reaction-picker:hover .reaction-option,
        .reaction-picker:focus-within .reaction-option {
            animation: reactionFloat .52s ease both;
        }
        .reaction-picker:hover .reaction-menu form:nth-child(2) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(2) .reaction-option { animation-delay:.03s; }
        .reaction-picker:hover .reaction-menu form:nth-child(3) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(3) .reaction-option { animation-delay:.06s; }
        .reaction-picker:hover .reaction-menu form:nth-child(4) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(4) .reaction-option { animation-delay:.09s; }
        .reaction-picker:hover .reaction-menu form:nth-child(5) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(5) .reaction-option { animation-delay:.12s; }
        .reaction-menu::after {
            content:"";
            position:absolute;
            left:1.1rem;
            bottom:-.38rem;
            width:.7rem;
            height:.7rem;
            background:#23272d;
            border-right:1px solid rgba(13,31,24,.35);
            border-bottom:1px solid rgba(13,31,24,.35);
            transform:rotate(45deg);
        }
        .reaction-option { position:relative; z-index:1; width:42px; height:42px; display:grid; place-items:center; border:0; border-radius:999px; background:var(--reaction-bg,#2f80ed); padding:0; color:#fff; font-size:0; font-weight:900; box-shadow:inset 0 -5px 10px rgba(0,0,0,.16), 0 .28rem .65rem rgba(0,0,0,.2); transition:transform .14s ease, filter .14s ease; }
        .reaction-option::before { content:attr(data-icon); font-size:1.45rem; line-height:1; }
        .reaction-option:hover, .reaction-option:focus { transform:translateY(-.48rem) scale(1.16); filter:saturate(1.08) brightness(1.04); }
        .reaction-option.like { --reaction-bg:linear-gradient(145deg,#53a5ff,#145bd8); }
        .reaction-option.love { --reaction-bg:linear-gradient(145deg,#ff5d82,#d81342); }
        .reaction-option.care { --reaction-bg:linear-gradient(145deg,#ffd86a,#f28d24); }
        .reaction-option.wow { --reaction-bg:linear-gradient(145deg,#ffd98a,#ff9f43); }
        .reaction-option.helpful { --reaction-bg:linear-gradient(145deg,#63d7a2,#168a5a); }
        .reaction-option.text-success { outline:3px solid rgba(255,255,255,.72); outline-offset:2px; }
        .reaction-display-icon { display:inline-grid; place-items:center; width:1.35rem; height:1.35rem; border-radius:999px; margin-right:.28rem; background:#f0f7f4; font-size:.95rem; line-height:1; vertical-align:-.18rem; }
        .reaction-counts { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; color:var(--feed-muted); font-size:.8rem; font-weight:800; }
        .reaction-count-pill { display:inline-flex; align-items:center; border:1px solid #d4edda; border-radius:999px; background:#fff; padding:.25rem .55rem .25rem .35rem; }
        @keyframes reactionFloat {
            0% { transform:translateY(.42rem) scale(.72); opacity:0; }
            58% { transform:translateY(-.34rem) scale(1.12); opacity:1; }
            100% { transform:translateY(0) scale(1); opacity:1; }
        }
        .comment { display:flex; gap:.65rem; margin-top:.75rem; }
        .comment-bubble { background:#f0f7f4; border:1px solid rgba(153,185,160,.55); border-radius:8px; padding:.6rem .75rem; flex:1; }
        .side-panel { border:1.5px solid #e8e0d0; border-radius:18px; background:linear-gradient(145deg,#f7fbf8,#edf7e7); padding:1.1rem; position:sticky; top:88px; transition:box-shadow .2s ease, border-color .2s ease; }
        .feed-empty { text-align:center; padding:2.75rem 1.25rem; }
        .feed-empty-icon { width:56px; height:56px; border-radius:50%; background:#f0f7f4; border:1px solid #d8f3dc; color:#2d6a4f; display:grid; place-items:center; margin:0 auto 1rem; }
        .feed-empty strong { display:block; font-family:'DM Serif Display', serif; font-weight:400; font-size:1.15rem; color:var(--feed-ink); margin-bottom:.4rem; }
        .feed-empty p { color:var(--feed-muted); font-size:.88rem; margin:0; }
        .side-panel:hover { box-shadow:0 1rem 2rem rgba(13,31,24,.1); border-color:#b7e4c7; }
        .side-panel h2 { margin-bottom:.5rem; }
        @media (max-width:1199.98px) { .feed-grid { grid-template-columns:1fr; } }
        @media (max-width:767.98px) { .media-grid { grid-template-columns:1fr; } }
    </style>

    <div class="feed-wrap">
        <section class="feed-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="feed-eyebrow">Community Feed</div>
                    <h1 class="h3 mb-1">MAO updates, programs, activities, and farmer discussions</h1>
                    <p class="mb-0 text-white-50">Farmers can react and comment on official MAO posts with photos, videos, and files.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="feed-chip"><span class="feed-pulse"></span> Live MAO Board</span>
                    <a href="{{ route('messages.index') }}" class="btn btn-light fw-bold">Open Messages</a>
                </div>
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
                            <div class="feed-eyebrow" style="color:#2d6a4f;">New Post</div>
                            <h2 class="h5 mb-3">Create MAO Post</h2>
                            <form method="POST" action="{{ route('community-feed.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerTitle">Post Title</label>
                                            <input id="composerTitle" name="title" class="form-control" placeholder="Post title" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerCategory">Category</label>
                                            <select id="composerCategory" name="category" class="form-select" required>
                                                @foreach(['Update','Program','Activity','Training','Advisory','Announcement'] as $category)
                                                    <option value="{{ $category }}">{{ $category }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerBody">Post Details</label>
                                            <textarea id="composerBody" name="body" class="form-control" rows="4" placeholder="Share details, schedules, instructions, or reminders..." required></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerEventDate">Event Date (Optional)</label>
                                            <input id="composerEventDate" name="event_date" type="datetime-local" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerVisibility">Visibility</label>
                                            <select id="composerVisibility" name="visibility" class="form-select" required>
                                                <option value="All Farmers">All Farmers</option>
                                                <option value="All Users">All Users</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="field-box">
                                            <label class="field-box-label" for="composerAttachments">Attachments (Optional)</label>
                                            <input id="composerAttachments" name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3"><button class="btn btn-primary fw-bold" type="submit">Publish Post</button></div>
                            </form>
                        </div>
                    </section>
                @endif

                @forelse($posts as $post)
                    @php
                        $userReaction = $post->reactions->firstWhere('user_id', auth()->id());
                        $counts = collect([
                            'Like' => $post->like_reactions_count,
                            'Love' => $post->love_reactions_count,
                            'Care' => $post->care_reactions_count,
                            'Wow' => $post->wow_reactions_count,
                            'Helpful' => $post->helpful_reactions_count,
                        ])->filter();
                        $canManagePost = $canPost;
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
                                        <summary>Edit</summary>
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
                            @php
                                $reactionIcons = [
                                    'Like' => '👍',
                                    'Love' => '❤',
                                    'Care' => '🥰',
                                    'Wow' => '😮',
                                    'Helpful' => '💡',
                                ];
                            @endphp
                            <div class="reaction-row">
                                <div class="reaction-picker">
                                    <button class="reaction-trigger {{ $userReaction ? 'active' : '' }}" type="button" aria-haspopup="true" aria-expanded="false">
                                        @if($userReaction)
                                            <span class="reaction-display-icon">{{ $reactionIcons[$userReaction->type] ?? '👍' }}</span>{{ $userReaction->type }}
                                        @else
                                            React
                                        @endif
                                    </button>
                                    <div class="reaction-menu" role="menu" aria-label="Choose a reaction">
                                        @foreach($reactionTypes as $reaction)
                                            <form method="POST" action="{{ route('community-feed.reactions.store', $post) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $reaction }}">
                                                @php
                                                    $reactionClass = strtolower($reaction);
                                                    $reactionIcon = $reactionIcons[$reaction] ?? '👍';
                                                @endphp
                                                <button type="submit" class="reaction-option {{ $reactionClass }} {{ $userReaction?->type === $reaction ? 'text-success' : '' }}" data-icon="{{ $reactionIcon }}" title="{{ $reaction }}" aria-label="{{ $reaction }}" role="menuitem">{{ $reaction }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                                @if($counts->isNotEmpty())
                                    <div class="reaction-counts">
                                        @foreach($counts as $type => $count)
                                            <span class="reaction-count-pill"><span class="reaction-display-icon">{{ $reactionIcons[$type] ?? '👍' }}</span>{{ $type }} {{ $count }}</span>
                                        @endforeach
                                    </div>
                                @endif
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
                    <div class="feed-card">
                        <div class="feed-card-body">
                            <div class="feed-empty">
                                <div class="feed-empty-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                </div>
                                <strong>No community posts yet.</strong>
                                <p>Once the MAO shares an update, it will appear here for farmers to view and discuss.</p>
                            </div>
                        </div>
                    </div>
                @endforelse

                {{ $posts->links() }}
            </main>

            <aside class="side-panel">
                <div class="feed-eyebrow" style="color:#2d6a4f;">Farmer Toolkit</div>
                <h2 class="h5 mb-2">What Farmers Can Do</h2>
                <p class="text-muted mb-3">Read MAO updates, react to useful posts, ask questions in comments, and message MAO directly for private concerns.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="{{ route('messages.index') }}">Start Conversation</a>
                    <a class="btn btn-outline-primary fw-bold" href="{{ route('planting-advisories.index') }}">Planting Advisories</a>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
