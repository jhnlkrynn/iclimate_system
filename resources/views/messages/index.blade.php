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
        // Farmers get the farmer design system (farmer-messages.css); MAO and IT keep this page's original look.
        $isFarmerPage = $currentUser->role === \App\Models\User::ROLE_FARMER;
        $isStaffPage = ! $isFarmerPage;
    @endphp
    <style>
        .msg-wrap { --msg-ink:var(--palette-ink); --msg-muted:#55645b; --msg-line:var(--sage-100); --msg-green:var(--sage-700); --msg-mint:var(--sage-500); --msg-blue:#2f6f8f; color: var(--msg-ink); }
        .msg-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.75rem 1.85rem; color:var(--msg-ink); background:linear-gradient(145deg,#f6f9f7 0%,var(--sage-100) 100%); border:1px solid var(--sage-100); box-shadow:0 1rem 2.3rem rgb(var(--sage-900-rgb) / .08); }
        .msg-hero::before {
            content:"";
            position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                radial-gradient(ellipse at 88% -10%, rgb(var(--sage-600-rgb) / .16) 0%, transparent 60%);
            pointer-events:none;
        }
        .msg-hero > * { position:relative; z-index:1; }
        .msg-hero h1 { font-family: 'Inter', system-ui, sans-serif; font-weight:400; letter-spacing:-0.01em; color:var(--msg-ink); }
        .msg-eyebrow { display:inline-flex; align-items:center; gap:8px; font-family: 'Inter', system-ui, sans-serif; font-size:.875rem; font-weight:500; text-transform: none; letter-spacing: 0; color:var(--sage-300); margin-bottom:.4rem; }
        .msg-eyebrow::before { content:none; }
        .msg-chip { display:inline-flex; align-items:center; gap:.5rem; border:1px solid var(--msg-line); border-radius:999px; padding:.5rem .85rem; background:rgb(var(--sage-700-rgb) / .06); color:var(--msg-ink); font-family: 'Inter', system-ui, sans-serif; font-size:.875rem; font-weight:500; letter-spacing:.02em; }
        .msg-pulse { width:8px; height:8px; border-radius:999px; background:var(--sage-500); box-shadow:0 0 0 5px rgb(var(--sage-300-rgb) / .2); flex-shrink:0; }
        .msg-shell { display:grid; grid-template-columns:340px minmax(0,1fr); gap:1rem; margin-top:1rem; align-items:stretch; min-height:clamp(620px, calc(100vh - 210px), 860px); }
        .msg-panel { border:1.5px solid var(--ic-sand-dark); border-radius:18px; background:linear-gradient(145deg, var(--ic-paper), #f7fbf8); box-shadow:0 .7rem 1.6rem rgb(var(--sage-900-rgb) / .06); overflow:hidden; display:flex; flex-direction:column; min-height:0; position:relative; }
        .msg-load-state { position:absolute; z-index:4; top:.75rem; right:.75rem; display:flex; align-items:center; gap:.6rem; max-width:min(100% - 1.5rem, 420px); padding:.5rem .7rem; border:1px solid var(--msg-line); border-radius:999px; background:rgba(255,255,255,.96); box-shadow:0 .4rem 1rem rgb(var(--sage-900-rgb) / .12); color:var(--msg-muted); font-size:.875rem; }
        .msg-load-state.is-error { color:#9b3027; border-color:#efc2bd; }
        .msg-load-state button { border:0; border-radius:999px; padding:.2rem .65rem; background:var(--sage-700); color:#fff; font-size:.8rem; font-weight:700; }
        .msg-head { padding:1rem; border-bottom:1px solid var(--msg-line); background:rgb(var(--sage-50-rgb) / .6); flex:0 0 auto; }

        /* ---- Search + tabs ---- */
        .msg-search-box { position:relative; }
        .msg-search-input { width:100%; background:#fff; border:1px solid var(--msg-line); border-radius:999px; padding:.65rem 1rem .65rem 2.3rem; color:var(--msg-ink); font-size:1rem; line-height:1.4; }
        .msg-search-input::placeholder { color:var(--msg-muted); }
        .msg-search-input:focus { outline:none; border-color:var(--sage-500); background:#fff; }
        .msg-search-icon { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); color:var(--msg-muted); pointer-events:none; }
        .msg-search-results { margin-top:.5rem; border:1px solid var(--msg-line); border-radius:12px; background:#fff; max-height:220px; overflow-y:auto; display:none; }
        .msg-search-results.show { display:block; }
        .msg-search-result { display:flex; align-items:center; gap:.6rem; padding:.55rem .7rem; cursor:pointer; border-bottom:1px solid var(--msg-line); }
        .msg-search-result:last-child { border-bottom:none; }
        .msg-search-result:hover { background:rgba(0,0,0,.035); }
        .msg-search-result.is-focused { background:rgb(var(--sage-500-rgb) / .12); }
        .msg-search-result .avatar-wrap { flex:0 0 auto; }
        .msg-search-result .avatar-status { width:9px; height:9px; }
        .msg-search-result.is-loading { opacity:.55; cursor:wait; pointer-events:none; }
        .msg-search-result.is-loading::after { content:""; width:14px; height:14px; margin-left:auto; flex-shrink:0; border:2px solid rgb(var(--sage-700-rgb) / .25); border-top-color:var(--ic-green-500); border-radius:50%; animation:msgSpin .6s linear infinite; }
        .msg-search-loading { display:flex; align-items:center; gap:.5rem; padding:.7rem; color:var(--msg-muted); font-size:.875rem; }
        .msg-search-loading::before { content:""; width:14px; height:14px; flex:0 0 auto; border:2px solid rgb(var(--sage-700-rgb) / .25); border-top-color:var(--ic-green-500); border-radius:50%; animation:msgSpin .6s linear infinite; }
        @keyframes msgSpin { to { transform:rotate(360deg); } }
        .msg-search-empty { padding:.6rem .7rem; color:var(--msg-muted); font-size:.875rem; line-height:1.45; }
        .msg-tabs { display:flex; gap:.4rem; margin-top:.75rem; overflow-x:auto; }
        .msg-tab { flex:0 0 auto; border:1px solid var(--msg-line); background:transparent; color:var(--msg-muted); border-radius:999px; padding:.38rem .86rem; font-size:.875rem; font-weight:700; white-space:nowrap; line-height:1.25; }
        .msg-tab.active { background:var(--sage-500); border-color:var(--sage-500); color:var(--palette-ink); }

        .thread-list { flex:1 1 auto; min-height:0; overflow:auto; }
        .msg-list-section { border-bottom:1px solid var(--msg-line); }
        .msg-section-heading { padding:.7rem 1rem .45rem; color:var(--msg-muted); font-size:.75rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; background:rgb(var(--sage-50-rgb) / .72); position:sticky; top:0; z-index:1; }
        .msg-section-count { min-width:1.5rem; padding:.12rem .4rem; border-radius:999px; background:rgb(var(--sage-500-rgb) / .16); color:var(--msg-green); text-align:center; font-size:.7rem; }
        .msg-group-heading { padding:.5rem 1rem .3rem; color:var(--msg-muted); font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .msg-person-link { display:flex; align-items:center; gap:.65rem; width:100%; padding:.65rem 1rem; border:0; border-bottom:1px solid var(--msg-line); background:transparent; color:inherit; text-align:left; cursor:pointer; }
        .msg-person-link:hover, .msg-person-link.is-focused, .msg-person-link.is-selected { background:rgb(var(--sage-500-rgb) / .11); }
        .msg-person-link.is-loading { opacity:.55; pointer-events:none; }
        .msg-person-meta { min-width:0; flex:1 1 auto; }
        .msg-person-status { color:var(--msg-muted); font-size:.78rem; }
        .msg-person-status.is-online { color:var(--sage-700); }
        .msg-load-more { border:1px solid var(--msg-line); border-radius:999px; padding:.38rem .8rem; background:#fff; color:var(--msg-green); font-size:.82rem; font-weight:700; }
        .msg-load-more:hover { background:rgb(var(--sage-500-rgb) / .1); }
        .msg-highlight { background:#fff0a8; border-radius:2px; padding:0 .05em; }
        .thread-link { display:block; padding:.85rem 1rem; border-bottom:1px solid var(--msg-line); color:inherit; text-decoration:none; border-left:4px solid transparent; transition:background .15s ease, border-color .15s ease; }
        .thread-link.active { background:rgb(var(--sage-500-rgb) / .14); border-left-color:var(--sage-700); }
        .thread-link:hover { background:rgba(0,0,0,.03); }
        .thread-link.unread .thread-name, .thread-link.unread .thread-preview { font-weight:800; color:var(--msg-ink); }

        /* ---- Avatar + presence ---- */
        .avatar-wrap { position:relative; flex:0 0 auto; }
        .avatar { width:40px; height:40px; border-radius:50%; display:grid; place-items:center; background:rgb(var(--sage-500-rgb) / .16); border:1px solid rgb(var(--sage-200-rgb) / .3); color:var(--sage-300); font-family: 'Inter', system-ui, sans-serif; font-weight:700; }
        .avatar-status { position:absolute; right:-1px; bottom:-1px; width:11px; height:11px; border-radius:999px; border:2px solid #fff; background:#c7d1cb; }
        .avatar-status.online { background:var(--sage-500); }

        .thread-row-top { display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; }
        .thread-time { font-family: 'Inter', system-ui, sans-serif; font-size:.75rem; color:var(--msg-muted); flex-shrink:0; line-height:1.35; }
        .thread-preview-row { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
        .unread-badge { flex-shrink:0; min-width:21px; min-height:21px; padding:.1rem .4rem; border-radius:999px; background:#C58B2A; color:#fff; font-family: 'Inter', system-ui, sans-serif; font-size:.75rem; font-weight:800; display:grid; place-items:center; line-height:1; }

        .chat-window { flex:1 1 auto; min-height:0; overflow:auto; padding:1rem; background:rgb(var(--sage-50-rgb) / .4); scroll-behavior:smooth; }
        .bubble-row { display:flex; margin-bottom:.8rem; }
        .bubble-row.mine { justify-content:flex-end; }
        .bubble { max-width:min(680px,82%); border:1px solid var(--msg-line); border-radius:14px; background:#fff; color:var(--msg-ink); padding:.7rem .85rem; }
        .mine .bubble { background:var(--sage-700); border-color:var(--sage-700); color:#fff; }
        .msg-meta { color:var(--msg-muted); font-family: 'Inter', system-ui, sans-serif; font-size:.8rem; letter-spacing:.02em; margin-top:.3rem; display:flex; align-items:center; gap:.35rem; line-height:1.35; }
        .mine .msg-meta { color:rgba(255,255,255,.72); }
        .msg-check { font-size:.78rem; color:rgba(255,255,255,.8); }
        .msg-check.seen { color:var(--sage-300); }
        .msg-actions { display:flex; justify-content:flex-end; margin-top:.5rem; }
        .msg-unsend { border:1px solid rgba(255,255,255,.52); border-radius:999px; background:rgba(255,255,255,.14); color:#fff; font-family: 'Inter', system-ui, sans-serif; font-size:.8rem; font-weight:700; padding:.25rem .6rem; line-height:1.2; }
        .msg-unsend:hover { background:rgba(255,255,255,.24); border-color:rgba(255,255,255,.82); color:#fff; text-decoration:none; }
        .msg-typing { padding:0 1rem .5rem; color:var(--msg-muted); font-family: 'Inter', system-ui, sans-serif; font-size:.875rem; display:none; line-height:1.4; }
        .msg-typing.show { display:block; }

        /* ---- Compose bar ---- */
        .msg-compose-wrap { padding:.85rem 1rem; border-top:1px solid var(--msg-line); background:rgb(var(--sage-50-rgb) / .5); flex:0 0 auto; }
        .msg-compose { display:flex; align-items:flex-end; gap:.4rem; background:#fff; border:1px solid var(--msg-line); border-radius:24px; padding:.4rem .5rem .4rem .7rem; position:relative; }
        .compose-icon-btn { border:0; background:transparent; color:var(--msg-muted); width:34px; height:34px; border-radius:999px; display:grid; place-items:center; flex-shrink:0; font-size:1.05rem; }
        .compose-icon-btn:hover { background:rgba(0,0,0,.05); color:var(--msg-ink); }
        .compose-input { flex:1 1 auto; resize:none; max-height:120px; background:transparent; border:0; color:var(--msg-ink); padding:.55rem .35rem; font-size:1rem; line-height:1.45; }
        .compose-input:focus { outline:none; box-shadow:none; }
        .compose-input::placeholder { color:var(--msg-muted); }
        .compose-send { border:0; background:var(--sage-700); color:#fff; width:38px; height:38px; border-radius:999px; display:grid; place-items:center; flex-shrink:0; font-weight:700; }
        .compose-send:disabled { opacity:.5; }
        .compose-file-chip { display:inline-flex; align-items:center; gap:.4rem; background:rgb(var(--sage-700-rgb) / .06); border:1px solid var(--msg-line); border-radius:999px; padding:.24rem .6rem; font-size:.875rem; margin-bottom:.4rem; line-height:1.25; }
        .compose-file-remove { border:0; background:transparent; color:var(--msg-muted); font-weight:700; }
        .emoji-popover { position:absolute; bottom:calc(100% + 8px); left:0; display:none; grid-template-columns:repeat(6,1fr); gap:.25rem; background:#fff; border:1px solid var(--msg-line); border-radius:14px; padding:.5rem; box-shadow:0 .7rem 1.6rem rgb(var(--sage-900-rgb) / .14); z-index:5; }
        .emoji-popover.show { display:grid; }
        .emoji-option { border:0; background:transparent; font-size:1.15rem; border-radius:8px; padding:.25rem; line-height:1; }
        .emoji-option:hover { background:rgba(0,0,0,.05); }

        .msg-empty { flex:1 1 auto; min-height:420px; display:grid; place-items:center; align-content:center; gap:.5rem; padding:2rem; }
        .unsend-confirm { position:fixed; inset:0; z-index:2300; display:none; place-items:center; padding:1rem; background:rgb(var(--sage-950-rgb) / .48); backdrop-filter:blur(5px); }
        .unsend-confirm.show { display:grid; }
        .unsend-card { width:min(420px,100%); overflow:hidden; border:1px solid rgb(var(--sage-100-rgb) / .9); border-radius:20px; background:linear-gradient(145deg,#fff,#f7fbf8); box-shadow:0 1.4rem 3.4rem rgb(var(--sage-950-rgb) / .34); }
        .unsend-card::before { content:""; display:block; height:7px; background:#B84A4A; }
        .unsend-body { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.85rem; padding:1.05rem; }
        .unsend-icon { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; background:#fff0ea; color:#d85b45; font-weight:900; font-size:1.25rem; }
        .unsend-title { margin:0 0 .3rem; color:var(--msg-ink); font-size:1.08rem; font-weight:900; }
        .unsend-text { margin:0; color:#4a5c52; line-height:1.45; }
        .unsend-actions { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; padding:0 1.05rem 1.05rem; }
        .unsend-cancel, .unsend-submit { border:0; border-radius:999px; padding:.72rem 1rem; font-weight:900; }
        .unsend-cancel { background:var(--sage-50); color:var(--sage-800); }
        .unsend-submit { background:#d85b45; color:#fff; box-shadow:0 .75rem 1.45rem rgba(216,91,69,.22); }
        .msg-wrap .text-muted { color: var(--msg-muted) !important; }
        .msg-wrap .alert-success { background: rgba(82,183,136,.14); border-color: rgba(116,198,157,.35); color: #1a3a2a; }
        .msg-back-link { display:none; align-items:center; gap:.4rem; color:var(--sage-300); font-weight:700; text-decoration:none; font-size:.85rem; margin-bottom:.5rem; }
        .msg-back-link:hover { color:var(--sage-200); }
        @media (max-width:1199.98px) {
            .msg-shell { grid-template-columns:1fr; min-height:auto; }
            .msg-panel { min-height:auto; }
            .thread-list { max-height:360px; flex:none; }
            .chat-window { min-height:440px; max-height:62vh; flex:none; }
        }
        @media (max-width:767.98px) {
            .msg-hero { padding:1rem; }
            .msg-shell { gap:.75rem; }
            .msg-head { padding:.7rem .85rem; }
            .msg-tabs { margin-top:.55rem; }
            .thread-link { padding:.65rem .85rem; }
            .chat-window { min-height:360px; max-height:58vh; padding:.75rem; }
            .thread-list { max-height:calc(100dvh - 230px); }
            .bubble { max-width:92%; overflow-wrap:anywhere; }
            .msg-compose-wrap { padding:.6rem .75rem; }
            .msg-empty { min-height:300px; padding:1.25rem; }
            /* On phones, an open conversation replaces the thread list instead of stacking below it. */
            .msg-shell.has-active aside.msg-panel { display:none; }
            .msg-back-link { display:inline-flex; }
            /* On phones, hide the "select a conversation" placeholder while browsing the list -- it adds scroll depth without any data or functionality of its own. */
            .msg-shell:not(.has-active) main.msg-panel { display:none; }
        }
        @media (max-width:420px) {
            .msg-hero { padding:.85rem; border-radius:18px; }
            .msg-shell { gap:.6rem; }
            .bubble { max-width:100%; }
            .unsend-actions { grid-template-columns:1fr; }
            .emoji-popover { grid-template-columns:repeat(5,1fr); }
        }
    </style>

    {{-- MAO/IT – Kaye: ?embed=1 is this page inside the Farmer Profiles [Mensahe] pop-up: the conversation only. --}}
    @if (request()->boolean('embed'))
        <style>
            .sidebar-fixed, .topbar, #mobileSidebar, #icAiWidget, .ic-ai-launcher, .mfd-header, .msg-hero, .fad-hero, .msg-shell > aside, .msg-back-link, .sidebar-backdrop { display: none !important; }
            .app-main { margin-left: 0 !important; }
            .page-shell, .msg-wrap.mfd { padding: 0 !important; }
            .msg-shell { grid-template-columns: minmax(0, 1fr) !important; min-height: 100vh !important; margin: 0 !important; }
        </style>
    @endif
    @if($isFarmerPage)
    {{-- #unsendConfirm stays outside .fad: .fad is a size container and would trap the fixed-position dialog. --}}
    <div class="fad fmsg">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Farmer Messages</span>
                <h1>Messages</h1>
                <p>Send private messages to the Municipal Agriculture Office and other farmers, and review your conversations.</p>
            </div>
            <div class="fad-hero-actions">
                <span class="fmsg-private">@include('dashboards.mao.icon', ['name' => 'check'])<span>Private conversations</span></span>
                <a href="{{ route('community-feed.index') }}" class="fad-btn is-light">Community Feed</a>
            </div>
        </header>
    @endif
    {{-- Staff accounts (IT and MAO) get the IT dashboard design system: .mfd / .itd parts plus it-pages.css. --}}
    <div @class(['msg-wrap', 'mfd itd itp' => $isStaffPage])
         data-current-user-id="{{ $currentUser->id }}"
         data-search-url="{{ route('messages.search') }}"
         data-people-url="{{ route('messages.people') }}"
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
        @if($isStaffPage)
        <x-it.page-header kicker="Private Conversations" title="Messages" lede="Send private messages to MAO staff and farmers, and review your conversations.">
            <span class="mfd-pill is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>Private Channel</span></span>
            <div class="itd-header-actions">
                <a href="{{ route('community-feed.index') }}" class="mfd-btn is-quiet">@include('dashboards.mao.icon', ['name' => 'farmers'])Announcements</a>
            </div>
        </x-it.page-header>
        @endif

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="msg-shell {{ $activeConversation ? 'has-active' : '' }}">
            <aside class="msg-panel">
                <div class="msg-head">
                    <div class="msg-search-box">
                        <span class="msg-search-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="threadSearch" class="msg-search-input" placeholder="Search people or conversations..." autocomplete="off">
                    </div>
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
                    <section class="msg-list-section" data-recent-section>
                        <div class="msg-section-heading">{{ __('messages.recent_conversations') }}</div>
                        <div data-recent-list>
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
                           data-search="{{ str(($other?->name ?? '').' '.($other?->role ?? '').' '.($other?->registeredBarangay() ?? ''))->lower() }}"
                                   data-other-id="{{ $other?->id }}">
                                    <div class="d-flex gap-2 align-items-start">
                                        <div class="avatar-wrap">
                                            <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                            <span class="avatar-status {{ $other?->isOnline() ? 'online' : '' }}"></span>
                                        </div>
                                        <div class="flex-grow-1" style="min-width:0;">
                                            <div class="thread-row-top">
                                                <div class="fw-bold thread-name text-truncate" data-display-name="{{ $other?->name ?? 'User' }}">{{ $other?->name ?? 'User' }}</div>
                                                <div class="thread-time">{{ $latest?->humanTimestamp() }}</div>
                                            </div>
                                            <div class="small text-muted">{{ $other?->role ?? 'User' }}</div>
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
                                <div class="p-3 text-muted msg-list-empty">{{ __('messages.no_conversations') }}</div>
                            @endforelse
                        </div>
                        <div class="p-3 text-muted msg-list-empty d-none" data-recent-empty>No matching conversations.</div>
                    </section>
                    <section class="msg-list-section" data-people-section>
                        <div class="msg-section-heading d-flex justify-content-between align-items-center">
                            <span>{{ __('messages.all_people') }}</span><span class="msg-section-count" data-people-count></span>
                        </div>
                        <div data-people-groups>
                            <div class="msg-search-loading">Loading people...</div>
                        </div>
                        <div class="p-3 text-muted msg-list-empty d-none" data-people-empty></div>
                        <div class="p-2 text-center">
                            <button type="button" class="msg-load-more d-none" data-people-load-more>{{ __('messages.load_more') }}</button>
                        </div>
                    </section>
                </div>
                @if(($conversationPaginator ?? null)?->hasPages())
                    <div class="border-top p-2">
                        {{ $conversationPaginator->links() }}
                    </div>
                @endif
            </aside>

            <main class="msg-panel" id="conversationPanel">
                @if($activeConversation)
                    @php($other = $activeConversation->otherParticipant($currentUser))
                    <div class="msg-head">
                        <a class="msg-back-link" href="{{ route('messages.index') }}">&larr; Back to Messages</a>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="avatar-wrap">
                                <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                <span class="avatar-status {{ $other?->isOnline() ? 'online' : '' }}" id="headerStatusDot"></span>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold mb-0">{{ $other?->name ?? 'Conversation' }}</h2>
                                <div class="small text-muted" id="headerStatusText">{{ $other?->role ?? 'User' }} &middot; {{ $other?->isOnline() ? 'Online' : 'Offline' }}</div>
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
                                        <a class="{{ $message->sender_id === $currentUser->id ? 'text-white' : '' }}" href="{{ route('messages.attachment', [$message->conversation_id, $message]) }}" target="_blank" rel="noopener">{{ $message->attachment_name }}</a>
                                    @endif
                                    <div class="msg-meta">
                                        <span>{{ $message->sender?->name ?? 'User' }} &middot; {{ $message->humanTimestamp() }}</span>
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
                        <div class="small mt-1 ps-2" id="composeStatus" role="status" aria-live="polite"></div>
                        <div class="small text-muted mt-1 ps-2">Press Enter to send &middot; Shift + Enter for a new line</div>
                    </div>
                @else
                    <div class="msg-empty text-center text-muted">
                        <h2 class="h5 fw-bold" style="color:var(--msg-ink);">{{ $isFarmerPage ? 'Select a conversation' : 'Select or start a conversation' }}</h2>
                        <p class="mb-0">Search for a person above, or pick a thread on the left, to message farmers, MAO staff, or IT support.</p>
                    </div>
                @endif
            </main>
        </div>
    </div>
    @if($isFarmerPage)
    </div>
    @endif
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
            if (!wrap) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                ?? document.querySelector('input[name="_token"]')?.value;
            const currentUserId = Number(wrap.dataset.currentUserId);
            const searchUrl = wrap.dataset.searchUrl;
            const openUrl = wrap.dataset.openUrl;
            let pollUrl = wrap.dataset.pollUrl;
            let sendUrl = wrap.dataset.sendUrl;
            let typingUrl = wrap.dataset.typingUrl;
            let unsendUrlBase = wrap.dataset.unsendUrlBase;
            let lastMessageId = Number(wrap.dataset.lastMessageId || 0);
            let activeConversationId = Number(wrap.dataset.conversationId || 0);
            let navigationSequence = 0;
            let navigationController = null;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));

            /* ---------------- Unread/last-message bootstrap for older browsers without dataset support is unnecessary; proceed. */

            /* ---------------- Tabs ---------------- */
            const tabs = document.querySelectorAll('.msg-tab');
            const threadLinks = document.querySelectorAll('.thread-link');
            let activeTabRole = 'all';

            const isConversationPayload = (data) => Boolean(
                data
                && Number(data.conversation_id) > 0
                && data.other
                && Array.isArray(data.messages)
                && data.messages.every((message) => message && typeof message === 'object' && message.id !== undefined)
                && typeof data.poll_url === 'string'
                && typeof data.send_url === 'string'
                && typeof data.typing_url === 'string'
                && typeof data.unsend_url_base === 'string'
            );

            const loadConversationUrl = async (url, signal) => {
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal,
                });
                const contentType = response.headers.get('content-type') || '';
                if (!response.ok) throw new Error(`Conversation request failed (${response.status})`);
                if (!contentType.includes('application/json')) throw new Error('Conversation response was not JSON');
                const data = await response.json();
                if (!isConversationPayload(data)) throw new Error('Conversation response was incomplete');
                return data;
            };

            const removeConversationLoadState = () => {
                document.querySelector('[data-conversation-load-state]')?.remove();
            };

            const showConversationLoadState = (message, { error = false, retry = null, fallbackUrl = null } = {}) => {
                removeConversationLoadState();
                const state = document.createElement('div');
                state.dataset.conversationLoadState = 'true';
                state.className = `msg-load-state${error ? ' is-error' : ''}`;
                state.innerHTML = `<span>${escapeHtml(message)}</span>${retry ? '<button type="button">Retry</button>' : ''}${fallbackUrl ? `<a href="${escapeHtml(fallbackUrl)}">Open normally</a>` : ''}`;
                if (retry) state.querySelector('button')?.addEventListener('click', retry, { once: true });
                conversationPanel?.append(state);
            };

            const applyThreadFilters = () => {
                const query = (document.getElementById('threadSearch')?.value || '').trim().toLowerCase();
                threadLinks.forEach((link) => {
                    const matchesTab = activeTabRole === 'all' || link.dataset.role === activeTabRole;
                    const matchesQuery = query === '' || (link.dataset.search || link.dataset.name).includes(query);
                    link.style.display = (matchesTab && matchesQuery) ? '' : 'none';
                });
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    tabs.forEach((t) => t.classList.remove('active'));
                    tab.classList.add('active');
                    activeTabRole = tab.dataset.roleFilter;
                    filterRecent((document.getElementById('threadSearch')?.value || '').trim().toLowerCase());
                });
            });

            threadLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (link.classList.contains('active')) return;
                    void navigateToConversation(link.href, link);
                });
            });

            /* ---------------- Search (existing threads + new recipients) ---------------- */
            const searchInput = document.getElementById('threadSearch');
            const peopleUrl = wrap.dataset.peopleUrl;
            const peopleGroups = document.querySelector('[data-people-groups]');
            const peopleEmpty = document.querySelector('[data-people-empty]');
            const peopleCount = document.querySelector('[data-people-count]');
            const peopleLoadMore = document.querySelector('[data-people-load-more]');
            const recentEmpty = document.querySelector('[data-recent-empty]');
            let searchDebounce = null;
            let peopleAbortController = null;
            let peopleRequestSequence = 0;
            let peoplePage = 0;
            let peopleHasMore = false;
            let peopleRows = [];
            let focusedPerson = -1;

            const highlight = (value, query) => {
                const safe = escapeHtml(value);
                if (!query) return safe;
                const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return safe.replace(new RegExp(`(${escaped})`, 'ig'), '<mark class="msg-highlight">$1</mark>');
            };

            const personLabel = (person) => person.role_label === 'Farmer' && person.barangay
                ? `Farmer · ${person.barangay}`
                : (person.role_label || person.role || 'User');

            const renderPeople = (people, query, append = false, total = null) => {
                if (!append) peopleRows = [];
                peopleRows = append ? peopleRows.concat(people) : people;
                const groups = { 'MAO Personnel': [], 'IT Support': [], Farmers: [] };
                peopleRows.forEach((person) => {
                    const group = person.role_label === 'Farmer' ? 'Farmers' : (person.role_label === 'IT Support' ? 'IT Support' : 'MAO Personnel');
                    groups[group].push(person);
                });
                peopleGroups.innerHTML = Object.entries(groups).filter(([, rows]) => rows.length).map(([label, rows]) => `
                    <div class="msg-people-group" data-people-group="${escapeHtml(label)}">
                        <div class="msg-group-heading">${escapeHtml(label)} <span>${rows.length}</span></div>
                        ${rows.map((person) => `
                            <button type="button" class="msg-person-link" data-recipient-id="${person.id}" data-person-name="${escapeHtml(person.name)}">
                                <div class="avatar-wrap"><div class="avatar">${escapeHtml(person.initial || 'U')}</div><span class="avatar-status ${person.online ? 'online' : ''}"></span></div>
                                <div class="msg-person-meta"><div class="fw-bold text-truncate">${highlight(person.name, query)}</div><div class="msg-person-status ${person.online ? 'is-online' : ''}">${escapeHtml(personLabel(person))} · ${person.online ? 'Online' : 'Offline'}</div></div>
                            </button>`).join('')}
                    </div>`).join('');
                peopleCount.textContent = (total ?? peopleRows.length) || '';
                peopleEmpty.classList.toggle('d-none', peopleRows.length > 0);
                if (!peopleRows.length) peopleEmpty.textContent = query ? `No people found for "${query}"` : 'No people found.';
                peopleLoadMore.classList.toggle('d-none', !peopleHasMore);
                focusedPerson = -1;
            };

            const filterRecent = (query) => {
                let visible = 0;
                threadLinks.forEach((link) => {
                    const matchesTab = activeTabRole === 'all' || link.dataset.role === activeTabRole;
                    const matchesQuery = !query || (link.dataset.search || link.dataset.name || '').includes(query);
                    link.style.display = matchesTab && matchesQuery ? '' : 'none';
                    const name = link.querySelector('[data-display-name]');
                    if (name) name.innerHTML = highlight(name.dataset.displayName || name.textContent, query);
                    if (matchesTab && matchesQuery) visible += 1;
                });
                recentEmpty.classList.toggle('d-none', visible !== 0 || threadLinks.length === 0);
            };

            const fetchPeople = async (query, page = 1, append = false) => {
                peopleAbortController?.abort();
                const controller = new AbortController();
                peopleAbortController = controller;
                const requestId = ++peopleRequestSequence;
                if (!append) peopleGroups.innerHTML = '<div class="msg-search-loading">Loading people...</div>';
                try {
                    const response = await fetch(`${peopleUrl}?q=${encodeURIComponent(query)}&page=${page}&per_page=30`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: controller.signal });
                    if (!response.ok) throw new Error(`People request failed (${response.status})`);
                    const data = await response.json();
                    if (requestId !== peopleRequestSequence || controller.signal.aborted) return;
                    peoplePage = data.meta?.current_page || page;
                    peopleHasMore = Boolean(data.meta?.has_more);
                    renderPeople(Array.isArray(data.people) ? data.people : [], query, append, data.meta?.total ?? null);
                } catch (error) {
                    if (controller.signal.aborted || requestId !== peopleRequestSequence) return;
                    peopleGroups.innerHTML = '<div class="msg-search-empty">Unable to load people. Try again.</div>';
                    peopleLoadMore.classList.add('d-none');
                }
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
                    const contentType = response.headers.get('content-type') || '';
                    if (!response.ok || !contentType.includes('application/json')) throw new Error(`Conversation start failed (${response.status})`);
                    const data = await response.json();
                    if (data.redirect) {
                        await navigateToConversation(data.redirect);
                    } else {
                        throw new Error('Conversation redirect was missing');
                    }
                } catch (error) {
                    showConversationLoadState('Unable to start conversation.', {
                        error: true,
                        retry: () => void openConversationWith(recipientId, rowEl),
                        fallbackUrl: '{{ route('messages.index') }}',
                    });
                    return;
                } finally {
                    openingConversation = false;
                    rowEl?.classList.remove('is-loading');
                    rowEl?.removeAttribute('aria-disabled');
                }
            };

            peopleGroups?.addEventListener('click', (event) => {
                const row = event.target.closest('[data-recipient-id]');
                if (row) void openConversationWith(row.dataset.recipientId, row);
            });

            searchInput?.setAttribute('role', 'combobox');
            searchInput?.setAttribute('aria-controls', 'threadList');

            searchInput?.addEventListener('input', () => {
                const query = searchInput.value.trim();
                filterRecent(query.toLowerCase());
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(() => void fetchPeople(query), 200);
            });

            searchInput?.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const rows = Array.from(peopleGroups?.querySelectorAll('[data-recipient-id]') || []);
                    if (!rows.length) return;
                    focusedPerson = (focusedPerson + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
                    rows.forEach((row, index) => row.classList.toggle('is-focused', index === focusedPerson));
                    rows[focusedPerson].scrollIntoView({ block: 'nearest' });
                } else if (event.key === 'Enter') {
                    const row = peopleGroups?.querySelectorAll('[data-recipient-id]')[focusedPerson];
                    if (row) {
                        event.preventDefault();
                        void openConversationWith(row.dataset.recipientId, row);
                    }
                } else if (event.key === 'Escape') {
                    searchInput.value = '';
                    filterRecent('');
                    clearTimeout(searchDebounce);
                    void fetchPeople('');
                }
            });

            peopleLoadMore?.addEventListener('click', () => {
                const query = searchInput.value.trim();
                if (peopleHasMore) void fetchPeople(query, peoplePage + 1, true);
            });
            filterRecent('');
            void fetchPeople('');

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
            unsendSubmit?.addEventListener('click', async () => {
                if (!pendingUnsendForm) return;
                const form = pendingUnsendForm;
                const bubbleRow = form.closest('[data-message-id]');
                try {
                    const response = await fetch(form.action, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    });
                    if (!response.ok) throw new Error('Unable to unsend message');
                    bubbleRow?.remove();
                    closeUnsendModal();
                } catch (error) {
                    form.querySelector('.msg-unsend')?.setAttribute('aria-label', 'Unsend failed; try again');
                }
            });

            bindUnsendForms(document);

            /* ---------------- Chat window: render, scroll, poll ---------------- */
            let conversationPanel = document.getElementById('conversationPanel');
            let chatWindow = document.getElementById('chatWindow');

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
                const unsendHtml = (isMine && !message.pending && !message.failed && unsendUrlBase)
                    ? `<form class="msg-actions" method="POST" action="${unsendUrlBase.replace(/\/0$/, '/' + message.id)}" data-unsend-form>
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="msg-unsend" type="submit">Unsend</button>
                       </form>`
                    : '';

                const pendingClass = message.pending ? ' pending' : (message.failed ? ' failed' : '');
                const pendingStatus = message.pending ? '<span class="msg-send-state">Sending...</span>' : (message.failed ? '<span class="msg-send-state">Failed to send — press Send to retry</span>' : '');
                return `<div class="bubble-row ${isMine ? 'mine' : ''}${pendingClass}" data-message-id="${message.id}">
                    <div class="bubble">
                        ${bodyHtml}
                        ${attachmentHtml}
                        <div class="msg-meta">
                            <span>${escapeHtml(message.sender_name)} &middot; ${escapeHtml(message.human_time)}</span>
                            ${checkHtml}${pendingStatus}
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

            let typingIndicator = document.getElementById('typingIndicator');
            let statusDot = document.getElementById('headerStatusDot');
            let statusText = document.getElementById('headerStatusText');

            let pollTimer = null;
            let pollInFlight = false;
            let pollGeneration = 0;
            let pollAbortController = null;
            const POLL_INTERVAL_MS = 8000;

            const poll = async () => {
                if (!pollUrl || pollInFlight || document.hidden) return;
                pollInFlight = true;
                const requestGeneration = pollGeneration;
                const requestConversationId = activeConversationId;
                const requestPollUrl = pollUrl;
                const controller = new AbortController();
                pollAbortController = controller;

                try {
                    const response = await fetch(`${requestPollUrl}?after_id=${lastMessageId}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        loader: false, // background polling
                        signal: controller.signal,
                    });
                    if (!response.ok) return;
                    const data = await response.json();

                    // A response for an older thread must never mutate the newly selected thread.
                    if (requestGeneration !== pollGeneration || requestConversationId !== activeConversationId || requestPollUrl !== pollUrl) return;

                    appendMessages(data.messages || []);
                    applyReadReceipts(data.own_read_receipts);

                    if (typingIndicator) typingIndicator.classList.toggle('show', Boolean(data.other_typing));

                    if (statusDot) statusDot.classList.toggle('online', Boolean(data.other_online));
                    if (statusText) {
                        const roleText = statusText.textContent.split('·')[0].trim();
                        statusText.textContent = `${roleText} · ${data.other_online ? 'Online' : 'Offline'}`;
                    }
                } catch (error) {
                    /* Silent: one failed poll should not stop the loop. Aborted polls are expected on navigation. */
                } finally {
                    if (pollAbortController === controller) pollAbortController = null;
                    if (requestGeneration === pollGeneration) pollInFlight = false;
                }
            };

            const startPolling = () => {
                if (!pollUrl || pollTimer || document.hidden) return;
                pollTimer = setInterval(poll, POLL_INTERVAL_MS);
            };

            const stopPolling = () => {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = null;
                pollAbortController?.abort();
                pollAbortController = null;
                pollGeneration += 1;
                pollInFlight = false;
            };

            const conversationMarkup = (data) => {
                const other = data.other || {};
                const online = Boolean(other.online);
                return `
                    <div class="msg-head">
                        <a class="msg-back-link" href="{{ route('messages.index') }}">&larr; Back to Messages</a>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="avatar-wrap"><div class="avatar">${escapeHtml((other.name || 'U').charAt(0).toUpperCase())}</div><span class="avatar-status ${online ? 'online' : ''}" id="headerStatusDot"></span></div>
                            <div><h2 class="h5 fw-bold mb-0">${escapeHtml(other.name || 'Conversation')}</h2><div class="small text-muted" id="headerStatusText">${escapeHtml(other.role || 'User')} &middot; ${online ? 'Online' : 'Offline'}</div></div>
                        </div>
                    </div>
                    <div class="chat-window" id="chatWindow">${(data.messages || []).map(renderMessage).join('')}</div>
                    <div class="msg-typing" id="typingIndicator">${escapeHtml(other.name || 'They')} is typing...</div>
                    <div class="msg-compose-wrap">
                        <div id="composeFileChip" class="compose-file-chip" style="display:none;"><span id="composeFileName"></span><button type="button" class="compose-file-remove" id="composeFileRemove" aria-label="Remove attachment">&times;</button></div>
                        <form class="msg-compose" id="composeForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <input type="file" name="attachment" id="attachmentInput" class="d-none">
                            <button type="button" class="compose-icon-btn" id="attachTrigger" title="Attach a file" aria-label="Attach a file">📎</button>
                            <button type="button" class="compose-icon-btn" id="emojiTrigger" title="Emoji" aria-label="Emoji">🙂</button>
                            <div class="emoji-popover" id="emojiPopover">${['😀','😂','😍','👍','🙏','🌾','🌧️','☀️','😢','😡','🎉','❤️'].map((emoji) => `<button type="button" class="emoji-option">${emoji}</button>`).join('')}</div>
                            <textarea name="body" id="composeInput" class="compose-input" rows="1" placeholder="Type a message..."></textarea>
                            <button class="compose-send" id="composeSend" type="submit" aria-label="Send">➤</button>
                        </form>
                        <div class="small mt-1 ps-2" id="composeStatus" role="status" aria-live="polite"></div>
                        <div class="small text-muted mt-1 ps-2">Press Enter to send &middot; Shift + Enter for a new line</div>
                    </div>`;
            };

            const refreshConversationElements = () => {
                conversationPanel = document.getElementById('conversationPanel');
                chatWindow = document.getElementById('chatWindow');
                typingIndicator = document.getElementById('typingIndicator');
                statusDot = document.getElementById('headerStatusDot');
                statusText = document.getElementById('headerStatusText');
            };

            const commitConversationData = (data, url, activeLink = null) => {
                stopPolling();
                activeConversationId = Number(data.conversation_id);
                pollUrl = data.poll_url;
                sendUrl = data.send_url;
                typingUrl = data.typing_url;
                unsendUrlBase = data.unsend_url_base;
                lastMessageId = Number(data.last_message_id || 0);
                wrap.dataset.pollUrl = pollUrl;
                wrap.dataset.sendUrl = sendUrl;
                wrap.dataset.typingUrl = typingUrl;
                wrap.dataset.unsendUrlBase = unsendUrlBase;
                document.querySelectorAll('.thread-link').forEach((link) => link.classList.toggle('active', link === activeLink));
                document.querySelector('.msg-shell')?.classList.add('has-active');
                const nextPanel = document.createElement('template');
                nextPanel.innerHTML = conversationMarkup(data);
                if (!nextPanel.content.querySelector('#chatWindow, .msg-empty')) {
                    throw new Error('Conversation markup was incomplete');
                }
                conversationPanel.replaceChildren(...nextPanel.content.childNodes);
                refreshConversationElements();
                bindUnsendForms(conversationPanel);
                bindCompose();
                scrollToBottom(false);
                startPolling();
                removeConversationLoadState();
                return url;
            };

            const navigateToConversation = async (url, activeLink = null, { historyMode = 'push' } = {}) => {
                const requestId = ++navigationSequence;
                navigationController?.abort();
                const controller = new AbortController();
                navigationController = controller;
                activeLink?.classList.add('is-loading');
                stopPolling();
                showConversationLoadState('Loading conversation...');

                try {
                    const data = await loadConversationUrl(url, controller.signal);
                    if (requestId !== navigationSequence || controller.signal.aborted) return false;

                    commitConversationData(data, url, activeLink);
                    if (historyMode === 'push' && window.location.href !== url) {
                        window.history.pushState({ messagesConversationId: Number(data.conversation_id) }, '', url);
                    }
                    return true;
                } catch (error) {
                    if (controller.signal.aborted || requestId !== navigationSequence) return false;
                    showConversationLoadState('Unable to load conversation.', {
                        error: true,
                        retry: () => void navigateToConversation(url, activeLink, { historyMode: 'none' }),
                        fallbackUrl: url,
                    });
                    startPolling();
                    return false;
                } finally {
                    if (requestId === navigationSequence) {
                        navigationController = null;
                        activeLink?.classList.remove('is-loading');
                    }
                }
            };

            window.addEventListener('popstate', () => {
                const match = window.location.pathname.match(/\/messages\/(\d+)\/?$/);
                if (!match) {
                    window.location.reload();
                    return;
                }

                const activeLink = Array.from(document.querySelectorAll('.thread-link'))
                    .find((link) => link.href === window.location.href || link.href.replace(/\/$/, '') === window.location.href.replace(/\/$/, ''));
                void navigateToConversation(window.location.href, activeLink, { historyMode: 'none' });
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    void poll();
                    startPolling();
                }
            });

            window.addEventListener('pagehide', stopPolling, { once: true });

            startPolling();

            /* ---------------- Compose: attachment, emoji, enter-to-send, AJAX submit, typing ping ---------------- */
            let composeForm;
            let composeInput;
            let composeSend;
            let attachTrigger;
            let attachmentInput;
            let composeFileChip;
            let composeFileName;
            let composeFileRemove;
            let emojiTrigger;
            let emojiPopover;

            const bindCompose = () => {
                composeForm = document.getElementById('composeForm');
                composeInput = document.getElementById('composeInput');
                composeSend = document.getElementById('composeSend');
                attachTrigger = document.getElementById('attachTrigger');
                attachmentInput = document.getElementById('attachmentInput');
                composeFileChip = document.getElementById('composeFileChip');
                composeFileName = document.getElementById('composeFileName');
                composeFileRemove = document.getElementById('composeFileRemove');
                emojiTrigger = document.getElementById('emojiTrigger');
                emojiPopover = document.getElementById('emojiPopover');
                if (!composeForm || composeForm.dataset.bound === 'true') return;
                composeForm.dataset.bound = 'true';

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

                let lastTypingPing = 0;
                composeInput?.addEventListener('input', () => {
                    if (!typingUrl) return;
                    const now = Date.now();
                    if (now - lastTypingPing < 3000) return;
                    lastTypingPing = now;
                    fetch(typingUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } }).catch(() => {});
                });

                composeInput?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        composeForm?.requestSubmit();
                    }
                });

                composeForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const body = composeInput.value.trim();
                    const hasAttachment = attachmentInput?.files?.length > 0;
                    if (!body && !hasAttachment) return;

                    const clientMessageId = composeForm.dataset.clientMessageId || (window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
                    composeForm.dataset.clientMessageId = clientMessageId;
                    const pendingId = `pending-${clientMessageId}`;
                    const status = document.getElementById('composeStatus');
                    composeSend.disabled = true;
                    if (status) { status.textContent = 'Sending...'; status.className = 'small mt-1 ps-2 text-muted'; }
                    chatWindow?.insertAdjacentHTML('beforeend', renderMessage({ id: pendingId, sender_id: currentUserId, sender_name: 'You', body, attachment_name: attachmentInput?.files?.[0]?.name, pending: true }));
                    scrollToBottom(true);

                    const formData = new FormData(composeForm);
                    formData.append('client_message_id', clientMessageId);
                    try {
                        const response = await fetch(sendUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.message) throw new Error(data.message || 'Unable to send message');
                        chatWindow?.querySelector(`[data-message-id="${pendingId}"]`)?.replaceWith(document.createRange().createContextualFragment(renderMessage(data.message)));
                        lastMessageId = Math.max(lastMessageId, Number(data.message.id || 0));
                        composeInput.value = '';
                        composeForm.dataset.clientMessageId = '';
                        autoResize();
                        attachmentInput.value = '';
                        composeFileChip.style.display = 'none';
                        if (status) status.textContent = '';
                        composeInput.focus();
                    } catch (error) {
                        const pending = chatWindow?.querySelector(`[data-message-id="${pendingId}"]`);
                        pending?.classList.remove('pending');
                        pending?.classList.add('failed');
                        pending?.querySelector('.msg-send-state')?.replaceChildren(document.createTextNode('Failed to send — press Send to retry'));
                        if (status) { status.textContent = 'Failed to send — your message is still here. Press Send to retry.'; status.className = 'small mt-1 ps-2 text-danger'; }
                    } finally {
                        composeSend.disabled = false;
                    }
                });
            };

            bindCompose();
        });
    </script>
</x-app-layout>
