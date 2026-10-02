@php $alerts = $it['alerts']; @endphp
<section class="itd-card is-{{ $it['attention']['level'] }}" aria-labelledby="itdAlertsTitle" data-it-alerts>
    <div class="itd-card-head">
        <div><h2 id="itdAlertsTitle">Attention Required</h2><p class="itd-sub">Problems found by the checks below, most severe first</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now'] ?? $it['stamps']['now']])</div>
        <span class="itd-count {{ count($alerts) ? 'has-items' : '' }}">{{ count($alerts) }}</span>
    </div>
    @if (count($alerts) === 0)
        <p class="itd-allgood"><span aria-hidden="true">✓</span> All clear. No issues need attention 👍</p>
    @else
        <ul class="itd-alert-list">
            @foreach ($alerts as $alert)
                <li class="itd-alert is-{{ $alert['severity'] }}">
                    <span class="itd-sev"><span aria-hidden="true">{{ $alert['severity'] === 'critical' ? '✕' : '▲' }}</span> {{ $alert['severity'] === 'critical' ? 'Critical' : 'Warning' }}</span>
                    <div class="itd-alert-body">
                        <strong>{{ $alert['title'] }}</strong>
                        <p>{{ $alert['message'] }}</p>
                        <small class="itd-mono">Detected {{ $alert['at']->format('M j, g:i A') }} ({{ $alert['at']->diffForHumans() }})</small>
                    </div>
                    <a class="itd-btn" href="{{ $alert['href'] }}">{{ $alert['action'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
