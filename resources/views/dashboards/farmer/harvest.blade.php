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
        <p class="fh-muted">@include('components.unvalidated-estimate')</p>
        @if ($h['compare_label'])
            <p class="fh-badge is-{{ ['typical' => 'info', 'above' => 'good', 'below' => 'watch'][$h['compare']] }}">{{ $h['compare_label'] }}</p>
            <p class="fh-muted">{{ __('farmer.home.harvest.compare_note') }}</p>
        @endif
        @if (! empty($h['size_note']))<p class="fh-muted">{{ $h['size_note'] }}</p>@endif
        <x-updated-at :at="$h['ran_at']" :label="__('farmer.home.updated')" />
        {{-- 2026-11-22: the card opens its summary panel; with a known farm size it is the same panel as the
             "Expected harvest this season" tile (tons), otherwise the estimate per hectare alone. --}}
        @php $expectedTile = collect($f['kpis'] ?? [])->first(fn (array $tile): bool => $tile['key'] === 'expected' && ! empty($tile['detail'])); @endphp
        <button type="button" class="fh-card-open" data-fh-detail-open="{{ $expectedTile ? 'kpi-expected' : 'harvest' }}" aria-haspopup="dialog"><span class="mfd-sr-only">{{ __('farmer.kpis.detail.open', ['card' => __('farmer.home.harvest.title')]) }}</span></button>
        @unless ($expectedTile)
            @include('dashboards.farmer.detail', ['key' => 'harvest', 'd' => [
                'title' => __('farmer.home.harvest.title'),
                'rows' => array_filter([
                    __('farmer.kpis.detail.yield_per_ha') => number_format($h['yield'], 2).' t/ha',
                    __('farmer.kpis.detail.season') => (string) ($h['season'] ?? ''),
                    __('farmer.kpis.detail.compared') => (string) ($h['compare_label'] ?? ''),
                    __('farmer.kpis.detail.size') => (string) ($h['size_note'] ?? ''),
                ], fn (string $v): bool => $v !== ''),
                'text' => __('farmer.kpis.detail.harvest_how'),
                'updated_at' => $h['ran_at'] ?? null,
                'link' => route('weather-predictions.index').'#yield',
            ]])
        @endunless
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
