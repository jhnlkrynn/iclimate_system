{{-- 4. Expected yield this season: one number in t/ha and Normal / Mas mataas / Mas mababa against the usual Lian yield.
     The estimate is made in the background (PrepareFarmerYieldEstimate); until it is saved the card says so. --}}
@php $h = $f['harvest']; @endphp
{{-- A missing farm size is asked for in the notification bell (FarmerMissingInfoNotifier), not with a card here. --}}
@unless ($h['state'] !== 'ok' && ($h['needs_area'] ?? false))
<section class="fh-card" aria-labelledby="fhHarvestTitle" data-fh-harvest @if ($h['state'] === 'loading') data-mfd-load="harvest" @endif>
    <h2 id="fhHarvestTitle" class="fh-kicker">{{ __('farmer.home.harvest.title') }}</h2>
    <p class="fh-muted">{{ $h['season'] }}</p>
    @if ($h['state'] === 'ok')
        <p class="fh-big">{{ number_format($h['yield'], 2) }} <small>t/ha</small></p>
        @if ($h['compare_label'])
            <p class="fh-badge is-{{ ['typical' => 'info', 'above' => 'good', 'below' => 'watch'][$h['compare']] }}">{{ $h['compare_label'] }}</p>
            <p class="fh-muted">{{ __('farmer.home.harvest.compare_note') }}</p>
        @endif
        <x-updated-at :at="$h['ran_at']" :label="__('farmer.home.updated')" />
    @elseif ($h['state'] === 'loading')
        <div class="ic-skel-lines" role="status"><span></span><span></span><span class="visually-hidden">{{ __('farmer.home.harvest.preparing') }}</span></div>
        <p class="fh-muted">{{ __('farmer.home.harvest.preparing') }}</p>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="$h['reason']" />
    @endif
</section>
@endunless
