@php
    $widgetChats = collect();

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
            'What is my predicted rice yield?',
            'When should I plant?',
            'My rice leaves are turning yellow',
        ],
    };
@endphp

<style>
    .ic-ai-widget {
        --ai-ink: #123123;
        --ai-muted: #5f7569;
        --ai-line: #d4e6da;
        --ai-sage: #eef7f0;
        --ai-panel: #fbfdf9;
        --ai-green: #1f6f4a;
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 2050;
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
        background: linear-gradient(135deg, #123123, #2d6a4f);
        box-shadow: 0 18px 34px rgba(13, 31, 24, .28);
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
        width: min(460px, calc(100vw - 32px));
        height: min(650px, calc(100vh - 100px));
        border: 1px solid var(--ai-line);
        border-radius: 18px;
        overflow: hidden;
        background: var(--ai-panel);
        box-shadow: 0 24px 58px rgba(13, 31, 24, .26);
        display: none;
        grid-template-rows: auto minmax(0, 1fr) auto auto;
    }

    .ic-ai-widget.open .ic-ai-panel {
        display: grid;
    }

    .ic-ai-header {
        min-height: 78px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        padding: .85rem .95rem;
        border-bottom: 1px solid var(--ai-line);
        background: linear-gradient(180deg, #ffffff, #f1f8f2);
    }

    .ic-ai-head-main {
        display: flex;
        align-items: center;
        gap: .7rem;
        min-width: 0;
    }

    .ic-ai-logo {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        object-fit: contain;
        flex-shrink: 0;
        background: #e8f5eb;
        border: 1px solid #cfe8d6;
    }

    .ic-ai-title {
        margin: 0;
        color: var(--ai-ink);
        font-size: 1.05rem;
        font-weight: 900;
        line-height: 1.15;
    }

    .ic-ai-subtitle {
        margin: .15rem 0 0;
        color: var(--ai-muted);
        font-size: .82rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .ic-ai-actions {
        display: flex;
        align-items: center;
        gap: .35rem;
        flex-shrink: 0;
    }

    .ic-ai-minimize,
    .ic-ai-close {
        width: 34px;
        height: 34px;
        border: 1px solid #cfe8d6;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: #fff;
        color: var(--ai-ink);
        font-size: 1.1rem;
        font-weight: 900;
        line-height: 1;
        padding: 0;
    }

    .ic-ai-minimize:hover,
    .ic-ai-close:hover {
        border-color: var(--ai-green);
        background: #edf7e7;
    }

    .ic-ai-body {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1rem .95rem .7rem;
        background:
            radial-gradient(circle at 20% 0%, rgba(143, 175, 154, .16), transparent 32%),
            linear-gradient(180deg, #f8fbf7, #edf7e7);
    }

    .ic-ai-msg {
        display: flex;
        margin-bottom: .9rem;
    }

    .ic-ai-msg.user {
        justify-content: flex-end;
    }

    .ic-ai-msg-col {
        max-width: 86%;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .ic-ai-msg.user .ic-ai-msg-col {
        align-items: flex-end;
        max-width: 82%;
    }

    .ic-ai-bubble {
        border: 1px solid var(--ai-line);
        border-radius: 14px;
        padding: .75rem .85rem;
        background: #fff;
        color: var(--ai-ink);
        line-height: 1.52;
        white-space: pre-line;
        overflow-wrap: anywhere;
        font-size: .95rem;
        box-shadow: 0 8px 20px rgba(13, 31, 24, .06);
    }

    .ic-ai-msg.assistant .ic-ai-bubble {
        background: #f6faf6;
        color: var(--ai-ink);
        border-color: #cfe8d6;
    }

    .ic-ai-msg.user .ic-ai-bubble {
        background: var(--ai-ink);
        color: #fff;
        border-color: var(--ai-ink);
    }

    .ic-ai-msg-time {
        font-size: .75rem;
        color: #7d9584;
        font-family: 'DM Mono', monospace;
        margin-top: .3rem;
        padding: 0 .1rem;
    }

    .ic-ai-msg.user .ic-ai-msg-time {
        color: #6f8679;
    }

    .ic-ai-results {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
        margin-top: .65rem;
    }

    .ic-ai-card {
        border: 1px solid #cfe8d6;
        border-left: 4px solid #52b788;
        border-radius: 10px;
        background: #fff;
        padding: .55rem;
    }

    .ic-ai-card-label {
        color: var(--ai-muted);
        font-family: 'DM Mono', monospace;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .ic-ai-card-value {
        color: var(--ai-ink);
        font-size: .86rem;
        font-weight: 900;
        margin-top: .18rem;
        line-height: 1.25;
    }

    .ic-ai-typing {
        display: none;
        align-items: center;
        gap: .35rem;
        color: var(--ai-muted);
        font-family: 'DM Mono', monospace;
        font-size: .78rem;
        font-weight: 700;
        padding: .45rem .95rem .7rem;
        background: #edf7e7;
        border-top: 1px solid rgba(212, 230, 218, .7);
    }

    .ic-ai-typing.show {
        display: flex;
    }

    .ic-ai-dot {
        width: .42rem;
        height: .42rem;
        border-radius: 999px;
        background: #52b788;
        animation: ic-ai-pulse 850ms ease-in-out infinite alternate;
    }

    .ic-ai-dot:nth-child(2) {
        animation-delay: 120ms;
    }

    .ic-ai-dot:nth-child(3) {
        animation-delay: 240ms;
    }

    @keyframes ic-ai-pulse {
        from { opacity: .35; transform: translateY(0); }
        to { opacity: 1; transform: translateY(-3px); }
    }

    .ic-ai-chips {
        display: flex;
        gap: .45rem;
        overflow-x: auto;
        padding: .75rem .95rem;
        background: #fff;
        border-top: 1px solid var(--ai-line);
        scrollbar-width: thin;
    }

    .ic-ai-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cfe8d6;
        background: #fff;
        color: var(--ai-green);
        border-radius: 999px;
        padding: .42rem .72rem;
        font-size: .82rem;
        font-weight: 800;
        line-height: 1.2;
        white-space: nowrap;
        flex: 0 0 auto;
    }

    .ic-ai-chip:hover {
        background: var(--ai-sage);
        border-color: #95d5b2;
    }

    .ic-ai-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px;
        gap: .6rem;
        padding: .85rem .95rem;
        border-top: 1px solid var(--ai-line);
        background: #fff;
        align-items: end;
    }

    .ic-ai-form textarea {
        min-height: 46px;
        max-height: 112px;
        resize: none;
        font-size: 1rem;
        line-height: 1.38;
        border-radius: 12px;
        padding: .7rem .85rem;
        border: 1px solid #cfe8d6;
        background: #fff;
        color: var(--ai-ink);
    }

    .ic-ai-send {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        padding: 0;
        background: var(--ai-ink);
        border-color: var(--ai-ink);
        color: #fff;
    }

    .ic-ai-send svg {
        width: 18px;
        height: 18px;
    }

    .ic-ai-send:disabled {
        opacity: .58;
        cursor: wait;
    }

    @media (max-width: 575.98px) {
        .ic-ai-widget {
            right: 8px !important;
            bottom: 8px !important;
            left: auto !important;
        }

        .ic-ai-panel {
            position: fixed !important;
            inset: auto 8px 8px 8px !important;
            width: auto !important;
            height: calc(100dvh - 16px) !important;
            border-radius: 16px;
        }

        .ic-ai-header {
            min-height: 72px;
            padding: .75rem;
        }

        .ic-ai-msg-col,
        .ic-ai-msg.user .ic-ai-msg-col {
            max-width: 92%;
        }

        .ic-ai-results {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 380px) {
        .ic-ai-title {
            font-size: .98rem;
        }

        .ic-ai-subtitle {
            font-size: .76rem;
        }

        .ic-ai-chips {
            padding-inline: .7rem;
        }
    }
</style>

<div id="icAiWidget" class="ic-ai-widget" data-message-url="{{ route('ai-chat.message') }}">
    <section class="ic-ai-panel" role="dialog" aria-modal="false" aria-labelledby="icAiTitle">
        <header class="ic-ai-header">
            <div class="ic-ai-head-main">
                <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate" class="ic-ai-logo">
                <div>
                    <h2 id="icAiTitle" class="ic-ai-title">Climora AI</h2>
                    <p class="ic-ai-subtitle">Weather, rice yield, planting, and system guidance</p>
                </div>
            </div>
            <div class="ic-ai-actions">
                <button type="button" class="ic-ai-minimize" data-ai-close aria-label="Minimize assistant">&minus;</button>
                <button type="button" class="ic-ai-close" data-ai-close aria-label="Close assistant">&times;</button>
            </div>
        </header>

        <div id="icAiBody" class="ic-ai-body" role="log" aria-live="polite">
            @forelse($widgetChats as $chat)
                <div class="ic-ai-msg user">
                    <div class="ic-ai-msg-col">
                        <div class="ic-ai-bubble">{{ $chat->question }}</div>
                        <div class="ic-ai-msg-time">{{ $chat->created_at?->shortTime() }}</div>
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
                        <div class="ic-ai-msg-time">{{ $chat->created_at?->shortTime() }}</div>
                    </div>
                </div>
            @empty
                <div class="ic-ai-msg assistant">
                    <div class="ic-ai-msg-col">
                        <div class="ic-ai-bubble">Hello! Ask me about iClimate features, weather prediction, rice yield, planting, irrigation, climate risk, announcements, notifications, reports, or your profile.</div>
                        <div class="ic-ai-msg-time" data-now-time>{{ now()->shortTime() }}</div>
                    </div>
                </div>
            @endforelse
        </div>

        <div id="icAiTyping" class="ic-ai-typing">
            <span>Climora AI is checking iClimate</span>
            <span class="ic-ai-dot"></span>
            <span class="ic-ai-dot"></span>
            <span class="ic-ai-dot"></span>
        </div>

        <div class="ic-ai-chips" aria-label="Suggested questions">
            @foreach($icAiChips as $chip)
                <button type="button" class="ic-ai-chip" data-ai-chip>{{ $chip }}</button>
            @endforeach
        </div>

        <form id="icAiForm" class="ic-ai-form">
            @csrf
            <textarea id="icAiInput" class="form-control" placeholder="Ask about iClimate..." rows="1" required></textarea>
            <button id="icAiSend" type="submit" class="btn btn-primary ic-ai-send" aria-label="Send message">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
    </section>

    <button type="button" class="ic-ai-toggle" data-ai-open aria-label="Open Climora AI">
        <img src="{{ asset('images/' . rawurlencode('ai assistant.png')) }}" alt="Climora AI">
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
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
        const token = widget.querySelector('input[name="_token"]')?.value;
        const messageUrl = widget.dataset.messageUrl;
        const saveStorageKey = 'iclimate_ai_save_conversation';
        let requestPending = false;

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
        const scrollMessages = () => { body.scrollTop = body.scrollHeight; };
        const timeNow = () => new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        const shouldSaveConversation = () => localStorage.getItem(saveStorageKey) !== '0';
        const resultCard = (label, value) => `<div class="ic-ai-card"><div class="ic-ai-card-label">${label}</div><div class="ic-ai-card-value">${escapeHtml(value || 'N/A')}</div></div>`;
        const relevantPredictionCards = (chat) => {
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

            const weather = chat.weather_prediction?.predicted_weather || 'N/A';
            const yieldValue = chat.rice_yield_prediction?.predicted_yield !== null && chat.rice_yield_prediction?.predicted_yield !== undefined
                ? `${Number(chat.rice_yield_prediction.predicted_yield).toFixed(2)} t/ha`
                : 'N/A';
            const planting = chat.planting_recommendation?.recommendation || chat.planting_recommendation?.action || 'N/A';
            const irrigation = chat.irrigation_recommendation?.recommendation || 'N/A';
            const cards = [];

            if (visibleCards.includes('weather')) cards.push(resultCard('Weather', weather));
            if (visibleCards.includes('yield')) cards.push(resultCard('Yield', yieldValue));
            if (visibleCards.includes('planting')) cards.push(resultCard('Planting', planting));
            if (visibleCards.includes('irrigation')) cards.push(resultCard('Irrigation', irrigation));

            return cards.length ? `<div class="ic-ai-results">${cards.join('')}</div>` : '';
        };
        const addMessage = (role, message, extra = '') => {
            body.insertAdjacentHTML(
                'beforeend',
                `<div class="ic-ai-msg ${role}"><div class="ic-ai-msg-col"><div class="ic-ai-bubble">${escapeHtml(message)}${extra}</div><div class="ic-ai-msg-time">${timeNow()}</div></div></div>`
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
            input.value = '';
            input.style.height = '';
            typing.classList.add('show');
            send.disabled = true;

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 45000);

            try {
                const response = await fetch(messageUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ question: trimmedQuestion, save_conversation: shouldSaveConversation() }),
                    signal: controller.signal,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'The assistant could not answer right now.');

                const chat = data.chat;
                addMessage('assistant', chat.answer, predictionGrid(chat));
            } catch (error) {
                const message = error.name === 'AbortError'
                    ? 'The assistant took too long. Please ask a shorter iClimate question or try again.'
                    : error.message;
                addMessage('assistant', message);
            } finally {
                clearTimeout(timeout);
                requestPending = false;
                typing.classList.remove('show');
                send.disabled = false;
                input.focus();
                scrollMessages();
            }
        };

        window.iClimateAi = {
            open: openAssistant,
            close: closeAssistant,
            toggle: () => widget.classList.contains('open') ? closeAssistant() : openAssistant(),
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
            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight, 112)}px`;
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
        scrollMessages();
    });
</script>
