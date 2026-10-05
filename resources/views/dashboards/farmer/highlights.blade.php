{{-- "Today's Highlights": two to four cards, each saying WHAT is happening and WHAT to do. Built in
     FarmerDashboardService::highlights() from real warnings, today's action, the crop calendar and new advice only. --}}
@php
    $items = $f['highlights'];
    $levelLabel = ['danger' => __('farmer.status.danger'), 'watch' => __('farmer.status.watch'), 'info' => __('farmer.status.info'), 'good' => __('farmer.status.good')];
@endphp
@if (count($items) > 0)
    <section aria-labelledby="mfdHighlightsTitle" data-mfd-highlights>
        <h2 class="mfd-section-title" id="mfdHighlightsTitle">{{ __('farmer.highlights.title') }}</h2>
        <ul @class(['mfd-highlights', 'is-'.count($items)])>
            @foreach ($items as $item)
                <li class="mfd-hl is-{{ $item['level'] }}" data-highlight="{{ $item['key'] }}">
                    <div class="mfd-hl-head">
                        <span class="mfd-hl-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $item['icon']])</span>
                        <span class="mfd-hl-kicker">{{ $item['kicker'] }}</span>
                        @if (in_array($item['level'], ['danger', 'watch'], true))
                            <span class="mfd-sr-only">({{ $levelLabel[$item['level']] }})</span>
                        @endif
                    </div>
                    <h3>{{ $item['title'] }}</h3>
                    @if ($item['what'])<p>{{ $item['what'] }}</p>@endif
                    @if ($item['todo'])<p class="mfd-hl-todo"><strong>{{ __('farmer.highlights.todo') }}</strong> {{ $item['todo'] }}</p>@endif
                    <div class="mfd-hl-foot">
                        @if (! empty($item['ask']))
                            <button type="button" class="mfd-link is-button" data-ai-prefill="{{ $item['ask'] }}">@include('dashboards.mao.icon', ['name' => 'sparkle']){{ __('farmer.highlights.ask') }}</button>
                        @endif
                        @if (! empty($item['href']))
                            <a class="mfd-link" href="{{ $item['href'] }}" @if (! empty($item['modal'])) data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" @endif>{{ $item['link'] }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
