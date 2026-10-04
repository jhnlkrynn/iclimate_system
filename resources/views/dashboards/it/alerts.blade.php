@php $alerts = $it['alerts']; @endphp
<section class="mfd-card itd-card is-{{ $it['attention']['level'] }}" aria-labelledby="itdAlertsTitle" data-it-alerts>
    <div class="mfd-card-head">
        <div><h2 id="itdAlertsTitle">Attention Required</h2><p class="itd-sub">Problems found by the checks below, most severe first</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now']])</div>
        <span class="itd-count {{ count($alerts) ? 'has-items' : '' }}" aria-label="{{ count($alerts) }} {{ count($alerts) === 1 ? 'issue' : 'issues' }}">{{ count($alerts) }}</span>
    </div>
    @if (count($alerts) === 0)
        @include('dashboards.it._state', ['kind' => 'good', 'text' => 'All clear. No issues need attention.'])
    @else
        <ul class="mfd-alert-list">
            @foreach ($alerts as $alert)
                <li class="mfd-alert is-{{ $alert['severity'] === 'critical' ? 'danger' : 'watch' }}">
                    <div class="mfd-alert-body">
                        @include('dashboards.it._badge', ['level' => $alert['severity'] === 'critical' ? 'crit' : 'warn', 'text' => $alert['severity'] === 'critical' ? 'Critical' : 'Warning'])
                        <strong>{{ $alert['title'] }}</strong>
                        <p class="mfd-muted">{{ $alert['message'] }}</p>
                        <small>Detected {{ $alert['at']->format('M j, g:i A') }} ({{ $alert['at']->diffForHumans() }})</small>
                    </div>
                    <a class="mfd-btn is-small" href="{{ $alert['href'] }}">{{ $alert['action'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
