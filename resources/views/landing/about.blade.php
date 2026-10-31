@php($about = __('landing.about'))
<section id="about" data-section aria-labelledby="about-title" class="lp-section">
    <div class="lp-wrap"><div class="about-container mx-auto max-w-[720px] text-center">
        <h2 id="about-title" class="lp-h2 mt-0">{{ $about['title'] }}</h2>
        <p class="lp-lead mx-auto">{{ $about['description'] }}</p>
        <blockquote class="mt-8 text-[20px] leading-relaxed text-ink">“{{ $about['mission'] }}”</blockquote>
        <div class="mt-8"><h3 class="text-base font-semibold text-ink">{{ $about['sources'] }}</h3><div class="about-sources mt-3 flex flex-wrap justify-center gap-x-2 gap-y-1 text-[17px] text-muted">@foreach($about['source_items'] as $source)<span>{{ $source }}</span>@if(!$loop->last)<span aria-hidden="true">&nbsp;·&nbsp;</span>@endif @endforeach</div></div>
    </div></div>
</section>
