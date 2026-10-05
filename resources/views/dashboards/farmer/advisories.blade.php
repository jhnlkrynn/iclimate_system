{{-- "Latest Farming Advice": up to three published, in-effect advisories (the farmer's barangay first, then Lian-wide),
     worded as on the Advisories page (FarmerAdvisoryView). The full advisory opens in the Advisories page's own pop-up. --}}
@php
    $items = $f['advisories'];
    $today = \Carbon\CarbonImmutable::now('Asia/Manila')->startOfDay();
    $label = function ($when) use ($today) {
        if (! $when) {
            return '';
        }
        $d = (int) $when->startOfDay()->diffInDays($today, false);

        return $d <= 0 ? __('farmer.advisories.today') : ($d === 1 ? __('farmer.advisories.yesterday') : $when->format('M j'));
    };
@endphp
<section class="mfd-card" aria-labelledby="mfdAdvisoriesTitle" data-mfd-advisories>
    <div class="mfd-card-head"><h2 id="mfdAdvisoriesTitle">{{ __('farmer.advisories.title') }}</h2><a class="mfd-link" href="{{ route('planting-advisories.index') }}">{{ __('farmer.advisories.all') }}</a></div>
    @if (count($items) === 0)
        <div class="mfd-empty">@include('dashboards.mao.icon', ['name' => 'advisory'])<p>{{ __('farmer.advisories.empty') }}</p></div>
    @else
        <ul class="mfd-adv-list">
            @foreach ($items as $adv)
                <li>
                    <div class="mfd-adv-text">
                        <div class="mfd-adv-tags">
                            @if ($adv['is_new'])<span class="mfd-pill is-info">{{ __('farmer.advisories.new') }}</span>@endif
                            @if ($adv['for_barangay'])<span class="mfd-pill is-good">{{ __('farmer.advisories.your_barangay') }}</span>@endif
                        </div>
                        <strong class="mfd-adv-title">{{ $adv['title'] }}</strong>
                        @if ($adv['summary'])<p>{{ $adv['summary'] }}</p>@endif
                        @if ($adv['action'])<p class="mfd-adv-action"><strong>{{ __('farmer.advisories.for_farmers') }}</strong> {{ $adv['action'] }}</p>@endif
                        <p class="mfd-adv-meta">{{ $adv['source'] }}@if ($adv['when']) • {{ $label($adv['when']) }}@endif</p>
                    </div>
                    <a class="mfd-btn is-quiet" href="{{ $adv['href'] }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="{{ __('farmer.advisories.read_label', ['title' => $adv['title']]) }}">{{ __('farmer.advisories.read') }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
