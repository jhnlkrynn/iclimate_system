@php($features = __('landing.features'))
<section id="features" data-section aria-labelledby="features-title" class="lp-section lp-section-surface"><div class="lp-wrap"><h2 id="features-title" class="lp-h2">{{ $features['title'] }}</h2>
    <ul class="mt-10 grid gap-x-12 sm:grid-cols-2" data-feature-grid>@foreach($features['items'] as $feature)<li data-feature class="border-t border-line py-5"><h3 class="lp-card-title">{{ $feature['title'] }}</h3><p class="mt-1 text-[17px] leading-relaxed text-muted">{{ $feature['text'] }}</p></li>@endforeach</ul>
</div></section>
