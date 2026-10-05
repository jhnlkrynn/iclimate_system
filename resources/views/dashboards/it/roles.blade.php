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
        {{-- Six columns that fit the card at every width; on phones each role becomes its own block (data-label names each figure). --}}
        <div class="itd-table-wrap">
            <table class="itd-table is-roles">
                <thead><tr><th scope="col">Role</th><th scope="col" class="itd-num">Total accounts</th><th scope="col" class="itd-num">Share</th><th scope="col" class="itd-num">Enabled</th><th scope="col" class="itd-num">Disabled</th><th scope="col" class="itd-num">Active users</th></tr></thead>
                <tbody>
                    @foreach ($r['rows'] as $row)
                        <tr data-role="{{ $row['role'] }}">
                            <th scope="row">{{ $row['label'] }}</th>
                            <td class="itd-num" data-label="Total accounts">{{ number_format($row['total']) }}</td>
                            <td class="itd-num" data-label="Share"><span class="itd-share"><span class="mfd-bar is-mine" aria-hidden="true"><i style="width: {{ $share($row) }}%"></i></span>{{ $pct($share($row)) }}</span></td>
                            <td class="itd-num" data-label="Enabled">{{ number_format($row['enabled']) }}</td>
                            <td class="itd-num" data-label="Disabled">{{ number_format($row['disabled']) }}</td>
                            <td class="itd-num" data-label="Active users"><span class="itd-active">{{ number_format($row['active']) }} <small>{{ $pct($row['pct']) }}</small></span></td>
                        </tr>
                    @endforeach
                    <tr class="itd-total">
                        <th scope="row">All roles</th>
                        <td class="itd-num" data-label="Total accounts">{{ number_format($r['total']) }}</td>
                        <td class="itd-num" data-label="Share">100%</td>
                        <td class="itd-num" data-label="Enabled">{{ number_format($r['enabled']) }}</td>
                        <td class="itd-num" data-label="Disabled">{{ number_format($r['disabled']) }}</td>
                        <td class="itd-num" data-label="Active users"><span class="itd-active">{{ number_format($r['active']) }} <small>{{ $pct($r['pct']) }}</small></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mfd-foot">Enabled and disabled are the account status set in User Management. Active users signed in or used the system in the last 30 days, shown with their share of the role.</p>
    @endif
</section>
