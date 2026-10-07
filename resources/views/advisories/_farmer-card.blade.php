@php
    $farmerView = new \App\Support\FarmerAdvisoryView($advisory);
    $tz = \App\Support\BusinessClock::timezone();
    $issued = $advisory->published_at?->copy()->timezone($tz);
    $checked = data_get($advisory->metadata, 'fetched_at') ?: $advisory->weatherData?->fetched_at;
    $checked = $checked ? \Illuminate\Support\Carbon::parse($checked)->timezone('Asia/Manila') : null;
    $badge = $farmerView->statusBadge();
    $affected = $farmerView->affectedBarangays();
    $isPagasa = $farmerView->isPagasa();
    $details = $farmerView->pagasaDetails();
    $basedOn = $farmerView->basedOn();
    // PAGASA writes ranges as "10– 150"; shown as "10–150" (same numbers).
    $range = fn (?string $text) => $text === null ? null : preg_replace('/\s*[–-]\s*/u', '–', trim($text));
    $temperature = collect(explode(';', (string) ($details['weather']['temperature_c'] ?? '')))
        ->map(fn ($part) => preg_match('/(Lowland|Upland)\s*:\s*(.+)/i', trim($part), $m) ? ucfirst(strtolower($m[1])).' '.$range($m[2]).'°C' : null)
        ->filter()->sortBy(fn ($part) => str_starts_with($part, 'Lowland') ? 0 : 1)->implode(' · ');
@endphp
{{-- Location → type and status → title → what is happening → what farmers can do → source and times → View Details. --}}
<li @class(['fad-acard', $isPagasa ? 'is-official' : 'is-local', 'is-wide' => $wide ?? false])>
    <div class="fad-acard-main">
    <p class="fad-acard-place"><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'map'])</span>{{ $farmerView->farmerLocation() }}</p>
    <div class="fad-acard-tags">
        <span class="fad-kicker">{{ $isPagasa ? 'Official PAGASA Advisory' : ($advisory->generated_automatically ? 'iClimate Local Farming Guidance' : $advisory->typeLabel().' Advisory') }}</span>
        <span class="fad-badge is-{{ $badge['tone'] }}">@include('dashboards.mao.icon', ['name' => $badge['icon']])<span>{{ $badge['label'] }}</span></span>
    </div>
    @if($isPagasa && $affected)
        <p class="fad-support">Affected barangays: {{ implode(', ', $affected) }}</p>
    @endif
    @if($farmerView->coverageNote())
        <p class="fad-support">{{ $farmerView->coverageNote() }}@if($farmerView->originalScope() && ! $farmerView->isNationalOutlook()) PAGASA scope: {{ $farmerView->originalScope() }}.@endif</p>
    @endif
    <h3 class="fad-title">{{ $advisory->title }}</h3>

    @if($details)
        {{-- PAGASA's own outlook table, as published; the full bulletin is in View Details. --}}
        <dl class="fad-outlook">
            @foreach(['rainfall_mm' => ['rain', 'Rainfall', ' mm'], 'relative_humidity_percent' => ['humidity', 'Relative humidity', '%'], 'rainy_days' => ['calendar', 'Rainy days', ' days']] as $key => [$icon, $label, $unit])
                @if(!empty($details['weather'][$key]))<div><dt><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span>{{ $label }}</dt><dd>{{ $range($details['weather'][$key]).$unit }}</dd></div>@endif
            @endforeach
            @if($temperature !== '')<div class="is-wide"><dt><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'temperature'])</span>Temperature</dt><dd>{{ $temperature }}</dd></div>@endif
        </dl>
        @if(!empty($details['crops']['Rice']))
            <h4 class="fad-label">Rice activities</h4>
            <ul class="fad-taglist">@foreach($details['crops']['Rice'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        @endif
        @if(!empty($details['farm_operations']))
            <h4 class="fad-label">Recommended farm operations (PAGASA)</h4>
            <ul class="fad-taglist">@foreach($details['farm_operations'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        @endif
    @else
        <dl class="fad-acard-facts">
            <div><dt>What is happening</dt><dd>{{ $farmerView->cardEffect() }}</dd></div>
            {{-- PAGASA bulletins rarely say what to do; this suggestion is iClimate's, and says so. --}}
            <div><dt>What farmers can do{{ $isPagasa ? ' · iClimate tip' : '' }}</dt><dd>{{ $farmerView->farmerAction() }}</dd></div>
        </dl>
    @endif

    </div>
    {{-- Basis and record details: below the content on a normal card, beside it on a wide card. --}}
    <div class="fad-acard-side">
    @if($basedOn)
        <ul class="fad-chips is-small" aria-label="Forecast values this advice is based on">
            <li class="fad-chips-label">Based on</li>
            @foreach($basedOn as $icon => $text)
                <li><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span>{{ $text }}</li>
            @endforeach
        </ul>
    @endif

    <div class="fad-acard-foot">
        <p class="fad-meta">
            <span class="fad-source {{ $isPagasa ? 'is-official' : '' }}">Source: {{ $farmerView->farmerSourceLabel() }}</span>
            @if($isPagasa)<span class="visually-hidden">PAGASA (official advisory)</span>@else<span class="visually-hidden">iClimate guidance from forecast data</span>@endif
            <span>Published: {{ $issued?->format('M j, Y · g:i A') ?? 'Not provided by source' }}</span>
            @if($checked)<span><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'clock'])</span>Updated: {{ $checked->format('M j, Y · g:i A') }}</span>@endif
            @if($advisory->valid_from && $advisory->valid_until && $details)<span>Valid: {{ $advisory->valid_from->copy()->timezone($tz)->format('M j') }} – {{ $advisory->valid_until->copy()->timezone($tz)->format('M j, Y') }}</span>
            @elseif($advisory->valid_until)<span>Valid until: {{ $advisory->valid_until->copy()->timezone($tz)->format('M j, Y · g:i A') }}</span>@endif
        </p>
        <a class="fad-btn is-primary fad-view" href="{{ route('planting-advisories.show', $advisory) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View details: {{ $advisory->title }}">View Details</a>
    </div>
    </div>
</li>
