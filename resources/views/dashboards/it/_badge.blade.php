{{-- Status pill in the shared farmer style: an icon plus a text label, so colour is never the only signal. $level = ok | warn | crit | unk. --}}
@php
    $lvl = $level ?? 'unk';
    [$tone, $glyph] = ['ok' => ['good', 'check'], 'warn' => ['watch', 'alert'], 'crit' => ['danger', 'danger'], 'unk' => ['unk', 'info']][$lvl] ?? ['unk', 'info'];
@endphp
<span class="mfd-pill is-{{ $tone }}">@include('dashboards.mao.icon', ['name' => $glyph])<span>{{ $text ?? \App\Services\Analytics\ItDashboardService::LEVELS[$lvl] }}</span></span>
