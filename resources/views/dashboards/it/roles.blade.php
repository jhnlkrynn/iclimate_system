@php
    $r = $it['roles'];
    $pct = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 1), '0'), '.').'%' : 'Not available';
@endphp
<section class="itd-card" aria-labelledby="itdRolesTitle" data-it-roles>
    <div class="itd-card-head">
        <div><h2 id="itdRolesTitle">Users by Role</h2><p class="itd-sub">Accounts and who has been active in 30 days</p>@include('dashboards.it._updated', ['at' => $it['stamps']['roles'] ?? $it['stamps']['now']])</div>
        <a class="itd-link" href="{{ route('users.index') }}">Manage users</a>
    </div>
    @if (($r['state'] ?? 'error') === 'error')
        <p class="itd-error" role="alert">Unable to retrieve user counts.</p>
    @elseif ($r['state'] === 'empty')
        <p class="itd-empty">No user accounts available.</p>
    @else
        <div class="itd-table-wrap">
            <table class="itd-table">
                <thead><tr><th scope="col">Role</th><th scope="col" class="itd-num">Total accounts</th><th scope="col" class="itd-num">Active (30 days)</th><th scope="col" class="itd-num">Active %</th></tr></thead>
                <tbody>
                    @foreach ($r['rows'] as $row)
                        <tr><td><strong>{{ $row['label'] }}</strong></td><td class="itd-num">{{ number_format($row['total']) }}</td><td class="itd-num">{{ number_format($row['active']) }}</td><td class="itd-num">{{ $pct($row['pct']) }}</td></tr>
                    @endforeach
                    <tr class="itd-total"><td><strong>All roles</strong></td><td class="itd-num">{{ number_format($r['total']) }}</td><td class="itd-num">{{ number_format($r['active']) }}</td><td class="itd-num">{{ $pct($r['pct']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <p class="itd-foot">Active means the account logged in or was used in the last 30 days.</p>
    @endif
</section>
