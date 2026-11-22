{{-- Farmer – Naicel (2026-10-08): the farmer's key figures (FarmerHomeKpis), up to four: farm size, days to harvest,
     expected harvest in tons and rainy days ahead. A tile is here only when its figure exists, so there is never an
     empty one; with none the whole row is left out.
     2026-11-22: a tile is a button, not a link. It opens its section of the summary panel on this page
     (dashboards/farmer.blade.php, [data-fh-drawer]); the panel's "View full details" link goes to the other page. --}}
@php
    $tiles = $f['kpis'] ?? [];
    // The panel section each tile opens (dashboards/farmer.blade.php names the sections the same way).
    $panelFor = ['farm' => 'farm-size', 'crop' => 'days-to-harvest', 'expected' => 'expected-harvest'];
@endphp
@if ($tiles !== [])
<section class="fh-kpis" aria-label="{{ __('farmer.kpis.title') }}" data-fh-kpis>
    @foreach ($tiles as $tile)
        <button type="button" class="fh-kpi" data-fh-kpi="{{ $tile['key'] }}" data-panel="{{ $panelFor[$tile['key']] ?? $tile['key'] }}" aria-haspopup="dialog" aria-controls="fhDrawer">
            <span class="fh-kpi-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}<i class="ic-go" aria-hidden="true"></i></span>
            <span class="fh-kpi-value"><span data-fh-kpi-value>{{ $tile['value'] }}</span>@if (! empty($tile['unit'])) <small>{{ $tile['unit'] }}</small>@endif</span>
            @if (($tile['progress'] ?? null) !== null)
                <span class="fh-kpi-bar" role="img" aria-label="{{ __('farmer.kpis.crop.progress', ['percent' => $tile['progress']]) }}"><span style="width: {{ $tile['progress'] }}%"></span></span>
            @endif
            @if (! empty($tile['note']))<span class="fh-kpi-note">{{ $tile['note'] }}</span>@endif
        </button>
    @endforeach
</section>
@endif
