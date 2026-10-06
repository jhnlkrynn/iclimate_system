{{-- The five summary cards. A status card (its value is a word) gets a dot in its status colour next to the word. --}}
<section aria-labelledby="itdKpiTitle" data-it-kpis>
    <h2 class="mfd-sr-only" id="itdKpiTitle">System at a glance</h2>
    <div class="itd-kpis">
        @foreach ($it['kpis'] as $k)
            @php
                $word = ! preg_match('/^[\d.,\s—%]+$/u', $k['value']);
                // A link that used to open a dashboard tab now points at its section: on this page, or on System Health.
                $target = str_contains($k['href'], 'tab=') ? \Illuminate\Support\Str::after($k['href'], '#') : null;
                $href = $target === null ? $k['href']
                    : (in_array($target, ['itd-kpis', 'itd-health', 'itd-roles', 'itd-jobs', 'itd-ops', 'itd-security'], true) ? '#'.$target : route('system-health.index').'#'.$target);
            @endphp
            <div class="mfd-kpi is-{{ $k['level'] }}" data-kpi="{{ $k['key'] }}">
                <span class="mfd-kpi-label">{{ $k['label'] }}</span>
                <div class="mfd-kpi-value {{ $word ? 'is-text' : '' }}">@if ($word)<i class="itd-dot is-{{ $k['level'] }}" aria-hidden="true"></i>@endif<span>{{ $k['value'] }}</span>@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif</div>
                <div class="mfd-kpi-meaning">{{ $k['note'] }}</div>
                <a class="itd-kpi-link" href="{{ $href }}" aria-label="{{ $k['label'] }}: open details"><span class="mfd-sr-only">Open details</span></a>
            </div>
        @endforeach
    </div>
</section>
