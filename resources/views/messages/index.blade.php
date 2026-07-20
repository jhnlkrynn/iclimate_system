<x-app-layout>
    <style>
        .msg-wrap { --msg-ink:#0d1f18; --msg-muted:rgba(255,255,255,.5); --msg-line:rgba(255,255,255,.1); --msg-green:#2d6a4f; --msg-mint:#52b788; --msg-blue:#2f6f8f; color: rgba(255,255,255,.85); }
        .msg-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.75rem 1.85rem; color:#fff; background:linear-gradient(145deg,#0d1f18 0%,#1a3a2a 62%,#163324 100%); box-shadow:0 1rem 2.3rem rgba(13,31,24,.16); }
        .msg-hero::before {
            content:"";
            position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                radial-gradient(ellipse at 88% -10%, rgba(82,183,136,.16) 0%, transparent 60%);
            pointer-events:none;
        }
        .msg-hero > * { position:relative; z-index:1; }
        .msg-hero h1 { font-family:'DM Serif Display', Georgia, serif; font-weight:400; letter-spacing:-0.01em; color:#fff; }
        .msg-eyebrow { display:inline-flex; align-items:center; gap:8px; font-family:'DM Mono', monospace; font-size:.7rem; font-weight:500; text-transform:uppercase; letter-spacing:.12em; color:#74c69d; margin-bottom:.4rem; }
        .msg-eyebrow::before { content:''; display:block; width:18px; height:1px; background:#74c69d; }
        .msg-chip { display:inline-flex; align-items:center; gap:.5rem; border:1px solid rgba(255,255,255,.18); border-radius:999px; padding:.5rem .85rem; background:rgba(255,255,255,.06); color:rgba(255,255,255,.85); font-family:'DM Mono', monospace; font-size:.76rem; font-weight:500; letter-spacing:.02em; }
        .msg-pulse { width:8px; height:8px; border-radius:999px; background:#74c69d; box-shadow:0 0 0 5px rgba(116,198,157,.2); flex-shrink:0; }
        .msg-shell { display:grid; grid-template-columns:340px minmax(0,1fr); gap:1rem; margin-top:1rem; align-items:stretch; min-height:clamp(620px, calc(100vh - 210px), 860px); }
        .msg-panel { border:1.5px solid rgba(255,255,255,.1); border-radius:18px; background:#0D1F18; box-shadow:0 .7rem 1.6rem rgba(20,32,51,.07); overflow:hidden; display:flex; flex-direction:column; min-height:0; }
        .msg-head { padding:1rem; border-bottom:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.02); }
        .field-box { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.16); border-radius:10px; padding:.55rem .7rem .45rem; }
        .field-box-label { display:block; font-family:'DM Mono', monospace; font-size:.62rem; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.5); margin-bottom:.3rem; }
        .field-box .form-control, .field-box .form-select { border:0; padding:0; background:transparent; color:#fff; }
        .field-box .form-control:focus, .field-box .form-select:focus { box-shadow:none; }
        .field-box .form-select option { color:#0d1f18; }
        .thread-list { flex:1 1 auto; min-height:0; overflow:auto; }
        .thread-link { display:block; padding:.85rem 1rem; border-bottom:1px solid rgba(255,255,255,.08); color:inherit; text-decoration:none; border-left:4px solid transparent; transition:background .15s ease, border-color .15s ease; }
        .thread-link.active { background:rgba(232,167,61,.14); border-left-color:#e8a73d; }
        .thread-link:hover { background:rgba(255,255,255,.05); }
        .avatar { width:40px; height:40px; border-radius:50%; display:grid; place-items:center; background:rgba(82,183,136,.16); border:1px solid rgba(149,213,178,.3); color:#74c69d; font-family:'DM Mono', monospace; font-weight:700; flex:0 0 auto; }
        .chat-window { flex:1 1 auto; min-height:0; overflow:auto; padding:1rem; background:rgba(255,255,255,.02); }
        .bubble-row { display:flex; margin-bottom:.8rem; }
        .bubble-row.mine { justify-content:flex-end; }
        .bubble { max-width:min(680px,82%); border:1px solid rgba(255,255,255,.12); border-radius:14px; background:rgba(255,255,255,.05); color:#fff; padding:.7rem .85rem; }
        .mine .bubble { background:#2d6a4f; border-color:#2d6a4f; color:#fff; }
        .msg-meta { color:var(--msg-muted); font-family:'DM Mono', monospace; font-size:.7rem; letter-spacing:.02em; margin-top:.3rem; }
        .mine .msg-meta { color:rgba(255,255,255,.72); }
        .msg-actions { display:flex; justify-content:flex-end; margin-top:.5rem; }
        .msg-unsend { border:1px solid rgba(255,255,255,.52); border-radius:999px; background:rgba(255,255,255,.14); color:#fff; font-family:'DM Mono', monospace; font-size:.68rem; font-weight:700; padding:.22rem .55rem; line-height:1.2; }
        .msg-unsend:hover { background:rgba(255,255,255,.24); border-color:rgba(255,255,255,.82); color:#fff; text-decoration:none; }
        .msg-compose { padding:1rem; border-top:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.02); }
        .msg-empty { flex:1 1 auto; min-height:420px; display:grid; place-items:center; padding:2rem; }
        .unsend-confirm { position:fixed; inset:0; z-index:2300; display:none; place-items:center; padding:1rem; background:rgba(13,31,24,.48); backdrop-filter:blur(5px); }
        .unsend-confirm.show { display:grid; }
        .unsend-card { width:min(420px,100%); overflow:hidden; border:1px solid rgba(212,237,218,.9); border-radius:20px; background:linear-gradient(145deg,#fff,#f7fbf8); box-shadow:0 1.4rem 3.4rem rgba(13,31,24,.34); }
        .unsend-card::before { content:""; display:block; height:7px; background:linear-gradient(90deg,#d85b45,#e8a73d,#52b788); }
        .unsend-body { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.85rem; padding:1.05rem; }
        .unsend-icon { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; background:#fff0ea; color:#d85b45; font-weight:900; font-size:1.25rem; }
        .unsend-title { margin:0 0 .3rem; color:#0d1f18; font-size:1.08rem; font-weight:900; }
        .unsend-text { margin:0; color:#3d5a48; line-height:1.45; }
        .unsend-actions { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; padding:0 1.05rem 1.05rem; }
        .unsend-cancel, .unsend-submit { border:0; border-radius:999px; padding:.72rem 1rem; font-weight:900; }
        .unsend-cancel { background:#f0f7f4; color:#1a3a2a; }
        .unsend-submit { background:#d85b45; color:#fff; box-shadow:0 .75rem 1.45rem rgba(216,91,69,.22); }
        .msg-wrap .text-muted { color: rgba(255,255,255,.5) !important; }
        .msg-wrap .alert-success { background: rgba(82,183,136,.14); border-color: rgba(116,198,157,.35); color: #d8f3dc; }
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
            .msg-compose { padding:.75rem; }
            .msg-empty { min-height:300px; padding:1.25rem; }
        }
    </style>

    <div class="msg-wrap">
        <section class="msg-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="msg-eyebrow">Messages</div>
                    <h1 class="h3 mb-1">Farmer and MAO conversations</h1>
                    <p class="mb-0 text-white-50">Private messages for follow-ups, concerns, activity questions, and document sharing.</p>
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
                    <div class="msg-eyebrow" style="color:#74c69d;">Start a thread</div>
                    <h2 class="h5 mb-3">New Message</h2>
                    <form method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" class="d-grid gap-2">
                        @csrf
                        <div class="field-box">
                            <label class="field-box-label" for="newMessageRecipient">Recipient</label>
                            <select id="newMessageRecipient" name="recipient_id" class="form-select" required>
                                <option value="">Choose recipient</option>
                                @foreach($recipients as $recipient)
                                    <option value="{{ $recipient->id }}">{{ $recipient->name }} | {{ $recipient->role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field-box">
                            <label class="field-box-label" for="newMessageBody">Message</label>
                            <textarea id="newMessageBody" name="body" class="form-control" rows="3" placeholder="Write your message..." required></textarea>
                        </div>
                        <div class="field-box">
                            <label class="field-box-label" for="newMessageAttachment">Attachment (Optional)</label>
                            <input id="newMessageAttachment" name="attachment" type="file" class="form-control">
                        </div>
                        <button class="btn btn-primary fw-bold" type="submit">Send</button>
                    </form>
                </div>
                <div class="thread-list">
                    @forelse($conversations as $conversation)
                        @php
                            $other = $conversation->otherParticipant(auth()->user());
                            $latest = $conversation->latestMessage;
                        @endphp
                        <a class="thread-link {{ $activeConversation?->id === $conversation->id ? 'active' : '' }}" href="{{ route('messages.show', $conversation) }}">
                            <div class="d-flex gap-2 align-items-start">
                                <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ $other?->name ?? 'User' }}</div>
                                    <div class="small text-muted">{{ $other?->role }}</div>
                                    <div class="small text-muted text-truncate">{{ $latest?->body ?: ($latest?->attachment_name ? 'Attachment: '.$latest->attachment_name : 'No messages yet') }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-muted">No conversations yet.</div>
                    @endforelse
                </div>
            </aside>

            <main class="msg-panel">
                @if($activeConversation)
                    @php($other = $activeConversation->otherParticipant(auth()->user()))
                    <div class="msg-head">
                        <div class="d-flex gap-2 align-items-center">
                            <div class="avatar">{{ str($other?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                            <div>
                                <h2 class="h5 fw-bold mb-0">{{ $other?->name ?? 'Conversation' }}</h2>
                                <div class="small text-muted">{{ $other?->role }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="chat-window" id="chatWindow">
                        @foreach($messages as $message)
                            <div class="bubble-row {{ $message->sender_id === auth()->id() ? 'mine' : '' }}">
                                <div class="bubble">
                                    @if($message->body)
                                        <div style="white-space:pre-line;">{{ $message->body }}</div>
                                    @endif
                                    @if($message->attachment_path)
                                        <a class="{{ $message->sender_id === auth()->id() ? 'text-white' : '' }}" href="{{ asset('storage/'.$message->attachment_path) }}" target="_blank">{{ $message->attachment_name }}</a>
                                    @endif
                                    <div class="msg-meta">{{ $message->sender?->name }} | {{ $message->created_at?->format('M d, h:i A') }}</div>
                                    @if($message->sender_id === auth()->id())
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
                    <form class="msg-compose" method="POST" action="{{ route('messages.reply', $activeConversation) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-2">
                            <div class="col-lg-8"><textarea name="body" class="form-control" rows="2" placeholder="Type a reply..." required></textarea></div>
                            <div class="col-lg-3"><input name="attachment" type="file" class="form-control"></div>
                            <div class="col-lg-1 d-grid"><button class="btn btn-primary fw-bold" type="submit">Send</button></div>
                        </div>
                    </form>
                @else
                    <div class="msg-empty text-center text-muted">
                        <h2 class="h5 fw-bold" style="color:#fff;">Select or start a conversation</h2>
                        <p class="mb-0">Use messages for private farmer concerns, MAO coordination, and follow-up questions.</p>
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
            const chat = document.getElementById('chatWindow');
            if (chat) chat.scrollTop = chat.scrollHeight;

            const modal = document.getElementById('unsendConfirm');
            const cancel = modal?.querySelector('[data-unsend-cancel]');
            const submit = modal?.querySelector('[data-unsend-submit]');
            let pendingForm = null;

            const close = () => {
                modal?.classList.remove('show');
                pendingForm = null;
            };

            document.querySelectorAll('[data-unsend-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.confirmed === 'true') return;
                    event.preventDefault();
                    pendingForm = form;
                    modal?.classList.add('show');
                });
            });

            cancel?.addEventListener('click', close);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) close();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal?.classList.contains('show')) close();
            });
            submit?.addEventListener('click', () => {
                if (!pendingForm) return;
                pendingForm.dataset.confirmed = 'true';
                pendingForm.submit();
            });
        });
    </script>
</x-app-layout>
