@php($how = __('landing.how'))
<section id="how" data-section aria-labelledby="how-title" class="lp-section">
    <div class="lp-wrap">
        <h2 id="how-title" class="lp-h2">{{ $how['title'] }}</h2>
        <p class="lp-lead">{{ $how['lead'] }}</p>

        <div class="how-steps mt-12" data-steps>
            <article class="how-step">
                <div class="how-step-copy"><h3>{{ $how['steps'][0]['title'] }}</h3><p>{{ $how['steps'][0]['text'] }}</p></div>
                <div class="how-fragment how-fragment-farm" data-ui-fragment="farm"><span class="fragment-label">My farm</span><strong>Prenza</strong><span>Rainfed · NSIC Rc 222</span></div>
            </article>
            <article class="how-step">
                <div class="how-step-copy"><h3>{{ $how['steps'][1]['title'] }}</h3><p>{{ $how['steps'][1]['text'] }}</p></div>
                <div class="how-fragment how-fragment-forecast" data-ui-fragment="forecast"><span class="fragment-label">Barangay forecast</span><strong>Prenza · 72%</strong><span>Rain chance · 31°C heat</span></div>
            </article>
            <article class="how-step">
                <div class="how-step-copy"><h3>{{ $how['steps'][2]['title'] }}</h3><p>{{ $how['steps'][2]['text'] }}</p></div>
                <div class="how-fragment how-fragment-advisory" data-ui-fragment="advisory"><span class="fragment-label">MAO planting advisory</span><strong>Plant after the rain</strong><span>Watch field water before planting.</span></div>
            </article>
            <article class="how-step">
                <div class="how-step-copy"><h3>{{ $how['steps'][3]['title'] }}</h3><p>{{ $how['steps'][3]['text'] }}</p></div>
                <div class="how-fragment how-fragment-harvest" data-ui-fragment="harvest"><span class="fragment-label">Harvest outlook</span><strong>Days to harvest: 69 · around Jan 8, 2027</strong></div>
            </article>
        </div>
    </div>
</section>
