@php
    $h = $it['extra']['heatmap'];
    $reg = $it['extra']['registrations'];
    $never = $it['extra']['never'];
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $hour = fn ($i) => ($i % 12 === 0 ? 12 : $i % 12).($i < 12 ? ' AM' : ' PM');
    $regMax = ($reg['counts'] ?? []) ? max($reg['counts']) : 0;
@endphp
<section class="mfd-card itd-card" aria-labelledby="itdUsageTitle" data-it-usage>
    <div class="mfd-card-head">
        <div><h2 id="itdUsageTitle">Usage Patterns</h2><p class="itd-sub">When people log in, and how fast new accounts are arriving</p>@include('dashboards.it._updated', ['at' => $it['stamps']['extra'] ?? $it['stamps']['now']])</div>
    </div>
    <div class="itd-two">
        <div data-it-heatmap>
            <h3 class="itd-h3 is-first">Login activity heatmap <span class="itd-period">last 30 days · day of week × hour (Manila time)</span></h3>
            @if (($h['state'] ?? 'error') === 'error')
                @include('dashboards.it._state', ['kind' => 'error', 'text' => 'Unable to read login activity.'])
            @elseif ($h['state'] === 'empty')
                @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No logins were recorded in the last 30 days. The heatmap fills in as people sign in.'])
            @else
                <div class="itd-heat-wrap">
                    <table class="itd-heat" aria-label="Logins by day of week and hour">
                        <thead><tr><th scope="col"><span class="mfd-sr-only">Day</span></th>@foreach (range(0, 23) as $i)<th scope="col" title="{{ $hour($i) }}">{{ $i % 3 === 0 ? $i : '' }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($h['grid'] as $d => $cells)
                                <tr><th scope="row">{{ $days[$d] }}</th>
                                    @foreach ($cells as $i => $c)
                                        <td style="--heat: {{ $h['max'] > 0 ? round($c / $h['max'], 2) : 0 }}" class="{{ $c > 0 ? 'has' : '' }}" title="{{ $days[$d] }} {{ $hour($i) }}: {{ $c }} {{ $c === 1 ? 'login' : 'logins' }}">{{ $c > 0 ? $c : '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="itd-legend" aria-hidden="true"><span>Fewer logins</span><span class="itd-legend-scale"></span><span>More logins</span><span class="itd-axis">X axis: hour of day (0–23, Manila time) · Y axis: day of week</span></div>
                <p class="mfd-foot">{{ number_format($h['total']) }} logins. Busiest: <strong>{{ $h['busiest']['day'] }} at {{ $hour($h['busiest']['hour']) }}</strong> ({{ $h['busiest']['count'] }}). Plan maintenance for the quietest hours.</p>
            @endif
        </div>

        <div data-it-registrations>
            <h3 class="itd-h3 is-first">New registrations per week <span class="itd-period">last 8 weeks</span></h3>
            @if (($reg['state'] ?? 'error') === 'error')
                @include('dashboards.it._state', ['kind' => 'error', 'text' => 'Unable to read registrations.'])
            @elseif ($reg['state'] === 'empty')
                @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No new accounts in the last 8 weeks.'])
            @else
                <p class="itd-axis">Y axis: new accounts · X axis: week starting (Monday)</p>
                <div class="itd-bars" role="img" aria-label="New registrations per week: {{ implode(', ', $reg['counts']) }}">
                    @foreach ($reg['counts'] as $i => $c)
                        <div class="itd-bar"><span class="itd-bar-value">{{ $c }}</span><span class="itd-bar-fill" style="height: {{ $regMax > 0 ? max(3, round($c / $regMax * 100)) : 0 }}%"></span><span class="itd-bar-label">{{ \Carbon\CarbonImmutable::parse($reg['weeks'][$i])->format('M j') }}</span></div>
                    @endforeach
                </div>
                <p class="mfd-foot">{{ number_format($reg['total']) }} new accounts in 8 weeks. Bars are labelled with the Monday of each week.</p>
            @endif
        </div>
    </div>
</section>
