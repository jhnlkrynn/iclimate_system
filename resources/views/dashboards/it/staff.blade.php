@php
    $staff = $it['staff'];
    $accounts = $staff['accounts'];
    $roleCounts = collect($accounts['rows'] ?? [])->mapWithKeys(fn (array $row): array => [strtolower($row['label']) => $row['total']]);
    $number = fn ($value): string => $value === null ? '—' : number_format((int) $value);
@endphp

<section class="kpi-section kpi-row mhd-tiles itd-kpi-grid" aria-label="Account overview">
    <a class="mhd-tile mfd-kpi" href="{{ route('users.index') }}"><span class="mhd-tile-label mfd-kpi-headline">Total users</span><span class="mhd-tile-value mfd-kpi-value">{{ $number($accounts['total'] ?? null) }}</span><span class="mhd-tile-note mfd-kpi-meaning">All iClimate accounts</span></a>
    <a class="mhd-tile mfd-kpi" href="{{ route('users.index') }}"><span class="mhd-tile-label mfd-kpi-headline">Active users</span><span class="mhd-tile-value mfd-kpi-value">{{ $number($accounts['active'] ?? null) }}</span><span class="mhd-tile-note mfd-kpi-meaning">Used iClimate in 30 days</span></a>
    <a class="mhd-tile mfd-kpi" href="{{ route('users.index') }}"><span class="mhd-tile-label mfd-kpi-headline">Farmer accounts</span><span class="mhd-tile-value mfd-kpi-value">{{ $number($roleCounts['farmer'] ?? null) }}</span><span class="mhd-tile-note mfd-kpi-meaning">Farmers using the system</span></a>
    <a class="mhd-tile mfd-kpi" href="{{ route('users.index') }}"><span class="mhd-tile-label mfd-kpi-headline">MAO staff</span><span class="mhd-tile-value mfd-kpi-value">{{ $number($roleCounts['mao personnel'] ?? null) }}</span><span class="mhd-tile-note mfd-kpi-meaning">Agriculture office accounts</span></a>
    <a class="mhd-tile mfd-kpi" href="{{ route('users.index') }}"><span class="mhd-tile-label mfd-kpi-headline">IT staff</span><span class="mhd-tile-value mfd-kpi-value">{{ $number($roleCounts['it expert'] ?? null) }}</span><span class="mhd-tile-note mfd-kpi-meaning">System management accounts</span></a>
</section>

<div class="account-overview-section account-overview-row itd-account-overview-row">
<section class="mtd-card mfd-card itd-card itd-staff-card itd-staff-overview" aria-labelledby="itdAccountOverviewTitle">
    <div class="mtd-card-head mfd-card-head">
        <div>
            <h2 id="itdAccountOverviewTitle">Account Overview</h2>
            <p class="itd-sub">A simple view of the people using iClimate</p>
            @include('dashboards.it._updated', ['at' => $it['stamps']['staff-summary'] ?? $it['stamps']['now']])
        </div>
        <a class="mfd-link" href="{{ route('users.index') }}">Manage users</a>
    </div>
    <div class="itd-staff-columns">
        <div>
            <h3 class="itd-h3">Recently created accounts</h3>
            @if ($staff['recent_accounts'] === [])
                @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'New accounts will appear here.'])
            @else
                <ul class="itd-simple-list">
                    @foreach ($staff['recent_accounts'] as $account)
                        <li><span><strong>{{ $account['name'] }}</strong><small>{{ $account['role'] }} · {{ $account['status'] }}</small></span><time datetime="{{ $account['created_at']?->toIso8601String() }}">{{ $account['created_at']?->format('M j, Y') }}</time></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <h3 class="itd-h3">Accounts needing review</h3>
            <div class="itd-summary-pair"><strong>{{ $number($staff['accounts_needing_review']) }}</strong><span>accounts need attention</span></div>
            <a class="mfd-btn is-quiet is-small" href="{{ route('users.index') }}">Review users</a>
        </div>
    </div>
