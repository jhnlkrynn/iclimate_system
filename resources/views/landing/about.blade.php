@php($about = __('landing.about'))
<section id="about" data-section aria-labelledby="about-title" class="lp-section">
    <div class="lp-wrap"><div class="grid gap-10 lg:grid-cols-2 lg:gap-20">
        <div class="max-w-[42rem]"><h2 id="about-title" class="lp-h2">{{ $about['title'] }}</h2><p class="lp-lead">{{ $about['description'] }}</p></div>
        <div class="max-w-[32rem]"><blockquote class="border-l-2 border-brand pl-5 text-[20px] leading-relaxed text-ink">“{{ $about['mission'] }}”</blockquote><div class="mt-8"><h3 class="text-base font-semibold text-ink">{{ $about['sources'] }}</h3><ul class="mt-3 list-disc space-y-2 pl-5 text-[17px] text-muted">@foreach($about['source_items'] as $source)<li>{{ $source }}</li>@endforeach</ul></div></div>
    </div></div>
</section>
