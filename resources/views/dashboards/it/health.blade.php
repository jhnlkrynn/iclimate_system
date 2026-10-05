{{-- One row per service: what it is, one plain sentence about it, and a status word with an icon (never colour alone). --}}
<section class="mfd-card itd-card" aria-labelledby="itdHealthTitle" data-it-health>
    <div class="mfd-card-head">
        <div><h2 id="itdHealthTitle">System Health</h2><p class="itd-sub">The services iClimate runs on, checked live</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now']])</div>
        <a class="mfd-link" href="{{ route('system-health.index') }}">Full diagnostics</a>
    </div>
    <ul class="itd-services">
        @foreach ($it['services'] as $s)
            <li class="itd-service is-{{ $s['level'] }}" data-service="{{ $s['key'] }}" data-level="{{ $s['level'] }}">
                <div class="itd-service-text">
                    <strong>{{ $s['name'] }}</strong>
                    <p>{{ $s['detail'] }}</p>
                </div>
                @include('dashboards.it._badge', ['level' => $s['level'], 'text' => $s['label']])
            </li>
        @endforeach
    </ul>
</section>
