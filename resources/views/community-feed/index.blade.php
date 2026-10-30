<x-app-layout>
    @vite('resources/css/pages/community-feed.css')

    @php $isStaffFeed = auth()->user()->role !== \App\Models\User::ROLE_FARMER; @endphp
    {{-- Staff accounts (IT and MAO) get the IT dashboard design system: .mfd / .itd parts plus it-pages.css. --}}
    <div @class(['feed-wrap', 'mfd itd itp' => $isStaffFeed])>
        @if($isStaffFeed)
            <x-it.page-header kicker="Community and Updates" title="Announcements" lede="MAO news and activities for farmers. Farmers read these on their Advisories page.">
                <span class="mfd-pill is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>MAO Updates</span></span>
                <div class="itd-header-actions">
                    <a href="{{ route('messages.index') }}" class="mfd-btn is-quiet">@include('dashboards.mao.icon', ['name' => 'message'])Open Messages</a>
                </div>
            </x-it.page-header>
        @else
            {{-- Farmers get the same header as the other farmer pages (.fad-hero, farmer-advisories.css). --}}
            <div class="fad is-header-only">
                <header class="fad-hero">
                    <div class="fad-hero-text">
                        <span class="fad-kicker">Community Feed</span>
                        <h1>Community</h1>
                        <p>MAO news and farmer discussions. Read MAO posts, react, and leave comments.</p>
                    </div>
                    <div class="fad-hero-actions">
                        <span class="fad-hero-note">@include('dashboards.mao.icon', ['name' => 'check'])<span>MAO Updates</span></span>
                        <a href="{{ route('messages.index') }}" class="fad-btn is-light">Open Messages</a>
                    </div>
                </header>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="feed-grid">
            <main class="d-grid gap-3">
                @if($canPost)
                    <details class="ic-collapse">
                        <summary><span class="ic-collapse-title">+ New Post</span></summary>
                    <section class="feed-card feed-composer">
                        <div class="feed-card-body">
                            <div class="feed-eyebrow" style="color:var(--palette-ink);">New Post</div>
                            <h2 class="h5 mb-3">Create MAO Post</h2>
                            <div class="composer-head">
                                <div class="composer-head-main">
                                    <span class="composer-head-dot" aria-hidden="true"></span>
                                    <div>
                                        <div class="composer-head-title">Official community update</div>
                                        <div class="composer-head-sub">Post advisories, schedules, programs, and farmer reminders.</div>
                                    </div>
                                </div>
                                <div class="composer-head-tags" aria-hidden="true">
                                    <span class="composer-head-tag">MAO Post</span>
                                    <span class="composer-head-tag">Will Show on Calendar</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('community-feed.store') }}" enctype="multipart/form-data" data-loading="true">
                                @csrf
                                <div class="composer-layout">
                                    <div class="composer-surface">
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
                                                    <textarea id="composerBody" name="body" class="form-control" rows="5" placeholder="Share details, schedules, instructions, or reminders..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerEventDate">Event Date</label>
                                                    <input id="composerEventDate" name="event_date" type="datetime-local" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerVisibility">Who Can See This</label>
                                                    <select id="composerVisibility" name="visibility" class="form-select" required>
                                                        <option value="All Farmers">All Farmers</option>
                                                        <option value="All Users">All Users</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerAttachments">Photos, Videos, or Files</label>
                                                    <input id="composerAttachments" name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="field-box composer-option d-flex gap-3 align-items-start" for="composerShowOnCalendar">
                                                    <input id="composerShowOnCalendar" name="show_on_calendar" value="1" type="checkbox" class="form-check-input mt-1">
                                                    <span>
                                                        <span class="field-box-label d-block mb-1">Important event</span>
                                                        <span class="small text-muted">Show this post on the Calendar using the event date.</span>
                                                    </span>
                                                </label>
                                                <div id="composerFileList" class="composer-file-list"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 composer-submit-row"><button class="btn btn-primary fw-bold" type="submit" data-loading-text="Publishing...">Publish Post</button></div>
                                    </div>
                                    </div>
                            </form>
                        </div>
                    </section>
                    </details>
                @endif

                @forelse($posts as $post)
                    @php
                        $userReaction = $post->reactions->firstWhere('user_id', auth()->id());
                        $canManagePost = $canPost;
                        $isArchived = $post->archived_at !== null;
                        $visibleReactions = $canManagePost
                            ? $post->reactions->reject(fn ($reaction) => $reaction->user_id === $post->user_id)
                            : $post->reactions;
                        $reactionGroups = $visibleReactions->groupBy('type');
                        $counts = $reactionGroups->map->count()->filter();
                    @endphp
                    <article id="post-{{ $post->id }}" class="feed-card feed-post-card">
                        <div class="feed-card-body">
                            <div class="feed-post-head d-flex justify-content-between gap-3">
                                <div class="feed-author">
                                    <div class="avatar">{{ str($post->author?->name ?? 'M')->substr(0, 1)->upper() }}</div>
                                    <div>
                                        <h2 class="feed-title h5">{{ $post->author?->name ?? 'MAO' }}</h2>
                                        <div class="feed-meta">{{ $post->created_at?->diffForHumans() }} @if($post->event_date) | Event: {{ $post->event_date->shortDateTime('M d, Y') }} @endif @if($post->show_on_calendar) | Calendar @endif</div>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    @if($isArchived)
                                        <span class="feed-badge archived">Archived</span>
                                    @endif
                                    <span class="feed-badge">{{ $post->category }}</span>
                                    @if($post->show_on_calendar)
                                        <span class="feed-badge">Calendar</span>
                                    @endif
                                </div>
                            </div>
                            <div class="feed-post-heading">{{ $post->title }}</div>
                            <p class="feed-body-text mt-1 mb-0" style="white-space:pre-line;">{{ $post->body }}</p>

                            @if($post->media->isNotEmpty())
                                <div class="media-grid">
                                    @foreach($post->media as $media)
                                        @php $mediaUrl = $media->url(); @endphp
                                        @if($mediaUrl && $media->media_type === 'image')
                                            <img src="{{ $mediaUrl }}" alt="{{ $media->original_name }}" loading="lazy">
                                        @elseif($mediaUrl && $media->media_type === 'video')
                                            <video controls src="{{ $mediaUrl }}"></video>
                                        @elseif($mediaUrl)
                                            <a class="file-tile" href="{{ $mediaUrl }}" target="_blank" rel="noopener">File: {{ $media->original_name }}</a>
                                        @else
                                            <div class="file-tile media-unavailable" role="status">Attachment unavailable: {{ $media->original_name }}</div>
                                        @endif
                                    @endforeach
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
                                @unless($canManagePost)
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
                                @endunless
                                @if($counts->isNotEmpty())
                                    <div class="reaction-counts">
                                        @foreach($counts as $type => $count)
                                            <button class="reaction-count-pill" type="button" data-reaction-viewer-open="reactionViewer{{ $post->id }}" data-reaction-type="{{ $type }}">
                                                <span class="reaction-display-icon">{{ $reactionIcons[$type] ?? '👍' }}</span>{{ $type }} {{ $count }}
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif($canManagePost)
                                    <div class="reaction-counts">
                                        <span class="reaction-count-pill">No reactions yet</span>
                                    </div>
                                @endif
                            </div>
                        @endunless

                        @if($counts->isNotEmpty())
                            <div id="reactionViewer{{ $post->id }}" class="reaction-viewer" role="dialog" aria-modal="true" aria-labelledby="reactionViewerTitle{{ $post->id }}">
                                <div class="reaction-viewer-card">
                                    <div class="reaction-viewer-head">
                                        <div id="reactionViewerTitle{{ $post->id }}" class="reaction-viewer-title">Reactions</div>
                                        <button class="reaction-viewer-close" type="button" aria-label="Close reactions" data-reaction-viewer-close>&times;</button>
                                    </div>
                                    <div class="reaction-viewer-body">
                                        @foreach($reactionTypes as $reaction)
                                            @php($users = $reactionGroups->get($reaction, collect()))
                                            <div class="reaction-viewer-group" data-reaction-group="{{ $reaction }}" @if($users->isEmpty()) hidden @endif>
                                                @foreach($users as $reactionRecord)
                                                    @php($name = $reactionRecord->user?->name ?? 'User')
                                                    <div class="reaction-viewer-user">
                                                        <span class="reaction-viewer-avatar">{{ str($name)->substr(0, 1)->upper() }}</span>
                                                        <span>{{ $name }}</span>
                                                        <span class="ms-auto reaction-display-icon">{{ $reactionIcons[$reaction] ?? '👍' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($canManagePost)
                            <div class="manage-actions">
                                <details class="manage-panel">
                                    <summary>Edit</summary>
                                    <div class="manage-panel-body">
                                        <form method="POST" action="{{ route('community-feed.update', $post) }}" enctype="multipart/form-data" data-loading="true">
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
                                                    <label class="form-label fw-semibold">Who Can See This</label>
                                                    <select name="visibility" class="form-select" required>
                                                        <option value="All Farmers" @selected(old('visibility', $post->visibility) === 'All Farmers')>All Farmers</option>
                                                        <option value="All Users" @selected(old('visibility', $post->visibility) === 'All Users')>All Users</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Add Attachments</label>
                                                    <input name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-check d-flex gap-2 align-items-start">
                                                        <input name="show_on_calendar" value="1" type="checkbox" class="form-check-input mt-1" @checked(old('show_on_calendar', $post->show_on_calendar))>
                                                        <span>
                                                            <span class="fw-semibold d-block">Important event</span>
                                                            <span class="small text-muted">Show this post on the Calendar using the event date.</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button class="btn btn-primary fw-bold" type="submit" data-loading-text="Saving...">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                @if(! $isArchived)
                                    <form method="POST" action="{{ route('community-feed.archive', $post) }}" data-loading="true" data-ic-confirm="Archive this post? Farmers will no longer see it.">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-secondary fw-bold" type="submit" data-loading-text="Archiving...">Archive</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('community-feed.destroy', $post) }}" data-loading="true" data-ic-confirm="Delete this post permanently?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger fw-bold" type="submit" data-loading-text="Deleting...">Delete</button>
                                </form>
                            </div>
                        @endif

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
                                    <button class="btn btn-outline-primary fw-bold" type="submit" data-loading-text="Posting...">Comment</button>
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
                <div class="feed-eyebrow" style="color:var(--sage-300);">Farmer Toolkit</div>
                <h2 class="h5 mb-2">What Farmers Can Do</h2>
                <p class="text-muted mb-3">Read MAO updates, react to useful posts, ask questions in comments, and message MAO directly for private concerns.</p>
                <details class="ic-collapse">
                    <summary><span class="ic-collapse-title">Farmer Guide <span class="ic-collapse-count">4</span></span></summary>
                    <div class="toolkit-list">
                        <div class="toolkit-item">
                            <strong>Check Official Updates</strong>
                            <span>Review posts for schedules, program requirements, advisory changes, and activity reminders from MAO personnel.</span>
                        </div>
                        <div class="toolkit-item">
                            <strong>Use Reactions for Quick Feedback</strong>
                            <span>Mark posts as helpful, liked, or important so the MAO can see which updates farmers are using most.</span>
                        </div>
                        <div class="toolkit-item">
                            <strong>Ask Public Questions</strong>
                            <span>Comment when your question can help other farmers, such as clarification about dates, locations, or instructions.</span>
                        </div>
                        <div class="toolkit-item">
                            <strong>Message Private Concerns</strong>
                            <span>Use messages for farm-specific issues, personal records, crop damage details, or concerns that need direct assistance.</span>
                        </div>
                    </div>
                    <div class="toolkit-note mb-3">
                        Before joining an activity, confirm the event date, prepare any listed documents, and follow the latest advisory if weather conditions change.
                        For urgent crop or weather concerns, send a direct message with your barangay, crop stage, and photos when available.
                    </div>
                </details>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="{{ route('messages.index') }}">Start Conversation</a>
                    <a class="btn btn-outline-primary fw-bold" href="{{ route('planting-advisories.index') }}">Planting Advice</a>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const composerAttachments = document.getElementById('composerAttachments');
            const composerFileList = document.getElementById('composerFileList');

            composerAttachments?.addEventListener('change', () => {
                if (!composerFileList) return;
                composerFileList.innerHTML = '';
                Array.from(composerAttachments.files || []).slice(0, 6).forEach((file) => {
                    const chip = document.createElement('span');
                    chip.className = 'composer-file-chip';
                    chip.textContent = file.name;
                    composerFileList.appendChild(chip);
                });
            });
            const closeViewer = (viewer) => {
                viewer?.classList.remove('show');
            };

            const closeAllViewers = (except = null) => {
                document.querySelectorAll('.reaction-viewer.show').forEach((viewer) => {
                    if (viewer !== except) closeViewer(viewer);
                });
            };

            const positionViewer = (viewer, button) => {
                const row = button.closest('.reaction-row');
                if (!row) return;

                row.appendChild(viewer);

                const rowRect = row.getBoundingClientRect();
                const buttonRect = button.getBoundingClientRect();
                const width = Math.min(360, rowRect.width - 32);
                let left = (buttonRect.left - rowRect.left) + (buttonRect.width / 2) - (width / 2);

                if (left + width > rowRect.width - 16) {
                    left = rowRect.width - width - 16;
                }

                if (left < 16) {
                    left = 16;
                }

                viewer.style.width = `${width}px`;
                viewer.style.setProperty('--reaction-popover-left', `${left}px`);
                viewer.querySelector('.reaction-viewer-card')?.style.setProperty('--reaction-caret-left', `${Math.max(18, buttonRect.left - rowRect.left - left + (buttonRect.width / 2) - 7)}px`);
            };

            document.querySelectorAll('[data-reaction-viewer-open]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const viewer = document.getElementById(button.dataset.reactionViewerOpen);
                    if (!viewer) return;

                    const alreadyOpen = viewer.classList.contains('show');
                    closeAllViewers(viewer);
                    if (alreadyOpen && viewer.dataset.activeReactionType === button.dataset.reactionType) {
                        closeViewer(viewer);
                        return;
                    }

                    const selectedType = button.dataset.reactionType;
                    viewer.dataset.activeReactionType = selectedType;
                    viewer.querySelectorAll('[data-reaction-group]').forEach((group) => {
                        group.hidden = group.dataset.reactionGroup !== selectedType;
                    });

                    const title = viewer.querySelector('.reaction-viewer-title');
                    if (title) title.textContent = `${selectedType} reactions`;

                    viewer.classList.add('show');
                    positionViewer(viewer, button);
                });
            });

            document.querySelectorAll('[data-reaction-viewer-close]').forEach((button) => {
                button.addEventListener('click', () => closeViewer(button.closest('.reaction-viewer')));
            });

            document.querySelectorAll('.reaction-viewer').forEach((viewer) => {
                viewer.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            });

            document.addEventListener('click', () => closeAllViewers());
            window.addEventListener('resize', () => closeAllViewers());

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                closeAllViewers();
            });
        });
    </script>
</x-app-layout>
