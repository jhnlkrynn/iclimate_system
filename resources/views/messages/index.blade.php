<x-app-layout>
    <style>
        .msg-wrap { --msg-ink:#0d1f18; --msg-muted:#607468; --msg-line:rgba(153,185,160,.7); --msg-green:#2d6a4f; --msg-mint:#52b788; --msg-blue:#1677b8; }
        .msg-hero { border-radius:8px; padding:1.25rem; color:#fff; background:linear-gradient(135deg,#0d1f18,#146b78 52%,#0d6a41); box-shadow:0 1rem 2.3rem rgba(13,31,24,.16); }
        .msg-shell { display:grid; grid-template-columns:340px minmax(0,1fr); gap:1rem; margin-top:1rem; align-items:stretch; min-height:clamp(620px, calc(100vh - 210px), 860px); }
        .msg-panel { border:1px solid var(--msg-line); border-radius:8px; background:#fff; box-shadow:0 .7rem 1.6rem rgba(20,32,51,.07); overflow:hidden; display:flex; flex-direction:column; min-height:0; }
        .msg-head { padding:1rem; border-bottom:1px solid #e4efe7; background:linear-gradient(145deg,#fff,#f2f8f3); }
        .thread-list { flex:1 1 auto; min-height:0; overflow:auto; }
        .thread-link { display:block; padding:.85rem 1rem; border-bottom:1px solid #edf3ee; color:inherit; text-decoration:none; }
        .thread-link.active, .thread-link:hover { background:#edf7e7; }
        .avatar { width:40px; height:40px; border-radius:50%; display:grid; place-items:center; background:#d8f3dc; color:#1f6f4a; font-weight:900; flex:0 0 auto; }
        .chat-window { flex:1 1 auto; min-height:0; overflow:auto; padding:1rem; background:linear-gradient(180deg,#f7fbf8,#edf7e7); }
        .bubble-row { display:flex; margin-bottom:.8rem; }
        .bubble-row.mine { justify-content:flex-end; }
        .bubble { max-width:min(680px,82%); border:1px solid #d4edda; border-radius:8px; background:#fff; padding:.7rem .85rem; }
        .mine .bubble { background:#1f6f4a; border-color:#1f6f4a; color:#fff; }
        .msg-meta { color:var(--msg-muted); font-size:.76rem; margin-top:.3rem; }
        .mine .msg-meta { color:rgba(255,255,255,.72); }
        .msg-compose { padding:1rem; border-top:1px solid #e4efe7; background:#fff; }
        .msg-empty { flex:1 1 auto; min-height:420px; display:grid; place-items:center; padding:2rem; }
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
                    <div class="small text-white-50 fw-bold text-uppercase mb-1">Messages</div>
                    <h1 class="h3 fw-bold mb-1">Farmer and MAO conversations</h1>
                    <p class="mb-0 text-white-50">Private messages for follow-ups, concerns, activity questions, and document sharing.</p>
                </div>
                <a href="{{ route('community-feed.index') }}" class="btn btn-light fw-bold">Community Feed</a>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="msg-shell">
            <aside class="msg-panel">
                <div class="msg-head">
                    <h2 class="h5 fw-bold mb-3">New Message</h2>
                    <form method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" class="d-grid gap-2">
                        @csrf
                        <select name="recipient_id" class="form-select" required>
                            <option value="">Choose recipient</option>
                            @foreach($recipients as $recipient)
                                <option value="{{ $recipient->id }}">{{ $recipient->name }} | {{ $recipient->role }}</option>
                            @endforeach
                        </select>
                        <textarea name="body" class="form-control" rows="3" placeholder="Write your message..." required></textarea>
                        <input name="attachment" type="file" class="form-control">
                        <button class="btn btn-primary fw-bold" type="submit">Send</button>
                    </form>
                </div>
                <div class="thread-list">
                    @forelse($conversations as $conversation)
                        @php
                            $other = $conversation->otherParticipant(auth()->user());
                            $latest = $conversation->messages->first();
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
                        <h2 class="h5 fw-bold text-dark">Select or start a conversation</h2>
                        <p class="mb-0">Use messages for private farmer concerns, MAO coordination, and follow-up questions.</p>
                    </div>
                @endif
            </main>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chat = document.getElementById('chatWindow');
            if (chat) chat.scrollTop = chat.scrollHeight;
        });
    </script>
</x-app-layout>
