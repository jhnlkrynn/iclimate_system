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
        .sl-table th { font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; white-space: nowrap; }
        .sl-table td { vertical-align: middle; }
        .sl-action { display: flex; align-items: center; gap: .6rem; font-weight: 600; }
        .sl-ico-wrap { flex: none; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: rgba(63,166,107,.14); color: #1f5a46; }
        .sl-badge { display: inline-block; padding: .15rem .65rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .sl-badge.is-ok { background: rgba(142,155,163,.2); color: #374151; }
        .sl-badge.is-fail { background: rgba(224,113,79,.18); color: #9a3a1f; }
        .sl-role { color: #4b5563; }
        .sl-empty { text-align: center; padding: 2.5rem 1rem; color: #64748b; }
        .sl-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }

        /* Card layout on phones: no horizontal scroll */
        @media (max-width: 767.98px) {
            .sl-table thead { display: none; }
            .sl-table, .sl-table tbody, .sl-table tr, .sl-table td { display: block; width: 100%; }
            .sl-table tr { padding: .75rem 0; border-bottom: 1px solid rgba(95,143,120,.25); }
            .sl-table td { border: 0; padding: .2rem 0; }
            .sl-table td[data-label]::before { content: attr(data-label) ": "; font-weight: 600; color: #64748b; font-size: .85rem; }
            .sl-table td.sl-td-action::before { content: none; }
        }

        .sl-modal { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .sl-modal[hidden] { display: none; }
        .sl-modal-bg { position: absolute; inset: 0; background: rgba(8,25,19,.55); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
        .sl-modal-panel { position: relative; width: min(620px, 100%); max-height: 90vh; display: flex; flex-direction: column; border-radius: 20px; color: #14261f; background: rgba(255,255,255,.88); border: 1px solid rgba(255,255,255,.6); box-shadow: 0 24px 60px rgba(8,25,19,.35); -webkit-backdrop-filter: blur(18px); backdrop-filter: blur(18px); overflow: hidden; }
        .sl-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(20,38,31,.1); }
        .sl-modal-head h2 { margin: 0; font-size: 1.2rem; font-weight: 700; }
        .sl-modal-body { padding: 1rem 1.25rem; overflow-y: auto; overflow-x: hidden; overflow-wrap: anywhere; font-size: 1rem; }
        .sl-modal-foot { padding: .8rem 1.25rem; border-top: 1px solid rgba(20,38,31,.1); text-align: right; }
        .sl-field { margin-bottom: .8rem; }
        .sl-field dt { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #2f7d5b; }
        .sl-field dd { margin: 0; font-size: 1.05rem; }
        .sl-tech { margin-top: .5rem; border: 1px solid rgba(20,38,31,.12); border-radius: 12px; background: rgba(20,38,31,.04); }
        .sl-tech summary { padding: .65rem .9rem; font-weight: 600; cursor: pointer; }
        .sl-tech-body { padding: 0 .9rem .8rem; }
        .sl-tech pre { margin: .3rem 0 0; padding: .7rem; max-height: 220px; overflow: auto; font-size: .8rem; white-space: pre-wrap; word-break: break-all; background: rgba(20,38,31,.07); border-radius: 8px; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .sl-card { background: rgba(15,44,35,.6); }
            :root:not([data-theme="light"]) .sl-card .sl-num { color: #7fd6b5; }
            :root:not([data-theme="light"]) .sl-card .sl-lbl, :root:not([data-theme="light"]) .sl-table th, :root:not([data-theme="light"]) .sl-role { color: #cbd5d0; }
            :root:not([data-theme="light"]) .sl-modal-panel { color: #e7f2ec; background: rgba(15,44,35,.86); }
        }
        :root[data-theme="dark"] .sl-card { background: rgba(15,44,35,.6); }
        :root[data-theme="dark"] .sl-card .sl-num { color: #7fd6b5; }
        :root[data-theme="dark"] .sl-modal-panel { color: #e7f2ec; background: rgba(15,44,35,.86); }
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

    <div class="sl-modal" id="slModal" hidden>
        <div class="sl-modal-bg" data-sl-close></div>
        <div class="sl-modal-panel" role="dialog" aria-modal="true" aria-labelledby="slModalTitle" tabindex="-1">
            <div class="sl-modal-head">
                <h2 id="slModalTitle">Activity Details</h2>
                <button type="button" class="btn-close" data-sl-close aria-label="Close"></button>
            </div>
            <div class="sl-modal-body">
                <dl class="mb-0">
                    <div class="sl-field"><dt>Activity</dt><dd data-f="action"></dd></div>
                    <div class="sl-field"><dt>User</dt><dd data-f="user"></dd></div>
                    <div class="sl-field"><dt>Role</dt><dd data-f="role"></dd></div>
                    <div class="sl-field"><dt>Date &amp; Time</dt><dd data-f="long"></dd></div>
                    <div class="sl-field"><dt>Status</dt><dd><span data-f="status"></span><div class="small text-muted" data-f="statusNote"></div></dd></div>
                    <div class="sl-field"><dt>Additional Information</dt><dd data-f="description"></dd></div>
                </dl>
                <details class="sl-tech">
                    <summary>Technical Details</summary>
                    <div class="sl-tech-body">
                        <dl class="mb-2" data-f="technical"></dl>
                        <dt class="small fw-bold text-uppercase" style="color:#2f7d5b;">Raw log data</dt>
                        <pre data-f="raw"></pre>
                    </div>
                </details>
            </div>
            <div class="sl-modal-foot"><button type="button" class="btn btn-primary" data-sl-close>Close</button></div>
        </div>
    </div>

    <script type="application/json" id="slData">@json($rows->values())</script>
    <script>
        (function () {
            const modal = document.getElementById('slModal');
            const rows = JSON.parse(document.getElementById('slData').textContent);
            const panel = modal.querySelector('.sl-modal-panel');
            let opener = null;
            const set = (key, text) => { modal.querySelector(`[data-f="${key}"]`).textContent = text; };

            function open(index, trigger) {
                const r = rows[index];
                if (!r) return;
                opener = trigger;
                ['action', 'user', 'role', 'long', 'status', 'description', 'raw'].forEach((k) => set(k, r[k] ?? ''));
                set('statusNote', r.status === 'Failed'
                    ? 'This log explicitly records a failure, error or denied request.'
                    : 'Recorded means iClimate logged this activity. It does not confirm the outcome.');
                const tech = modal.querySelector('[data-f="technical"]');
                tech.replaceChildren();
                Object.entries(r.technical).forEach(([k, v]) => {
                    const dt = document.createElement('dt'); dt.textContent = k; dt.className = 'small fw-bold';
                    const dd = document.createElement('dd'); dd.textContent = v; dd.className = 'mb-1';
                    tech.append(dt, dd);
                });
                modal.querySelector('.sl-tech').open = false;
                modal.hidden = false;
                document.documentElement.style.overflow = 'hidden';
                panel.focus();
            }
            function close() {
                modal.hidden = true;
                document.documentElement.style.overflow = '';
                if (opener) opener.focus();
            }
            document.querySelectorAll('[data-sl-view]').forEach((b) => b.addEventListener('click', () => open(Number(b.dataset.slView), b)));
            modal.addEventListener('click', (e) => { if (e.target.closest('[data-sl-close]')) close(); });
            document.addEventListener('keydown', (e) => {
                if (modal.hidden) return;
                if (e.key === 'Escape') { close(); return; }
                if (e.key === 'Tab') {
                    const f = [...modal.querySelectorAll('button, summary, [href]')].filter((n) => n.offsetParent !== null);
                    if (!f.length) return;
                    const first = f[0]; const last = f[f.length - 1];
                    if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) { e.preventDefault(); last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                }
            });
        })();
    </script>
</x-app-layout>
