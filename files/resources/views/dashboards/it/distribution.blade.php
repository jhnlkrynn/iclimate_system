@php $d = $it['distribution']; @endphp
<section class="itd-card" aria-labelledby="itdDistTitle" data-it-distribution>
    <div class="itd-card-head"><h2 id="itdDistTitle">User Distribution</h2></div>
    @if ($d['state'] === 'error')
        <p class="itd-error" role="alert">{{ $d['message'] }}</p>
    @elseif ($d['state'] === 'empty')
        <p class="itd-empty">No user accounts available.</p>
    @else
        <div class="itd-dist">
            <div class="itd-donut">
                <canvas id="itUsersByRoleChart" data-donut="{{ json_encode(['labels' => array_column($d['rows'], 'label'), 'counts' => array_column($d['rows'], 'count'), 'colors' => array_column($d['rows'], 'color')]) }}" data-chart-unit="accounts" aria-label="User distribution by role"></canvas>
                <div class="itd-donut-center"><strong>{{ number_format($d['total']) }}</strong><span>TOTAL USERS</span></div>
            </div>
            <ul class="itd-legend">
                @foreach ($d['rows'] as $row)
                    <li>
                        <span class="itd-swatch" style="background: {{ $row['color'] }}" aria-hidden="true"></span>
                        <span class="itd-legend-name">{{ $row['label'] }}</span>
                        <strong>{{ number_format($row['count']) }}</strong>
                        <small class="itd-muted">{{ $row['pct'] !== null ? rtrim(rtrim(number_format($row['pct'], 1), '0'), '.').'%' : '' }}</small>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
