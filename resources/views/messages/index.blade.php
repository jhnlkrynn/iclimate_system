<x-app-layout>
    @php
        $currentUser = auth()->user();
        $tabLabels = [
            \App\Models\User::ROLE_FARMER => 'Farmers',
            \App\Models\User::ROLE_MAO => 'MAO Staff',
            \App\Models\User::ROLE_IT_EXPERT => 'IT Expert',
        ];
        $rolesPresent = $conversations
            ->map(fn ($conversation) => $conversation->otherParticipant($currentUser)?->role)
            ->filter()
            ->unique()
            ->values();
    @endphp
    <style>
        .msg-wrap { --msg-ink:#1f2a24; --msg-muted:#6b7c72; --msg-line:#e3ece6; --msg-green:#2d6a4f; --msg-mint:#52b788; --msg-blue:#2f6f8f; color: var(--msg-ink); }
        .msg-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.75rem 1.85rem; color:var(--msg-ink); background:linear-gradient(145deg,#f6f9f7 0%,#e7f0ea 100%); border:1px solid #e3ece6; box-shadow:0 1rem 2.3rem rgba(31,42,36,.08); }
        .msg-hero::before {
            content:"";
            position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                radial-gradient(ellipse at 88% -10%, rgba(82,183,136,.16) 0%, transparent 60%);
            pointer-events:none;
        }
        .msg-hero > * { position:relative; z-index:1; }
        .msg-hero h1 { font-family:'DM Serif Display', Georgia, serif; font-weight:400; letter-spacing:-0.01em; color:var(--msg-ink); }
        .msg-eyebrow { display:inline-flex; align-items:center; gap:8px; font-family:'DM Mono', monospace; font-size:.7rem; font-weight:500; text-transform:uppercase; letter-spacing:.12em; color:#74c69d; margin-bottom:.4rem; }
        .msg-eyebrow::before { content:''; display:block; width:18px; height:1px; background:#74c69d; }
        .msg-chip { display:inline-flex; align-items:center; gap:.5rem; border:1px solid var(--msg-line); border-radius:999px; padding:.5rem .85rem; background:rgba(45,106,79,.06); color:var(--msg-ink); font-family:'DM Mono', monospace; font-size:.76rem; font-weight:500; letter-spacing:.02em; }
        .msg-pulse { width:8px; height:8px; border-radius:999px; background:#74c69d; box-shadow:0 0 0 5px rgba(116,198,157,.2); flex-shrink:0; }
        .msg-shell { display:grid; grid-template-columns:340px minmax(0,1fr); gap:1rem; margin-top:1rem; align-items:stretch; min-height:clamp(620px, calc(100vh - 210px), 860px); }
        .msg-panel { border:1.5px solid var(--ic-sand-dark); border-radius:18px; background:linear-gradient(145deg, var(--ic-paper), #f7fbf8); box-shadow:0 .7rem 1.6rem rgba(31,42,36,.06); overflow:hidden; display:flex; flex-direction:column; min-height:0; }
        .msg-head { padding:1rem; border-bottom:1px solid var(--msg-line); background:rgba(243,246,244,.6); flex:0 0 auto; }

        /* ---- Search + tabs ---- */
        .msg-search-box { position:relative; }
        .msg-search-input { width:100%; background:#fff; border:1px solid var(--msg-line); border-radius:999px; padding:.6rem 1rem .6rem 2.3rem; color:var(--msg-ink); font-size:.88rem; }
        .msg-search-input::placeholder { color:var(--msg-muted); }
        .msg-search-input:focus { outline:none; border-color:#52b788; background:#fff; }
        .msg-search-icon { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); color:var(--msg-muted); pointer-events:none; }
        .msg-search-results { margin-top:.5rem; border:1px solid var(--msg-line); border-radius:12px; background:#fff; max-height:220px; overflow-y:auto; display:none; }
        .msg-search-results.show { display:block; }
        .msg-search-result { display:flex; align-items:center; gap:.6rem; padding:.55rem .7rem; cursor:pointer; border-bottom:1px solid var(--msg-line); }
        .msg-search-result:last-child { border-bottom:none; }
        .msg-search-result:hover { background:rgba(45,106,79,.06); }
        .msg-search-result.is-loading { opacity:.55; cursor:wait; pointer-events:none; }
        .msg-search-result.is-loading::after { content:""; width:14px; height:14px; margin-left:auto; flex-shrink:0; border:2px solid rgba(45,106,79,.25); border-top-color:var(--ic-green-500); border-radius:50%; animation:msgSpin .6s linear infinite; }
        @keyframes msgSpin { to { transform:rotate(360deg); } }
        .msg-search-empty { padding:.6rem .7rem; color:var(--msg-muted); font-size:.8rem; }
        .msg-tabs { display:flex; gap:.4rem; margin-top:.75rem; overflow-x:auto; }
        .msg-tab { flex:0 0 auto; border:1px solid var(--msg-line); background:transparent; color:var(--msg-muted); border-radius:999px; padding:.32rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
        .msg-tab.active { background:#52b788; border-color:#52b788; color:#0d1f18; }

        .thread-list { flex:1 1 auto; min-height:0; overflow:auto; }
        .thread-link { display:block; padding:.85rem 1rem; border-bottom:1px solid var(--msg-line); color:inherit; text-decoration:none; border-left:4px solid transparent; transition:background .15s ease, border-color .15s ease; }
        .thread-link.active { background:rgba(232,167,61,.14); border-left-color:#e8a73d; }
        .thread-link:hover { background:rgba(45,106,79,.05); }
        .thread-link.unread .thread-name, .thread-link.unread .thread-preview { font-weight:800; color:var(--msg-ink); }

        /* ---- Avatar + presence ---- */
        .avatar-wrap { position:relative; flex:0 0 auto; }
        .avatar { width:40px; height:40px; border-radius:50%; display:grid; place-items:center; background:rgba(82,183,136,.16); border:1px solid rgba(149,213,178,.3); color:#74c69d; font-family:'DM Mono', monospace; font-weight:700; }
        .avatar-status { position:absolute; right:-1px; bottom:-1px; width:11px; height:11px; border-radius:999px; border:2px solid #fff; background:#c7d1cb; }
        .avatar-status.online { background:#52b788; }

        .thread-row-top { display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; }
        .thread-time { font-family:'DM Mono', monospace; font-size:.66rem; color:var(--msg-muted); flex-shrink:0; }
        .thread-preview-row { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
        .unread-badge { flex-shrink:0; min-width:19px; height:19px; padding:0 .35rem; border-radius:999px; background:#e8a73d; color:#0d1f18; font-family:'DM Mono', monospace; font-size:.66rem; font-weight:800; display:grid; place-items:center; }

        .chat-window { flex:1 1 auto; min-height:0; overflow:auto; padding:1rem; background:rgba(243,246,244,.4); scroll-behavior:smooth; }
        .bubble-row { display:flex; margin-bottom:.8rem; }
        .bubble-row.mine { justify-content:flex-end; }
        .bubble { max-width:min(680px,82%); border:1px solid var(--msg-line); border-radius:14px; background:#fff; color:var(--msg-ink); padding:.7rem .85rem; }
        .mine .bubble { background:#2d6a4f; border-color:#2d6a4f; color:#fff; }
        .msg-meta { color:var(--msg-muted); font-family:'DM Mono', monospace; font-size:.7rem; letter-spacing:.02em; margin-top:.3rem; display:flex; align-items:center; gap:.35rem; }
        .mine .msg-meta { color:rgba(255,255,255,.72); }
        .msg-check { font-size:.78rem; color:rgba(255,255,255,.5); }
        .msg-check.seen { color:#74c69d; }
        .msg-actions { display:flex; justify-content:flex-end; margin-top:.5rem; }
        .msg-unsend { border:1px solid rgba(255,255,255,.52); border-radius:999px; background:rgba(255,255,255,.14); color:#fff; font-family:'DM Mono', monospace; font-size:.68rem; font-weight:700; padding:.22rem .55rem; line-height:1.2; }
        .msg-unsend:hover { background:rgba(255,255,255,.24); border-color:rgba(255,255,255,.82); color:#fff; text-decoration:none; }
        .msg-typing { padding:0 1rem .5rem; color:var(--msg-muted); font-family:'DM Mono', monospace; font-size:.72rem; display:none; }
        .msg-typing.show { display:block; }

        /* ---- Compose bar ---- */
        .msg-compose-wrap { padding:.85rem 1rem; border-top:1px solid var(--msg-line); background:rgba(243,246,244,.5); flex:0 0 auto; }
        .msg-compose { display:flex; align-items:flex-end; gap:.4rem; background:#fff; border:1px solid var(--msg-line); border-radius:24px; padding:.4rem .5rem .4rem .7rem; position:relative; }
        .compose-icon-btn { border:0; background:transparent; color:var(--msg-muted); width:34px; height:34px; border-radius:999px; display:grid; place-items:center; flex-shrink:0; font-size:1.05rem; }
        .compose-icon-btn:hover { background:rgba(45,106,79,.08); color:var(--msg-ink); }
        .compose-input { flex:1 1 auto; resize:none; max-height:120px; background:transparent; border:0; color:var(--msg-ink); padding:.5rem .3rem; font-size:.9rem; line-height:1.4; }
        .compose-input:focus { outline:none; box-shadow:none; }
        .compose-input::placeholder { color:var(--msg-muted); }
        .compose-send { border:0; background:#e8a73d; color:#0d1f18; width:38px; height:38px; border-radius:999px; display:grid; place-items:center; flex-shrink:0; font-weight:700; }
        .compose-send:disabled { opacity:.5; }
        .compose-file-chip { display:inline-flex; align-items:center; gap:.4rem; background:rgba(45,106,79,.06); border:1px solid var(--msg-line); border-radius:999px; padding:.2rem .55rem; font-size:.72rem; margin-bottom:.4rem; }
        .compose-file-remove { border:0; background:transparent; color:var(--msg-muted); font-weight:700; }
        .emoji-popover { position:absolute; bottom:calc(100% + 8px); left:0; display:none; grid-template-columns:repeat(6,1fr); gap:.25rem; background:#fff; border:1px solid var(--msg-line); border-radius:14px; padding:.5rem; box-shadow:0 .7rem 1.6rem rgba(31,42,36,.14); z-index:5; }
        .emoji-popover.show { display:grid; }
        .emoji-option { border:0; background:transparent; font-size:1.15rem; border-radius:8px; padding:.25rem; line-height:1; }
        .emoji-option:hover { background:rgba(45,106,79,.08); }

        .msg-empty { flex:1 1 auto; min-height:420px; display:grid; place-items:center; padding:2rem; }
        .unsend-confirm { position:fixed; inset:0; z-index:2300; display:none; place-items:center; padding:1rem; background:rgba(13,31,24,.48); backdrop-filter:blur(5px); }
        .unsend-confirm.show { display:grid; }
        .unsend-card { width:min(420px,100%); overflow:hidden; border:1px solid rgba(212,237,218,.9); border-radius:20px; background:linear-gradient(145deg,#fff,#f7fbf8); box-shadow:0 1.4rem 3.4rem rgba(13,31,24,.34); }
        .unsend-card::before { content:""; display:block; height:7px; background:linear-gradient(90deg,#d85b45,#e8a73d,#52b788); }
        .unsend-body { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.85rem; padding:1.05rem; }
        .unsend-icon { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; background:#fff0ea; color:#d85b45; font-weight:900; font-size:1.25rem; }
        .unsend-title { margin:0 0 .3rem; color:var(--msg-ink); font-size:1.08rem; font-weight:900; }
        .unsend-text { margin:0; color:#4a5c52; line-height:1.45; }
        .unsend-actions { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; padding:0 1.05rem 1.05rem; }
        .unsend-cancel, .unsend-submit { border:0; border-radius:999px; padding:.72rem 1rem; font-weight:900; }
        .unsend-cancel { background:#f0f7f4; color:#1a3a2a; }
        .unsend-submit { background:#d85b45; color:#fff; box-shadow:0 .75rem 1.45rem rgba(216,91,69,.22); }
        .msg-wrap .text-muted { color: var(--msg-muted) !important; }
        .msg-wrap .alert-success { background: rgba(82,183,136,.14); border-color: rgba(116,198,157,.35); color: #1a3a2a; }
        @media (max-width:1199.98px) {
            .msg-shell { grid-template-columns:1fr; min-height:auto; }
            .msg-panel { min-height:auto; }
            .thread-list { max-height:360px; flex:none; }
            .chat-window { min-height:440px; max-height:62vh; flex:none; }
        }
        @media (max-width:767.98px) {
            .msg-hero { padding:1rem; }
            .msg-head { padding:.85rem; }
            .chat-window { min-height:360px; max-height:58vh; padding:.75rem; }
            .thread-list { max-height:300px; }
            .bubble { max-width:92%; overflow-wrap:anywhere; }
            .msg-compose-wrap { padding:.6rem .75rem; }
            .msg-empty { min-height:300px; padding:1.25rem; }
        }
        @media (max-width:420px) {
            .msg-hero { border-radius:18px; }
            .msg-shell { gap:.75rem; }
            .bubble { max-width:100%; }
            .unsend-actions { grid-template-columns:1fr; }
            .emoji-popover { grid-template-columns:repeat(5,1fr); }
        }
    </style>

    <div class="msg-wrap"
         data-current-user-id="{{ $currentUser->id }}"
         data-search-url="{{ route('messages.search') }}"
         data-open-url="{{ route('messages.open') }}"
         @if($activeConversation)
             data-conversation-id="{{ $activeConversation->id }}"
             data-poll-url="{{ route('messages.poll', $activeConversation) }}"
             data-send-url="{{ route('messages.reply', $activeConversation) }}"
             data-typing-url="{{ route('messages.typing', $activeConversation) }}"
             data-unsend-url-base="{{ route('messages.destroy-message', [$activeConversation, 0]) }}"
             data-last-message-id="{{ $messages->max('id') ?? 0 }}"
         @endif
    >
        <section class="msg-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="msg-eyebrow">Messages</div>
                    <h1 class="h3 mb-1">Farmer and MAO conversations</h1>
                    <p class="mb-0 text-muted">Private messages for follow-ups, concerns, activity questions, and document sharing.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="msg-chip"><span class="msg-pulse"></span> Private Channel</span>
                    <a href="{{ route('community-feed.index') }}" class="btn btn-light fw-bold">Community Feed</a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="msg-shell">
            <aside class="msg-panel">
                <div class="msg-head">
                    <div class="msg-search-box">
                        <span class="msg-search-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="threadSearch" class="msg-search-input" placeholder="Search people or conversations..." autocomplete="off">
                    </div>
                    <div id="searchResults" class="msg-search-results"></div>

                    @if($rolesPresent->count() > 1)
                        <div class="msg-tabs" role="tablist">
                            <button type="button" class="msg-tab active" data-role-filter="all">All</button>
                            @foreach($rolesPresent as $role)
                                <button type="button" class="msg-tab" data-role-filter="{{ $role }}">{{ $tabLabels[$role] ?? $role }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="thread-list" id="threadList">
                    @forelse($conversations as $conversation)
                        @php
                            $other = $conversation->otherParticipant($currentUser);
                            $latest = $conversation->latestMessage;
                            $unread = $unreadCounts->get($conversation->id, 0);
                        @endphp
                        <a class="thread-link {{ $activeConversation?->id === $conversation->id ? 'active' : '' }} {{ $unread > 0 ? 'unread' : '' }}"
                           href="{{ route('messages.show', $conversation) }}"
                           data-role="{{ $other?->role }}"
                           data-name="{{ str($other?->name)->lower() }}"
                           data-other-id="{{ $other?->id }}">
                            <div class="d-flex gap-2 align-items-start">
                                <div class="avatar-wrap">
                                    <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                    <span class="avatar-status {{ $other?->isOnline() ? 'online' : '' }}"></span>
                                </div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="thread-row-top">
                                        <div class="fw-bold thread-name text-truncate">{{ $other?->name ?? 'User' }}</div>
                                        <div class="thread-time">{{ $latest?->humanTimestamp() }}</div>
                                    </div>
                                    <div class="small text-muted">{{ $other?->role }}</div>
                                    <div class="thread-preview-row">
                                        <div class="small text-muted text-truncate thread-preview">
                                            {{ $latest?->sender_id === $currentUser->id ? 'You: ' : '' }}{{ $latest?->body ?: ($latest?->attachment_name ? 'Attachment: '.$latest->attachment_name : 'No messages yet') }}
                                        </div>
                                        @if($unread > 0)
                                            <span class="unread-badge">{{ $unread > 9 ? '9+' : $unread }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-muted">No conversations yet. Use the search box above to message someone.</div>
                    @endforelse
                </div>
            </aside>

            <main class="msg-panel">
                @if($activeConversation)
                    @php($other = $activeConversation->otherParticipant($currentUser))
                    <div class="msg-head">
                        <div class="d-flex gap-2 align-items-center">
                            <div class="avatar-wrap">
                                <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                <span class="avatar-status {{ $other?->isOnline() ? 'online' : '' }}" id="headerStatusDot"></span>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold mb-0">{{ $other?->name ?? 'Conversation' }}</h2>
                                <div class="small text-muted" id="headerStatusText">{{ $other?->role }} &middot; {{ $other?->isOnline() ? 'Online' : 'Offline' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="chat-window" id="chatWindow">
                        @foreach($messages as $message)
                            <div class="bubble-row {{ $message->sender_id === $currentUser->id ? 'mine' : '' }}" data-message-id="{{ $message->id }}">
                                <div class="bubble">
                                    @if($message->body)
                                        <div style="white-space:pre-line;">{{ $message->body }}</div>
                                    @endif
                                    @if($message->attachment_path)
                                        <a class="{{ $message->sender_id === $currentUser->id ? 'text-white' : '' }}" href="{{ asset('storage/'.$message->attachment_path) }}" target="_blank">{{ $message->attachment_name }}</a>
                                    @endif
                                    <div class="msg-meta">
                                        <span>{{ $message->sender?->name }} &middot; {{ $message->humanTimestamp() }}</span>
                                        @if($message->sender_id === $currentUser->id)
                                            <span class="msg-check {{ $message->read_at ? 'seen' : '' }}" data-message-id="{{ $message->id }}">{{ $message->read_at ? '✓✓' : '✓' }}</span>
                                        @endif
                                    </div>
                                    @if($message->sender_id === $currentUser->id)
                                        <form class="msg-actions" method="POST" action="{{ route('messages.destroy-message', [$activeConversation, $message]) }}" data-unsend-form>
                                            @csrf
                                            @method('DELETE')
                                            <button class="msg-unsend" type="submit">Unsend</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="msg-typing" id="typingIndicator">{{ $other?->name ?? 'They' }} is typing...</div>
                    <div class="msg-compose-wrap">
                        <div id="composeFileChip" class="compose-file-chip" style="display:none;">
                            <span id="composeFileName"></span>
                            <button type="button" class="compose-file-remove" id="composeFileRemove" aria-label="Remove attachment">&times;</button>
                        </div>
                        <form class="msg-compose" id="composeForm" method="POST" action="{{ route('messages.reply', $activeConversation) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="file" name="attachment" id="attachmentInput" class="d-none">
                            <button type="button" class="compose-icon-btn" id="attachTrigger" title="Attach a file" aria-label="Attach a file">📎</button>
                            <button type="button" class="compose-icon-btn" id="emojiTrigger" title="Emoji" aria-label="Emoji">🙂</button>
                            <div class="emoji-popover" id="emojiPopover">
                                @foreach(['😀','😂','😍','👍','🙏','🌾','🌧️','☀️','😢','😡','🎉','❤️'] as $emoji)
                                    <button type="button" class="emoji-option">{{ $emoji }}</button>
                                @endforeach
                            </div>
                            <textarea name="body" id="composeInput" class="compose-input" rows="1" placeholder="Type a message..."></textarea>
                            <button class="compose-send" id="composeSend" type="submit" aria-label="Send">➤</button>
                        </form>
                        <div class="small text-muted mt-1 ps-2">Press Enter to send &middot; Shift + Enter for a new line</div>
                    </div>
                @else
                    <div class="msg-empty text-center text-muted">
                        <h2 class="h5 fw-bold" style="color:var(--msg-ink);">Select or start a conversation</h2>
                        <p class="mb-0">Search for a person above, or pick a thread on the left, to message farmers, MAO staff, or IT support.</p>
                    </div>
                @endif
            </main>
        </div>
    </div>
    <div id="unsendConfirm" class="unsend-confirm" role="alertdialog" aria-modal="true" aria-labelledby="unsendConfirmTitle">
        <div class="unsend-card">
            <div class="unsend-body">
                <div class="unsend-icon" aria-hidden="true">!</div>
                <div>
                    <h2 id="unsendConfirmTitle" class="unsend-title">Unsend this message?</h2>
                    <p class="unsend-text">This will permanently delete your message from this conversation. Attachments sent with it will also be removed.</p>
                </div>
            </div>
            <div class="unsend-actions">
                <button type="button" class="unsend-cancel" data-unsend-cancel>Cancel</button>
                <button type="button" class="unsend-submit" data-unsend-submit>Unsend Message</button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrap = document.querySelector('.msg-wrap');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                ?? document.querySelector('input[name="_token"]')?.value;
            const currentUserId = Number(wrap.dataset.currentUserId);
            const searchUrl = wrap.dataset.searchUrl;
            const openUrl = wrap.dataset.openUrl;
            const pollUrl = wrap.dataset.pollUrl;
            const sendUrl = wrap.dataset.sendUrl;
            const typingUrl = wrap.dataset.typingUrl;
            const unsendUrlBase = wrap.dataset.unsendUrlBase;
            let lastMessageId = Number(wrap.dataset.lastMessageId || 0);

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));

            /* ---------------- Unread/last-message bootstrap for older browsers without dataset support is unnecessary; proceed. */

            /* ---------------- Tabs ---------------- */
            const tabs = document.querySelectorAll('.msg-tab');
            const threadLinks = document.querySelectorAll('.thread-link');
            let activeTabRole = 'all';

            const applyThreadFilters = () => {
                const query = (document.getElementById('threadSearch')?.value || '').trim().toLowerCase();
                threadLinks.forEach((link) => {
                    const matchesTab = activeTabRole === 'all' || link.dataset.role === activeTabRole;
                    const matchesQuery = query === '' || link.dataset.name.includes(query);
                    link.style.display = (matchesTab && matchesQuery) ? '' : 'none';
                });
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    tabs.forEach((t) => t.classList.remove('active'));
                    tab.classList.add('active');
                    activeTabRole = tab.dataset.roleFilter;
                    applyThreadFilters();
                });
            });

            /* ---------------- Search (existing threads + new recipients) ---------------- */
            const searchInput = document.getElementById('threadSearch');
            const searchResults = document.getElementById('searchResults');
            const knownOtherIds = new Set(Array.from(threadLinks)
                .map((link) => link.dataset.otherId)
                .filter(Boolean));

            let searchDebounce = null;

            const renderSearchResults = (recipients) => {
                if (!recipients.length) {
                    searchResults.innerHTML = '<div class="msg-search-empty">No matching people found.</div>';
                    searchResults.classList.add('show');
                    return;
                }

                searchResults.innerHTML = recipients.map((recipient) => `
                    <div class="msg-search-result" data-recipient-id="${recipient.id}">
                        <div class="avatar" style="width:32px;height:32px;font-size:.8rem;">${escapeHtml(recipient.name.charAt(0).toUpperCase())}</div>
                        <div>
                            <div class="fw-bold" style="font-size:.85rem;">${escapeHtml(recipient.name)}</div>
                            <div class="text-muted" style="font-size:.72rem;">${escapeHtml(recipient.role)}</div>
                        </div>
                    </div>
                `).join('');
                searchResults.classList.add('show');

                searchResults.querySelectorAll('[data-recipient-id]').forEach((row) => {
                    row.addEventListener('click', () => openConversationWith(row.dataset.recipientId, row));
                });
            };

            let openingConversation = false;

            const openConversationWith = async (recipientId, rowEl) => {
                if (openingConversation) return;
                openingConversation = true;
                rowEl?.classList.add('is-loading');
                rowEl?.setAttribute('aria-disabled', 'true');

                try {
                    const response = await fetch(openUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ recipient_id: recipientId }),
                    });
                    const data = await response.json();
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                } catch (error) {
                    window.location.href = '{{ route('messages.index') }}';
                    return;
                } finally {
                    openingConversation = false;
                    rowEl?.classList.remove('is-loading');
                    rowEl?.removeAttribute('aria-disabled');
                }
            };

            searchInput?.addEventListener('input', () => {
                applyThreadFilters();

                const query = searchInput.value.trim();
                clearTimeout(searchDebounce);

                if (query === '') {
                    searchResults.classList.remove('show');
                    return;
                }

                searchDebounce = setTimeout(async () => {
                    try {
                        const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await response.json();
                        const notYetMessaged = (data.recipients || []).filter((r) => !knownOtherIds.has(String(r.id)));
                        renderSearchResults(notYetMessaged);
                    } catch (error) {
                        /* silent: search is a progressive enhancement over the visible thread list */
                    }
                }, 250);
            });

            document.addEventListener('click', (event) => {
                if (!searchResults.contains(event.target) && event.target !== searchInput) {
                    searchResults.classList.remove('show');
                }
            });

            /* ---------------- Unsend (works for both server-rendered and dynamically appended messages) ---------------- */
            const unsendModal = document.getElementById('unsendConfirm');
            const unsendCancel = unsendModal?.querySelector('[data-unsend-cancel]');
            const unsendSubmit = unsendModal?.querySelector('[data-unsend-submit]');
            let pendingUnsendForm = null;

            const closeUnsendModal = () => {
                unsendModal?.classList.remove('show');
                pendingUnsendForm = null;
            };

            const bindUnsendForms = (root) => {
                root.querySelectorAll('[data-unsend-form]').forEach((form) => {
                    if (form.dataset.bound === 'true') return;
                    form.dataset.bound = 'true';
                    form.addEventListener('submit', (event) => {
                        if (form.dataset.confirmed === 'true') return;
                        event.preventDefault();
                        pendingUnsendForm = form;
                        unsendModal?.classList.add('show');
                    });
                });
            };

            unsendCancel?.addEventListener('click', closeUnsendModal);
            unsendModal?.addEventListener('click', (event) => {
                if (event.target === unsendModal) closeUnsendModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && unsendModal?.classList.contains('show')) closeUnsendModal();
            });
            unsendSubmit?.addEventListener('click', () => {
                if (!pendingUnsendForm) return;
                pendingUnsendForm.dataset.confirmed = 'true';
                pendingUnsendForm.submit();
            });

            bindUnsendForms(document);

            /* ---------------- Chat window: render, scroll, poll ---------------- */
            const chatWindow = document.getElementById('chatWindow');

            const isNearBottom = () => {
                if (!chatWindow) return true;
                return chatWindow.scrollTop + chatWindow.clientHeight >= chatWindow.scrollHeight - 60;
            };

            const scrollToBottom = (smooth = true) => {
                if (!chatWindow) return;
                chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            };

            if (chatWindow) scrollToBottom(false);

            const renderMessage = (message) => {
                const isMine = Number(message.sender_id) === currentUserId;
                const bodyHtml = message.body ? `<div style="white-space:pre-line;">${escapeHtml(message.body)}</div>` : '';
                const attachmentHtml = message.attachment_url
                    ? `<a class="${isMine ? 'text-white' : ''}" href="${message.attachment_url}" target="_blank" rel="noopener">${escapeHtml(message.attachment_name || 'Attachment')}</a>`
                    : '';
                const checkHtml = isMine
                    ? `<span class="msg-check ${message.read_at ? 'seen' : ''}" data-message-id="${message.id}">${message.read_at ? '✓✓' : '✓'}</span>`
                    : '';
                const unsendHtml = (isMine && unsendUrlBase)
                    ? `<form class="msg-actions" method="POST" action="${unsendUrlBase.replace(/\/0$/, '/' + message.id)}" data-unsend-form>
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="msg-unsend" type="submit">Unsend</button>
                       </form>`
                    : '';

                return `<div class="bubble-row ${isMine ? 'mine' : ''}" data-message-id="${message.id}">
                    <div class="bubble">
                        ${bodyHtml}
                        ${attachmentHtml}
                        <div class="msg-meta">
                            <span>${escapeHtml(message.sender_name)} &middot; ${escapeHtml(message.human_time)}</span>
                            ${checkHtml}
                        </div>
                        ${unsendHtml}
                    </div>
                </div>`;
            };

            const appendMessages = (messages) => {
                if (!messages.length || !chatWindow) return;
                const wasNearBottom = isNearBottom();

                messages.forEach((message) => {
                    if (message.id <= lastMessageId && chatWindow.querySelector(`[data-message-id="${message.id}"]`)) return;
                    chatWindow.insertAdjacentHTML('beforeend', renderMessage(message));
                    lastMessageId = Math.max(lastMessageId, message.id);
                });

                bindUnsendForms(chatWindow);

                if (wasNearBottom) scrollToBottom(true);
            };

            const applyReadReceipts = (receipts) => {
                (receipts || []).forEach((receipt) => {
                    if (!receipt.read_at) return;
                    const check = chatWindow?.querySelector(`.msg-check[data-message-id="${receipt.id}"]`);
                    if (check && !check.classList.contains('seen')) {
                        check.classList.add('seen');
                        check.textContent = '✓✓';
                    }
                });
            };

            const typingIndicator = document.getElementById('typingIndicator');
            const statusDot = document.getElementById('headerStatusDot');
            const statusText = document.getElementById('headerStatusText');

            let pollTimer = null;
            let pollInFlight = false;

            const poll = async () => {
                if (!pollUrl || pollInFlight || document.hidden) return;
                pollInFlight = true;

                try {
                    const response = await fetch(`${pollUrl}?after_id=${lastMessageId}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;
                    const data = await response.json();

                    appendMessages(data.messages || []);
                    applyReadReceipts(data.own_read_receipts);

                    if (typingIndicator) typingIndicator.classList.toggle('show', Boolean(data.other_typing));

                    if (statusDot) statusDot.classList.toggle('online', Boolean(data.other_online));
                    if (statusText) {
                        const roleText = statusText.textContent.split('·')[0].trim();
                        statusText.textContent = `${roleText} · ${data.other_online ? 'Online' : 'Offline'}`;
                    }
                } catch (error) {
                    /* silent: one failed poll should not stop the loop */
                } finally {
                    pollInFlight = false;
                }
            };

            const startPolling = () => {
                if (!pollUrl || pollTimer) return;
                pollTimer = setInterval(poll, 4000);
            };

            const stopPolling = () => {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = null;
            };

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    poll();
                    startPolling();
                }
            });

            startPolling();

            /* ---------------- Compose: attachment, emoji, enter-to-send, AJAX submit, typing ping ---------------- */
            const composeForm = document.getElementById('composeForm');
            const composeInput = document.getElementById('composeInput');
            const composeSend = document.getElementById('composeSend');
            const attachTrigger = document.getElementById('attachTrigger');
            const attachmentInput = document.getElementById('attachmentInput');
            const composeFileChip = document.getElementById('composeFileChip');
            const composeFileName = document.getElementById('composeFileName');
            const composeFileRemove = document.getElementById('composeFileRemove');
            const emojiTrigger = document.getElementById('emojiTrigger');
            const emojiPopover = document.getElementById('emojiPopover');

            const autoResize = () => {
                if (!composeInput) return;
                composeInput.style.height = 'auto';
                composeInput.style.height = `${Math.min(composeInput.scrollHeight, 120)}px`;
            };

            composeInput?.addEventListener('input', autoResize);

            attachTrigger?.addEventListener('click', () => attachmentInput?.click());
            attachmentInput?.addEventListener('change', () => {
                const file = attachmentInput.files?.[0];
                if (file) {
                    composeFileName.textContent = file.name;
                    composeFileChip.style.display = 'inline-flex';
                } else {
                    composeFileChip.style.display = 'none';
                }
            });
            composeFileRemove?.addEventListener('click', () => {
                attachmentInput.value = '';
                composeFileChip.style.display = 'none';
            });

            emojiTrigger?.addEventListener('click', (event) => {
                event.stopPropagation();
                emojiPopover?.classList.toggle('show');
            });
            emojiPopover?.querySelectorAll('.emoji-option').forEach((btn) => {
                btn.addEventListener('click', () => {
                    composeInput.value += btn.textContent;
                    composeInput.focus();
                    autoResize();
                });
            });
            document.addEventListener('click', (event) => {
                if (emojiPopover && !emojiPopover.contains(event.target) && event.target !== emojiTrigger) {
                    emojiPopover.classList.remove('show');
                }
            });

            let lastTypingPing = 0;
            composeInput?.addEventListener('input', () => {
                if (!typingUrl) return;
                const now = Date.now();
                if (now - lastTypingPing < 3000) return;
                lastTypingPing = now;
                fetch(typingUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                }).catch(() => {});
            });

            composeInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    composeForm?.requestSubmit();
                }
            });

            composeForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                const body = composeInput.value.trim();
                const hasAttachment = attachmentInput?.files?.length > 0;
                if (!body && !hasAttachment) return;

                composeSend.disabled = true;
                const formData = new FormData(composeForm);

                try {
                    const response = await fetch(sendUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await response.json();

                    if (response.ok && data.message) {
                        appendMessages([data.message]);
                        scrollToBottom(true);
                    }
                } catch (error) {
                    /* silent: the message stays in the input so the user can retry */
                    composeSend.disabled = false;
                    return;
                }

                composeInput.value = '';
                autoResize();
                attachmentInput.value = '';
                composeFileChip.style.display = 'none';
                composeSend.disabled = false;
                composeInput.focus();
            });
        });
    </script>
</x-app-layout>
