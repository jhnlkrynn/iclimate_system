<x-app-layout>
    @php
        $renderSection = fn (string $name): string => view('dashboards.farmer.'.$name, ['f' => $f])->render();
        $outlook = $f['outlook'];
        $rainfall = $f['rainfall'];
        $rainfallData = $rainfall ? json_encode(['labels' => $rainfall['chart']['labels'], 'datasets' => $rainfall['chart']['datasets']]) : null;
        $yieldHistory = $outlook && count($outlook['history']['labels']) >= 2 ? json_encode($outlook['history']) : null;
    @endphp

    <div class="mfd" data-farmer-dashboard data-live-url="{{ route('farmer.dashboard.live') }}">
        {{-- Read out by screen readers after a refresh; the sections themselves are not live, so a refresh does not re-read the page. --}}
        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden>
            <span data-mfd-refresh-error-text>We couldn't refresh your dashboard. You are seeing the last information that loaded.</span>
            <button type="button" class="mfd-btn is-quiet" data-mfd-retry>Try again</button>
        </div>
        {{-- Page order is reading order for everyone (screen readers and keyboard included): what to do today comes before the numbers. --}}
        @foreach (['hero', 'notice', 'alerts', 'action', 'plan', 'kpis', 'forecast', 'status', 'setup'] as $name)
            @php $html = $renderSection($name); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif @if ($name === 'alerts') aria-live="polite" @endif>{!! $html !!}</div>
        @endforeach

        <details class="mfd-map" id="farmRiskMap">
            <summary>Risk map and barangay details</summary>
            <div class="mfd-map-body">
                <p class="mfd-foot" style="margin-top:0">Farm risk is a planning guide only. It is not an official hazard warning.</p>
                @include('partials.barangay-risk-map', [
                    'heading' => 'Lian Municipality Risk Map',
                    'subheading' => 'All 19 barangays at a glance, using the weather right now. Tap a barangay for details.',
                    'showRefresh' => false,
                    'highlightBarangay' => $f['barangay'] ? \App\Support\LianBarangays::canonicalName($f['barangay']) : null,
                ])
            </div>
        </details>

        @if ($outlook || $rainfall)
            <div class="mfd-analytics {{ $outlook && $rainfall ? 'is-two' : '' }}">
                @if ($outlook)
                    <section class="mfd-card" aria-labelledby="mfdYieldTitle" data-mfd-yield>
                        <div class="mfd-card-head"><h2 id="mfdYieldTitle">My Expected Harvest</h2></div>
                        <div class="mfd-bar-row">
                            <div class="mfd-bar-label">{{ $outlook['same_month'] ? 'Lian average for '.$outlook['month_name'] : 'Lian average, all months' }} <span>only for comparing · {{ number_format($outlook['historical'], 2) }} t/ha</span></div>
                            <div class="mfd-bar" role="img" aria-label="{{ $outlook['same_month'] ? 'Lian average for '.$outlook['month_name'] : 'Lian average, all months' }} {{ number_format($outlook['historical'], 2) }} tons per hectare"><i @style(['width: ' . round($outlook['historical'] / $outlook['max'] * 100) . '%'])></i></div>
                        </div>
                        <div class="mfd-bar-row">
                            <div class="mfd-bar-label">Your expected harvest <span>best guess · {{ number_format($outlook['predicted'], 2) }} t/ha</span></div>
                            <div class="mfd-bar is-mine" role="img" aria-label="Your expected harvest {{ number_format($outlook['predicted'], 2) }} tons per hectare"><i @style(['width: ' . round($outlook['predicted'] / $outlook['max'] * 100) . '%'])></i></div>
                        </div>
                        @if ($outlook['own'])
                            <div class="mfd-bar-row">
                                <div class="mfd-bar-label">Your own past harvests <span>{{ $outlook['own']['count'] }} recorded · average {{ number_format($outlook['own']['average'], 2) }} t/ha</span></div>
                                <div class="mfd-bar is-own" role="img" aria-label="Your own average harvest {{ number_format($outlook['own']['average'], 2) }} tons per hectare from {{ $outlook['own']['count'] }} recorded harvests"><i @style(['width: ' . min(100, round($outlook['own']['average'] / max($outlook['max'], $outlook['own']['average']) * 100)) . '%'])></i></div>
                            </div>
                            <p><span class="mfd-diff {{ $outlook['own']['difference'] < 0 ? 'is-below' : '' }}">{{ $outlook['own']['difference'] > 0 ? '+' : '' }}{{ number_format($outlook['own']['difference'], 2) }} t/ha</span> <span class="mfd-muted">compared with your own average harvest</span></p>
                        @endif
                        <p><span class="mfd-diff {{ $outlook['difference'] < 0 ? 'is-below' : '' }}">{{ $outlook['difference'] > 0 ? '+' : '' }}{{ number_format($outlook['difference'], 2) }} t/ha</span> <span class="mfd-muted">compared with {{ $outlook['same_month'] ? "Lian's average for ".$outlook['month_name'] : "Lian's average for all months" }}</span></p>
                        @if ($yieldHistory)
                            <div class="mfd-chart-sm is-loading">
                                <canvas id="farmerYieldHistoryChart" data-mfd-chart="{{ $yieldHistory }}" role="img"
                                    aria-label="Lian average rice yield by year in tons per hectare, with your expected harvest as a dashed line"></canvas>
                            </div>
                            <p class="mfd-foot">Lian's average harvest for each year. A year with fewer than 12 months of records uses only the months we have.</p>
                        @endif
                        <p class="mfd-foot">ⓘ This is a best guess and may change with the weather and your farm. The average is for all of Lian ({{ $outlook['same_month'] ? $outlook['month_name'].' records, ' : 'monthly records, ' }}{{ $outlook['from'] }}–{{ $outlook['to'] }}), not your own past harvests or your barangay's. We do not have harvest records for your barangay yet.</p>
                    </section>
                @endif
                @if ($rainfall)
                    <section class="mfd-card" aria-labelledby="mfdRainTitle" data-mfd-rainfall>
                        <div class="mfd-card-head"><h2 id="mfdRainTitle">Rainfall Each Month — {{ $rainfall['year'] }} <span class="mfd-muted">(latest year on record)</span></h2></div>
                        <div class="mfd-chart is-loading">
                            <canvas id="farmerRainfallTrendChart" data-mfd-chart="{{ $rainfallData }}"
                                data-chart-source="Ambulong monthly climate records (recorded); iClimate monthly model (forecast, lighter bars)" data-chart-type="mixed"
                                data-chart-unit="mm" data-chart-period="{{ $rainfall['year'] }}, by month" data-chart-info-style="icon" aria-label="Monthly rainfall for Lian"></canvas>
                        </div>
                        <p class="mfd-foot">Rainfall by month in millimetres, from the Ambulong weather station (the recording station for Lian, not measured in your barangay). Solid bars are recorded rainfall{{ $rainfall['has_forecast'] ? '; lighter bars with a dashed edge are forecast' : '' }}.</p>
                    </section>
                @endif
            </div>
        @endif

        @php $html = $renderSection('advisories'); @endphp
        <div class="mfd-slot" data-slot="advisories" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>

        <section class="mfd-card mfd-quickwrap" aria-labelledby="mfdQuickTitle">
            <div class="mfd-card-head"><h2 id="mfdQuickTitle">Quick Actions</h2></div>
            <div class="mfd-quick">
                <a href="{{ route('planting-advisories.index') }}">@include('dashboards.mao.icon', ['name' => 'advisory'])View Advisories</a>
                <a href="{{ route('calendar.index') }}">@include('dashboards.mao.icon', ['name' => 'calendar'])Open Calendar</a>
                <a href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">@include('dashboards.mao.icon', ['name' => 'yield'])Check Prediction</a>
                <a href="{{ route('notifications.index') }}">@include('dashboards.mao.icon', ['name' => 'bell'])Notifications @if ($f['unread'] > 0)<span class="mfd-badge">{{ $f['unread'] }}</span>@endif</a>
            </div>
        </section>

        <details class="mfd-info">
            <summary>ⓘ About This Information</summary>
            <div class="mfd-info-panel">
                <ul>
                    <li><strong>Live weather</strong> The current reading for Lian, Batangas from an online weather service. It is not measured on your own farm.</li>
                    <li><strong>Forecast</strong> What the weather service expects for the coming days. Forecasts can change.</li>
                    <li><strong>Past rainfall</strong> Rainfall for each month in past years, recorded for Lian.</li>
                    <li><strong>Expected harvest</strong> A best guess made by iClimate. It is not a real harvest record.</li>
                    <li><strong>Advisories</strong> Published by the Municipal Agriculture Office.</li>
                </ul>
            </div>
        </details>
    </div>
</x-app-layout>
