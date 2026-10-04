@props(['title', 'kicker' => null, 'lede' => null])

{{-- The header band of every staff (IT and MAO) page: the same .mfd-header the IT dashboard uses (farmer-dashboard.css, it-dashboard.css).
     Kicker, Georgia page title, one-line description; status pills and buttons go in the slot. --}}
<header class="mfd-header">
    <div class="mfd-header-main">
        @if ($kicker)<span class="mfd-kicker">{{ $kicker }}</span>@endif
        <h1>{{ $title }}</h1>
        @if ($lede)<p class="itd-lede">{{ $lede }}</p>@endif
    </div>
    @if (trim($slot) !== '')
        <div class="mfd-fresh">{{ $slot }}</div>
    @endif
</header>
