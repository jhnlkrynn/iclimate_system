@php
    $r = $it['roles'];
    $pct = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 1), '0'), '.').'%' : 'Not available';
    $share = fn ($row) => $r['total'] > 0 ? round($row['total'] / $r['total'] * 100, 1) : 0;
@endphp
<section class="mfd-card itd-card" aria-labelledby="itdRolesTitle" data-it-roles>
    <div class="mfd-card-head">
        <div><h2 id="itdRolesTitle">Users by Role</h2><p class="itd-sub">Accounts, their status, and who has been active in 30 days</p>@include('dashboards.it._updated', ['at' => $it['stamps']['roles'] ?? $it['stamps']['now']])</div>
        <a class="mfd-link" href="{{ route('users.index') }}">Manage users</a>
    </div>
    @if (($r['state'] ?? 'error') === 'error')
        @include('dashboards.it._state', ['kind' => 'error', 'text' => 'Unable to retrieve user counts.'])
    @elseif ($r['state'] === 'empty')
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No user accounts available.'])
    @else
        <div class="itd-table-wrap">
            <table class="itd-table is-roles">
                <thead><tr><th scope="col">Role</th><th scope="col" class="itd-num">Total accounts</th><th scope="col" class="itd-num">Share</th><th scope="col" class="itd-num">Enabled</th><th scope="col" class="itd-num">Disabled</th><th scope="col" class="itd-num">Active (30 days)</th><th scope="col" class="itd-num">Active %</th></tr></thead>
                <tbody>
                    @foreach ($r['rows'] as $row)
                        <tr data-role="{{ $row['role'] }}">
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td class="itd-num">{{ number_format($row['total']) }}</td>
                            <td class="itd-num"><span class="itd-share"><span class="mfd-bar is-mine" aria-hidden="true"><i style="width: {{ $share($row) }}%"></i></span>{{ $pct($share($row)) }}</span></td>
                            <td class="itd-num">{{ number_format($row['enabled']) }}</td>
                            <td class="itd-num">{{ number_format($row['disabled']) }}</td>
                            <td class="itd-num">{{ number_format($row['active']) }}</td>
                            <td class="itd-num">{{ $pct($row['pct']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="itd-total"><td><strong>All roles</strong></td><td class="itd-num">{{ number_format($r['total']) }}</td><td class="itd-num">100%</td><td class="itd-num">{{ number_format($r['enabled']) }}</td><td class="itd-num">{{ number_format($r['disabled']) }}</td><td class="itd-num">{{ number_format($r['active']) }}</td><td class="itd-num">{{ $pct($r['pct']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mfd-foot">Enabled and disabled are the account status set in User Management. Active means the account logged in or was used in the last 30 days.</p>
    @endif
</section>
