<div class="itd-kpi-stack" data-it-kpis>
    @foreach ([$it['kpis'], $it['kpis_extra']] as $row => $cards)
        <div class="itd-kpis {{ $row === 1 ? 'is-four' : '' }}">
            @foreach ($cards as $k)
                <div class="itd-kpi is-{{ $k['level'] }}" data-kpi="{{ $k['key'] }}">
                    <div class="itd-kpi-top">
                        <span class="itd-kpi-label">{{ $k['label'] }}</span>
                        <span class="itd-info" tabindex="0" role="note" aria-label="{{ $k['tip'] }}" data-tip="{{ $k['tip'] }}">i</span>
                    </div>
                    <div class="itd-kpi-value {{ preg_match('/^[\d.,\s—%]+$/u', $k['value']) ? '' : 'is-text' }}">{{ $k['value'] }}@if ($k['unit'])<span class="itd-unit">{{ $k['unit'] }}</span>@endif</div>
                    @include('dashboards.it._badge', ['level' => $k['level']])
                    @if ($k['compare'])
                        <div class="itd-compare {{ $k['good'] === null ? '' : ($k['good'] ? 'is-good' : 'is-bad') }}">{{ $k['compare'] }}</div>
                    @endif
                    @if ($k['href'])<a class="itd-kpi-link" href="{{ $k['href'] }}" @if (str_contains($k['href'], 'tab=')) data-tab-link="{{ \Illuminate\Support\Str::between($k['href'], 'tab=', '#') }}" data-target="{{ \Illuminate\Support\Str::after($k['href'], '#') }}" @endif aria-label="{{ $k['label'] }}: open details"><span class="visually-hidden">Open details</span></a>@endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
