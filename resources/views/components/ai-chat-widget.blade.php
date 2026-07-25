@php
    $widgetChats = collect();

    $icAiHelpTopics = match (auth()->user()->role) {
        \App\Models\User::ROLE_MAO => [
            ['icon' => '🌤️', 'name' => 'Weather & Climate', 'desc' => 'Get the latest weather updates and forecasts'],
            ['icon' => '🌾', 'name' => 'Yield & Production', 'desc' => 'Barangay-level yield and production summaries'],
            ['icon' => '📅', 'name' => 'Planting Advisories', 'desc' => 'Active advisories and scheduling guidance'],
            ['icon' => '⚠️', 'name' => 'Climate Risk Alerts', 'desc' => 'Early warnings and risk notifications'],
            ['icon' => '📢', 'name' => 'Announcements', 'desc' => 'Latest updates and advisory announcements'],
            ['icon' => '👤', 'name' => 'Account & Help', 'desc' => 'Manage your profile and get support'],
        ],
        \App\Models\User::ROLE_IT_EXPERT => [
            ['icon' => '🖥️', 'name' => 'System Health', 'desc' => 'Database, API, and server status checks'],
            ['icon' => '👥', 'name' => 'User Management', 'desc' => 'Registered users and account activity'],
            ['icon' => '🔌', 'name' => 'API Status', 'desc' => 'Farming AI API availability and uptime'],
            ['icon' => '⚠️', 'name' => 'Error Logs', 'desc' => 'Recent system errors and issues'],
            ['icon' => '📢', 'name' => 'Announcements', 'desc' => 'Latest updates and advisory announcements'],
            ['icon' => '👤', 'name' => 'Account & Help', 'desc' => 'Manage your profile and get support'],
        ],
        default => [
            ['icon' => '🌤️', 'name' => 'Weather & Climate', 'desc' => 'Get the latest weather updates and forecasts'],
            ['icon' => '🌾', 'name' => 'Rice Yield Prediction', 'desc' => 'AI-powered yield prediction and insights'],
            ['icon' => '📅', 'name' => 'Planting & Irrigation', 'desc' => 'Best planting time and irrigation advice'],
            ['icon' => '⚠️', 'name' => 'Climate Risk Alerts', 'desc' => 'Early warnings and risk notifications'],
            ['icon' => '📢', 'name' => 'Announcements', 'desc' => 'Latest updates and advisory announcements'],
            ['icon' => '👤', 'name' => 'Account & Help', 'desc' => 'Manage your profile and get support'],
        ],
    };
@endphp

