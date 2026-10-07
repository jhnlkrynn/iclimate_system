<x-app-layout>
    @php
        $overall = $it['overall'];
        // Status level -> the shared status pill (colour, icon). The Refresh script swaps the header pill with the same map.
        $icon = fn (string $name): string => view('dashboards.mao.icon', ['name' => $name])->render();
        // "Operational" (warn) is still a working system, so it keeps the green pill; its rows on the System Health card say what to review.
        $pills = ['ok' => ['good', $icon('check')], 'warn' => ['good', $icon('check')], 'crit' => ['watch', $icon('alert')], 'unk' => ['unk', $icon('info')]];
        $pill = $pills[$overall['level']] ?? $pills['unk'];
    @endphp

    {{-- IT Dashboard (2026-10): four things only — System Status, Users by Role, Errors and Failed Jobs (with retry) and
         Security. Usage Patterns, Resources and the Data Pipeline are on the System Health page; the activity log is
         System Logs; the Heat Map is its own page. Same design system as the farmer dashboard (.mfd / .itd). --}}
    <div class="mfd itd" data-it-dashboard data-live-url="{{ route('admin.dashboard.live') }}">
        <header class="mfd-header">
            <div class="mfd-header-main">
                <span class="mfd-kicker">iClimate System Administration</span>
                <h1>IT Personnel Dashboard</h1>
                <p class="itd-lede">System status, users, errors and security at a glance</p>
            </div>
            <div class="mfd-fresh">
                <span class="mfd-pill is-{{ $pill[0] }}" data-it-status>{!! $pill[1] !!}<span data-it-status-text>{{ $overall['label'] }}</span></span>
                <span class="mfd-age" role="status" aria-live="polite">Updated at <span data-it-refreshed>{{ $it['checked_at']->format('g:i:s A') }}</span></span>
                <button type="button" class="mfd-btn is-quiet" data-it-refresh aria-label="Refresh the dashboard">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-it-refresh-text>Refresh</span></button>
            </div>
        </header>

        @if (session('status'))<div class="mfd-note is-good" role="status">@include('dashboards.mao.icon', ['name' => 'check'])<p>{{ session('status') }}</p></div>@endif

        {{-- 1. System Status --}}
        <section class="itd-section" aria-labelledby="itdSectionStatus" data-it-section="status">
            <h2 class="itd-section-title" id="itdSectionStatus">System Status</h2>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-kpis" data-slot="kpis">@include('dashboards.it.kpis')</div></div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-health" data-slot="health">@include('dashboards.it.health')</div></div>
        </section>

        {{-- 2. Users by Role --}}
        <section class="itd-section" aria-label="Users by Role" data-it-section="users">
            {{-- its card carries the "Users by Role" heading --}}
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-roles" data-slot="roles">@include('dashboards.it.roles')</div></div>
        </section>

        {{-- 3. Errors and Failed Jobs --}}
        <section class="itd-section" aria-labelledby="itdSectionErrors" data-it-section="errors">
            <h2 class="itd-section-title" id="itdSectionErrors">Errors and Failed Jobs</h2>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-jobs" data-slot="jobs">@include('dashboards.it.jobs')</div></div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-ops" data-slot="ops">@include('dashboards.it.ops')</div></div>
        </section>

        {{-- 4. Security --}}
        <section class="itd-section" aria-label="Security" data-it-section="security">
            {{-- its card carries the "Security" heading --}}
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-security" data-slot="security">@include('dashboards.it.security')</div></div>
        </section>

        <p class="itd-more">
            <a class="mfd-link" href="{{ route('system-health.index') }}">System Health: usage patterns, resources and the data pipeline →</a>
            <a class="mfd-link" href="{{ route('system-logs.index') }}">System Logs →</a>
        </p>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-it-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);

            // Retry and other confirm-first forms.
            root.addEventListener('submit', (e) => { const msg = e.target.dataset && e.target.dataset.confirm; if (msg && !window.confirm(msg)) e.preventDefault(); });

            // Refresh: each section dims and shimmers (the shared .mfd-slot skeleton) while its new HTML is fetched.
            const pills = @json($pills);
            // force: the Refresh button (?refresh=1 rebuilds every section); the 5-minute timer reads the prepared overview.
            async function refresh(force = false) {
                const btn = $('[data-it-refresh]'); const label = $('[data-it-refresh-text]');
                const slots = [...root.querySelectorAll('[data-slot]')];
                btn.disabled = true; label.textContent = 'Refreshing...'; slots.forEach((s) => s.classList.add('is-loading'));
                try {
                    const res = await fetch(root.dataset.liveUrl + (force === true ? '?refresh=1' : ''), { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    Object.entries(data.slots).forEach(([key, html]) => {
                        const el = root.querySelector('[data-slot="' + key + '"]'); if (el) el.innerHTML = html;
                    });
                    $('[data-it-refreshed]').textContent = data.refreshed_at;
                    const pill = pills[data.level] || pills.unk; const status = $('[data-it-status]');
                    status.className = 'mfd-pill is-' + pill[0];
                    status.innerHTML = pill[1] + '<span data-it-status-text></span>';
                    status.lastElementChild.textContent = data.status;
                } catch (e) {
                    $('[data-it-refreshed]').textContent = 'failed. Showing the last loaded data.';
                } finally {
                    btn.disabled = false; label.textContent = 'Refresh'; slots.forEach((s) => s.classList.remove('is-loading'));
                }
            }
            $('[data-it-refresh]').addEventListener('click', () => refresh(true));
            // Health changes slowly: refresh every 5 minutes, and only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, 300000);
        })();
    </script>
</x-app-layout>
