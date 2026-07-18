@php
    $widgetChats = collect();
    $assistantName = 'PalayPilot';
    $assistantSubtitle = 'iClimate rice guidance powered by Groq llama-3.3 and Predict.py';
    $widgetPredictionCards = function ($chat): array {
        $intent = (string) ($chat->intent ?? '');

        return match ($intent) {
            'Weather Prediction' => ['weather'],
            'Rice Yield Prediction' => ['yield'],
            'Planting Recommendation' => ['planting'],
            'Irrigation Recommendation' => ['irrigation'],
            'Climate Risk' => ['weather'],
            default => [],
        };
    };
@endphp

<style>
    .ic-ai-widget { position: fixed; right: 1rem; bottom: 1rem; z-index: 2050; font-family: 'Inter', system-ui, sans-serif; }
    .ic-ai-toggle { width: 58px; height: 58px; border: 0; border-radius: 999px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #1a3a2a, #2d6a4f); box-shadow: 0 .9rem 1.8rem rgba(13,31,24,.26); font-weight: 700; font-family: 'DM Mono', monospace; }
    .ic-ai-panel { position: absolute; right: 0; bottom: 4.5rem; width: min(380px, calc(100vw - 2rem)); height: min(620px, calc(100vh - 7rem)); border: 1.5px solid #e8e0d0; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 1.4rem 3rem rgba(13,31,24,.22); display: none; grid-template-rows: auto minmax(0,1fr) auto; }
    .ic-ai-widget.open .ic-ai-panel { display: grid; }
    .ic-ai-head { padding: .85rem 1rem; color: #fff; background: linear-gradient(135deg, #0d1f18, #2d6a4f); display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
    .ic-ai-title { font-family: 'DM Serif Display', serif; font-weight: 400; line-height: 1.2; }
    .ic-ai-sub { font-family: 'DM Mono', monospace; font-size: .68rem; color: rgba(255,255,255,.68); margin-top: .2rem; }
    .ic-ai-close { border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.1); color: #fff; border-radius: 999px; width: 30px; height: 30px; font-weight: 700; }
    .ic-ai-body { overflow: auto; padding: .8rem; background: linear-gradient(180deg, #f7fbf8, #edf7e7); }
    .ic-ai-msg { display: flex; margin-bottom: .72rem; }
    .ic-ai-msg.user { justify-content: flex-end; }
    .ic-ai-bubble { max-width: 86%; border: 1.5px solid #d4edda; border-radius: 14px; padding: .68rem .75rem; background: #fff; color: #0d1f18; line-height: 1.42; white-space: pre-line; overflow-wrap: anywhere; font-size: .9rem; }
    .ic-ai-msg.user .ic-ai-bubble { background: #1f6f4a; color: #fff; border-color: #1f6f4a; }
    .ic-ai-results { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem; margin-top: .6rem; }
    .ic-ai-card { border: 1px solid #d4edda; border-left: 4px solid #52b788; border-radius: 10px; background: #fff; padding: .5rem; }
    .ic-ai-card-label { color: #5f7569; font-family: 'DM Mono', monospace; font-size: .6rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
    .ic-ai-card-value { color: #0d1f18; font-size: .78rem; font-weight: 700; margin-top: .16rem; }
    .ic-ai-typing { display: none; color: #5f7569; font-family: 'DM Mono', monospace; font-size: .74rem; font-weight: 500; padding: 0 .8rem .6rem; background: #edf7e7; }
    .ic-ai-typing.show { display: block; }
    .ic-ai-form { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .5rem; padding: .7rem; border-top: 1px solid #d4edda; background: #fff; }
    .ic-ai-form textarea { min-height: 42px; max-height: 110px; resize: vertical; font-size: .88rem; }
    .ic-ai-send { min-width: 72px; font-weight: 900; }
    .ic-ai-chips { display: flex; flex-wrap: wrap; gap: .4rem; padding: .5rem .7rem 0; background: #fff; }
    .ic-ai-chip { border: 1px solid #d4edda; background: #f7fbf8; color: #1f6f4a; border-radius: 999px; padding: .3rem .6rem; font-size: .72rem; font-weight: 600; }
    .ic-ai-chip:hover { background: #edf7e7; }
    @media (max-width: 575.98px) {
        .ic-ai-widget { right: .65rem; bottom: .65rem; }
        .ic-ai-panel { width: calc(100vw - 1.3rem); height: min(590px, calc(100vh - 6rem)); }
        .ic-ai-results { grid-template-columns: 1fr; }
    }
</style>

<div id="icAiWidget" class="ic-ai-widget" data-message-url="{{ route('ai-chat.message') }}">
    <div class="ic-ai-panel" role="dialog" aria-label="{{ $assistantName }} assistant">
        <div class="ic-ai-head">
            <div>
                <div class="ic-ai-title">{{ $assistantName }}</div>
                <div class="ic-ai-sub">{{ $assistantSubtitle }}</div>
            </div>
            <button type="button" class="ic-ai-close" aria-label="Close assistant">x</button>
        </div>
        <div id="icAiBody" class="ic-ai-body">
            @forelse($widgetChats as $chat)
                <div class="ic-ai-msg user"><div class="ic-ai-bubble">{{ $chat->question }}</div></div>
                <div class="ic-ai-msg assistant">
                    <div class="ic-ai-bubble">
                        {{ $chat->answer }}
                        @php
                            $visibleCards = $widgetPredictionCards($chat);
                        @endphp
                        @if($visibleCards)
                            <div class="ic-ai-results">
                                @if(in_array('weather', $visibleCards, true))
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Weather</div><div class="ic-ai-card-value">{{ data_get($chat->weather_prediction, 'predicted_weather', 'N/A') }}</div></div>
                                @endif
                                @if(in_array('yield', $visibleCards, true))
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Yield</div><div class="ic-ai-card-value">{{ data_get($chat->rice_yield_prediction, 'predicted_yield') !== null ? number_format((float) data_get($chat->rice_yield_prediction, 'predicted_yield'), 2).' t/ha' : 'N/A' }}</div></div>
                                @endif
                                @if(in_array('planting', $visibleCards, true))
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Planting</div><div class="ic-ai-card-value">{{ data_get($chat->planting_recommendation, 'recommendation', data_get($chat->planting_recommendation, 'action', 'N/A')) }}</div></div>
                                @endif
                                @if(in_array('irrigation', $visibleCards, true))
                                    <div class="ic-ai-card"><div class="ic-ai-card-label">Irrigation</div><div class="ic-ai-card-value">{{ data_get($chat->irrigation_recommendation, 'recommendation', 'N/A') }}</div></div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="ic-ai-msg assistant"><div class="ic-ai-bubble">Hello, I am {{ $assistantName }}. Ask me about iClimate features, weather prediction, rice yield, planting, irrigation, climate risk, announcements, notifications, reports, or your profile.</div></div>
            @endforelse
        </div>
        <div id="icAiTyping" class="ic-ai-typing">{{ $assistantName }} is checking iClimate tools...</div>
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
            <textarea id="icAiInput" class="form-control" placeholder="Ask PalayPilot about iClimate..." required></textarea>
            <button id="icAiSend" type="submit" class="btn btn-primary ic-ai-send">Send</button>
        </form>
    </div>
    <button type="button" class="ic-ai-toggle" aria-label="Open {{ $assistantName }} assistant">PP</button>
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
            const yieldValue = chat.rice_yield_prediction?.predicted_yield !== null && chat.rice_yield_prediction?.predicted_yield !== undefined ? `${Number(chat.rice_yield_prediction.predicted_yield).toFixed(2)} t/ha` : 'N/A';
            const planting = chat.planting_recommendation?.recommendation || chat.planting_recommendation?.action || 'N/A';
            const irrigation = chat.irrigation_recommendation?.recommendation || 'N/A';
            const cards = [];
            if (visibleCards.includes('weather')) cards.push(resultCard('Weather', weather));
            if (visibleCards.includes('yield')) cards.push(resultCard('Yield', yieldValue));
            if (visibleCards.includes('planting')) cards.push(resultCard('Planting', planting));
            if (visibleCards.includes('irrigation')) cards.push(resultCard('Irrigation', irrigation));
            return cards.length ? `<div class="ic-ai-results">${cards.join('')}</div>` : '';
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

            body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg user"><div class="ic-ai-bubble">${escapeHtml(question)}</div></div>`);
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
                body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg assistant"><div class="ic-ai-bubble">${escapeHtml(chat.answer)}${predictionGrid(chat)}</div></div>`);
            } catch (error) {
                const message = error.name === 'AbortError'
                    ? 'The assistant took too long. Please ask a shorter iClimate question or try again.'
                    : error.message;
                body.insertAdjacentHTML('beforeend', `<div class="ic-ai-msg assistant"><div class="ic-ai-bubble">${escapeHtml(message)}</div></div>`);
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
