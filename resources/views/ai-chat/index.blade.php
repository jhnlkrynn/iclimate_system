@php
    $assistantName = 'Climora AI';
    $assistantSubtitle = 'iClimate rice guidance powered by Groq llama-3.3 and Predict.py';
    $predictionCards = function ($chat): array {
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

<x-app-layout>
    <style>
        .ai-console { --ai-ink:#0d1f18; --ai-muted:#5f7569; --ai-green:#2d6a4f; --ai-mint:#52b788; --ai-blue:#1677b8; --ai-gold:#f4b63f; --ai-red:#d85b45; --ai-line:rgba(153,185,160,.72); width:100%; max-width:100%; overflow-x:hidden; }
        .ai-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.35rem; margin-bottom:1.25rem; color:#1f2a24; background:radial-gradient(circle at 84% 12%, rgba(82,183,136,.16), transparent 30%), linear-gradient(145deg,#f6f9f7 0%,#e7f0ea 100%); border:1px solid #e3ece6; box-shadow:0 1rem 2.3rem rgba(31,42,36,.08); }
        .ai-hero::before { content:""; position:absolute; inset:0; background:linear-gradient(90deg,rgba(31,42,36,.05) 1px,transparent 1px),linear-gradient(0deg,rgba(31,42,36,.04) 1px,transparent 1px); background-size:38px 38px; mask-image:linear-gradient(90deg,rgba(0,0,0,.78),transparent 88%); }
        .ai-hero > * { position:relative; z-index:1; }
        .ai-eyebrow { font-size:.72rem; font-weight:900; text-transform:uppercase; letter-spacing:.12em; color:#2d6a4f; }
        .ai-hero h1 { overflow-wrap:anywhere; }
        .ai-hero p { max-width:min(820px,100%); }
        .ai-hero .text-white-50 { color:#4a5c52 !important; }
        .ai-hero-actions { display:flex; justify-content:flex-end; width:auto; }
        .ai-hero-actions .btn-outline-light { color:#1f2a24; border-color:#c3d3ca; }
        .ai-hero-actions .btn-outline-light:hover { background:rgba(45,106,79,.08); border-color:#2d6a4f; color:#1f2a24; }
        .ai-shell { display:grid; grid-template-columns:minmax(0,1fr) minmax(280px,340px); gap:1rem; align-items:start; min-width:0; }
        .ai-panel { border:1px solid var(--ai-line); border-radius:18px; background:linear-gradient(145deg,rgba(244,250,239,.98),rgba(232,244,230,.98)); box-shadow:0 .9rem 2rem rgba(20,32,51,.07); overflow:hidden; }
        .ai-panel-header { display:flex; justify-content:space-between; gap:1rem; align-items:center; padding:1rem; border-bottom:1px solid rgba(153,185,160,.58); background:rgba(226,241,219,.58); }
        .ai-panel-title { margin:0; color:var(--ai-ink); font-size:1rem; font-weight:900; }
        .ai-panel-sub { margin:.15rem 0 0; color:var(--ai-muted); font-size:.82rem; }
        .ai-chat-window { height:clamp(430px,58vh,680px); min-height:0; overflow:auto; padding:1rem; background:linear-gradient(180deg,#f7fbf8,#edf7e7); overscroll-behavior:contain; }
        .ai-message { display:flex; margin-bottom:1rem; }
        .ai-message.user { justify-content:flex-end; }
        .ai-bubble { max-width:min(760px,86%); min-width:0; border:1px solid #d4edda; border-radius:8px; padding:.85rem .95rem; background:#fff; color:var(--ai-ink); box-shadow:0 .45rem 1rem rgba(13,31,24,.05); white-space:pre-line; line-height:1.46; overflow-wrap:anywhere; word-break:normal; }
        .ai-message.user .ai-bubble { background:#1f6f4a; color:#fff; border-color:#1f6f4a; }
        .ai-meta { color:#789081; font-size:.74rem; font-weight:800; margin-top:.45rem; }
        .ai-message.user .ai-meta { color:rgba(255,255,255,.72); }
        .ai-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.75rem; padding:1rem; border-top:1px solid var(--ai-line); background:#fff; align-items:end; }
        .ai-form textarea { min-height:54px; max-height:140px; resize:vertical; border-color:#cfe8d3; width:100%; }
        .ai-send { min-width:116px; min-height:54px; font-weight:900; }
        .ai-chip-grid { display:grid; grid-template-columns:1fr; gap:.55rem; padding:1rem; }
        .ai-chip { border:1px solid #d4edda; border-radius:8px; background:#fff; color:var(--ai-ink); padding:.72rem .8rem; text-align:left; font-weight:800; line-height:1.25; overflow-wrap:anywhere; transition:transform .15s ease,border-color .15s ease,background .15s ease; }
        .ai-chip:hover { transform:translateY(-1px); border-color:var(--ai-mint); background:#f0f7f4; }
        .ai-result-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; margin-top:.8rem; min-width:0; }
        .ai-result-card { border:1px solid #d4edda; border-left:5px solid var(--accent,#52b788); border-radius:8px; background:#fff; padding:.75rem; }
        .ai-result-label { color:var(--ai-muted); font-size:.68rem; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
        .ai-result-value { color:var(--ai-ink); font-weight:900; margin-top:.25rem; line-height:1.25; overflow-wrap:anywhere; }
        .ai-aside { position:sticky; top:1rem; }
        .ai-prompt-sections { display:grid; gap:0; }
        .ai-typing { display:none; padding:0 1rem 1rem; color:var(--ai-muted); font-weight:800; }
        .ai-typing.show { display:block; }
        .ai-save-choice { margin-top:.75rem; display:grid; gap:.5rem; }
        .ai-save-choice-label { color:#3d5a48; font-weight:900; font-size:.86rem; }
        .ai-save-actions { display:flex; flex-wrap:wrap; gap:.5rem; }
        .ai-save-btn { border:1px solid #95d5b2; border-radius:999px; background:#fff; color:#1f6f4a; padding:.4rem .75rem; font-weight:900; }
        .ai-save-btn.active { background:#1f6f4a; border-color:#1f6f4a; color:#fff; }
        .ai-privacy-note { padding:.75rem 1rem; border-top:1px solid var(--ai-line); background:#f7fbf8; color:var(--ai-muted); font-weight:800; font-size:.84rem; }
        .dot { display:inline-block; width:.42rem; height:.42rem; border-radius:999px; background:var(--ai-mint); margin-left:.2rem; animation:pulse 1s infinite alternate; }
        .dot:nth-child(2) { animation-delay:.15s; } .dot:nth-child(3) { animation-delay:.3s; }
        @keyframes pulse { from { opacity:.35; transform:translateY(0); } to { opacity:1; transform:translateY(-3px); } }
        @media (max-width:1199.98px) {
            .ai-shell { grid-template-columns:1fr; }
            .ai-aside { position:static; }
            .ai-chat-window { height:clamp(420px,58vh,620px); }
            .ai-chip-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .ai-prompt-sections { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:767.98px) {
            .ai-hero { padding:1rem; margin-bottom:1rem; }
            .ai-hero-actions, .ai-hero-actions form, .ai-hero-actions .btn { width:100%; }
            .ai-panel-header { align-items:flex-start; flex-direction:column; padding:.9rem; }
            .ai-chat-window { height:min(58vh,520px); min-height:340px; padding:.75rem; }
            .ai-form { grid-template-columns:1fr; padding:.75rem; gap:.55rem; }
            .ai-send { width:100%; min-height:46px; }
            .ai-bubble { max-width:94%; padding:.75rem .8rem; font-size:.92rem; }
            .ai-result-grid { grid-template-columns:1fr; }
            .ai-prompt-sections { grid-template-columns:1fr; }
            .ai-chip-grid { grid-template-columns:1fr; padding:.75rem; }
        }
        @media (max-width:420px) {
            .ai-chat-window { height:min(56vh,460px); min-height:300px; }
            .ai-bubble { max-width:100%; }
            .ai-message.user .ai-bubble { max-width:96%; }
            .ai-meta { font-size:.68rem; }
        }
    </style>

    <div class="ai-console">
        <section class="ai-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="ai-eyebrow mb-2">{{ $assistantName }}</div>
                    <h1 class="h2 fw-bold mb-2">Chat with climate-aware rice guidance</h1>
                    <p class="mb-0 text-white-50" style="max-width:820px;">{{ $assistantSubtitle }}. Ask natural farming questions and get weather, yield, planting, irrigation, and warning guidance.</p>
                </div>
                <div class="ai-hero-actions">
                    <form method="POST" action="{{ route('ai-chat.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-light fw-bold" type="submit">Clear Conversation</button>
                    </form>
                </div>
            </div>
        </section>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="ai-shell">
            <section class="ai-panel">
                <div class="ai-panel-header">
                    <div>
                        <h2 class="ai-panel-title">Conversation</h2>
                        <p class="ai-panel-sub">Guidance combines Groq responses, Predict.py yield estimation, weather inputs, and iClimate decision rules.</p>
                    </div>
                </div>
                <div id="chatWindow" class="ai-chat-window">
                    @forelse($chats as $chat)
                        <div class="ai-message user">
                            <div class="ai-bubble">{{ $chat->question }}<div class="ai-meta">{{ $chat->created_at?->shortDateTime() }}</div></div>
                        </div>
                        <div class="ai-message assistant">
                            <div class="ai-bubble">
                                {{ $chat->answer }}
                                @php
                                    $visibleCards = $predictionCards($chat);
                                @endphp
                                @if($visibleCards)
                                    <div class="ai-result-grid">
                                        @if(in_array('weather', $visibleCards, true))
                                            <div class="ai-result-card"><div class="ai-result-label">Weather</div><div class="ai-result-value">{{ data_get($chat->weather_prediction, 'predicted_weather', 'N/A') }}</div></div>
                                        @endif
                                        @if(in_array('yield', $visibleCards, true))
                                            <div class="ai-result-card ai-yield"><div class="ai-result-label">Yield</div><div class="ai-result-value">{{ data_get($chat->rice_yield_prediction, 'predicted_yield') !== null ? number_format((float) data_get($chat->rice_yield_prediction, 'predicted_yield'), 2).' t/ha' : 'N/A' }}</div></div>
                                        @endif
                                        @if(in_array('planting', $visibleCards, true))
                                            <div class="ai-result-card"><div class="ai-result-label">Planting</div><div class="ai-result-value">{{ data_get($chat->planting_recommendation, 'recommendation', data_get($chat->planting_recommendation, 'action', 'N/A')) }}</div></div>
                                        @endif
                                        @if(in_array('irrigation', $visibleCards, true))
                                            <div class="ai-result-card ai-irrigation"><div class="ai-result-label">Irrigation</div><div class="ai-result-value">{{ data_get($chat->irrigation_recommendation, 'recommendation', 'N/A') }}</div></div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="ai-message assistant">
                            <div class="ai-bubble">
                                Hello, I am {{ $assistantName }}. Ask me about planting, irrigation, rainfall, drought, fertilizer, climate warnings, or rice yield.
                                <div class="ai-save-choice" data-ai-save-choice>
                                    <div class="ai-save-choice-label">Should this AI conversation be saved for memory and history?</div>
                                    <div class="ai-save-actions">
                                        <button type="button" class="ai-save-btn" data-save-mode="1">Save conversation</button>
                                        <button type="button" class="ai-save-btn" data-save-mode="0">Do not save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
                <div id="typingIndicator" class="ai-typing">{{ $assistantName }} is checking the models<span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
                <div id="aiPrivacyNote" class="ai-privacy-note"></div>
                <form id="chatForm" class="ai-form">
                    @csrf
                    <textarea id="questionInput" name="question" class="form-control" placeholder="Ask Climora AI: Should I plant rice next week?" required></textarea>
                    <button id="sendButton" class="btn btn-primary ai-send" type="submit">Send</button>
                </form>
            </section>

            <aside class="ai-panel ai-aside">
                <div class="ai-prompt-sections">
                    <div>
                        <div class="ai-panel-header">
                            <div>
                                <h2 class="ai-panel-title">Quick Actions</h2>
                                <p class="ai-panel-sub">Tap a prompt to start.</p>
                            </div>
                        </div>
                        <div class="ai-chip-grid">
                            @foreach([
                                'Weather Prediction' => 'Will it rain and what should I prepare for my rice field?',
                                'Predict Rice Yield' => 'Predict my rice yield using the latest climate record.',
                                'Planting Advice' => 'Should I plant rice this week?',
                                'Irrigation Advice' => 'Is today a good day to irrigate my farm?',
                                'Climate Warnings' => 'Do I have drought, flood, heat, wind, or water shortage risk?',
                                'Fertilizer Guidance' => 'What fertilizer steps can help if my expected yield is low?',
                                'Smart Scenario' => 'Rainfed farm, dry season, rainfall is 60mm, humidity 50%. Should I irrigate?',
                                'Pest Check' => 'After heavy rain, what pest or disease signs should I check in rice?',
                            ] as $label => $prompt)
                                <button type="button" class="ai-chip" data-prompt="{{ $prompt }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="ai-panel-header">
                            <div>
                                <h2 class="ai-panel-title">Suggested Questions</h2>
                                <p class="ai-panel-sub">Farmer-friendly examples.</p>
                            </div>
                        </div>
                        <div class="ai-chip-grid">
                            @foreach([
                                'When should I plant rice?',
                                'What causes low rice yield?',
                                'What is the effect of heavy rainfall?',
                                'What should I do during drought?',
                                'May 80mm ulan at rainfed ang bukid ko. Pwede na bang magtanim?',
                            ] as $prompt)
                                <button type="button" class="ai-chip" data-prompt="{{ $prompt }}">{{ $prompt }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('chatForm');
            const input = document.getElementById('questionInput');
            const chatWindow = document.getElementById('chatWindow');
            const typing = document.getElementById('typingIndicator');
            const sendButton = document.getElementById('sendButton');
            const token = document.querySelector('input[name="_token"]').value;
            const privacyNote = document.getElementById('aiPrivacyNote');
            const saveStorageKey = 'iclimate_ai_save_conversation';
            const scrollToBottom = () => { chatWindow.scrollTop = chatWindow.scrollHeight; };
            const escapeHtml = (value) => value.replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
            const resultCard = (label, value, extra = '') => `<div class="ai-result-card ${extra}"><div class="ai-result-label">${label}</div><div class="ai-result-value">${escapeHtml(String(value || 'N/A'))}</div></div>`;
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
            const selectedSaveMode = () => localStorage.getItem(saveStorageKey);
            const shouldSaveConversation = () => selectedSaveMode() !== '0';
            const renderSaveChoice = () => {
                const mode = selectedSaveMode();
                document.querySelectorAll('[data-save-mode]').forEach((btn) => {
                    btn.classList.toggle('active', btn.dataset.saveMode === mode);
                });
                if (!privacyNote) return;
                if (mode === '0') {
                    privacyNote.textContent = 'Conversation saving is off. New AI replies will not be saved to your history.';
                } else if (mode === '1') {
                    privacyNote.textContent = 'Conversation saving is on. Climora AI can use recent saved chats as memory.';
                } else {
                    privacyNote.textContent = 'Choose whether Climora AI should save this conversation.';
                }
            };
            document.querySelectorAll('[data-save-mode]').forEach((button) => {
                button.addEventListener('click', () => {
                    localStorage.setItem(saveStorageKey, button.dataset.saveMode);
                    renderSaveChoice();
                    input.focus();
                });
            });
            renderSaveChoice();
            const addUser = (question) => {
                chatWindow.insertAdjacentHTML('beforeend', `<div class="ai-message user"><div class="ai-bubble">${escapeHtml(question)}<div class="ai-meta">Just now</div></div></div>`);
                scrollToBottom();
            };
            const addAssistant = (chat) => {
                const weather = chat.weather_prediction?.predicted_weather || 'N/A';
                const yieldValue = chat.rice_yield_prediction?.predicted_yield !== null && chat.rice_yield_prediction?.predicted_yield !== undefined ? `${Number(chat.rice_yield_prediction.predicted_yield).toFixed(2)} t/ha` : 'N/A';
                const planting = chat.planting_recommendation?.recommendation || chat.planting_recommendation?.action || 'N/A';
                const irrigation = chat.irrigation_recommendation?.recommendation || 'N/A';
                const visibleCards = relevantPredictionCards(chat);
                const cards = [];
                if (visibleCards.includes('weather')) cards.push(resultCard('Weather', weather));
                if (visibleCards.includes('yield')) cards.push(resultCard('Yield', yieldValue, 'ai-yield'));
                if (visibleCards.includes('planting')) cards.push(resultCard('Planting', planting));
                if (visibleCards.includes('irrigation')) cards.push(resultCard('Irrigation', irrigation, 'ai-irrigation'));
                const resultGrid = cards.length ? `<div class="ai-result-grid">${cards.join('')}</div>` : '';
                chatWindow.insertAdjacentHTML('beforeend', `<div class="ai-message assistant"><div class="ai-bubble">${escapeHtml(chat.answer)}
                    ${resultGrid}
                </div></div>`);
                scrollToBottom();
            };
            document.querySelectorAll('[data-prompt]').forEach((button) => {
                button.addEventListener('click', () => {
                    input.value = button.dataset.prompt;
                    input.focus();
                });
            });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const question = input.value.trim();
                if (!question) return;
                addUser(question);
                input.value = '';
                typing.classList.add('show');
                sendButton.disabled = true;
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 45000);
                try {
                    const response = await fetch('{{ route('ai-chat.message') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ question, save_conversation: shouldSaveConversation() }),
                        signal: controller.signal,
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'The assistant could not answer right now.');
                    addAssistant(data.chat);
                } catch (error) {
                    const message = error.name === 'AbortError'
                        ? 'The assistant took too long to answer. Please try a shorter question, or make sure the local farming model API is running.'
                        : error.message;
                    addAssistant({ answer: message, source_type: 'Error', weather_prediction: null, rice_yield_prediction: null, planting_recommendation: null, irrigation_recommendation: null, response_time_ms: 0, confidence_score: 0 });
                } finally {
                    clearTimeout(timeout);
                    typing.classList.remove('show');
                    sendButton.disabled = false;
                    input.focus();
                }
            });
            scrollToBottom();
        });
    </script>
</x-app-layout>
