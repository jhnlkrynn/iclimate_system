@php $alerts = $it['alerts']; @endphp
@if (count($alerts) === 0)
    <section class="itd-card itd-alerts is-clear" aria-label="Attention required" data-it-alerts>
        <p class="itd-allgood"><span aria-hidden="true">✓</span> No system issues requiring attention.</p>
    </section>
@else
    <section class="itd-card itd-alerts" aria-labelledby="itdAlertsTitle" data-it-alerts>
        <div class="itd-card-head">
            <h2 id="itdAlertsTitle">Attention Required</h2>
            <span class="itd-count has-items">{{ count($alerts) }}</span>
        </div>
        <ul class="itd-alert-list">
            @foreach ($alerts as $alert)
                <li class="itd-alert is-{{ $alert['severity'] }}">
                    <span class="itd-sev">{{ strtoupper($alert['severity']) }}</span>
                    <div class="itd-alert-body">
                        <strong>{{ $alert['title'] }}</strong>
                        <p>{{ $alert['message'] }}</p>
                        <small>Last checked {{ $alert['at']->diffForHumans() }}</small>
                    </div>
                    <a class="itd-btn" href="{{ $alert['href'] }}">{{ $alert['action'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
