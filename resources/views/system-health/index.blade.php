@php
    $icon = fn (string $name): string => view('dashboards.mao.icon', ['name' => $name])->render();
    $check = fn (string $key): ?array => collect($checks)->firstWhere('key', $key);
    $statusTone = fn (string $status): string => match ($status) {
        \App\Services\SystemHealthService::HEALTHY => 'is-good',
        \App\Services\SystemHealthService::NEEDS_ATTENTION => 'is-watch',
        default => 'is-unk',
    };
    $overallNeedsAttention = ((int) ($summary['needs_attention'] ?? 0) + (int) ($summary['unavailable'] ?? 0)) > 0;
    $overallLabel = $overallNeedsAttention ? 'Needs Attention' : 'Working';
    $overallTone = $overallNeedsAttention ? 'is-watch' : 'is-good';
    $weather = $check('weather');
    $prediction = $check('prediction');
    $friendlyState = fn (?array $row, string $good, string $attention): string => ($row['status'] ?? null) === \App\Services\SystemHealthService::HEALTHY ? $good : $attention;

    $itData = $it ?? [];
    $roles = $itData['roles'] ?? [];
    $registrations = $itData['extra']['registrations'] ?? [];
    $loginStats = $itData['extra']['logins'] ?? [];
    $integrity = $itData['integrity'] ?? [];
    $number = fn (mixed $value): string => $value === null ? '—' : number_format((int) $value);

    $attentionItems = [];
    if (($integrity['without_profile'] ?? 0) > 0) {
        $attentionItems[] = number_format((int) $integrity['without_profile']).' farmer accounts need profile completion.';
    }
    foreach ($checks as $row) {
        if (($row['status'] ?? null) === \App\Services\SystemHealthService::HEALTHY) {
            continue;
        }
        $message = match ($row['key'] ?? '') {
            'weather' => 'Weather data is delayed.',
            'prediction' => 'Predictions are unavailable.',
            'queue', 'failed-jobs', 'analytics', 'scheduler' => 'Tasks need review.',
            'database' => 'System connection needs attention.',
            default => 'Some system data needs attention.',
        };
        if (! in_array($message, $attentionItems, true)) {
            $attentionItems[] = $message;
        }
        if (count($attentionItems) >= 5) {
            break;
        }
    }
@endphp

