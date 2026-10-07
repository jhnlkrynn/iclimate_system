<x-app-layout>
    <x-slot:title>{{ __('farmer.survey.title') }}</x-slot:title>
    {{-- Farmer Survey & Model Feedback – Naicel (2026-10-09): FarmerSurveyController. Three questions answered 1 to 5 with
         big buttons, in the farmer's language. Opened from an advisory or a yield prediction, the answer is saved with it. --}}
    @php
        $S = fn (string $key, array $replace = []) => __('farmer.survey.'.$key, $replace);
        $locale = app()->getLocale();
        $seasonWord = fn (?string $season): string => __('yield_request.'.strtolower((string) $season));
    @endphp
    <style>
        .fsv-q { border: 0; padding: 0; margin: 0 0 1.25rem; }
        .fsv-q legend { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; float: none; }
        .fsv-scale { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .5rem; }
        .fsv-opt { position: relative; }
        .fsv-opt input { position: absolute; opacity: 0; inset: 0; width: 100%; height: 100%; cursor: pointer; }
        .fsv-opt span { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; min-height: 64px; padding: .4rem .25rem;
            border: 2px solid var(--line, #dfe7e2); border-radius: 10px; background: var(--card, #fff); text-align: center; font-size: .9rem; line-height: 1.2; }
        .fsv-opt span strong { font-size: 1.25rem; }
        .fsv-opt input:checked + span { border-color: var(--brand, #1f7a4d); background: var(--good-bg, #e6f4ec); font-weight: 700; }
        .fsv-opt input:focus-visible + span { outline: 3px solid var(--brand, #1f7a4d); outline-offset: 2px; }
        .fsv-about { padding: .75rem 1rem; border-left: 4px solid var(--brand, #1f7a4d); border-radius: 4px; background: var(--subtle, #f5f8f6); font-weight: 600; margin-bottom: 1.25rem; }
        .fsv-mine li { padding: .6rem 0; border-bottom: 1px solid var(--line, #dfe7e2); }
        .fsv-mine li:last-child { border-bottom: 0; }
        @media (max-width: 575.98px) { .fsv-scale { grid-template-columns: minmax(0, 1fr); } .fsv-opt span { flex-direction: row; gap: 8px; min-height: 48px; justify-content: flex-start; padding-left: 1rem; } }
    </style>
    <div class="fad" data-farmer-survey lang="{{ $locale }}">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $S('kicker') }}</span>
                <h1>{{ $S('heading') }}</h1>
                <p>{{ $S('intro') }}</p>
            </div>
            <div class="fad-hero-actions">
                <a class="fad-btn is-quiet" href="{{ route('farmer.dashboard') }}">{{ $S('back') }}</a>
            </div>
        </header>

        @if (session('success'))<div class="alert alert-success" role="status" data-survey-saved>{{ session('success') }}</div>@endif

        <form class="fad-card mb-3" method="POST" action="{{ route('farmer.survey.store') }}" data-loading="true" data-survey-form>
            @csrf
            @if ($advisory)
                <input type="hidden" name="subject" value="advisory">
                <input type="hidden" name="planting_advisory_id" value="{{ $advisory->id }}">
                <p class="fsv-about" data-survey-about="advisory">{{ $S('about_advisory', ['title' => $advisory->title]) }}</p>
            @elseif ($prediction)
                <input type="hidden" name="subject" value="prediction">
                <input type="hidden" name="yield_prediction_request_id" value="{{ $prediction->id }}">
                <p class="fsv-about" data-survey-about="prediction">{{ $S('about_prediction', ['season' => $seasonWord($prediction->season), 'yield' => number_format((float) $prediction->predicted_yield, 2)]) }}</p>
            @else
                <div class="mb-4">
                    <label class="form-label fw-bold" for="fsvSubject">{{ $S('about') }}</label>
                    <select class="form-select form-select-lg" id="fsvSubject" name="subject" required>
                        @foreach (\App\Models\FarmerSurveyResponse::SUBJECTS as $key)
                            <option value="{{ $key }}" @selected(old('subject', $subject) === $key)>{{ $S('subjects.'.$key) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @foreach (\App\Models\FarmerSurveyResponse::QUESTIONS as $question)
                <fieldset class="fsv-q">
                    <legend>{{ $loop->iteration }}. {{ $S('questions.'.$question) }}</legend>
                    <div class="fsv-scale">
                        @foreach (range(1, 5) as $value)
                            <label class="fsv-opt">
                                <input type="radio" name="{{ $question }}" value="{{ $value }}" required @checked((int) old($question) === $value)>
                                <span><strong>{{ $value }}</strong>{{ $S('scale.'.$value) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="mb-3">
                <label class="form-label fw-bold" for="fsvComment">{{ $S('comment') }}</label>
                <textarea class="form-control" id="fsvComment" name="comment" rows="3" maxlength="1000" placeholder="{{ $S('comment_hint') }}">{{ old('comment') }}</textarea>
            </div>
            @if ($errors->any())<div class="text-danger mb-2" role="alert">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary btn-lg" type="submit" data-loading-text="...">{{ $S('submit') }}</button>
        </form>

        @if ($mine->isNotEmpty())
        <section class="fad-card" aria-labelledby="fsvMine" data-survey-mine>
            <h2 class="h5 fw-bold" id="fsvMine">{{ $S('mine') }}</h2>
            <ul class="list-unstyled mb-0 fsv-mine">
                @foreach ($mine as $answer)
                    <li>
                        <strong>{{ $answer->advisory?->title ?? $S('subjects.'.$answer->subject) }}</strong> · {{ $S('average', ['score' => number_format($answer->score(), 1)]) }}
                        <div class="small text-muted">{{ $S('answered', ['date' => $answer->created_at->copy()->timezone(\App\Support\BusinessClock::timezone())->locale($locale)->translatedFormat('M j, Y')]) }}</div>
                    </li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>
</x-app-layout>
