@php($stats = \App\Support\LandingStats::get())
@if ($stats) @php($numbers = __('landing.numbers'))
<section id="numbers" data-section aria-labelledby="numbers-title" class="lp-section"><div class="lp-wrap"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><h2 id="numbers-title" class="lp-h2">{{ $numbers['title'] }}</h2><p class="lp-lead mt-0">{{ $numbers['lead'] }}</p></div>
<dl class="mt-10 grid grid-cols-2 gap-8 border-y border-line py-8 sm:grid-cols-4 sm:gap-4">@foreach($numbers['labels'] as $key => $label)<div data-stat="{{ $key }}"><dd class="text-[2.75rem] font-bold leading-none tracking-[-0.03em] text-brand">{{ number_format($stats[$key]) }}</dd><dt class="mt-2 text-[17px] leading-snug text-muted">{{ $label }}</dt></div>@endforeach</dl>
</div></section>
@endif
