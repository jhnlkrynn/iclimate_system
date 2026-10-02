{{-- Status pill: a dot carrying a shape (✓ ▲ ✕ ?) plus a text label, so colour is never the only signal. --}}
@php
    $glyph = ['ok' => '✓', 'warn' => '▲', 'crit' => '✕', 'unk' => '?'];
    $lvl = $level ?? 'unk';
@endphp
<span class="itd-badge is-{{ $lvl }}"><span class="itd-dot" aria-hidden="true">{{ $glyph[$lvl] }}</span>{{ $text ?? \App\Services\Analytics\ItDashboardService::LEVELS[$lvl] }}</span>
