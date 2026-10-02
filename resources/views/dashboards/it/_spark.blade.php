{{-- Tiny trend line. Needs $counts (array of numbers) and $label (read out by screen readers). Empty state is text, never a blank chart. --}}
@php
    $counts = array_values($counts ?? []);
    $max = $counts ? max($counts) : 0;
    $n = count($counts);
    $pts = [];
    foreach ($counts as $i => $v) {
        $pts[] = round($n > 1 ? $i / ($n - 1) * 96 + 2 : 50, 1).','.round(22 - ($max > 0 ? $v / $max * 18 : 0), 1);
    }
@endphp
@if ($n === 0 || $max === 0)
    <span class="itd-muted">None in this period</span>
@else
    <svg class="itd-spark" viewBox="0 0 100 24" role="img" aria-label="{{ $label }}: {{ implode(', ', $counts) }}" preserveAspectRatio="none">
        <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        <circle cx="{{ explode(',', end($pts))[0] }}" cy="{{ explode(',', end($pts))[1] }}" r="2" fill="currentColor"/>
    </svg>
@endif
