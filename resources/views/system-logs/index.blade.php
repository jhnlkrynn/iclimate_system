<x-app-layout>
    @php
        $icons = [
            'login' => '<path d="M10 3h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4M3 10h9m0 0-3-3m3 3-3 3"/>',
            'logout' => '<path d="M10 3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M8 10h9m0 0-3-3m3 3-3 3"/>',
            'create' => '<path d="M10 4v12M4 10h12"/>',
            'update' => '<path d="m4 13.5-.5 3 3-.5L15.5 7 13 4.5 4 13.5Z"/>',
            'delete' => '<path d="M5 6h10M8 6V4h4v2M6.5 6l.5 10h6l.5-10"/>',
            'prediction' => '<path d="M3 15l4-5 3 3 6-8"/>',
            'other' => '<circle cx="10" cy="10" r="6"/><path d="M10 7v3.5l2 1.5"/>',
        ];
        $icon = fn (string $k) => '<svg class="sl-ico" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$k] ?? $icons['other']).'</svg>';
        $hasFilters = collect($filters)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
    @endphp

    <style>
        .sl-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .sl-card { padding: 1rem 1.15rem; border-radius: 16px; background: rgba(255,255,255,.72); border: 1px solid rgba(95,143,120,.25); }
        .sl-card .sl-num { font-size: 1.9rem; font-weight: 800; color: #1f5a46; line-height: 1.1; }
        .sl-card .sl-lbl { font-size: .95rem; font-weight: 600; color: #374151; }
        .sl-card .sl-sub { font-size: .8rem; color: #64748b; overflow-wrap: anywhere; }
        .sl-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; align-items: end; }
        .sl-filters .sl-search { grid-column: 1 / -1; }
        .sl-filters label { font-size: .85rem; font-weight: 600; margin-bottom: .2rem; }
        .sl-table { font-size: 1rem; }
        .sl-table th { font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; color: #3d4f45; white-space: nowrap; }
        .sl-table td { vertical-align: middle; }
        .sl-action { display: flex; align-items: center; gap: .6rem; font-weight: 600; }
        .sl-ico-wrap { flex: none; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: rgba(63,166,107,.14); color: #1f5a46; }
        .sl-badge { display: inline-block; padding: .15rem .65rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .sl-badge.is-ok { background: rgba(142,155,163,.2); color: #374151; }
        .sl-badge.is-fail { background: rgba(224,113,79,.18); color: #9a3a1f; }
        .sl-table td.sl-role, .sl-table td.sl-role * { color: #2b3a33 !important; opacity: 1 !important; }
        .sl-empty { text-align: center; padding: 2.5rem 1rem; color: #64748b; }
        .sl-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }

        /* Card layout while the content column is narrow: no horizontal scroll. MAO/IT – Naicel (2026-10-07): up to 991px,
           because from 768px the fixed sidebar now sits beside the page and the table no longer fits there. */
        .sl-pager nav { max-width: 100%; }
        .sl-pager nav > div { flex-wrap: wrap; row-gap: .5rem; }
        .sl-pager .pagination { flex-wrap: wrap; }
        @media (max-width: 991.98px) {
            .sl-table thead { display: none; }
            .sl-table, .sl-table tbody, .sl-table tr, .sl-table td { display: block; width: 100%; }
            .sl-table tr { padding: .75rem 0; border-bottom: 1px solid rgba(95,143,120,.25); }
            .sl-table td { border: 0; padding: .2rem 0; }
            .sl-table td[data-label]::before { content: attr(data-label) ": "; font-weight: 600; color: #64748b; font-size: .85rem; }
            .sl-table td.sl-td-action::before { content: none; }
        }

        .sl-raw { max-height: 280px; overflow: auto; padding: 12px; border-radius: 6px; background: #f8faf9; border: 1px solid #e3e8e5; font-size: .8125rem; white-space: pre-wrap; overflow-wrap: anywhere; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .sl-card { background: rgba(15,44,35,.6); }
            :root:not([data-theme="light"]) .sl-card .sl-num { color: #7fd6b5; }
            :root:not([data-theme="light"]) .sl-card .sl-lbl { color: #e3ece7; }
            :root:not([data-theme="light"]) .sl-card .sl-sub { color: #b9cbc2; }
        }
        :root[data-theme="dark"] .sl-card { background: rgba(15,44,35,.6); }
        :root[data-theme="dark"] .sl-card .sl-num { color: #7fd6b5; }
        :root[data-theme="dark"] .sl-card .sl-lbl { color: #e3ece7; }
        :root[data-theme="dark"] .sl-card .sl-sub { color: #b9cbc2; }
    </style>

    <section class="page-hero">
        <div class="eyebrow mb-2">Activity</div>
        <h1 class="h2 fw-bold mb-2">System Logs</h1>
        <p class="mb-0" style="color: var(--ic-ink-mid);">See who did what, and when.</p>
    </section>

    <div class="sl-cards">
        <div class="sl-card"><div class="sl-num">{{ number_format($summary['total']) }}</div><div class="sl-lbl">Total Activities</div><div class="sl-sub">{{ $summary['total'] === 0 ? 'No activity recorded yet' : 'All recorded activity' }}</div></div>
        <div class="sl-card"><div class="sl-num">{{ number_format($summary['today']) }}</div><div class="sl-lbl">Today's Activities</div><div class="sl-sub">{{ $summary['today'] === 0 ? 'None yet today' : \App\Support\BusinessClock::now()->format('M j, Y') }}</div></div>
        <div class="sl-card"><div class="sl-num">{{ number_format($summary['logins_today']) }}</div><div class="sl-lbl">Logins Today</div><div class="sl-sub">{{ $summary['logins_today'] === 0 ? 'No sign-ins yet today' : 'Sign-ins since midnight' }}</div></div>
        <div class="sl-card"><div class="sl-num">{{ number_format($summary['recent_users']) }}</div><div class="sl-lbl">Recent Users</div><div class="sl-sub">{{ $summary['recent_names'] ? implode(', ', $summary['recent_names']).($summary['recent_users'] > 3 ? ' and others' : '') : 'No users active in the last 7 days' }}</div></div>
    </div>

    <div class="card no-lift mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('system-logs.index') }}" class="sl-filters">
                <div class="sl-search">
                    <label for="slSearch">Search</label>
                    <input id="slSearch" class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search activity...">
                </div>
                <div>
                    <label for="slAction">Action</label>
                    <select id="slAction" name="action" class="form-select">
                        <option value="">All Actions</option>
                        @foreach ($actions as $label)
                            <option value="{{ $label }}" @selected($filters['action'] === $label)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="slUser">User</label>
                    <select id="slUser" name="user" class="form-select">
                        <option value="">All Users</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected($filters['user'] === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="slRole">Role</label>
                    <select id="slRole" name="role" class="form-select">
                        <option value="">All Roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="slDate">Date</label>
                    <input id="slDate" class="form-control" type="date" name="date" value="{{ $filters['date'] }}">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('system-logs.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card no-lift">
        <div class="card-body">
            @if ($rows->isEmpty())
                <div class="sl-empty">
                    <strong>{{ $hasFilters ? 'No activity matches your filters.' : 'No activity has been recorded yet.' }}</strong>
                    @if ($hasFilters)<div class="mt-1">Try a different search, or press Reset.</div>@endif
                </div>
            @else
                <table class="table sl-table mb-0">
                    <thead>
                        <tr><th>Action</th><th>User</th><th>Role</th><th>Date &amp; Time</th><th title="Recorded means iClimate logged the activity. It does not confirm the outcome.">Status</th><th class="text-end">View</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="sl-td-action"><div class="sl-action"><span class="sl-ico-wrap">{!! $icon($row['kind']) !!}</span>{{ $row['action'] }}</div></td>
                                <td data-label="User">{{ $row['user'] }}</td>
                                <td data-label="Role" class="sl-role">{{ $row['role'] }}</td>
                                <td data-label="Time">{{ $row['short'] }}</td>
                                <td data-label="Status"><span class="sl-badge {{ $row['status'] === 'Failed' ? 'is-fail' : 'is-ok' }}">{{ $row['status'] }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-sl-view="{{ $loop->index }}" aria-haspopup="dialog">View</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        @if ($logs->total() > 0)
            <div class="card-footer sl-pager">
                <span class="small text-muted">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }} activities</span>
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <script type="application/json" id="slData">@json($rows->values())</script>
    <script>
        // "View" opens the activity in the shared iClimate information pop-up (resources/js/info-modal.js).
        (function () {
            const rows = JSON.parse(document.getElementById('slData').textContent);
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-sl-view]');
                const r = button && rows[Number(button.dataset.slView)];
                if (!r || !window.iClimateModal) return;
                const statusNote = r.status === 'Failed'
                    ? 'This log explicitly records a failure, error or denied request.'
                    : 'Recorded means iClimate logged this activity. It does not confirm the outcome.';
                const technical = Object.entries(r.technical || {}).map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('');
                window.iClimateModal.open({
                    title: 'Activity Details',
                    kicker: 'System Logs',
                    description: r.action || '',
                    icon: r.status === 'Failed' ? 'danger' : 'report',
                    tone: r.status === 'Failed' ? 'danger' : '',
                    trigger: button,
                    body: `<section class="fad-m-section"><dl class="fad-m-details">
                            <div><dt>Activity</dt><dd>${esc(r.action)}</dd></div>
                            <div><dt>User</dt><dd>${esc(r.user)}</dd></div>
                            <div><dt>Role</dt><dd>${esc(r.role)}</dd></div>
                            <div><dt>Date &amp; Time</dt><dd>${esc(r.long)}</dd></div>
                            <div><dt>Status</dt><dd>${esc(r.status)}<br><span class="fad-m-small">${esc(statusNote)}</span></dd></div>
                            <div><dt>Additional Information</dt><dd>${esc(r.description) || '—'}</dd></div>
                        </dl></section>
                        <details class="fad-details"><summary>Technical Details</summary>
                            <dl class="fad-m-details">${technical}</dl>
                            <p class="fad-label" style="margin-top:12px">Raw log data</p>
                            <pre class="sl-raw">${esc(r.raw)}</pre>
                        </details>`,
                });
            });
        })();
    </script>
</x-app-layout>
