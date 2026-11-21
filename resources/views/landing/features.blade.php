@php($features = __('landing.features'))
@php($featureRoutes = ['analysis.weather', 'rice-productions.index', 'planting-advisories.index', 'climate-records.index', 'heat-map.index', 'reports.index'])
<section id="features" data-section aria-labelledby="features-title" class="lp-section lp-section-surface"><div class="lp-wrap"><div class="lp-section-heading"><h2 id="features-title" class="lp-h2 mt-0">{{ $features['title'] }}</h2><p class="lp-lead mx-auto">{{ $features['lead'] }}</p></div>
    <div class="features-layout mt-10">
        <figure class="features-photo" data-features-photo>
            <picture>
                <source type="image/webp" srcset="{{ asset('images/landing/lian-rice-field-960.webp') }}">
                <source type="image/jpeg" srcset="{{ asset('images/landing/lian-rice-field-960.jpg') }}">
                <img src="{{ asset('images/landing/lian-rice-field-960.jpg') }}" alt="Green rice fields in Lian" loading="lazy" class="h-full w-full rounded-xl object-cover">
            </picture>
        </figure>
        <div class="features-main">
        <div class="features-preview" data-features-preview aria-live="polite">
            @foreach($features['items'] as $index => $feature)
                <article id="feature-preview-{{ $index }}" role="tabpanel" class="feature-preview-panel{{ $loop->first ? ' is-active' : '' }}" data-feature-preview="{{ $index }}" @unless($loop->first) hidden @endunless>
                    @if($index === 2)
                        <img src="{{ asset('images/landing/farmer-dashboard.jpg') }}" alt="iClimate farmer dashboard showing harvest, advisories and wet-season status" loading="lazy">
                        <p class="feature-preview-caption">Farmer dashboard</p>
                    @else
                        <div class="feature-preview-placeholder">
                            <strong>{{ $feature['title'] }}</strong>
                            <span>{{ $feature['text'] }}</span>
                            <a href="{{ auth()->check() ? route($featureRoutes[$index]) : '#login' }}" class="lp-btn-primary mt-2 self-center">{{ auth()->check() ? 'Open '.strtolower($feature['title']) : 'Log in to use this' }}</a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        <ul class="features-list" data-feature-grid role="tablist" aria-label="iClimate features">
            @foreach($features['items'] as $index => $feature)
                <li data-feature class="feature-item{{ $loop->first ? ' is-active' : '' }}">
                    <button type="button" role="tab" class="feature-select" data-feature-select="{{ $index }}" aria-controls="feature-preview-{{ $index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">
                        <span><strong class="lp-card-title">{{ $feature['title'] }}</strong><span class="feature-description">{{ $feature['text'] }}</span></span>
                    </button>
                    <a class="feature-open" href="{{ auth()->check() ? route($featureRoutes[$index]) : '#login' }}">{{ auth()->check() ? 'Open feature' : 'Log in to open' }}</a>
                </li>
            @endforeach
        </ul>
        </div>
    </div>
</div></section>
