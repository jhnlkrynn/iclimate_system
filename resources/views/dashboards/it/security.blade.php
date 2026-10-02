@php
    $s = $it['security'];
    $l = $it['extra']['logins'];
    $n = fn ($v) => $v === null ? 'Not available' : number_format($v);
    $rows = [
        ['Failed login attempts (24h)', $s['failed_24h'], ($s['failed_24h'] ?? 0) > 0, null],
        ['Failed login attempts (7d)', $s['failed_7d'], ($s['failed_7d'] ?? 0) > 0, null],
        ['Login lockouts (24h)', $s['lockouts_24h'], ($s['lockouts_24h'] ?? 0) > 0, null],
        ['Password resets requested (7d)', $s['resets_7d'], false, null],
        ['Privileged actions (7d)', $s['privileged_7d'], false, null],
        ['Dormant accounts (no login in 90+ days)', $s['dormant'], ($s['dormant'] ?? 0) > 0, route('users.index')],
        ['Accounts that never logged in', $it['extra']['never'], ($it['extra']['never'] ?? 0) > 0, route('users.index', ['integrity' => 'never_logged_in'])],
    ];
@endphp
<section class="itd-card is-{{ ($s['failed_24h'] ?? 0) > 0 || ($s['lockouts_24h'] ?? 0) > 0 ? 'warn' : 'ok' }}" aria-labelledby="itdSecTitle" data-it-security>
    <div class="itd-card-head">
        <div><h2 id="itdSecTitle">Security</h2><p class="itd-sub">Logins, lockouts, resets and privileged activity</p>@include('dashboards.it._updated', ['at' => $it['stamps']['security'] ?? $it['stamps']['now']])</div>
        <a class="itd-link" href="{{ route('system-logs.index') }}">View logs</a>
    </div>
    <dl class="itd-rows">
        @foreach ($rows as [$label, $value, $flag, $link])
            <div><dt>{{ $label }}</dt><dd class="itd-num {{ $value === null ? 'itd-muted' : '' }}">
                @if ($flag)<span class="itd-badge is-warn"><span aria-hidden="true">▲</span> {{ $n($value) }}</span>@else{{ $n($value) }}@endif
                @if ($flag && $link ?? false)<a class="itd-link" href="{{ $link }}">Review</a>@endif
            </dd></div>
        @endforeach
        @if (($l['state'] ?? 'error') === 'ok')
            <div data-it-offhours><dt>Logins outside normal hours (10 PM–5 AM, 7d)</dt><dd class="itd-num">
                @if ($l['off_hours'] > 0)<span class="itd-badge is-warn"><span aria-hidden="true">▲</span> {{ number_format($l['off_hours']) }}</span> <a class="itd-link" href="{{ route('system-logs.index', ['action' => 'Logged In']) }}">Review</a>@else 0 @endif
            </dd></div>
        @endif
    </dl>

    @if (($l['state'] ?? 'error') === 'error')
        <p class="itd-error" role="alert">Unable to read login activity.</p>
    @else
        <h3 class="itd-h3" data-it-failed-ips>Failed logins by IP <span class="itd-period">top 5, last 7 days</span></h3>
        @if (count($l['ips']) === 0)
            <p class="itd-allgood"><span aria-hidden="true">✓</span> No failed logins in the last 7 days 👍</p>
        @else
            <div class="itd-table-wrap">
                <table class="itd-table">
                    <thead><tr><th scope="col">IP address</th><th scope="col" class="itd-num">Failed attempts</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @foreach ($l['ips'] as $row)
                            <tr>
                                <td class="itd-mono">{{ $row['ip'] }}</td>
                                <td class="itd-num">@if ($row['count'] >= 5)<span class="itd-badge is-crit"><span aria-hidden="true">✕</span> {{ number_format($row['count']) }}</span>@else{{ number_format($row['count']) }}@endif</td>
                                <td><a class="itd-link" href="{{ route('system-logs.index', ['search' => $row['ip'], 'action' => 'Failed Login']) }}">Investigate</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="itd-foot">Five or more failures from one IP in a week may be a brute-force attempt.</p>
        @endif

        <h3 class="itd-h3" data-it-admin-actions>Admin actions by user <span class="itd-period">last 7 days</span></h3>
        @if (count($l['admins']) === 0)
            <p class="itd-empty">No administrative actions in the last 7 days. Changes made by admins will be listed here.</p>
        @else
            <dl class="itd-rows">
                @foreach ($l['admins'] as $a)
                    <div><dt>{{ $a['name'] }}</dt><dd class="itd-num">{{ number_format($a['count']) }} <a class="itd-link" href="{{ route('system-logs.index', ['user' => $a['id']]) }}">View</a></dd></div>
                @endforeach
            </dl>
        @endif
    @endif
    <p class="itd-foot">Privileged actions are role changes, deletions, archives and exports. Failed logins and lockouts are counted from when they began being logged.</p>
</section>
