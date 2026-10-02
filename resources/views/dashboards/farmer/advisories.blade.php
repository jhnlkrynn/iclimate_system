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
@if (count($items) > 0)
    <section class="mfd-card" aria-labelledby="mfdAdvisoriesTitle" data-mfd-advisories>
        <div class="mfd-card-head"><h2 id="mfdAdvisoriesTitle">{{ __('farmer.advisories.title') }}</h2><a class="mfd-link" href="{{ route('planting-advisories.index') }}">{{ __('farmer.advisories.all') }}</a></div>
        <ul class="mfd-adv-list">
            @foreach ($items as $adv)
                <li>
                    <div class="mfd-adv-head"><strong>{{ $adv['title'] }}</strong><span class="mfd-muted">{{ $label($adv['when']) }}</span></div>
                    @if ($adv['summary'])<p>{{ $adv['summary'] }}</p>@endif
                    <a class="mfd-link" href="{{ $adv['href'] }}">{{ __('farmer.advisories.read') }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
