@props(['includeFarmType' => false])

@foreach(\App\Support\HeatMapLegend::legendRows() as $row)
    <div class="legend-row"><span class="legend-swatch" style="background:{{ $row['color'] }}"></span> {{ $row['label'] }}</div>
@endforeach

@if($includeFarmType)
    <div class="risk-label mt-2">Farm Type Layer</div>
    @foreach([
        ['label' => 'Rainfed', 'color' => '#2563eb'],
        ['label' => 'Irrigated', 'color' => '#16a34a'],
        ['label' => 'Mixed', 'color' => '#a855f7'],
        ['label' => 'Unknown', 'color' => '#6b7280'],
    ] as $row)
        <div class="legend-row"><span class="legend-swatch" style="background:{{ $row['color'] }}"></span> {{ $row['label'] }}</div>
    @endforeach
@endif
