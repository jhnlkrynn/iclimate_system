{{-- 4. Expected yield this season: one number in t/ha and Normal / Mas mataas / Mas mababa against the usual Lian yield.
     The estimate is made in the background (PrepareFarmerYieldEstimate); until it is saved the card says so. --}}
@php $h = $f['harvest']; @endphp
{{-- Farmer – Naicel: a missing farm size is asked for in the notification bell (FarmerMissingInfoNotifier), not with a card here. --}}
@if ($h['state'] !== 'ok' && ($h['needs_area'] ?? false))
    {{-- Farmer – Naicel (2026-10-09): no farm size yet, so no estimate of their own. The card shows what Lian farms
         usually harvest this month (past records) instead of leaving the row one card short. The missing farm size is
         still asked for in the notification bell only (no prompt on Home). --}}
    @if (is_numeric($h['usual'] ?? null))
    <section class="fh-card" aria-labelledby="fhUsualYieldTitle" data-fh-usual-yield>
        <h2 id="fhUsualYieldTitle" class="fh-kicker">{{ __('farmer.home.harvest.usual_title') }}</h2>
        <p class="fh-muted">{{ $h['season'] }}</p>
        <p class="fh-big">{{ number_format((float) $h['usual'], 2) }} <small>t/ha</small></p>
        <p class="fh-words">{{ __('farmer.home.harvest.usual_note') }}</p>
    </section>
    @endif
@else
<section @class(['fh-card', 'fh-card-opens' => $h['state'] === 'ok']) aria-labelledby="fhHarvestTitle" data-fh-harvest @if ($h['state'] === 'loading') data-mfd-load="harvest" @endif>
    <h2 id="fhHarvestTitle" class="fh-kicker">{{ __('farmer.home.harvest.title') }}</h2>
    <p class="fh-muted">{{ $h['season'] }}</p>
    @if ($h['state'] === 'ok')
        <p class="fh-big">{{ number_format($h['yield'], 2) }} <small>t/ha</small></p>
        {{-- The "Unvalidated estimate" disclosure stays until the model has validation evidence; no Normal/Higher/Lower badge
             on the farmer card (owner decision 2026-11-20). --}}
        <p class="fh-muted">@include('components.unvalidated-estimate')</p>
        @if (! empty($h['farm_type_split']))
            @php $split = $h['farm_type_split']; @endphp
            @if ($split['active'])
                <p class="fh-muted" data-fh-farm-type-split>{{ __('farmer.yield_farm_type.split', ['rainfed' => number_format($split['rainfed_t_ha'], 1), 'irrigated' => number_format($split['irrigated_t_ha'], 1)]) }} ({{ $split['basis'] }})</p>
            @else
                <p class="fh-muted" data-fh-farm-type-pending>{{ __('farmer.yield_farm_type.pending', ['count' => $split['rainfed_count'], 'min' => $split['min_harvests']]) }}</p>
            @endif
        @endif
        @if (! empty($h['size_note']))<p class="fh-muted">{{ $h['size_note'] }}</p>@endif
        <x-updated-at :at="$h['ran_at']" :label="__('farmer.home.updated')" />
        {{-- 2026-11-22: the whole card is a button that opens the "expected-harvest" section of the summary panel
             (dashboards/farmer.blade.php). --}}
        <button type="button" class="fh-card-open" data-panel="expected-harvest" aria-haspopup="dialog" aria-controls="fhDrawer"><span class="mfd-sr-only">{{ __('farmer.kpis.detail.open', ['card' => __('farmer.home.harvest.title')]) }}</span></button>
    @elseif ($h['state'] === 'loading')
        <div class="ic-skel-lines" role="status"><span></span><span></span><span class="visually-hidden">{{ __('farmer.home.harvest.preparing') }}</span></div>
        <p class="fh-muted">{{ __('farmer.home.harvest.preparing') }}</p>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="$h['reason']" />
        @if ($h['needs_area'])
            @if (! empty($h['farm_id']))
                <a class="fh-link" href="{{ route('farmer.farms.edit', $h['farm_id']) }}">{{ __('farms.yield.add_size') }}</a>
            @else
                <a class="fh-link" href="{{ route('profile.edit') }}">{{ __('farmer.home.harvest.add_area') }}</a>
            @endif
        @endif
    @endif
</section>
@endif
