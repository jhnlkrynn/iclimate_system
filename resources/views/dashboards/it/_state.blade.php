{{-- An inline notice in the shared farmer style. $kind = empty | error | good, $text = the message. --}}
@php
    [$stateClass, $stateIcon] = ['empty' => ['mfd-empty', 'info'], 'error' => ['mfd-empty is-error', 'danger'], 'good' => ['mfd-note is-good', 'check']][$kind];
@endphp
<div class="{{ $stateClass }}" @if ($kind === 'error') role="alert" @endif>@include('dashboards.mao.icon', ['name' => $stateIcon])<p>{{ $text }}</p></div>