<x-app-layout>
    <div class="mfd itd itp itp-simple-health">
        <x-it.page-header kicker="iClimate System Administration" title="System Status" lede="See what is working, what needs attention, and what to do next.">
            <span class="mfd-pill {{ $overallTone }}">{!! $icon($overallNeedsAttention ? 'alert' : 'check') !!}<span>{{ $overallLabel }}</span></span>
            <div class="itp-header-meta" role="status"><span class="mfd-age">Last updated {{ $checkedAt->format('M j, Y g:i A') }}</span></div>
            <div class="itd-header-actions"><a class="mfd-btn is-quiet" href="{{ route('system-health.index', ['refresh' => 1]) }}">@include('dashboards.mao.icon', ['name' => 'refresh'])Refresh Data</a></div>
        </x-it.page-header>

        <section class="itp-section" aria-labelledby="shStatusTitle">
            <div class="itp-section-head"><h2 id="shStatusTitle">System Status</h2><p>A quick view of the services people use every day.</p></div>
            <div class="itp-simple-status" aria-label="System status summary">
                <div class="itp-simple-status-item"><span class="itp-simple-label">System</span><strong class="{{ $overallTone }}">{{ $overallLabel }}</strong></div>
                <div class="itp-simple-status-item"><span class="itp-simple-label">Weather Data</span><strong class="{{ $statusTone($weather['status'] ?? '') }}">{{ $friendlyState($weather, 'Updated', 'Delayed') }}</strong></div>
                <div class="itp-simple-status-item"><span class="itp-simple-label">Predictions</span><strong class="{{ $statusTone($prediction['status'] ?? '') }}">{{ $friendlyState($prediction, 'Available', 'Unavailable') }}</strong></div>
                <div class="itp-simple-status-item"><span class="itp-simple-label">Last Updated</span><strong>{{ $checkedAt->format('M j, Y g:i A') }}</strong></div>
            </div>
        </section>

        <section class="itp-section" aria-labelledby="shAttentionTitle">
            <div class="itp-section-head"><h2 id="shAttentionTitle">Needs Attention</h2><p>Only the most important items that may need action.</p></div>
            <div class="itp-attention-box {{ $attentionItems === [] ? 'is-clear' : 'is-watch' }}">
                @if ($attentionItems === [])
                    {!! $icon('check') !!}<p>Nothing important needs attention right now.</p>
                @else
                    {!! $icon('alert') !!}<ul>@foreach (array_slice($attentionItems, 0, 5) as $item)<li>{{ $item }}</li>@endforeach</ul>
                @endif
            </div>
        </section>

        <section class="itp-section" aria-labelledby="shActivityTitle">
            <div class="itp-section-head"><h2 id="shActivityTitle">User Activity</h2><p>Simple account and sign-in activity.</p></div>
            <div class="itp-activity-grid">
                <div><span>Total users</span><strong>{{ $number($roles['total'] ?? null) }}</strong></div>
                <div><span>Active users</span><strong>{{ $number($roles['active'] ?? null) }}</strong></div>
                <div><span>New accounts this week</span><strong>{{ $number(collect($registrations['counts'] ?? [])->last()) }}</strong></div>
                <div><span>Recent logins</span><strong>{{ $number($loginStats['success'] ?? null) }}</strong><small>last 7 days</small></div>
            </div>
        </section>

        <section class="itp-section" aria-labelledby="shActionsTitle">
            <div class="itp-section-head"><h2 id="shActionsTitle">Quick Actions</h2><p>Open the task you need.</p></div>
            <div class="itp-quick-actions">
                <a class="mfd-btn" href="{{ route('users.index') }}">Manage Users</a>
                <a class="mfd-btn" href="{{ route('reports.index') }}">View Reports</a>
                <a class="mfd-btn" href="{{ route('messages.index') }}">Open Messages</a>
                <a class="mfd-btn" href="{{ route('system-health.index', ['refresh' => 1]) }}">Refresh Data</a>
                <a class="mfd-btn" href="{{ route('admin.settings.index') }}">Settings</a>
            </div>
        </section>

        <details class="itp-advanced">
            <summary>Advanced Details</summary>
            <p class="itp-advanced-note">Technical information is available here when support staff need it.</p>
            <section class="itp-section" aria-labelledby="shChecksTitle">
                <div class="itp-section-head"><h2 id="shChecksTitle">Detailed Checks</h2><p>Technical details are kept separate from the normal work view.</p></div>
                <div class="itp-grid">
                    @foreach ($checks as $row)
                        @php $tone = $statusTone($row['status']); @endphp
                        <article class="mfd-card itp-check" data-health-check="{{ $row['key'] }}" data-health-status="{{ $row['status'] }}">
                            <div class="itp-check-head"><h3 class="itp-card-title">{{ $row['title'] }}</h3><span class="mfd-pill {{ $tone }}">{{ $row['status'] }}</span></div>
                            <p>{{ $row['summary'] }}</p>
                            @if (! empty($row['details']))<dl class="mfd-facts itd-rows">@foreach ($row['details'] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>@endif
                        </article>
                    @endforeach
                </div>
            </section>
            @if ($itData !== [])
                <section class="itp-section" aria-labelledby="shTechnicalDataTitle">
                    <div class="itp-section-head"><h2 id="shTechnicalDataTitle">Technical Data</h2></div>
                    <div class="itd-row"><div class="itd-cell">@include('dashboards.it.usage')</div></div>
                    <div class="itd-row"><div class="itd-cell">@include('dashboards.it.pipeline')</div></div>
                </section>
            @endif
        </details>
    </div>
</x-app-layout>
