{{--
    Landing page section heading (resources/views/landing/*): an uppercase eyebrow after a short brand rule, a bold heading
    of two lines at most (break it with <br class="max-sm:hidden">), and one short muted sentence. Styles: landing.css.
--}}
@props(['id', 'eyebrow', 'lead' => null])
<div {{ $attributes->merge(['class' => 'max-w-[40rem]']) }} data-reveal>
    <p class="lp-eyebrow">{{ $eyebrow }}</p>
    <h2 id="{{ $id }}" class="lp-h2">{{ $slot }}</h2>
    @if ($lead)
        <p class="lp-lead">{{ $lead }}</p>
    @endif
</div>
