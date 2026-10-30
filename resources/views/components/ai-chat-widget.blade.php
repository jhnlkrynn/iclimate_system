@php
    $widgetChats = collect();

    // Greeting uses only the account's first name; nothing else about the account is shown.
    $icAiFirstName = \Illuminate\Support\Str::of((string) auth()->user()?->name)->squish()->before(' ')->toString();
    $icAiGreeting = $icAiFirstName !== '' ? 'Hi, '.$icAiFirstName.'! How can I help you today?' : 'Hi! How can I help you today?';
    $icAiFarmerLanguage = auth()->user()?->role === \App\Models\User::ROLE_FARMER;

    $icAiChips = match (auth()->user()->role) {
        \App\Models\User::ROLE_MAO => [
            'Which barangay has the highest yield?',
            'Show a production summary for this season',
            'How many active planting advisories are there?',
            'Latest announcement',
        ],
        \App\Models\User::ROLE_IT_EXPERT => [
            'How many users are registered?',
            'Is the database connected?',
            'Is the farming AI API online?',
            'Any recent errors in the system?',
        ],
        default => [
            'Will it rain this week?',
            'What is my expected rice yield?',
            'When should I plant?',
            'My rice leaves are turning yellow',
        ],
    };

    // Static label/unit lookup for the "Why this yield estimate" panel,
    // keyed by the deployed model column name so JS (for messages sent
    // live without a page reload) doesn't have to duplicate
    // FeatureCatalog's content — only its classify() thresholds, which
    // are small enough to mirror directly (see classifyFeature() below;
    // keep both in sync with FeatureCatalog::classify()).
    $icAiFeatureCatalog = collect(\App\Services\Prediction\FeatureCatalog::forSchema(\App\Services\Prediction\FeatureCatalog::SCHEMA_DEPLOYED))
        ->mapWithKeys(fn (array $feature, string $key): array => [
            $feature['model_keys']['deployed'] => ['key' => $key, 'label' => $feature['label'], 'unit' => $feature['unit']],
        ]);
@endphp

