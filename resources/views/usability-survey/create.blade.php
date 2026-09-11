<x-app-layout>
    <style>
        .sus-page { display: grid; gap: 1rem; }
        .sus-item-card { border: 1px solid var(--ic-border); border-radius: 14px; background: var(--ic-panel); padding: 1.1rem; }
        .sus-item-number { display: inline-flex; align-items: center; justify-content: center; width: 1.9rem; height: 1.9rem; border-radius: 999px; background: var(--ic-green-100); color: var(--ic-green-700); font-weight: 900; font-size: .85rem; flex: 0 0 auto; }
        .sus-item-text { color: var(--ic-ink); font-weight: 700; line-height: 1.4; }
        .sus-scale { display: flex; gap: .4rem; margin-top: .85rem; flex-wrap: wrap; }
        .sus-scale-option { flex: 1 1 0; min-width: 84px; }
        .sus-scale-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        .sus-scale-option label { display: flex; flex-direction: column; align-items: center; gap: .15rem; width: 100%; border: 1.5px solid var(--ic-border); border-radius: 10px; padding: .55rem .4rem; cursor: pointer; font-weight: 700; color: var(--ic-ink-mid); font-size: .8rem; text-align: center; transition: all .15s ease; }
        .sus-scale-option input[type="radio"]:checked + label { border-color: var(--ic-green-500); background: var(--ic-green-50); color: var(--ic-green-700); }
        .sus-scale-option label .sus-scale-value { font-size: 1.05rem; font-weight: 900; color: var(--ic-ink); }
        .sus-scale-caption { display: flex; justify-content: space-between; font-size: .72rem; color: var(--ic-muted); margin-top: .4rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .sus-existing-note { border: 1px solid var(--ic-green-300); background: var(--ic-green-50); border-radius: 14px; padding: .9rem 1.1rem; color: var(--ic-ink); }
        @media (max-width: 575.98px) {
            .sus-scale { gap: .3rem; }
            .sus-scale-option { min-width: 56px; }
            .sus-scale-option label { padding: .5rem .2rem; font-size: .68rem; }
        }
    </style>

    <div class="sus-page">
        <section class="page-hero">
            <div>
                <div class="eyebrow mb-2">System Usability Scale</div>
                <h1 class="h2 fw-bold mb-2">System Evaluation</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid); max-width: 760px;">
                    Help us evaluate whether iClimate is easy to use and useful for your work. This is a standard 10-question usability survey — there are no right or wrong answers, just your honest reaction to using the system. It takes about 2 minutes.
                </p>
            </div>
        </section>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($existing)
            <div class="sus-existing-note">
                You already submitted an evaluation on {{ $existing->created_at?->format('M d, Y \a\t g:i A') }}. Submitting the form below will update that response instead of creating a new one.
            </div>
        @endif

        @if ($canViewResults)
            <div class="d-flex justify-content-end">
                <a href="{{ route('usability-survey.results') }}" class="btn btn-outline-success btn-sm fw-bold">View Aggregate Results &rarr;</a>
            </div>
        @endif

        <form method="POST" action="{{ route('usability-survey.store') }}" class="sus-page">
            @csrf

            @foreach ($items as $number => $item)
                <div class="sus-item-card">
                    <div class="d-flex align-items-start gap-3">
                        <span class="sus-item-number">{{ $number }}</span>
                        <div class="flex-grow-1">
                            <div class="sus-item-text">{{ $item['text'] }}</div>
                            <div class="sus-scale" role="radiogroup" aria-label="{{ $item['text'] }}">
                                @foreach ([1, 2, 3, 4, 5] as $value)
                                    @php
                                        $inputId = "item_{$number}_{$value}";
                                        $checked = old("item_{$number}", $existing?->{"item_{$number}"}) == $value;
                                    @endphp
                                    <div class="sus-scale-option">
                                        <input type="radio" id="{{ $inputId }}" name="item_{{ $number }}" value="{{ $value }}" @checked($checked) required>
                                        <label for="{{ $inputId }}">
                                            <span class="sus-scale-value">{{ $value }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="sus-scale-caption">
                                <span>Strongly Disagree</span>
                                <span>Strongly Agree</span>
                            </div>
                            @error("item_{$number}")
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="sus-item-card">
                <label class="form-label fw-semibold" for="comments">Anything else you'd like to share? (optional)</label>
                <textarea class="form-control" id="comments" name="comments" rows="3" maxlength="2000">{{ old('comments', $existing?->comments) }}</textarea>
                @error('comments')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary fw-bold px-4">{{ $existing ? 'Update My Evaluation' : 'Submit Evaluation' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
