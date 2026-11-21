@php($stats = \App\Support\LandingStats::get())
@if ($stats) @php($numbers = __('landing.numbers'))
<section id="numbers" data-section aria-labelledby="numbers-title" class="lp-section"><div class="lp-wrap"><div class="lp-section-heading"><h2 id="numbers-title" class="lp-h2 mt-0">{{ $numbers['title'] }}</h2><p class="lp-lead mx-auto">{{ $numbers['lead'] }}</p></div>
<dl class="lp-stats mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4">@foreach($numbers['labels'] as $key => $label)<div class="lp-stat" data-stat="{{ $key }}"><dd class="text-[2.25rem] font-semibold leading-none tracking-[-0.01em] text-brand">{{ number_format($stats[$key]) }}</dd><dt class="mt-2 text-[17px] leading-snug text-muted">{{ $label }}</dt></div>@endforeach</dl>
</div></section>
@endif
