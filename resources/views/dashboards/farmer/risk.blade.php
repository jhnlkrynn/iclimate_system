{{-- 3. Barangay risk: the Heat Map's rating as one color badge (Mababa / Katamtaman / Mataas). --}}
@php $r = $f['risk']; @endphp
{{-- A missing or unconfirmed barangay is asked for in the notification bell (FarmerMissingInfoNotifier), not with a card here. --}}
@unless (in_array($r['state'], ['nobarangay', 'unverified'], true))
<section class="fh-card" aria-labelledby="fhRiskTitle" data-fh-risk>
    <h2 id="fhRiskTitle" class="fh-kicker">{{ __('farmer.home.risk.title') }}</h2>
    @if ($r['state'] === 'ok')
        <p class="fh-risk-badge is-{{ $r['tone'] }}">{{ $r['label'] }}</p>
        <p class="fh-words">{{ $r['meaning'] }}</p>
        <x-updated-at :at="$r['updated_at']" :label="__('farmer.home.updated')" />
        <a class="fh-link" href="{{ route('heat-map.index') }}">{{ __('farmer.home.risk.view') }}</a>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif
</section>
@endunless
