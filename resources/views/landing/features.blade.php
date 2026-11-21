@php($features = __('landing.features'))
@php($featureRoutes = ['analysis.weather', 'rice-productions.index', 'planting-advisories.index', 'climate-records.index', 'heat-map.index', 'reports.index'])
<section id="features" data-section aria-labelledby="features-title" class="lp-section lp-section-surface"><div class="lp-wrap"><div class="lp-section-heading"><h2 id="features-title" class="lp-h2 mt-0">{{ $features['title'] }}</h2><p class="lp-lead mx-auto">{{ $features['lead'] }}</p></div>
    <div class="features-layout mt-10">
        <figure class="features-photo" data-features-photo>
            <picture>
                <source type="image/webp" srcset="{{ asset('images/landing/lian-rice-field-960.webp') }} 960w, {{ asset('images/landing/lian-rice-field.webp') }} 1191w" sizes="(min-width: 1024px) 40vw, 100vw">
                <source type="image/jpeg" srcset="{{ asset('images/landing/lian-rice-field-960.jpg') }} 960w, {{ asset('images/landing/lian-rice-field.jpg') }} 1191w" sizes="(min-width: 1024px) 40vw, 100vw">
                <img src="{{ asset('images/landing/lian-rice-field.jpg') }}" alt="Green rice fields in Lian" width="1191" height="677" loading="lazy" class="h-full w-full rounded-xl object-cover">
            </picture>
        </figure>
        <div class="features-main">
        {{-- Six plain cards: each says what the feature does and links to it (or to the sign-in card). --}}
        <ul class="features-list" data-feature-grid aria-label="iClimate features">
            @foreach($features['items'] as $index => $feature)
                <li data-feature class="feature-item">
                    <h3 class="lp-card-title">{{ $feature['title'] }}</h3>
                    <p class="feature-description">{{ $feature['text'] }}</p>
                    <a class="feature-open" href="{{ auth()->check() ? route($featureRoutes[$index]) : '#login' }}">{{ auth()->check() ? 'Open feature' : 'Log in to open' }}</a>
                </li>
            @endforeach
        </ul>
        </div>
    </div>
</div></section>
