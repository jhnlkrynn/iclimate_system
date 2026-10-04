@php
    $farmerView = new \App\Support\FarmerAdvisoryView($advisory);
    $issued = $advisory->updated_at?->copy()->timezone(\App\Support\BusinessClock::timezone());
    $riskTone = $farmerView->farmerRiskTone();
@endphp
<li class="fad-acard">
    <div class="fad-acard-tags">
        <span class="fad-badge {{ $farmerView->isPagasa() ? 'is-danger' : 'is-info' }}">{{ $farmerView->isPagasa() ? 'Official Advisory' : 'iClimate Advisory' }}</span>
        <span class="fad-badge is-{{ $riskTone }}">{{ $farmerView->farmerRiskLevel() }}</span>
    </div>
    <h3 class="fad-title">{{ $advisory->title }}</h3>
    <p class="fad-support">{{ $farmerView->farmerArea() }}</p>
    <dl class="fad-acard-facts">
        <div><dt>Possible Effect</dt><dd>{{ $farmerView->farmerEffect() }}</dd></div>
        <div><dt>What to do</dt><dd>{{ $farmerView->farmerAction() }}</dd></div>
    </dl>
    <div class="fad-acard-foot">
        <p class="fad-meta">
            <span class="fad-source {{ $farmerView->isPagasa() ? 'is-official' : '' }}">Source: {{ $farmerView->farmerSourceLabel() }}</span>
            @if($farmerView->isPagasa())<span class="visually-hidden">PAGASA (official advisory)</span>@else<span class="visually-hidden">iClimate guidance from forecast data</span>@endif
            @if($issued)<span>Last updated: {{ $issued->format('M j, Y · g:i A') }}</span>@endif
            @if($advisory->valid_until)<span>Valid until: {{ $advisory->valid_until->copy()->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y · g:i A') }}</span>@endif
        </p>
        <a class="fad-btn is-primary fad-view" href="{{ route('planting-advisories.show', $advisory) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View advisory: {{ $advisory->title }}">View Details</a>
    </div>
</li>
