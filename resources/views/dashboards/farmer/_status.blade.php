{{-- Status pill: icon AND text, never colour alone. $level = good | watch | danger | info, $label = the words. --}}
@php $statusIcon = ['good' => 'check', 'watch' => 'alert', 'danger' => 'danger', 'info' => 'info'][$level] ?? 'info'; @endphp
<span class="mfd-pill is-{{ $level }}">@include('dashboards.mao.icon', ['name' => $statusIcon])<span>{{ $label }}</span></span>
