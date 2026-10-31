@php($about = __('landing.about'))
<section id="about" data-section aria-labelledby="about-title" class="lp-section">
    <div class="lp-wrap"><div class="about-container mx-auto max-w-[720px] text-center">
        <h2 id="about-title" class="lp-h2 mt-0">{{ $about['title'] }}</h2>
        <p class="lp-lead mx-auto">{{ $about['description'] }}</p>
        <div class="about-rule mx-auto mt-8" aria-hidden="true"></div>
        <blockquote class="mt-8 text-[20px] leading-relaxed text-ink">“{{ $about['mission'] }}”</blockquote>
        <div class="mt-4 flex items-center justify-center gap-2 text-sm text-muted"><img src="{{ asset('images/mao-lian-seal.png') }}" alt="" width="24" height="24" class="h-6 w-6 rounded-full object-contain"><span>{{ $about['attribution'] }}</span></div>
    </div>
    @if (is_file(storage_path('app/public/landing/about.jpg')))
        <figure class="about-photo mx-auto mt-8 max-w-[960px]" data-about-photo><img src="{{ asset('storage/landing/about.jpg') }}" alt="{{ $about['photo_alt'] }}" class="h-full w-full rounded-xl object-cover"><figcaption class="mt-2 text-sm text-muted">{{ $about['photo_caption'] }}</figcaption></figure>
    @endif
    <div class="about-sources-block mx-auto mt-8 max-w-[960px] text-center"><h3 class="text-sm font-medium text-muted">{{ $about['sources'] }}</h3><div class="about-sources mt-3 grid grid-cols-2 text-left sm:grid-cols-4">@foreach($about['source_items'] as $source)<div class="about-source px-3 py-3 first:pl-0 even:border-l even:border-sage-200 sm:border-l sm:border-sage-200 sm:first:border-l-0 sm:even:border-l-0"><h4 class="font-semibold text-ink">{{ $source['name'] }}</h4><p class="mt-1 text-sm leading-relaxed text-muted">{{ $source['text'] }}</p></div>@endforeach</div></div>
    </div>
</section>
