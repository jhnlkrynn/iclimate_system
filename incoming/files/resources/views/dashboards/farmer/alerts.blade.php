@php $alerts = $f['alerts']; @endphp
@if (count($alerts) > 0)
    <section class="mfd-card mfd-alerts" id="mfdAlerts" aria-labelledby="mfdAlertsTitle" data-mfd-alerts>
        <div class="mfd-card-head"><h2 id="mfdAlertsTitle">Important Alerts</h2><span class="mfd-count">{{ count($alerts) }}</span></div>
        <ul class="mfd-alert-list">
            @foreach ($alerts as $alert)
                <li class="mfd-alert is-{{ $alert['severity'] }}">
                    <span class="mfd-alert-icon">@include('dashboards.mao.icon', ['name' => $alert['icon']])</span>
                    <div class="mfd-alert-body">
                        <strong>{{ $alert['title'] }}</strong>
                        <span>{{ $alert['message'] }}</span>
                    </div>
                    <a class="mfd-btn is-quiet" href="{{ $alert['href'] }}">Details</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