</section>
</div>

<div class="details-section dashboard-details-row itd-details-row">
<section class="mtd-card mfd-card itd-card itd-staff-card itd-staff-messages" aria-labelledby="itdMessagesTitle">
    <div class="mtd-card-head mfd-card-head">
        <div><h2 id="itdMessagesTitle">Recent Messages</h2><p class="itd-sub">The latest messages exchanged in iClimate</p></div>
        <a class="mfd-link" href="{{ route('messages.index') }}">Open messages</a>
    </div>
    <p class="itd-message-count"><strong>{{ $number($staff['messages_total']) }}</strong> messages total · <strong>{{ $number($staff['unread_messages']) }}</strong> not yet marked as read</p>
    @if ($staff['recent_messages'] === [])
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'Recent messages will appear here.'])
    @else
        <ul class="itd-simple-list">
            @foreach ($staff['recent_messages'] as $message)
                <li><span><strong>{{ $message['sender'] }}</strong><small>{{ $message['body'] !== '' ? $message['body'] : 'Attachment or empty message' }}</small></span><time datetime="{{ $message['at']?->toIso8601String() }}">{{ $message['at']?->format('M j, g:i A') }}</time></li>
            @endforeach
        </ul>
    @endif
</section>

<section class="mtd-card mfd-card itd-card itd-staff-card itd-staff-activity" aria-labelledby="itdActivityTitle">
    <div class="mtd-card-head mfd-card-head">
        <div><h2 id="itdActivityTitle">User Activity</h2><p class="itd-sub">Recent account and access changes</p></div>
        <a class="mfd-link" href="{{ route('system-logs.index') }}">View activity log</a>
    </div>
    @if ($staff['recent_user_activity'] === [])
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'User-management activity will appear here.'])
    @else
        <ul class="itd-simple-list">
            @foreach ($staff['recent_user_activity'] as $activity)
                <li><span><strong>{{ $activity['action'] }}</strong><small>{{ $activity['user'] }}</small></span><time datetime="{{ $activity['at']?->toIso8601String() }}">{{ $activity['at']?->format('M j, g:i A') }}</time></li>
            @endforeach
        </ul>
    @endif
</section>

<section class="mtd-card mfd-card itd-card itd-staff-card itd-staff-reports" aria-labelledby="itdReportsTitle">
    <div class="mtd-card-head mfd-card-head">
        <div><h2 id="itdReportsTitle">Reports Summary</h2><p class="itd-sub">Reports prepared for the agriculture office</p></div>
        <a class="mfd-link" href="{{ route('reports.index') }}">View reports</a>
    </div>
    <div class="itd-report-metrics">
        <div class="itd-summary-pair"><strong>{{ $number($staff['reports_this_month']) }}</strong><span>reports this month</span></div>
        <div class="itd-summary-pair"><strong>{{ $number($staff['reports_total']) }}</strong><span>reports available</span></div>
    </div>
</section>

<!-- The detail grid ends before Quick Actions so the four dashboard rows stay structurally independent. -->
</div>

<div class="quick-actions-section quick-actions-row itd-quick-actions-row">
<section class="mtd-card mfd-card itd-card itd-staff-card itd-staff-quick" aria-labelledby="itdQuickActionsTitle">
    <div class="mtd-card-head mfd-card-head"><div><h2 id="itdQuickActionsTitle">Quick Actions</h2></div></div>
    <div class="itd-quick-actions">
        <a class="mfd-btn" href="{{ route('users.create') }}">Create user</a>
        <a class="mfd-btn" href="{{ route('users.index') }}">Manage users</a>
        <a class="mfd-btn" href="{{ route('reports.index') }}">View reports</a>
        <a class="mfd-btn" href="{{ route('messages.index') }}">Open messages</a>
        <a class="mfd-btn" href="{{ route('admin.settings.index') }}">Account settings</a>
    </div>
</section>

</div>
