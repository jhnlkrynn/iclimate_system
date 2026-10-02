@php
    $r = $mao['risk'];
    $levels = [
        'Low' => ['color' => '#2f9e6e', 'glyph' => '✓', 'class' => 'is-low'],
        'Moderate' => ['color' => '#d9a21b', 'glyph' => '⚠', 'class' => 'is-mod'],
        'High' => ['color' => '#e0714f', 'glyph' => '!', 'class' => 'is-high'],
        'Severe' => ['color' => '#b03a2e', 'glyph' => '‼', 'class' => 'is-sev'],
    ];
@endphp
@if (($r['state'] ?? null) === 'ok')
    <section class="mtd-card" aria-labelledby="mtdRiskTitle" data-mao-risk>
        <div class="mtd-card-head"><h2 id="mtdRiskTitle">Farm Risk Overview</h2></div>
        <div class="mtd-risk">
            <div class="mtd-donut">
                <canvas id="maoRiskDonutChart"
                    data-donut="{{ json_encode(['labels' => array_keys($levels), 'counts' => array_values($r['counts']), 'colors' => array_column($levels, 'color')]) }}"
                    data-chart-source="Calculated by iClimate from weather, expected yield and barangay exposure"
                    data-chart-type="calculated" data-chart-unit="barangays" data-chart-info-style="icon"
                    data-chart-method="Each barangay gets a score from 0 to 1 that combines rainfall (34%), expected yield (27%), barangay exposure (18%) and temperature (14%), with a penalty for farm stress factors. Score 0.85 or more is Severe, 0.68 or more is High, 0.45 or more is Moderate, and anything lower is Low."
                    data-chart-note="{{ \App\Services\Risk\AgriculturalRiskScorer::METHODOLOGY_CAVEAT }}"
                    aria-label="Barangays by farm risk level"></canvas>
                <div class="mtd-donut-center"><strong>{{ $r['total'] }}</strong><span>BARANGAYS</span></div>
            </div>
            <ul class="mtd-risk-list">
                @foreach ($levels as $level => $meta)
                    <li class="{{ $meta['class'] }}">
                        <span class="mtd-risk-glyph" aria-hidden="true">{{ $meta['glyph'] }}</span>
                        <span class="mtd-risk-name">{{ $level }}</span>
                        <strong>{{ $r['counts'][$level] }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
        <p class="mtd-foot">Farm risk is a planning indicator based on available weather and agricultural data.@if ($r['computed_at']) Calculated {{ $r['computed_at']->diffForHumans() }}.@endif</p>
    </section>
@else
    <section class="mtd-card" aria-labelledby="mtdRiskTitle" data-mao-risk-empty>
        <div class="mtd-card-head"><h2 id="mtdRiskTitle">Farm Risk Overview</h2></div>
        <div class="mtd-empty" role="status">
            @if (($r['state'] ?? null) === 'error')<strong>Farm risk ratings are temporarily unavailable.</strong><span>Please try again shortly.</span>
            @else<strong>No data available.</strong><span>No barangay has a calculated farm-risk rating yet.</span>@endif
        </div>
    </section>
@endif
