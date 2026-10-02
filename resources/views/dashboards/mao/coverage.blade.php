@php $f = $mao['farmers']; @endphp
@if (($f['state'] ?? null) === 'ok')
    <section class="mtd-card" aria-labelledby="mtdCoverageTitle" data-mao-coverage>
        <div class="mtd-card-head"><h2 id="mtdCoverageTitle">Farmer Coverage</h2></div>
        <dl class="mtd-rows">
            <div><dt>Registered Farmers</dt><dd>{{ number_format($f['farmers']) }}</dd></div>
            <div><dt>Farmer Organizations</dt><dd>{{ number_format($f['organizations']) }}</dd></div>
            <div><dt>Barangays Covered</dt><dd>{{ $f['barangays_covered'] }} / {{ $f['barangays_total'] }}</dd></div>
            <div><dt>Farmers Without Organization</dt><dd>{{ number_format($f['without_organization']) }}</dd></div>
            @if ($f['farm_area'] > 0)<div><dt>Registered Farm Area</dt><dd>{{ number_format($f['farm_area'], 2) }} ha</dd></div>@endif
        </dl>
        <a class="mtd-btn is-quiet" href="{{ route('farmer-profiles.index') }}">View Farmer Analytics</a>
    </section>
@else
    <section class="mtd-card" aria-labelledby="mtdCoverageTitle" data-mao-coverage-empty>
        <div class="mtd-card-head"><h2 id="mtdCoverageTitle">Farmer Coverage</h2></div>
        <div class="mtd-empty" role="status">
            @if (($f['state'] ?? null) === 'error')<strong>Farmer records are temporarily unavailable.</strong><span>Please try again shortly.</span>
            @else<strong>No data available.</strong><span>No farmer profiles have been registered yet.</span>@endif
        </div>
    </section>
@endif
