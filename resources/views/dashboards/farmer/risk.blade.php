{{-- 3. Barangay risk: the Heat Map's rating as one color badge (Mababa / Katamtaman / Mataas).
     2026-11-22: the card opens its summary panel (detail.blade.php) on this page; the heat map link still works. --}}
@php $r = $f['risk']; @endphp
{{-- A missing or unconfirmed barangay is asked for in the notification bell (FarmerMissingInfoNotifier), not with a card here. --}}
@unless (in_array($r['state'], ['nobarangay', 'unverified'], true))
<section @class(['fh-card', 'fh-card-opens' => $r['state'] === 'ok']) aria-labelledby="fhRiskTitle" data-fh-risk>
    <h2 id="fhRiskTitle" class="fh-kicker">{{ __('farmer.home.risk.title') }}</h2>
    @if ($r['state'] === 'ok')
        <p class="fh-risk-badge is-{{ $r['tone'] }}">{{ $r['label'] }}</p>
        <p class="fh-words">{{ $r['meaning'] }}</p>
        <x-updated-at :at="$r['updated_at']" :label="__('farmer.home.updated')" data-weather-fetched="barangays" />
        <button type="button" class="fh-card-open" data-fh-detail-open="risk" aria-haspopup="dialog"><span class="mfd-sr-only">{{ __('farmer.kpis.detail.open', ['card' => __('farmer.home.risk.title')]) }}</span></button>
        <a class="fh-link" href="{{ route('heat-map.index') }}">{{ __('farmer.home.risk.view') }}</a>
        @include('dashboards.farmer.detail', ['key' => 'risk', 'd' => \App\Services\Farmer\FarmerHomeKpis::riskDetail($r, (string) ($f['barangay'] ?? ''))])
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif
</section>
@endunless
