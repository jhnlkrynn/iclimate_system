{{-- Farmer – Naicel (2026-10-08): the farmer's key figures (FarmerHomeKpis), up to four: farm size, days to harvest,
     expected harvest in tons and rainy days ahead. A tile is here only when its figure exists, so there is never an
     empty one; with none the whole row is left out.
     2026-11-22: a tile opens its summary panel on this page (detail.blade.php, farmer-dashboard.js) instead of another
     page. Its href is the page with the full details, so without scripts (or with Ctrl/middle click) it still goes there. --}}
@php $tiles = $f['kpis'] ?? []; @endphp
@if ($tiles !== [])
<section class="fh-kpis" aria-label="{{ __('farmer.kpis.title') }}" data-fh-kpis>
    @foreach ($tiles as $tile)
        <a class="fh-kpi" href="{{ $tile['detail']['link'] ?? $tile['href'] }}" data-fh-kpi="{{ $tile['key'] }}"
            @if (! empty($tile['detail'])) data-fh-detail-open="kpi-{{ $tile['key'] }}" aria-haspopup="dialog" @endif>
            <span class="fh-kpi-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}<i class="ic-go" aria-hidden="true"></i></span>
            <span class="fh-kpi-value"><span data-fh-kpi-value>{{ $tile['value'] }}</span>@if (! empty($tile['unit'])) <small>{{ $tile['unit'] }}</small>@endif</span>
            @if (($tile['progress'] ?? null) !== null)
                <span class="fh-kpi-bar" role="img" aria-label="{{ __('farmer.kpis.crop.progress', ['percent' => $tile['progress']]) }}"><span style="width: {{ $tile['progress'] }}%"></span></span>
            @endif
            @if (! empty($tile['note']))<span class="fh-kpi-note">{{ $tile['note'] }}</span>@endif
        </a>
    @endforeach
    @foreach ($tiles as $tile)
        @if (! empty($tile['detail']))
            @include('dashboards.farmer.detail', ['key' => 'kpi-'.$tile['key'], 'd' => $tile['detail']])
        @endif
    @endforeach
</section>
@endif
