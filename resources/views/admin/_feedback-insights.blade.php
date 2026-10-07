{{-- Farmer Survey & Model Feedback – Naicel (2026-10-09): the Live Insights cards (ModelFeedbackService::insights).
     Rendered with the page and again by ModelFeedbackController::insights each time the page re-reads them. --}}
@foreach ($insights['items'] as $item)
    <article @class(['mfb-insight', 'is-'.$item['tone']]) data-insight="{{ $item['key'] }}">
        <h3>{{ $item['title'] }}</h3>
        <p>{{ $item['text'] }}</p>
    </article>
@endforeach
