<x-app-layout>
    @php
        $overall = $it['overall'];
        // Status level -> the shared status pill (colour, icon). The Refresh script swaps the header pill with the same map.
        $icon = fn (string $name): string => view('dashboards.mao.icon', ['name' => $name])->render();
        // "Operational" (warn) is still a working system, so it keeps the green pill; its rows on the System Health card say what to review.
        $pills = ['ok' => ['good', $icon('check')], 'warn' => ['good', $icon('check')], 'crit' => ['watch', $icon('alert')], 'unk' => ['unk', $icon('info')]];
        $pill = $pills[$overall['level']] ?? $pills['unk'];
        $statusTitle = match ($overall['level']) {
            'ok' => 'System ready',
            'crit' => 'Needs attention',
            default => 'System ready with items to review',
        };
        $statusMessage = match ($overall['level']) {
            'ok' => 'User management, reports, and messages are available.',
            'crit' => 'Some important system functions may need attention.',
            default => 'User management, reports, and messages remain available while selected items are reviewed.',
        };
    @endphp

    {{-- IT Dashboard: a simple work surface for MAO IT staff. Technical diagnostics remain available from System Health
         and System Logs, but the normal dashboard focuses on accounts, messages, reports, activity, and settings. --}}
    <div class="mfd itd" data-it-dashboard data-live-url="{{ route('admin.dashboard.live') }}">
        <header class="mtd-header mfd-header">
            <div class="mtd-header-main mfd-header-main">
                <span class="mtd-kicker mfd-kicker">iClimate System Management</span>
                <h1>IT Personnel Dashboard</h1>
                <p class="itd-lede">Manage users, messages, reports, and account settings</p>
                <span class="mtd-place">Municipality of Lian, Batangas · iClimate</span>
            </div>
            <div class="mtd-tools mfd-fresh">
                <span class="mfd-pill is-{{ $pill[0] }}" data-it-status>{!! $pill[1] !!}<span data-it-status-text>{{ $overall['label'] }}</span></span>
                <span class="mfd-age" role="status" aria-live="polite">Updated at <span data-it-refreshed>{{ $it['checked_at']->format('g:i:s A') }}</span></span>
                <button type="button" class="mfd-btn is-quiet" data-it-refresh aria-label="Refresh the dashboard">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-it-refresh-text>Refresh</span></button>
            </div>
        </header>

        <section class="itd-status-banner is-{{ $pill[0] }}" aria-labelledby="itdStatusTitle">
            <span class="itd-status-icon" aria-hidden="true">{!! $icon($overall['level'] === 'crit' ? 'alert' : 'check') !!}</span>
            <div>
                <p class="itd-status-kicker">System status</p>
                <h2 id="itdStatusTitle">{{ $statusTitle }}</h2>
                <p>{{ $statusMessage }}</p>
            </div>
            <span class="mfd-pill is-{{ $pill[0] }}">{!! $pill[1] !!}<span>{{ $overall['label'] }}</span></span>
        </section>

        @if (session('status'))<div class="mfd-note is-good" role="status">@include('dashboards.mao.icon', ['name' => 'check'])<p>{{ session('status') }}</p></div>@endif

        <section class="itd-section" aria-labelledby="itdSectionWork" data-it-section="work">
            <h2 class="itd-section-title" id="itdSectionWork">Your Work Area</h2>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-staff" data-slot="staff">@include('dashboards.it.staff')</div></div>
        </section>

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
