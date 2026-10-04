<section aria-labelledby="itdKpiTitle" data-it-kpis>
    <h2 class="mfd-section-title" id="itdKpiTitle">System at a glance</h2>
    <div class="itd-kpi-stack">
        @foreach ([$it['kpis'], $it['kpis_extra']] as $row => $cards)
            <div class="mfd-kpis {{ $row === 1 ? 'is-four' : '' }}">
                @foreach ($cards as $k)
                    <div class="mfd-kpi is-{{ $k['level'] }}" data-kpi="{{ $k['key'] }}">
                        <div class="itd-kpi-top">
                            <span class="mfd-kpi-label">{{ $k['label'] }}</span>
                            <span class="itd-info" tabindex="0" role="note" aria-label="{{ $k['tip'] }}" data-tip="{{ $k['tip'] }}">i</span>
                        </div>
                        <div class="mfd-kpi-value {{ preg_match('/^[\d.,\s—%]+$/u', $k['value']) ? '' : 'is-text' }}"><span>{{ $k['value'] }}</span>@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif</div>
                        @include('dashboards.it._badge', ['level' => $k['level']])
                        @if ($k['compare'])
                            <div class="mfd-kpi-meaning itd-compare {{ $k['good'] === null ? '' : ($k['good'] ? 'is-good' : 'is-bad') }}">{{ $k['compare'] }}</div>
                        @endif
                        @if ($k['href'])<a class="itd-kpi-link" href="{{ $k['href'] }}" @if (str_contains($k['href'], 'tab=')) data-tab-link="{{ \Illuminate\Support\Str::between($k['href'], 'tab=', '#') }}" data-target="{{ \Illuminate\Support\Str::after($k['href'], '#') }}" @endif aria-label="{{ $k['label'] }}: open details"><span class="mfd-sr-only">Open details</span></a>@endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