<style>
    .ic-ai-widget { position: fixed; right: 1rem; bottom: 1rem; z-index: 2050; font-family: 'Inter', system-ui, sans-serif; }
    .ic-ai-toggle { width: 58px; height: 58px; border: 0; border-radius: 999px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #1a3a2a, #2d6a4f); box-shadow: 0 .9rem 1.8rem rgba(13,31,24,.26); font-weight: 700; font-family: 'DM Mono', monospace; padding: 0; overflow: hidden; }
    .ic-ai-toggle img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 999px; }
    .ic-ai-panel {
        position: absolute; right: 0; bottom: 4.5rem;
        width: min(920px, calc(100vw - 2rem)); height: min(660px, calc(100vh - 7rem));
        border: 1.5px solid #e8e0d0; border-radius: 18px; overflow: hidden;
        background: #fff; box-shadow: 0 1.4rem 3rem rgba(13,31,24,.22);
        display: none; grid-template-columns: minmax(240px, 300px) minmax(0, 1fr);
    }
    .ic-ai-widget.open .ic-ai-panel { display: grid; }

    /* ---- Left info panel ---- */
    .ic-ai-left { background: linear-gradient(160deg, #0d1f18, #1a3a2a 60%, #204631); color: #fff; padding: 1.1rem 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: .9rem; }
    .ic-ai-left-head { display: flex; align-items: flex-start; gap: .6rem; }
    .ic-ai-logo { width: 46px; height: 46px; border-radius: 12px; object-fit: contain; flex-shrink: 0; }
    .ic-ai-brand { font-family: 'DM Serif Display', serif; font-size: 1.15rem; line-height: 1.2; }
    .ic-ai-brand-accent { color: #74c69d; }
    .ic-ai-brand-sub { font-family: 'DM Mono', monospace; font-size: .66rem; color: rgba(255,255,255,.68); margin-top: .25rem; line-height: 1.35; }
    .ic-ai-left-divider { border: none; border-top: 1px solid rgba(255,255,255,.14); margin: 0; }
    .ic-ai-welcome-card { background: rgba(82,183,136,.16); border: 1px solid rgba(116,198,157,.35); border-radius: 12px; padding: .75rem .8rem; display: flex; gap: .55rem; align-items: flex-start; }
    .ic-ai-welcome-icon { width: 30px; height: 30px; border-radius: 999px; background: rgba(255,255,255,.12); display: grid; place-items: center; flex-shrink: 0; font-size: .95rem; }
    .ic-ai-welcome-card strong { display: block; font-size: .85rem; margin-bottom: .3rem; }
    .ic-ai-welcome-card p { margin: 0; font-size: .76rem; line-height: 1.45; color: rgba(255,255,255,.82); }
    .ic-ai-help-title { font-size: .78rem; font-weight: 700; color: #95d5b2; display: flex; align-items: center; gap: .4rem; }
    .ic-ai-help-list { display: flex; flex-direction: column; }
    .ic-ai-help-item { display: flex; gap: .6rem; align-items: flex-start; padding: .65rem 0; border-bottom: 1px solid rgba(255,255,255,.1); }
    .ic-ai-help-item:last-child { border-bottom: none; }
    .ic-ai-help-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,.08); display: grid; place-items: center; flex-shrink: 0; font-size: 1rem; }
    .ic-ai-help-name { font-size: .82rem; font-weight: 700; }
    .ic-ai-help-desc { font-size: .72rem; color: rgba(255,255,255,.62); margin-top: .1rem; line-height: 1.35; }

    /* ---- Right chat panel ---- */
    .ic-ai-right { display: grid; grid-template-rows: minmax(0,1fr) auto auto; position: relative; background: linear-gradient(180deg, #f7fbf8, #e9f5e1); min-width: 0; }
    .ic-ai-close { position: absolute; top: .7rem; right: .8rem; z-index: 2; border: 1px solid #d4edda; background: #fff; color: #0d1f18; border-radius: 999px; width: 30px; height: 30px; font-weight: 700; line-height: 1; }
    .ic-ai-body { overflow-y: auto; padding: 1.1rem 1.1rem .6rem; }
    .ic-ai-msg { display: flex; margin-bottom: .8rem; }
    .ic-ai-msg.user { justify-content: flex-end; }
    .ic-ai-msg-col { max-width: 78%; display: flex; flex-direction: column; }
    .ic-ai-msg.user .ic-ai-msg-col { align-items: flex-end; }
    .ic-ai-bubble { border: 1.5px solid #d4edda; border-radius: 14px; padding: .68rem .8rem; background: #123123; color: #fff; line-height: 1.48; white-space: pre-line; overflow-wrap: anywhere; font-size: .9rem; }
    .ic-ai-msg.assistant .ic-ai-bubble { background: #123123; color: #fff; border-color: #123123; }
    .ic-ai-msg.user .ic-ai-bubble { background: #eef7f0; color: #0d1f18; border-color: #d4edda; }
    .ic-ai-msg-time { font-size: .65rem; color: #7d9584; font-family: 'DM Mono', monospace; margin-top: .28rem; padding: 0 .1rem; }
    .ic-ai-results { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem; margin-top: .6rem; }
    .ic-ai-card { border: 1px solid rgba(255,255,255,.2); border-left: 4px solid #52b788; border-radius: 10px; background: rgba(255,255,255,.06); padding: .5rem; }
    .ic-ai-card-label { color: rgba(255,255,255,.6); font-family: 'DM Mono', monospace; font-size: .6rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
    .ic-ai-card-value { color: #fff; font-size: .78rem; font-weight: 700; margin-top: .16rem; }
    .ic-ai-typing { display: none; color: #5f7569; font-family: 'DM Mono', monospace; font-size: .74rem; font-weight: 500; padding: 0 1.1rem .5rem; }
    .ic-ai-typing.show { display: block; }
    .ic-ai-chips { display: flex; flex-wrap: wrap; gap: .45rem; padding: 0 1.1rem .7rem; }
    .ic-ai-chip { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid #cfe8d6; background: #fff; color: #1f6f4a; border-radius: 999px; padding: .4rem .75rem; font-size: .74rem; font-weight: 600; }
    .ic-ai-chip:hover { background: #edf7e7; }
    .ic-ai-form { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .55rem; padding: .8rem 1.1rem; border-top: 1px solid #d4edda; background: #fdfef9; align-items: center; }
    .ic-ai-form textarea { min-height: 46px; max-height: 110px; resize: vertical; font-size: .88rem; border-radius: 999px; padding: .65rem 1.1rem; border: 1px solid #d4edda; background: #fff; }
    .ic-ai-send { width: 44px; height: 44px; border-radius: 999px; display: grid; place-items: center; flex-shrink: 0; padding: 0; background: #123123; border-color: #123123; }

    @media (max-width: 767.98px) {
        .ic-ai-panel { grid-template-columns: 1fr; }
        .ic-ai-left { display: none; }
    }
    @media (max-width: 575.98px) {
        .ic-ai-widget { right: .65rem; bottom: .65rem; }
        .ic-ai-panel { width: calc(100vw - 1.3rem); height: min(590px, calc(100vh - 6rem)); }
        .ic-ai-results { grid-template-columns: 1fr; }
    }
</style>

<div id="icAiWidget" class="ic-ai-widget" data-message-url="{{ route('ai-chat.message') }}">
    <div class="ic-ai-panel" role="dialog" aria-label="iClimate assistant">
        <div class="ic-ai-left">
            <div class="ic-ai-left-head">
                <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate" class="ic-ai-logo">
                <div>
                    <div class="ic-ai-brand"><span class="ic-ai-brand-accent">iClimate</span> Assistant</div>
                    <div class="ic-ai-brand-sub">System help and farmer-friendly prediction guidance</div>
                </div>
            </div>
            <hr class="ic-ai-left-divider">
            <div class="ic-ai-welcome-card">
                <div class="ic-ai-welcome-icon">🌱</div>
                <div>
                    <strong>Hello! I'm iClimate Assistant 🌱</strong>
                    <p>I can help you with weather forecasts, rice yield predictions, planting schedules, irrigation tips, climate risks, announcements, and more. How can I assist you today?</p>
                </div>
            </div>
            <div class="ic-ai-help-title">🌱 I can help you with:</div>
            <div class="ic-ai-help-list">
                @foreach($icAiHelpTopics as $topic)
                    <div class="ic-ai-help-item">
                        <div class="ic-ai-help-icon">{{ $topic['icon'] }}</div>
                        <div>
                            <div class="ic-ai-help-name">{{ $topic['name'] }}</div>
                            <div class="ic-ai-help-desc">{{ $topic['desc'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ic-ai-right">
            <button type="button" class="ic-ai-close" aria-label="Close assistant">x</button>
            <div id="icAiBody" class="ic-ai-body">
                @forelse($widgetChats as $chat)
                    <div class="ic-ai-msg user">
                        <div class="ic-ai-msg-col">
                            <div class="ic-ai-bubble">{{ $chat->question }}</div>
                            <div class="ic-ai-msg-time">{{ $chat->created_at?->format('g:i A') }}</div>
                        </div>
                    </div>
                    <div class="ic-ai-msg assistant">
                        <div class="ic-ai-msg-col">
                            <div class="ic-ai-bubble">
                                {{ $chat->answer }}
                                @if($chat->weather_prediction || $chat->rice_yield_prediction || $chat->planting_recommendation || $chat->irrigation_recommendation)
                                    <div class="ic-ai-results">
                                        <div class="ic-ai-card"><div class="ic-ai-card-label">Weather</div><div class="ic-ai-card-value">{{ data_get($chat->weather_prediction, 'predicted_weather', 'N/A') }}</div></div>
                                        <div class="ic-ai-card"><div class="ic-ai-card-label">Yield</div><div class="ic-ai-card-value">{{ data_get($chat->rice_yield_prediction, 'predicted_yield') !== null ? number_format((float) data_get($chat->rice_yield_prediction, 'predicted_yield'), 2).' t/ha' : 'N/A' }}</div></div>
                                        <div class="ic-ai-card"><div class="ic-ai-card-label">Planting</div><div class="ic-ai-card-value">{{ data_get($chat->planting_recommendation, 'recommendation', data_get($chat->planting_recommendation, 'action', 'N/A')) }}</div></div>
                                        <div class="ic-ai-card"><div class="ic-ai-card-label">Irrigation</div><div class="ic-ai-card-value">{{ data_get($chat->irrigation_recommendation, 'recommendation', 'N/A') }}</div></div>
                                    </div>
                                @endif
                            </div>
                            <div class="ic-ai-msg-time">{{ $chat->created_at?->format('g:i A') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="ic-ai-msg assistant">
                        <div class="ic-ai-msg-col">
                            <div class="ic-ai-bubble">Hello! Ask me about iClimate features, weather prediction, rice yield, planting, irrigation, climate risk, announcements, notifications, reports, or your profile.</div>
                            <div class="ic-ai-msg-time" data-now-time>{{ now()->format('g:i A') }}</div>
                        </div>
                    </div>
                @endforelse
            </div>
            <div id="icAiTyping" class="ic-ai-typing">Assistant is checking iClimate...</div>
            @php
                $icAiChips = match (auth()->user()->role) {
                    \App\Models\User::ROLE_MAO => ['Which barangay has the highest yield?', 'Show a production summary for this season', 'How many active planting advisories are there?', 'Latest announcement'],
                    \App\Models\User::ROLE_IT_EXPERT => ['How many users are registered?', 'Is the database connected?', 'Is the farming AI API online?', 'Any recent errors in the system?'],
                    default => ['Will it rain this week?', 'What is my predicted rice yield?', 'When should I plant?', 'My rice leaves are turning yellow'],
                };
            @endphp
            <div class="ic-ai-chips">
                @foreach($icAiChips as $chip)
                    <button type="button" class="ic-ai-chip">{{ $chip }}</button>
                @endforeach
            </div>
            <form id="icAiForm" class="ic-ai-form">
                @csrf
                <textarea id="icAiInput" class="form-control" placeholder="Ask about iClimate..." required></textarea>
                <button id="icAiSend" type="submit" class="btn btn-primary ic-ai-send" aria-label="Send">➤</button>
            </form>
        </div>
    </div>
    <button type="button" class="ic-ai-toggle" aria-label="Open iClimate assistant">
        <img src="{{ asset('images/' . rawurlencode('ai assistant.png')) }}" alt="iClimate AI Assistant">
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const widget = document.getElementById('icAiWidget');
        if (!widget) return;

        const toggle = widget.querySelector('.ic-ai-toggle');
        const close = widget.querySelector('.ic-ai-close');
        const form = document.getElementById('icAiForm');
        const input = document.getElementById('icAiInput');
        const body = document.getElementById('icAiBody');
        const typing = document.getElementById('icAiTyping');
        const send = document.getElementById('icAiSend');
        const token = widget.querySelector('input[name="_token"]').value;
        const messageUrl = widget.dataset.messageUrl;

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
        const scroll = () => { body.scrollTop = body.scrollHeight; };
        const timeNow = () => new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        const resultCard = (label, value) => `<div class="ic-ai-card"><div class="ic-ai-card-label">${label}</div><div class="ic-ai-card-value">${escapeHtml(value || 'N/A')}</div></div>`;
        const hasPrediction = (chat) => chat.weather_prediction || chat.rice_yield_prediction || chat.planting_recommendation || chat.irrigation_recommendation;
        const predictionGrid = (chat) => {
            if (!hasPrediction(chat)) return '';
            const weather = chat.weather_prediction?.predicted_weather || 'N/A';
            const yieldValue = chat.rice_yield_prediction?.predicted_yield !== null && chat.rice_yield_prediction?.predicted_yield !== undefined ? `${Number(chat.rice_yield_prediction.predicted_yield).toFixed(2)} t/ha` : 'N/A';
            const planting = chat.planting_recommendation?.recommendation || chat.planting_recommendation?.action || 'N/A';
            const irrigation = chat.irrigation_recommendation?.recommendation || 'N/A';
            return `<div class="ic-ai-results">${resultCard('Weather', weather)}${resultCard('Yield', yieldValue)}${resultCard('Planting', planting)}${resultCard('Irrigation', irrigation)}</div>`;
        };

        widget.querySelectorAll('.ic-ai-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                input.value = btn.textContent;
                input.focus();
            });
        });

        toggle.addEventListener('click', () => {
            widget.classList.toggle('open');
            if (widget.classList.contains('open')) {
                input.focus();
                scroll();
            }
        });
        close.addEventListener('click', () => widget.classList.remove('open'));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const question = input.value.trim();
            if (!question) return;

            body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg user"><div class="ic-ai-msg-col"><div class="ic-ai-bubble">${escapeHtml(question)}</div><div class="ic-ai-msg-time">${timeNow()}</div></div></div>`);
            input.value = '';
            typing.classList.add('show');
            send.disabled = true;
            scroll();

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 45000);

            try {
                const response = await fetch(messageUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ question }),
                    signal: controller.signal,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'The assistant could not answer right now.');
                const chat = data.chat;
                body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg assistant"><div class="ic-ai-msg-col"><div class="ic-ai-bubble">${escapeHtml(chat.answer)}${predictionGrid(chat)}</div><div class="ic-ai-msg-time">${timeNow()}</div></div></div>`);
            } catch (error) {
                const message = error.name === 'AbortError'
                    ? 'The assistant took too long. Please ask a shorter iClimate question or try again.'
                    : error.message;
                body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg assistant"><div class="ic-ai-msg-col"><div class="ic-ai-bubble">${escapeHtml(message)}</div><div class="ic-ai-msg-time">${timeNow()}</div></div></div>`);
            } finally {
                clearTimeout(timeout);
                typing.classList.remove('show');
                send.disabled = false;
                input.focus();
                scroll();
            }
        });

        scroll();
    });
</script>
