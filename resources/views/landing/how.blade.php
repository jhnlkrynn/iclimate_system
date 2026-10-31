@php($how = __('landing.how'))
<section id="how" data-section aria-labelledby="how-title" class="lp-section">
    <div class="lp-wrap">
        <h2 id="how-title" class="lp-h2">{{ $how['title'] }}</h2>
        <p class="lp-lead">{{ $how['lead'] }}</p>

        <div class="season-timeline mt-12" data-season-timeline>
            <div class="season-months" aria-label="Sample wet season months">
                @foreach ($how['months'] as $month)
                    <span data-season-month>{{ $month }}</span>
                @endforeach
            </div>
            <div class="season-track" aria-hidden="true"></div>
            <ol class="season-steps" data-steps>
                <li class="season-step" data-season-step="farm" style="--season-position: 0%">
                    <span class="season-point" aria-hidden="true"></span>
                    <div class="season-step-copy"><span class="season-number" data-step-number>01</span><h3>{{ $how['steps'][0]['title'] }}</h3><p>{{ $how['steps'][0]['text'] }}</p></div>
                    <div class="season-fragment season-fragment-farm" data-ui-fragment="farm"><span class="fragment-label">My farm</span><strong>Prenza</strong><span>Rainfed · NSIC Rc 222</span></div>
                </li>
                <li class="season-step" data-season-step="forecast" style="--season-position: 33%">
                    <span class="season-point" aria-hidden="true"></span>
                    <div class="season-step-copy"><span class="season-number" data-step-number>02</span><h3>{{ $how['steps'][1]['title'] }}</h3><p>{{ $how['steps'][1]['text'] }}</p></div>
                    <div class="season-fragment season-fragment-forecast" data-ui-fragment="forecast"><span class="fragment-label">Barangay forecast</span><strong>Prenza · 72%</strong><span>Rain chance · 31°C heat</span></div>
                </li>
                <li class="season-step" data-season-step="advisory" style="--season-position: 66%">
                    <span class="season-point" aria-hidden="true"></span>
                    <div class="season-step-copy"><span class="season-number" data-step-number>03</span><h3>{{ $how['steps'][2]['title'] }}</h3><p>{{ $how['steps'][2]['text'] }}</p></div>
                    <div class="season-fragment season-fragment-advisory" data-ui-fragment="advisory"><span class="fragment-label">MAO planting advisory</span><strong>Plant after the rain</strong><span>Watch field water before planting.</span></div>
                </li>
                <li class="season-step" data-season-step="harvest" style="--season-position: 100%">
                    <span class="season-point" aria-hidden="true"></span>
                    <div class="season-step-copy"><span class="season-number" data-step-number>04</span><h3>{{ $how['steps'][3]['title'] }}</h3><p>{{ $how['steps'][3]['text'] }}</p></div>
                    <div class="season-fragment season-fragment-harvest" data-ui-fragment="harvest"><span class="fragment-label">Harvest outlook</span><strong>Days to harvest: 69 · around Jan 8, 2027</strong></div>
                </li>
            </ol>
        </div>
    </div>
</section>
