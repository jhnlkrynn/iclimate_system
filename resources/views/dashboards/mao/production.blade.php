@php $p = $mao['production']; @endphp
@if ($p)
    <section class="mtd-card" aria-labelledby="mtdProductionTitle" data-mao-production>
        <div class="mtd-card-head"><div>
                <h2 id="mtdProductionTitle">Planting &amp; Production</h2>
                @if ($p['year'])<p class="mtd-sub">Recorded harvests for {{ $p['year'] }} · {{ $p['barangays'] }} of {{ $p['barangays_total'] }} barangays reporting</p>@endif
            </div><a class="mtd-link" href="{{ route('rice-productions.index') }}">Rice production records</a></div>
        <div class="mtd-tiles">
            <div class="mtd-tile"><span class="mtd-tile-label">Harvested Area</span><strong>{{ number_format($p['planted'], 1) }}<small>ha</small></strong></div>
            @if ($p['irrigated'] > 0)<div class="mtd-tile"><span class="mtd-tile-label">Irrigated</span><strong>{{ number_format($p['irrigated'], 1) }}<small>ha</small></strong></div>@endif
            @if ($p['rainfed'] > 0)<div class="mtd-tile"><span class="mtd-tile-label">Rainfed</span><strong>{{ number_format($p['rainfed'], 1) }}<small>ha</small></strong></div>@endif
            @if ($p['expected_yield'] !== null)<div class="mtd-tile"><span class="mtd-tile-label">Expected Yield <em>(model estimate)</em></span><strong>{{ number_format($p['expected_yield'], 2) }}<small>t/ha</small></strong></div>@endif
            @if ($p['recorded_yield'] !== null)<div class="mtd-tile"><span class="mtd-tile-label">Recorded Yield <em>(harvest records)</em></span><strong>{{ number_format($p['recorded_yield'], 2) }}<small>t/ha</small></strong></div>@endif
        </div>
    </section>
@endif
