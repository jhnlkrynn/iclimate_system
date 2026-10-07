{{-- Dashboard redesign – Naicel (2026-10-09): a half-donut gauge drawn as SVG (no chart library), used by the MAO and
     farmer dashboards. $segments: [['value' => number, 'color' => css colour, 'label' => text], ...]; $total: what the
     whole arc stands for (default: the sum of the values); $big / $small: the words in the middle; $label: what a screen
     reader hears. Styles: .ic-gauge in resources/css/mao-dashboard.css. --}}
@php
    $gaugeTotal = (float) ($total ?? array_sum(array_column($segments, 'value')));
    $gaugeOffset = 0.0;
    $gaugeArc = 'M 18 100 A 82 82 0 0 1 182 100';
@endphp
<div class="ic-gauge">
    <svg viewBox="0 0 200 112" role="img" aria-label="{{ $label }}">
        <path d="{{ $gaugeArc }}" fill="none" stroke-width="26" pathLength="100" class="ic-gauge-track" />
        @foreach ($segments as $segment)
            @if ($gaugeTotal > 0 && $segment['value'] > 0)
                @php $part = round($segment['value'] / $gaugeTotal * 100, 2); @endphp
                <path d="{{ $gaugeArc }}" fill="none" stroke="{{ $segment['color'] }}" stroke-width="26" pathLength="100"
                    stroke-dasharray="{{ $part }} 100" stroke-dashoffset="-{{ $gaugeOffset }}" />
                @php $gaugeOffset += $part; @endphp
            @endif
        @endforeach
    </svg>
    <div class="ic-gauge-center"><strong>{{ $big }}</strong><span>{{ $small }}</span></div>
</div>
@if (collect($segments)->contains(fn ($segment) => ! empty($segment['label'])))
    <ul class="ic-gauge-legend">
        @foreach ($segments as $segment)
            @if (! empty($segment['label']))
                <li><span class="ic-gauge-dot" style="background: {{ $segment['color'] }}"></span>{{ $segment['label'] }}</li>
            @endif
        @endforeach
    </ul>
@endif
