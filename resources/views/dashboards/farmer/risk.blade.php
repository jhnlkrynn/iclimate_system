{{-- 3. Barangay risk: the Heat Map's rating as one color badge (Mababa / Katamtaman / Mataas). --}}
@php $r = $f['risk']; @endphp
<section class="fh-card" aria-labelledby="fhRiskTitle" data-fh-risk>
    <h2 id="fhRiskTitle" class="fh-kicker">{{ __('farmer.home.risk.title') }}</h2>
    @if ($r['state'] === 'ok')
        <p class="fh-risk-badge is-{{ $r['tone'] }}">{{ $r['label'] }}</p>
        <p class="fh-words">{{ $r['meaning'] }}</p>
        <x-updated-at :at="$r['updated_at']" :label="__('farmer.home.updated')" />
        <a class="fh-link" href="{{ route('heat-map.index') }}">{{ __('farmer.home.risk.view') }}</a>
    @elseif (in_array($r['state'], ['nobarangay', 'unverified'], true))
        {{-- A stored name that is not one official barangay (e.g. a bare "Poblacion") is never guessed or rated. --}}
        <p class="fh-words" @if ($r['state'] === 'unverified') data-fh-barangay-unverified @endif>{{ __('farmer.home.risk.'.$r['state'], ['barangay' => $r['stored'] ?? '']) }}</p>
        <a class="fh-link" href="{{ route('profile.edit') }}">{{ __('farmer.my_farm_page.edit_profile') }}</a>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif
</section>
