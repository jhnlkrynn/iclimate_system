@props([
    'attribute' => 'data-layer',
    'includeYield' => false,
    'includeFarmType' => false,
])

@php
    $layers = [
        'impact' => 'Climate Impact',
        'rainfall' => 'Rainfall Risk',
    ];

    if ($includeYield) {
        $layers['yield'] = 'Rice Yield';
    }

    if ($includeFarmType) {
        $layers['farm_type'] = 'Farm Type';
    }

    $layers['irrigation'] = 'Irrigation Priority';
@endphp

@foreach($layers as $key => $label)
    <button
        class="layer-btn {{ $loop->first ? 'active' : '' }}"
        type="button"
        {{ $attribute }}="{{ $key }}"
        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
    >{{ $label }}</button>
@endforeach
