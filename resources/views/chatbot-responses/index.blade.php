<x-app-layout>
    <style>
        .chatbot-kb-grid { display:grid; gap:1rem; }
        .chatbot-kb-answer { max-width:560px; white-space:normal; color:var(--ic-ink-mid); }
        .chatbot-kb-keywords { display:flex; flex-wrap:wrap; gap:.35rem; max-width:420px; }
        .chatbot-kb-keyword { border:1px solid #d4e6da; border-radius:999px; padding:.18rem .5rem; background:#f7fbf8; color:#1f6f4a; font-weight:800; font-size:.75rem; }
        .chatbot-kb-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }
        @media (max-width:767.98px) {
            .chatbot-kb-actions { justify-content:flex-start; }
        }
    </style>

    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Climora AI</div>
                <h1 class="h2 fw-bold mb-2">Chatbot Responses</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Manage rule-based keywords, answers, role visibility, and activation status.</p>
            </div>
            <a class="btn btn-light align-self-start align-self-xl-end" href="{{ route('chatbot-responses.create') }}">Create Response</a>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <details class="ic-collapse" @if($search || $status || $role) data-force-open="true" @endif>
        <summary><span class="ic-collapse-title">Filters</span></summary>
        <div class="card filter-panel no-lift mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET" data-loading="true">
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold">Search</label>
                        <input class="form-control form-control-lg" name="search" value="{{ $search }}" placeholder="Search question, answer, or keywords">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select form-select-lg" name="status">
                            <option value="">All</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-semibold">Visible To</label>
                        <select class="form-select form-select-lg" name="role">
                            <option value="">All roles</option>
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-auto d-flex gap-2">
                        <button class="btn btn-outline-primary btn-lg" type="submit" data-loading-text="Filtering...">Apply</button>
                        <a class="btn btn-outline-secondary btn-lg" href="{{ route('chatbot-responses.index') }}">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </details>

    <div class="card no-lift">
        <div class="card-header border-0 d-flex flex-column flex-md-row justify-content-between gap-2 py-3">
            <div class="fw-semibold">Managed Answers</div>
            <div class="small text-muted">Showing {{ $responses->firstItem() ?? 0 }}-{{ $responses->lastItem() ?? 0 }} of {{ $responses->total() }}</div>
        </div>
        @if($responses->count())
            <div class="table-responsive ic-table-hide-mobile">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Question</th>
                            <th>Language</th>
                            <th>Keywords & Synonyms</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Used</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($responses as $response)
                            <tr>
                                <td class="fw-semibold">{{ $response->category }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $response->question }}</div>
                                    <div class="chatbot-kb-answer">{{ \Illuminate\Support\Str::limit($response->answer, 140) }}</div>
                                </td>
                                <td>{{ $response->language }}</td>
                                <td>
                                    <div class="chatbot-kb-keywords">
                                        @foreach(array_slice($response->keywords ?? [], 0, 8) as $keyword)
                                            <span class="chatbot-kb-keyword">{{ $keyword }}</span>
                                        @endforeach
                                        @foreach(array_slice($response->synonyms ?? [], 0, 5) as $synonym)
                                            <span class="chatbot-kb-keyword">{{ $synonym }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $response->visible_roles_text }}</td>
                                <td><span class="badge text-bg-{{ $response->status === 'Active' ? 'success' : 'secondary' }}">{{ $response->status }}</span></td>
                                <td>{{ $response->times_used }}</td>
                                <td class="text-end">
                                    <div class="chatbot-kb-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('chatbot-responses.edit', $response) }}">Edit</a>
                                        @if($response->status === 'Active')
                                            <form method="POST" action="{{ route('chatbot-responses.deactivate', $response) }}" data-loading="true">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" data-loading-text="Updating...">Deactivate</button></form>
                                        @else
                                            <form method="POST" action="{{ route('chatbot-responses.activate', $response) }}" data-loading="true">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success" data-loading-text="Updating...">Activate</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('chatbot-responses.destroy', $response) }}" data-loading="true" onsubmit="return confirm('Delete this chatbot response?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-loading-text="Deleting...">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body ic-table-cards">
                @foreach($responses as $response)
                    <div class="ic-table-card">
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Category</div><div class="ic-table-card-value">{{ $response->category }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Question</div><div class="ic-table-card-value">{{ $response->question }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Language</div><div class="ic-table-card-value">{{ $response->language }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Keywords</div><div class="ic-table-card-value">{{ $response->keywords_text }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Synonyms</div><div class="ic-table-card-value">{{ $response->synonyms_text ?: 'None' }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Roles</div><div class="ic-table-card-value">{{ $response->visible_roles_text }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Status</div><div class="ic-table-card-value">{{ $response->status }}</div></div>
                        <div class="ic-table-card-actions chatbot-kb-actions">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('chatbot-responses.edit', $response) }}">Edit</a>
                            <form method="POST" action="{{ $response->status === 'Active' ? route('chatbot-responses.deactivate', $response) : route('chatbot-responses.activate', $response) }}" data-loading="true">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary" data-loading-text="Updating...">{{ $response->status === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                            <form method="POST" action="{{ route('chatbot-responses.destroy', $response) }}" data-loading="true" onsubmit="return confirm('Delete this chatbot response?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-loading-text="Deleting...">Delete</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-body border-top d-flex justify-content-center justify-content-md-end">
                {{ $responses->links() }}
            </div>
        @else
            <div class="card-body text-center p-5">
                <div class="h5 mb-2">No chatbot responses found</div>
                <p class="text-muted mb-3">Create a response so Climora can answer that topic from database keywords.</p>
                <a class="btn btn-outline-primary" href="{{ route('chatbot-responses.create') }}">Create Response</a>
            </div>
        @endif
    </div>
</x-app-layout>
