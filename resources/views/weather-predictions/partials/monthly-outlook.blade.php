@php
    $mode = $monthlyOutlook['mode'];
    $m = $monthlyOutlook['model'];
    $w = $monthlyOutlook['weekly'];
    $h = $monthlyOutlook['historical'];
    $rowsList = function (array $rows, string $missing = 'Not available for this period') {
        $html = '';
        foreach ($rows as $r) {
            if (array_key_exists('available', $r) && ! $r['available']) {
                $html .= '<div class="wp-outlook-row is-unavailable"><span class="wp-outlook-k">'.e($r['label']).'</span><span class="wp-outlook-v">'.e($missing).'</span></div>';
                continue;
            }
            $html .= '<div class="wp-outlook-row"><span class="wp-outlook-k">'.e($r['label']).'</span><span class="wp-outlook-v">'.e($r['value']).' <small>'.e($r['unit'] ?? '').'</small></span></div>';
        }
        return new \Illuminate\Support\HtmlString($html);
    };
    $title = match ($mode) {
        'monthly' => 'Monthly Weather Prediction',
        'weekly' => 'Weekly Weather Predictions',
        default => 'Weather Prediction',
    };
@endphp
<section class="wp-outlook" aria-labelledby="wpOutlookTitle" data-monthly-outlook data-outlook-mode="{{ $mode }}" data-outlook-month="{{ $weatherCalendar['month']->format('Y-m') }}">
    <div class="wp-outlook-head">
        <h3 id="wpOutlookTitle" class="h5 fw-bold mb-0">{{ $title }} &middot; {{ $monthlyOutlook['label'] }}</h3>
        <span class="wp-outlook-period">Forecast period: {{ $monthlyOutlook['period'] }}</span>
    </div>

    @if($mode === 'monthly')
        <div class="wp-outlook-block">
            <div class="wp-outlook-tag is-model">{{ $m['type'] }}</div>
            {{-- The model's wind has no confirmed unit, so only IT sees it; a number without a unit is not safe to read. --}}
            <div class="wp-outlook-grid">{{ $rowsList(($isTechnical ?? false) ? $m['rows'] : array_values(array_filter($m['rows'], fn ($r) => $r['label'] !== 'Expected Wind'))) }}</div>

            @php $c = $m['cards']; @endphp
            <h4 class="wp-outlook-subtitle">Seasonal &amp; Outlook Information</h4>
            <div class="wp-outlook-cards">
                <article class="wp-ocard" aria-labelledby="ocSeasonTitle">
                    <h5 id="ocSeasonTitle" class="wp-ocard-title">Seasonal Pattern</h5>
                    <div class="wp-season {{ $c['season']['active'] === 'Wet' ? 'is-active' : '' }}">
                        <span class="wp-season-icon" aria-hidden="true">&#127783;</span>
                        <div><strong>Wet Season</strong><span>{{ $c['season']['wet'] }}</span></div>
                        @if($c['season']['active'] === 'Wet')<em>{{ $c['season']['month'] }} is here</em>@endif
                    </div>
                    <div class="wp-season {{ $c['season']['active'] === 'Dry' ? 'is-active' : '' }}">
                        <span class="wp-season-icon" aria-hidden="true">&#9728;&#65039;</span>
                        <div><strong>Dry Season</strong><span>{{ $c['season']['dry'] }}</span></div>
                        @if($c['season']['active'] === 'Dry')<em>{{ $c['season']['month'] }} is here</em>@endif
                    </div>
                </article>
                @foreach(['rainfall' => 'Rainfall Outlook', 'temperature' => 'Temperature Outlook'] as $key => $cardTitle)
                    @php $oc = $c[$key]; @endphp
                    <article class="wp-ocard" aria-labelledby="oc{{ ucfirst($key) }}Title">
                        <h5 id="oc{{ ucfirst($key) }}Title" class="wp-ocard-title">{{ $cardTitle }}</h5>
                        @if($oc['available'])
                            <div class="wp-ocard-status is-{{ \Illuminate\Support\Str::slug($oc['status']) }}">{{ $oc['status'] }}</div>
                            <div class="wp-ocard-value">{{ $oc['value'] }}</div>
                            <p class="wp-ocard-caption">{{ $oc['caption'] }}</p>
                            <p class="wp-ocard-basis"><strong>Historical comparison</strong> &middot; {{ $oc['years'] }} years ({{ $oc['span'] }})</p>
                        @else
                            <div class="wp-ocard-none">Not available for this period</div>
                            <p class="wp-ocard-caption">{{ $oc['reason'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
            <p class="wp-outlook-fineprint">These compare iClimate&rsquo;s estimate with past records for the same month. Past averages are not today&rsquo;s weather. This is not an official forecast.</p>
            <dl class="wp-outlook-meta">
                <div><dt>Forecast Period</dt><dd>{{ $m['period'] }}</dd></div>
                <div><dt>Forecast Type</dt><dd>{{ $m['type'] }} (not an official forecast)</dd></div>
                <div><dt>Source</dt><dd>{{ $m['source'] }}</dd></div>
                <div><dt>Last Updated</dt><dd>{{ $m['updated'] }}</dd></div>
                <div><dt>How sure is this?</dt><dd>{{ $m['uncertainty'] }}</dd></div>
            </dl>
        </div>
    @elseif($mode === 'weekly')
        <p class="small text-muted mb-2">No estimate for the whole month yet, so these are weekly summaries of the daily forecast.</p>
        <div class="wp-outlook-weeks">
            @foreach($w['weeks'] as $week)
                <article class="wp-outlook-week {{ $week['has_data'] ? '' : 'is-empty' }}" data-week-start="{{ $week['start'] }}" data-week-end="{{ $week['end'] }}">
                    <div class="wp-outlook-week-head">
                        <strong>{{ $week['label'] }}</strong>
                        <span>{{ $week['short'] }}</span>
                    </div>
                    <div class="small text-muted mb-2">Forecast period: {{ $week['period'] }} &middot; Coverage: {{ $week['coverage'] }}</div>
                    @if($week['has_data'])
                        <div class="wp-outlook-grid is-compact">{{ $rowsList($week['rows'], 'No forecast yet') }}</div>
                        @if($week['conditions'])
                            <p class="small mb-0 mt-2"><strong>Forecast weather:</strong>
                                {{ collect($week['conditions'])->map(fn ($n, $c) => $c.' ('.$n.' '.($n === 1 ? 'day' : 'days').')')->implode(', ') }}</p>
                        @endif
                    @else
                        <p class="wp-outlook-empty mb-0">No forecast yet</p>
                    @endif
                </article>
            @endforeach
        </div>
        <dl class="wp-outlook-meta">
            <div><dt>Forecast Type</dt><dd>{{ $w['type'] }}</dd></div>
            <div><dt>Source</dt><dd>{{ $w['source'] }}</dd></div>
            <div><dt>Last Updated</dt><dd>{{ $w['updated'] }}</dd></div>
        </dl>
        <p class="wp-outlook-missing">{{ $w['note'] }}</p>
    @else
        <div class="wp-outlook-block">
            <p class="wp-outlook-empty mb-1">No forecast is available for this month yet.</p>
            @if($monthlyOutlook['model_message'] && ($isTechnical ?? false))<p class="text-muted small mb-0">{{ $monthlyOutlook['model_message'] }}</p>@endif
        </div>
    @endif

    @if($h && ($h['observed_rows'] || $h['average_rows']))
        <div class="wp-outlook-block">
            <div class="wp-outlook-tag is-history">{{ $h['type'] }}</div>
            @if($h['observed_rows'])
                <p class="small text-muted mb-2">What was recorded in {{ $weatherCalendar['label'] }}:</p>
                <div class="wp-outlook-grid">{{ $rowsList($h['observed_rows']) }}</div>
            @endif
            @if($h['average_rows'])
                <p class="small text-muted mb-2 mt-2">Average for {{ $weatherCalendar['month']->format('F') }} over {{ $h['years'] }} other years ({{ $h['span'] }}):</p>
                <div class="wp-outlook-grid">{{ $rowsList($h['average_rows']) }}</div>
            @endif
            <p class="small text-muted mb-0 mt-2">Source: {{ $h['source'] }}. Past records for reference; not a forecast for {{ $weatherCalendar['label'] }}.</p>
        </div>
    @endif

    <p class="wp-outlook-pagasa">{{ $monthlyOutlook['pagasa_note'] }}</p>
</section>
