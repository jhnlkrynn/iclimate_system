{{-- Typhoon Safety Check-In: rendered only while a typhoon advisory is in effect (empty otherwise, so the slot hides). --}}
@php $t = $f['typhoon']; @endphp
@if ($t)
    <section class="fh-card fh-typhoon" aria-labelledby="fhTyphoonTitle" data-fh-typhoon>
        <h2 id="fhTyphoonTitle" class="fh-kicker">{{ __('farmer.home.typhoon.title') }}</h2>
        <p class="fh-big">{{ __('farmer.home.typhoon.question') }}</p>
        <p class="fh-muted">{{ $t['title'] }} · {{ $t['severity'] }}</p>
        @if ($t['status_label'])
            <p class="fh-badge is-{{ $t['status'] === 'needs_help' ? 'danger' : 'good' }}">{{ __('farmer.home.typhoon.answered', ['status' => $t['status_label']]) }}</p>
        @endif
        <form class="fh-typhoon-form" method="POST" action="{{ route('typhoon-safety.store') }}">
            @csrf
            <input type="hidden" name="event_key" value="{{ $t['key'] }}">
            <label class="mfd-sr-only" for="fhTyphoonNote">{{ __('farmer.home.typhoon.note') }}</label>
            <textarea id="fhTyphoonNote" name="note" rows="2" placeholder="{{ __('farmer.home.typhoon.note') }}">{{ old('note', $t['note']) }}</textarea>
            <div class="fh-typhoon-actions">
                <button class="mfd-btn is-primary" type="submit" name="status" value="safe">{{ __('farmer.home.typhoon.safe') }}</button>
                <button class="mfd-btn is-danger" type="submit" name="status" value="needs_help">{{ __('farmer.home.typhoon.help') }}</button>
            </div>
        </form>
    </section>
@endif