<style>
    .ic-ai-widget {
        --ai-ink: var(--sage-800);
        --ai-muted: #5f7569;
        --ai-line: var(--sage-100);
        --ai-sage: var(--sage-50);
        --ai-panel: #fbfdf9;
        --ai-green: var(--sage-700);
        --ai-primary: var(--sage-800);
        position: fixed;
        right: 24px;
        bottom: 24px;
        /* Above the sticky top bar and dashboard cards, below modals and the side drawers. */
        z-index: 1040;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .ic-ai-toggle {
        width: 62px;
        height: 62px;
        border: 0;
        border-radius: 999px;
        display: grid;
        place-items: center;
        color: #fff;
        background: #fff;
        border: 2px solid var(--sage-700);
        box-shadow: 0 18px 34px rgb(var(--sage-950-rgb) / .28);
        padding: 0;
        overflow: hidden;
    }

    .ic-ai-toggle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: 999px;
    }

    .ic-ai-widget.open .ic-ai-toggle {
        display: none;
    }

    .ic-ai-panel {
        position: absolute;
        right: 0;
        bottom: 0;
        width: min(400px, calc(100vw - 32px));
        height: min(560px, calc(100vh - 48px));
        height: min(560px, calc(100dvh - 48px));
        border: 1px solid var(--ai-line);
        border-radius: 18px;
        overflow: hidden;
        background: var(--ai-panel);
        box-shadow: 0 24px 58px rgb(var(--sage-950-rgb) / .26);
        display: none;
        /* Header / messages (with the typing dots) / suggestions / input. The one column is capped at the panel width
           so long text wraps or truncates instead of widening the panel; only the message list grows and scrolls. */
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: auto minmax(0, 1fr) auto auto;
    }

    .ic-ai-widget.open .ic-ai-panel {
        display: grid;
        transform-origin: bottom right;
        animation: ic-ai-panel-in 320ms cubic-bezier(.22, 1, .36, 1) both;
    }

    @keyframes ic-ai-panel-in {
        from { opacity: 0; transform: translateY(14px) scale(.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .ic-ai-toggle {
        transition: transform 200ms ease, box-shadow 200ms ease;
    }

    .ic-ai-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 22px 38px rgb(var(--sage-950-rgb) / .32);
    }

    .ic-ai-msg {
        animation: ic-ai-msg-in 260ms ease-out both;
    }

    @keyframes ic-ai-msg-in {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-ai-widget.open .ic-ai-panel,
        .ic-ai-msg {
            animation: none;
        }

        .ic-ai-toggle,
        .ic-ai-toggle:hover {
            transition: none;
            transform: none;
        }
    }

    /* One compact row: logo and title take what is left; the language picker and buttons never shrink. */
    .ic-ai-header {
        position: relative;
        display: flex;
        align-items: center;
        gap: .5rem;
        min-height: 64px;
        padding: .5rem .75rem;
        border-bottom: 1px solid var(--ai-line);
        background: linear-gradient(180deg, #ffffff, var(--sage-50));
    }

    .ic-ai-head-main {
        display: flex;
        align-items: center;
        gap: .55rem;
        flex: 1 1 auto;
        min-width: 0;
    }

    .ic-ai-head-text {
        min-width: 0;
    }

    .ic-ai-logo {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: contain;
        flex-shrink: 0;
        background: #fff;
        border: 1px solid var(--sage-100);
    }

    .ic-ai-title {
        margin: 0;
        color: var(--ai-ink);
        font-size: 18px;
        font-weight: 600;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ic-ai-subtitle {
        margin: 1px 0 0;
        color: var(--ai-muted);
        font-size: 13px;
        font-weight: 400;
        line-height: 1.35;
        overflow-wrap: break-word;
    }

    .ic-ai-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    /* Language pill: globe, EN/FIL and a chevron drawn over the page's own select, which opens the options. */
    .ic-ai-language { position:relative; display:flex; align-items:center; flex:0 0 auto; color:var(--ai-ink); }
    .ic-ai-language-icon { position:absolute; left:9px; width:15px; height:15px; color:var(--ai-green); pointer-events:none; }
    .ic-ai-language-chevron { position:absolute; right:8px; width:13px; height:13px; color:var(--ai-muted); pointer-events:none; }
    .ic-ai-language select { appearance:none; -webkit-appearance:none; width:auto; height:32px; min-height:32px; border:1px solid var(--sage-100); border-radius:8px; padding:0 25px 0 29px; background:#fff; color:var(--sage-800); font:inherit; font-weight:600; cursor:pointer; box-shadow:none; }
    .ic-ai-language select:hover { border-color:var(--ai-green); background:var(--sage-50); }
    .ic-ai-language select:focus-visible { outline:2px solid var(--ai-green); outline-offset:1px; }
    .ic-ai-language-notice { position:absolute; right:4.5rem; top:calc(100% + .35rem); z-index:2; display:none; max-width:220px; padding:.45rem .6rem; border:1px solid #f0c36d; border-radius:7px; background:#fff8e8; color:#795b16; font-size:.72rem; font-weight:800; box-shadow:0 .35rem .9rem rgb(var(--sage-950-rgb) / .12); }
    .ic-ai-language-notice.show { display:block; }

    .ic-ai-actions {
        display: flex;
        align-items: center;
        gap: .3rem;
        flex-shrink: 0;
    }

    .ic-ai-minimize,
    .ic-ai-close {
        width: 32px;
        height: 32px;
        min-height: 32px;
        border: 1px solid var(--sage-100);
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: #fff;
        color: var(--ai-ink);
        padding: 0;
    }

    .ic-ai-minimize svg,
    .ic-ai-close svg {
        width: 16px;
        height: 16px;
    }

    .ic-ai-minimize:focus-visible,
    .ic-ai-close:focus-visible {
        outline: 2px solid var(--ai-green);
        outline-offset: 1px;
    }

    .ic-ai-minimize:hover,
    .ic-ai-close:hover {
        border-color: var(--ai-green);
        background: var(--sage-50);
    }

    .ic-ai-body {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1rem .95rem .7rem;
        background:
            radial-gradient(circle at 20% 0%, rgb(var(--sage-600-rgb) / .16), transparent 32%),
            linear-gradient(180deg, #f8fbf7, var(--sage-50));
    }

    .ic-ai-msg {
        display: flex;
        margin-bottom: 12px;
    }

    .ic-ai-msg.user {
        justify-content: flex-end;
    }

    /* Bot on the left, you on the right; each time sits under its own bubble. */
    .ic-ai-msg-col {
        max-width: 80%;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
    }

    .ic-ai-msg.user .ic-ai-msg-col {
        align-items: flex-end;
    }

    /* Flat bubbles: white with a thin line for Climora, solid dark green for you. */
    .ic-ai-bubble {
        max-width: 100%;
        border: 1px solid var(--ai-line);
        border-radius: 14px;
        padding: 12px 14px;
        background: #fff;
        color: var(--ai-ink);
        font-size: 15px;
        line-height: 1.5;
        white-space: pre-line;
        overflow-wrap: anywhere;
        box-shadow: none;
    }

    .ic-ai-msg.user .ic-ai-bubble {
        background: var(--ai-primary);
        color: #fff;
        border-color: var(--ai-primary);
    }

    .ic-ai-msg-time {
        font-size: 12px;
        line-height: 1.3;
        color: #52665a;
        font-family: 'Inter', system-ui, sans-serif;
        margin-top: 4px;
        padding: 0 2px;
    }

    .ic-ai-msg.user .ic-ai-msg-time {
        color: #52665a;
    }

    .ic-ai-results {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
        margin-top: .65rem;
    }

    .ic-ai-card {
        border: 1px solid var(--sage-100);
        border-left: 4px solid var(--sage-500);
        border-radius: 10px;
        background: #fff;
        padding: .55rem;
    }

    .ic-ai-card-label {
        color: var(--ai-muted);
        font-family: 'Inter', system-ui, sans-serif;
        font-size: .68rem;
        font-weight: 800;
        text-transform: none;
        letter-spacing: 0;
    }

    .ic-ai-card-value {
        color: var(--ai-ink);
        font-size: .86rem;
        font-weight: 900;
        margin-top: .18rem;
        line-height: 1.25;
    }

    .ai-feature-list { display: grid; gap: .4rem; }
    .ai-feature-row { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; padding: .4rem .55rem; border: 1px solid var(--ai-line); border-radius: 8px; background: #fbfdfb; font-size: .78rem; }
    .ai-feature-dot { width: .55rem; height: .55rem; border-radius: 999px; flex: 0 0 auto; background: var(--sage-200); }
    .ai-feature-dot.tone-green { background: var(--sage-500); }
    .ai-feature-dot.tone-amber { background: #ffd166; }
    .ai-feature-dot.tone-red { background: #d85b45; }
    .ai-feature-label { color: var(--ai-muted); font-weight: 800; }
    .ai-feature-value { color: var(--ai-ink); font-weight: 900; margin-left: auto; }
    .ai-feature-status { color: var(--ai-muted); font-size: .68rem; font-weight: 800; text-transform: none; letter-spacing: .03em; }

    /* While Climora answers: three dots in a bot bubble at the end of the messages (the words are for screen readers). */
    .ic-ai-typing {
        display: none;
        width: fit-content;
        margin-bottom: 12px;
        padding: 14px 16px;
        border: 1px solid var(--ai-line);
        border-radius: 14px;
        background: #fff;
    }

    .ic-ai-typing.show {
        display: flex;
    }

    /* Three dots that hop in turn; the delays stagger the same keyframes. */
    .ic-ai-dots {
        display: inline-flex;
        align-items: flex-end;
        gap: .28rem;
        height: .9rem;
    }

    .ic-ai-dot {
        width: .5rem;
        height: .5rem;
        border-radius: 999px;
        background: var(--sage-500);
        animation: ic-ai-bounce 1s ease-in-out infinite;
    }

    .ic-ai-dot:nth-child(2) {
        animation-delay: .15s;
    }

    .ic-ai-dot:nth-child(3) {
        animation-delay: .3s;
    }

    @keyframes ic-ai-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: .55; }
        30% { transform: translateY(-.4rem); opacity: 1; }
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-ai-dot {
            animation: ic-ai-fade 1.4s ease-in-out infinite;
        }

        @keyframes ic-ai-fade {
            0%, 100% { opacity: .35; }
            50% { opacity: 1; }
        }
    }

    /* Suggestions: every chip shown in full, wrapping onto more rows as needed (the message list gives up the room).
       After the first question they fold behind a small "Suggestions" button. */
    .ic-ai-suggest {
        background: #fff;
        border-top: 1px solid var(--ai-line);
    }

    .ic-ai-suggest-toggle {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 28px;
        min-height: 28px;
        margin: 6px 8px 0;
        padding: 0 8px;
        border: 0;
        border-radius: 8px;
        background: none;
        color: var(--ai-green);
        font-weight: 600;
    }

    .ic-ai-suggest-toggle[hidden] { display: none; }
    .ic-ai-suggest-toggle:hover { background: var(--ai-sage); }
    .ic-ai-suggest-toggle svg { width: 14px; height: 14px; transition: transform 160ms ease; }
    .ic-ai-suggest-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
    .ic-ai-suggest.is-folded { padding-bottom: 6px; }

    .ic-ai-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px 16px;
    }

    .ic-ai-chips[hidden] { display: none; }

    .ic-ai-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--sage-100);
        background: #fff;
        color: var(--ai-green);
        border-radius: 999px;
        min-height: 36px;
        max-width: 100%;
        padding: 8px 14px;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.2;
        white-space: normal; /* a long chip wraps inside itself on a very narrow screen */
        text-align: left;
        flex: 0 1 auto;
    }

    .ic-ai-chip:hover {
        background: var(--ai-sage);
        border-color: var(--sage-200);
    }

    .ic-ai-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px;
        gap: .5rem;
        padding: 12px 16px;
        border-top: 1px solid var(--ai-line);
        background: #fff;
        align-items: end;
    }

    .ic-ai-suggest + .ic-ai-form {
        border-top: 0;
    }

    /* Grows from one line to four (the script sets the height), then scrolls. */
    .ic-ai-form textarea {
        width: 100%;
        min-width: 0;
        height: 44px;
        min-height: 44px;
        resize: none;
        overflow-y: auto;
        font-size: 16px; /* 16px keeps phones from zooming in on the box */
        line-height: 22px;
        border-radius: 12px;
        padding: 10px 12px;
        border: 1px solid var(--sage-100);
        background: #fff;
        color: var(--ai-ink);
    }

    /* Solid dark green with a white arrow when there is a question to send; pale and not clickable when the box is
       empty or Climora is still answering. */
    .ic-ai-send {
        width: 44px;
        height: 44px;
        min-height: 44px;
        border: 0;
        border-radius: 12px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        padding: 0;
        background: var(--ai-primary);
        color: #fff;
        cursor: pointer;
        transition: background-color 160ms ease, color 160ms ease;
    }

    .ic-ai-send:hover:not(:disabled) {
        background: var(--sage-900);
    }

    .ic-ai-send:focus-visible {
        outline: 2px solid var(--ai-green);
        outline-offset: 2px;
    }

    .ic-ai-send svg {
        width: 18px;
        height: 18px;
    }

    .ic-ai-send:disabled {
        background: var(--sage-100);
        color: #8fa597;
        cursor: not-allowed;
    }

    /* Phones: a full-width bottom sheet that leaves a strip of the page showing at the top, clear of the notch and
       the home bar. */
    @media (max-width: 639.98px) {
        .ic-ai-widget {
            right: 16px;
            bottom: max(16px, env(safe-area-inset-bottom));
        }

        .ic-ai-panel {
            position: fixed;
            inset: auto 0 0 0;
            width: 100%;
            height: min(560px, calc(100vh - 16px));
            height: min(560px, calc(100dvh - max(16px, env(safe-area-inset-top))));
            border-width: 1px 0 0;
            border-radius: 18px 18px 0 0;
            transform-origin: bottom center;
        }

        .ic-ai-header {
            padding-inline: max(.75rem, env(safe-area-inset-left)) max(.75rem, env(safe-area-inset-right));
        }

        .ic-ai-form {
            padding: 12px max(16px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        }

        .ic-ai-results {
            grid-template-columns: 1fr;
        }
    }


    /* Narrow phones: beside the controls the subtitle would need three or more lines, so it takes its own full-width
       line under the logo, title, language and buttons instead (the two wrappers step aside for the header's row). */
    @media (max-width: 419.98px) {
        .ic-ai-header { flex-wrap: wrap; row-gap: 4px; }
        .ic-ai-head-main,
        .ic-ai-head-text { display: contents; }
        .ic-ai-title { flex: 1 1 0; min-width: 0; }
        .ic-ai-subtitle { order: 1; flex: 0 0 100%; margin: 0; }
    }

    /* accessibility.css makes every element in the panel 18px, buttons at least 52px and fields 56px tall. The panel
       keeps its own compact scale instead (15px messages, 13-14px controls, 16px typing); the ID wins over those
       rules and !important is needed only where they use it. */
    #icAiWidget .ic-ai-panel { font-size: 15px !important; line-height: 1.5 !important; }
    #icAiWidget .ic-ai-panel * { font-size: inherit !important; line-height: inherit !important; }
    #icAiWidget .ic-ai-panel .ic-ai-title { font-size: 18px !important; line-height: 1.25 !important; }
    #icAiWidget .ic-ai-panel .ic-ai-subtitle { font-size: 13px !important; line-height: 1.35 !important; }
    #icAiWidget .ic-ai-panel :is(.ic-ai-language select, .ic-ai-suggest-toggle) { font-size: 13px !important; line-height: 1 !important; }
    #icAiWidget .ic-ai-panel .ic-ai-language-notice { font-size: 12px !important; line-height: 1.35 !important; }
    #icAiWidget .ic-ai-panel .ic-ai-msg-time { font-size: 12px !important; line-height: 1.3 !important; }
    #icAiWidget .ic-ai-panel .ic-ai-chip { font-size: 14px !important; line-height: 1.2 !important; padding: 8px 14px; min-height: 36px; }
    #icAiWidget .ic-ai-panel .ic-ai-form textarea { font-size: 16px !important; line-height: 22px !important; min-height: 44px; }
    #icAiWidget .ic-ai-panel :is(.ic-ai-card-label, .ai-feature-status) { font-size: 12px !important; line-height: 1.3 !important; }
    #icAiWidget .ic-ai-panel :is(.ic-ai-card-value, .ai-feature-row) { font-size: 14px !important; line-height: 1.35 !important; }
    #icAiWidget .ic-ai-panel :is(.ic-ai-minimize, .ic-ai-close) { min-height: 32px; }
    #icAiWidget .ic-ai-panel .ic-ai-suggest-toggle { min-height: 28px; padding: 0 8px; }
    #icAiWidget .ic-ai-panel .ic-ai-send { min-height: 44px; padding: 0; }
    #icAiWidget .ic-ai-panel .ic-ai-language select { min-height: 32px; padding-top: 0; padding-bottom: 0; }
</style>

<script type="application/json" id="icAiFeatureCatalog">{!! json_encode($icAiFeatureCatalog, JSON_UNESCAPED_SLASHES) !!}</script>
<div id="icAiWidget" class="ic-ai-widget" data-message-url="{{ route('ai-chat.message') }}">
    <section class="ic-ai-panel" role="dialog" aria-modal="false" aria-labelledby="icAiTitle">
        <header class="ic-ai-header">
            <div class="ic-ai-head-main">
                <img src="{{ asset('images/climora-ai-icon.png') }}" alt="" class="ic-ai-logo">
                <div class="ic-ai-head-text">
                    <h2 id="icAiTitle" class="ic-ai-title">Climora AI</h2>
                    <p class="ic-ai-subtitle" title="Weather, rice yield, planting, and system guidance">Weather, rice yield, planting, and system guidance</p>
                </div>
            </div>
            @if($icAiFarmerLanguage)
                <label class="ic-ai-language" for="icAiLanguage" title="Conversation Language">
                    <span class="ic-ai-sr">Conversation Language</span>
                    <x-icon name="globe" class="ic-ai-language-icon" />
                    <x-icon name="chevron-down" class="ic-ai-language-chevron" stroke-width="2.25" />
                    <select id="icAiLanguage" data-language-url="{{ route('ai-chat.language') }}" data-language-persisted="{{ auth()->user()->aiLanguageCode() ? '1' : '0' }}" data-language-db-value="{{ auth()->user()->aiLanguageCode() ?? '' }}" aria-label="Conversation Language">
                        <option value="en" @selected(auth()->user()->aiLanguageCode() === 'en')>EN</option>
                        <option value="tl" @selected(auth()->user()->aiLanguageCode() === 'tl')>FIL</option>
                    </select>
                </label>
            @endif
            @if($icAiFarmerLanguage)
                <div id="icAiLanguageNotice" class="ic-ai-language-notice" role="status" aria-live="polite"></div>
            @endif
            <div class="ic-ai-actions">
                <button type="button" class="ic-ai-minimize" data-ai-close aria-label="Minimize chat" title="Minimize chat"><x-icon name="minus" /></button>
                <button type="button" class="ic-ai-close" data-ai-close aria-label="Close chat" title="Close chat"><x-icon name="x" /></button>
            </div>
        </header>

        <div id="icAiBody" class="ic-ai-body" role="log" aria-live="polite">
            @forelse($widgetChats as $chat)
                <div class="ic-ai-msg user">
                    <div class="ic-ai-msg-col">
                        <div class="ic-ai-bubble">{{ $chat->question }}</div>
                        <div class="ic-ai-msg-time">{{ $chat->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') }}</div>
                    </div>
                </div>
                <div class="ic-ai-msg assistant">
                    <div class="ic-ai-msg-col">
                        <div class="ic-ai-bubble">
                            {{ $chat->answer }}
                            @if($chat->weather_prediction || $chat->rice_yield_prediction || $chat->planting_recommendation || $chat->irrigation_recommendation)
                                <div class="ic-ai-results">
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Weather</div><div class="ic-ai-card-value">{{ data_get($chat->weather_prediction, 'predicted_weather', 'N/A') }}</div></div>
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Yield</div><div class="ic-ai-card-value">{{ data_get($chat->rice_yield_prediction, 'predicted_yield') !== null ? number_format((float) data_get($chat->rice_yield_prediction, 'predicted_yield'), 2).' t/ha' : 'Not available yet' }}</div></div>
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Planting</div><div class="ic-ai-card-value">{{ data_get($chat->planting_recommendation, 'recommendation', data_get($chat->planting_recommendation, 'action', 'N/A')) }}</div></div>
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Irrigation</div><div class="ic-ai-card-value">{{ data_get($chat->irrigation_recommendation, 'recommendation', 'N/A') }}</div></div>
                                </div>
                            @endif
                            @php
                                $widgetYieldFeatureRows = collect(data_get($chat->rice_yield_prediction, 'features', []))
                                    ->map(function ($value, $modelKey) {
                                        $entry = \App\Services\Prediction\FeatureCatalog::byModelKey((string) $modelKey, \App\Services\Prediction\FeatureCatalog::SCHEMA_DEPLOYED);
                                        $status = is_numeric($value) && $entry
                                            ? \App\Services\Prediction\FeatureCatalog::classify($entry['key'], (float) $value)
                                            : null;

                                        return [
                                            'value' => $value,
                                            'label' => $entry['label'] ?? $modelKey,
                                            'unit' => $entry['unit'] ?? '',
                                            'status' => $status,
                                        ];
                                    })
                                    ->values();
                            @endphp
                            @if($widgetYieldFeatureRows->isNotEmpty())
                                <details class="ic-collapse mt-2">
                                    <summary><span class="ic-collapse-title">Why this yield estimate</span></summary>
                                    <div class="ai-feature-list mt-2">
                                        @foreach($widgetYieldFeatureRows as $row)
                                            <div class="ai-feature-row">
                                                <span class="ai-feature-dot {{ $row['status']['tone'] ?? '' }}"></span>
                                                <span class="ai-feature-label">{{ $row['label'] }}</span>
                                                <span class="ai-feature-value">{{ is_numeric($row['value']) ? number_format((float) $row['value'], 2) : $row['value'] }}{{ $row['unit'] ? ' '.$row['unit'] : '' }}</span>
                                                @if($row['status'])
                                                    <span class="ai-feature-status">{{ $row['status']['label'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                        <div class="ic-ai-msg-time">{{ $chat->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') }}</div>
                    </div>
                </div>
            @empty
                <div class="ic-ai-msg assistant">
                    <div class="ic-ai-msg-col">
                        <div class="ic-ai-bubble">{{ $icAiGreeting }}</div>
                        <div class="ic-ai-msg-time" data-now-time>{{ \App\Support\BusinessClock::now()->format('g:i A') }}</div>
                    </div>
                </div>
            @endforelse

            {{-- Stays the last thing in the list; new messages go in above it. --}}
            <div id="icAiTyping" class="ic-ai-typing" role="status" aria-live="polite">
                <span class="ic-ai-dots" aria-hidden="true">
                    <span class="ic-ai-dot"></span>
                    <span class="ic-ai-dot"></span>
                    <span class="ic-ai-dot"></span>
                </span>
                <span class="ic-ai-typing-text ic-ai-sr">Climora AI is checking iClimate...</span>
            </div>
        </div>

        <div class="ic-ai-suggest">
            <button type="button" class="ic-ai-suggest-toggle" aria-expanded="true" aria-controls="icAiChips" hidden>Suggestions <x-icon name="chevron-down" stroke-width="2.25" /></button>
            <div id="icAiChips" class="ic-ai-chips" role="group" aria-label="Suggested questions">
                @foreach($icAiChips as $chip)
                    <button type="button" class="ic-ai-chip" data-ai-chip>{{ $chip }}</button>
                @endforeach
            </div>
        </div>

        <form id="icAiForm" class="ic-ai-form">
            @csrf
            <textarea id="icAiInput" class="form-control" placeholder="Ask about iClimate..." rows="1" required></textarea>
            <button id="icAiSend" type="submit" class="ic-ai-send" aria-label="Send message" disabled>
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
    </section>

    <button type="button" class="ic-ai-toggle" data-ai-open aria-label="Open Climora AI">
        <img src="{{ asset('images/climora-ai-icon.png') }}" alt="Climora AI">
    </button>
</div>

<script>
    // Runs at once when the widget is added after the page loaded (it is fetched on the launcher's first click).
    (() => { const icAiInit = () => {
        if (window.iClimateAiWidgetInitialized) return;
        window.iClimateAiWidgetInitialized = true;

        const widget = document.getElementById('icAiWidget');
        if (!widget) return;

        const panel = widget.querySelector('.ic-ai-panel');
        const form = document.getElementById('icAiForm');
        const input = document.getElementById('icAiInput');
        const body = document.getElementById('icAiBody');
        const typing = document.getElementById('icAiTyping');
        const send = document.getElementById('icAiSend');
        const languageSelect = document.getElementById('icAiLanguage');
        const token = widget.querySelector('input[name="_token"]')?.value;
        const messageUrl = widget.dataset.messageUrl;
        const saveStorageKey = 'iclimate_ai_save_conversation';
        const languageStorageKey = 'iclimate_ai_language';
        const languageFallbackStorageKey = 'iclimate_ai_language_fallback';
        let requestPending = false;
        let languageSaveInFlight = false;
        let languageNoticeTimer = null;

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
        // Newest message in view: now, and again after the browser lays out what was just added or shown.
        const scrollMessages = () => {
            body.scrollTop = body.scrollHeight;
            requestAnimationFrame(() => { body.scrollTop = body.scrollHeight; });
        };
        // The box grows with what is typed, up to four lines, then scrolls.
        const fitInput = () => {
            if (!input) return;
            const style = getComputedStyle(input);
            const lineHeight = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.4;
            const maxHeight = lineHeight * 4 + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom) + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth), maxHeight)}px`;
        };
        const chips = widget.querySelector('.ic-ai-chips');
        const suggest = widget.querySelector('.ic-ai-suggest');
        const suggestToggle = widget.querySelector('.ic-ai-suggest-toggle');
        // After the first question the chips fold behind a small "Suggestions" button, so the talk gets the room.
        const showChips = (show) => {
            if (!chips || !suggestToggle) return;
            chips.hidden = !show;
            suggestToggle.setAttribute('aria-expanded', String(show));
            suggest?.classList.toggle('is-folded', !show);
        };
        const foldChips = () => {
            if (!chips) return;
            suggestToggle?.removeAttribute('hidden');
            showChips(false);
        };
        // Send is clickable only with something typed and no answer still on its way.
        const updateSend = () => {
            if (send) send.disabled = requestPending || !input?.value.trim();
        };
        // Always hours and minutes in Lian's time, e.g. "1:00 PM" (the page renders the greeting's time the same way).
        const timeNow = () => new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' });
        const shouldSaveConversation = () => localStorage.getItem(saveStorageKey) !== '0';
        const selectedLanguage = () => languageSelect?.value || null;
        const languageText = (english, tagalog) => selectedLanguage() === 'tl' ? tagalog : english;
        const showLanguageNotice = (message) => {
            const notice = document.getElementById('icAiLanguageNotice');
            if (!notice) return;
            notice.textContent = message;
            notice.classList.add('show');
            clearTimeout(languageNoticeTimer);
            languageNoticeTimer = setTimeout(() => notice.classList.remove('show'), 5000);
        };
        const hideLanguageNotice = () => {
            const notice = document.getElementById('icAiLanguageNotice');
            if (!notice) return;
            clearTimeout(languageNoticeTimer);
            notice.textContent = '';
            notice.classList.remove('show');
        };
        const restoreLanguage = () => {
            if (!languageSelect) return;
            try {
                const dbLanguage = languageSelect.dataset.languageDbValue;
                const fallback = localStorage.getItem(languageFallbackStorageKey);
                const saved = localStorage.getItem(languageStorageKey);

                // The database is authoritative. A fallback wins only after
                // a failed save, so the current session choice survives.
                if ((fallback === 'en' || fallback === 'tl') && fallback !== dbLanguage) {
                    languageSelect.value = fallback;
                } else if (dbLanguage === 'en' || dbLanguage === 'tl') {
                    languageSelect.value = dbLanguage;
                    localStorage.removeItem(languageFallbackStorageKey);
                    localStorage.setItem(languageStorageKey, dbLanguage);
                } else if (saved === 'en' || saved === 'tl') {
                    languageSelect.value = saved;
                }
            } catch {
                // The account value remains the fallback when storage is unavailable.
            }
        };
        const updateLanguageUi = () => {
            const typingText = typing?.querySelector('.ic-ai-typing-text');
            if (typingText) typingText.textContent = languageText('Climora AI is checking iClimate...', 'Sinusuri ng Climora AI ang iClimate...');
        };
        const resultCard = (label, value) => `<div class="ic-ai-card"><div class="ic-ai-card-label">${label}</div><div class="ic-ai-card-value">${escapeHtml(value || 'Not available yet')}</div></div>`;
        const featureCatalog = (() => {
            try {
                return JSON.parse(document.getElementById('icAiFeatureCatalog')?.textContent || '{}');
            } catch {
                return {};
            }
        })();
        // Mirrors App\Services\Prediction\FeatureCatalog::classify() — keep both in sync.
        const classifyFeature = (key, value) => {
            if (typeof value !== 'number' || Number.isNaN(value)) return null;

            if (key === 'temp_range') {
                return value > 8 ? { label: 'Volatile', tone: 'tone-amber' } : { label: 'Stable', tone: 'tone-green' };
            }
            if (key.includes('rainfall')) {
                if (value >= 300) return { label: 'Heavy Rain', tone: 'tone-red' };
                if (value <= 70) return { label: 'Dry', tone: 'tone-amber' };
                return { label: 'Workable Range', tone: 'tone-green' };
            }
            if (key.includes('temp')) {
                if (value > 32) return { label: 'Hot', tone: 'tone-red' };
                if (value < 24) return { label: 'Cool', tone: 'tone-amber' };
                return { label: 'Favorable', tone: 'tone-green' };
            }

            return null;
        };
        const yieldFeatureList = (chat) => {
            const features = chat.rice_yield_prediction?.features;
            if (!features || typeof features !== 'object') return '';

            const rows = Object.entries(features).map(([modelKey, rawValue]) => {
                const meta = featureCatalog[modelKey] || { key: modelKey, label: modelKey, unit: '' };
                const numericValue = typeof rawValue === 'number' ? rawValue : parseFloat(rawValue);
                const status = classifyFeature(meta.key, numericValue);
                const displayValue = Number.isFinite(numericValue) ? numericValue.toFixed(2) : escapeHtml(String(rawValue));
                const unitSuffix = meta.unit ? ` ${escapeHtml(meta.unit)}` : '';
                const statusHtml = status ? `<span class="ai-feature-status">${escapeHtml(status.label)}</span>` : '';

                return `<div class="ai-feature-row"><span class="ai-feature-dot ${status ? status.tone : ''}"></span><span class="ai-feature-label">${escapeHtml(meta.label)}</span><span class="ai-feature-value">${displayValue}${unitSuffix}</span>${statusHtml}</div>`;
            }).join('');

            if (!rows) return '';

            return `<details class="ic-collapse mt-2"><summary><span class="ic-collapse-title">Why this yield estimate</span></summary><div class="ai-feature-list mt-2">${rows}</div></details>`;
        };
        const relevantPredictionCards = (chat) => {
            // A short farmer answer already says what to do; the raw result cards would repeat it in engine wording.
            if (chat.memory?.brief) return [];
            switch (chat.intent) {
                case 'Weather Prediction': return ['weather'];
                case 'Rice Yield Prediction': return ['yield'];
                case 'Planting Recommendation': return ['planting'];
                case 'Irrigation Recommendation': return ['irrigation'];
                case 'Climate Risk': return ['weather'];
                default: return [];
            }
        };
        const predictionGrid = (chat) => {
            const visibleCards = relevantPredictionCards(chat);
            if (!visibleCards.length) return '';

            const weather = chat.weather_prediction?.predicted_weather || 'Not available yet';
            const yieldValue = chat.rice_yield_prediction?.predicted_yield !== null && chat.rice_yield_prediction?.predicted_yield !== undefined
                ? `${Number(chat.rice_yield_prediction.predicted_yield).toFixed(2)} t/ha`
                : 'Not available yet';
            const planting = chat.planting_recommendation?.recommendation || chat.planting_recommendation?.action || 'Not available yet';
            const irrigation = chat.irrigation_recommendation?.recommendation || 'Not available yet';
            const cards = [];

            if (visibleCards.includes('weather')) cards.push(resultCard('Weather', weather));
            if (visibleCards.includes('yield')) cards.push(resultCard('Yield', yieldValue));
            if (visibleCards.includes('planting')) cards.push(resultCard('Planting', planting));
            if (visibleCards.includes('irrigation')) cards.push(resultCard('Irrigation', irrigation));

            const grid = cards.length ? `<div class="ic-ai-results">${cards.join('')}</div>` : '';
            const features = visibleCards.includes('yield') ? yieldFeatureList(chat) : '';

            return grid + features;
        };
        // A short farmer answer leads with its answer line in bold; everything stays escaped.
        const answerHtml = (chat) => {
            if (!chat.memory?.brief) return escapeHtml(chat.answer);
            const [first, ...rest] = String(chat.answer ?? '').split('\n');
            return `<strong>${escapeHtml(first)}</strong>${rest.length ? '\n' + escapeHtml(rest.join('\n')) : ''}`;
        };
        const addMessage = (role, message, extra = '', html = null) => {
            typing.insertAdjacentHTML(
                'beforebegin',
                `<div class="ic-ai-msg ${role}"><div class="ic-ai-msg-col"><div class="ic-ai-bubble">${html ?? escapeHtml(message)}${extra}</div><div class="ic-ai-msg-time">${timeNow()}</div></div></div>`
            );
            scrollMessages();
        };
        const openAssistant = () => {
            widget.classList.add('open');
            panel?.removeAttribute('hidden');
            input?.focus();
            scrollMessages();
        };
        const closeAssistant = () => {
            widget.classList.remove('open');
            panel?.setAttribute('hidden', 'hidden');
            widget.querySelector('[data-ai-open]')?.focus();
        };
        const submitQuestion = async (question) => {
            if (requestPending) return;
            const trimmedQuestion = String(question ?? '').trim();
            if (!trimmedQuestion) return;

            requestPending = true;
            addMessage('user', trimmedQuestion);
            foldChips();
            input.value = '';
            input.style.height = '';
            typing.classList.add('show');
            scrollMessages();
            updateSend();

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);

            try {
                const response = await fetch(messageUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ question: trimmedQuestion, save_conversation: shouldSaveConversation(), language: selectedLanguage() }),
                    signal: controller.signal,
                    loader: false, // the chat shows its own typing indicator
                });
                const data = await response.json();
                if (!response.ok) throw new Error('assistant request failed');

                const chat = data.chat;
                addMessage('assistant', chat.answer, predictionGrid(chat), answerHtml(chat));
            } catch (error) {
                const message = error.name === 'AbortError'
                    ? languageText('The assistant took too long. Please ask a shorter iClimate question or try again.', 'Matagal sumagot ang assistant. Magtanong nang mas maikli o subukan ulit.')
                    : languageText('The assistant could not answer right now. Please try again.', 'Hindi makasagot ang assistant ngayon. Subukan ulit.');
                addMessage('assistant', message);
            } finally {
                clearTimeout(timeout);
                requestPending = false;
                typing.classList.remove('show');
                updateSend();
                input.focus();
                scrollMessages();
            }
        };

        languageSelect?.addEventListener('change', async () => {
            const language = selectedLanguage();
            if (languageSaveInFlight || !['en', 'tl'].includes(language)) return;

            languageSaveInFlight = true;
            try {
                localStorage.setItem(languageStorageKey, language);
            } catch {
                // The selected control value still keeps this session in the chosen language.
            }
            updateLanguageUi();
            languageSelect.disabled = true;

            try {
                const response = await fetch(languageSelect.dataset.languageUrl, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ language }),
                });
                if (!response.ok) throw new Error('language update failed');
                const data = await response.json();
                if (data.language !== language) throw new Error('language update mismatch');
                languageSelect.dataset.languageDbValue = language;
                localStorage.setItem(languageStorageKey, language);
                localStorage.removeItem(languageFallbackStorageKey);
                hideLanguageNotice();
            } catch {
                try {
                    localStorage.setItem(languageStorageKey, language);
                    localStorage.setItem(languageFallbackStorageKey, language);
                } catch {
                    // The selected control value still keeps this session in
                    // the chosen language when storage is unavailable.
                }
                showLanguageNotice(languageText('Language could not be saved. It will remain active for this session.', 'Hindi na-save ang wika. Mananatili itong aktibo sa session na ito.'));
            } finally {
                languageSaveInFlight = false;
                languageSelect.disabled = false;
            }
        });
        restoreLanguage();
        updateLanguageUi();

        window.iClimateAi = {
            open: openAssistant,
            close: closeAssistant,
            toggle: () => widget.classList.contains('open') ? closeAssistant() : openAssistant(),
            prefill: (question) => {
                openAssistant();
                if (input) {
                    input.value = String(question ?? '');
                    fitInput();
                    updateSend();
                    input.focus();
                }
            },
            ask: (question) => {
                openAssistant();
                submitQuestion(question);
            },
        };

        document.addEventListener('click', (event) => {
            const openTrigger = event.target.closest('[data-ai-open]');
            const closeTrigger = event.target.closest('[data-ai-close]');
            const chip = event.target.closest('[data-ai-chip]');

            if (openTrigger) {
                event.preventDefault();
                openAssistant();
                return;
            }

            if (closeTrigger && widget.contains(closeTrigger)) {
                event.preventDefault();
                closeAssistant();
                return;
            }

            if (chip && widget.contains(chip)) {
                event.preventDefault();
                input.value = chip.textContent.trim();
                openAssistant();
                submitQuestion(input.value);
            }
        });

        input.addEventListener('input', () => {
            fitInput();
            updateSend();
        });
        // Opening the suggestions keeps the newest message in view above them.
        suggestToggle?.addEventListener('click', () => {
            showChips(chips.hidden);
            scrollMessages();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey) return;
            event.preventDefault();
            submitQuestion(input.value);
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitQuestion(input.value);
        });

        panel?.setAttribute('hidden', 'hidden');
        updateSend();
        scrollMessages();
    }; document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', icAiInit) : icAiInit(); })();
</script>
