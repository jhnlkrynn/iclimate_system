{{-- Advisory details as shown in the Advisories page popup (PlantingAdvisoryController@show with ?modal=1).
     Written for farmers: plain words, the advice first, details after. Same data as advisories/show.blade.php
     (the full page stays for direct links, notifications and MAO tools); App\Support\FarmerAdvisoryView only
     rewords it — every sentence comes from the advisory and every number from the weather service, unchanged. --}}
@php
    $tz = \App\Support\BusinessClock::timezone();
    $local = fn ($time) => $time?->copy()->timezone($tz);
    $view = new \App\Support\FarmerAdvisoryView($advisory);
    $wx = $advisoryWeather;
    $isFarmer = auth()->user()->role === \App\Models\User::ROLE_FARMER;
    $issued = $local($advisory->created_at);
    $validFrom = $local($advisory->valid_from);
    $validUntil = $local($advisory->valid_until);
    $alert = $view->alertLevel();
    $officialUrl = $view->officialUrl();
    $displayArea = $isFarmer ? $view->farmerArea() : $view->area();
    $summary = $view->quickSummary();
    $effects = $view->farmEffects();
    $actions = $view->actions();
    $weatherCards = \App\Support\FarmerAdvisoryView::weatherCards($wx['rows'] ?? []);
    $weatherNotes = \App\Support\FarmerAdvisoryView::weatherNotes($wx);
@endphp

<header class="fad-m-head">
    <div class="fad-m-head-text">
        <p class="fad-m-eyebrow">{{ $view->eyebrow() }}</p>
        <h2 id="fadModalTitle" tabindex="-1">{{ $advisory->title }}</h2>
        <p class="fad-m-meta">
            @if ($issued)<span>Issued: {{ $issued->format('F j, Y') }}</span>@endif
            <span>For: {{ $displayArea }}</span>
        </p>
        @if ($alert)
            <p class="fad-m-alert is-{{ $alert['tone'] }}">@include('dashboards.mao.icon', ['name' => 'alert'])<span>{{ $alert['label'] }}</span></p>
        @endif
    </div>
    <button type="button" class="fad-m-x" data-fad-close aria-label="Close advisory">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</header>

<div class="fad-m-scroll">
    {{-- The three things a farmer needs first: what is happening, what it means, what to do. --}}
    <section class="fad-m-summary" aria-labelledby="fadSummaryTitle">
        <h3 id="fadSummaryTitle">Quick Summary</h3>
        @foreach ($summary as $sentence)
            <p>{{ $sentence }}</p>
        @endforeach
    </section>

    @if ($effects !== [])
        <section class="fad-m-section" aria-labelledby="fadEffectsTitle">
            <h3 id="fadEffectsTitle">What This Means for Your Farm</h3>
            <ul class="fad-m-list">
                @foreach ($effects as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="fad-m-section fad-m-todo" aria-labelledby="fadActionsTitle">
        <h3 id="fadActionsTitle">What You Should Do</h3>
        <ul class="fad-m-list is-actions">
            @foreach ($actions as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </section>

    <section class="fad-m-section" aria-labelledby="fadWeatherTitle" data-advisory-weather="{{ $wx['status'] }}">
        <h3 id="fadWeatherTitle">Weather at a Glance</h3>
        @if ($wx['status'] === 'unavailable' || $weatherCards === [])
            <p class="fad-m-empty">Weather numbers are not available for this advisory right now.</p>
        @else
            <dl class="fad-m-weather">
                @foreach ($weatherCards as $card)
                    <div data-weather="{{ $card['key'] }}">
                        <dt>{{ $card['label'] }}</dt>
                        @if ($card['value'] !== null)
                            <dd><strong>{{ $card['value'] }}</strong>@if ($card['note'])<span>{{ $card['note'] }}</span>@endif</dd>
                        @else
                            <dd><span class="is-missing">Not available</span></dd>
                        @endif
                    </div>
                @endforeach
            </dl>
            <p class="fad-m-small">Weather forecast for {{ $wx['location'] }}, {{ $wx['period'] }}.</p>
            @foreach ($weatherNotes as $note)
                <p class="fad-m-small">{{ $note }}</p>
            @endforeach
            <p class="fad-m-small">Weather source: Open-Meteo</p>
            @if($isFarmer && $advisory->target_barangay)
                <p class="fad-m-small">Weather information is based on the nearest available forecast data.</p>
            @endif
        @endif
    </section>

    <section class="fad-m-section" aria-labelledby="fadDetailsTitle">
        <h3 id="fadDetailsTitle">Advisory Details</h3>
        <dl class="fad-m-details">
            <div><dt>Area</dt><dd>{{ $displayArea }}</dd></div>
            <div><dt>Issued</dt><dd>{{ $issued?->format('F j, Y · g:i A') ?? 'Not available' }}</dd></div>
            @if ($validFrom && $validFrom->isFuture())
                <div><dt>Starts</dt><dd>{{ $validFrom->format('F j, Y · g:i A') }}</dd></div>
            @endif
            <div><dt>Valid Until</dt><dd>{{ $validUntil?->format('F j, Y · g:i A') ?? 'Until further notice' }}</dd></div>
            <div><dt>Source</dt><dd>{{ $view->sourceName(auth()->user()) }}</dd></div>
            @if ($canManage)
                <div><dt>Review status</dt><dd>{{ $advisory->approved_at ? 'Approved by '.$advisory->approvedBy?->name : $advisory->statusLabel() }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="fad-m-important" aria-labelledby="fadImportantTitle">
        <h3 id="fadImportantTitle">@include('dashboards.mao.icon', ['name' => 'alert'])<span>Important</span></h3>
        @foreach ($view->importantNote() as $line)
            <p>{{ $line }}</p>
        @endforeach
    </section>

    <section class="fad-m-section" aria-labelledby="fadSourceTitle">
        <h3 id="fadSourceTitle">Official Source</h3>
        <p class="fad-m-body">{{ $officialUrl ? 'Read the full advisory on the PAGASA website.' : 'For official weather news, check the PAGASA website.' }}</p>
        <div class="fad-m-links">
            @if ($officialUrl)
                <a class="fad-btn is-primary" href="{{ $officialUrl }}" target="_blank" rel="noopener noreferrer">View Official PAGASA Advisory</a>
            @else
                <a class="fad-btn is-quiet" href="https://www.pagasa.dost.gov.ph/" target="_blank" rel="noopener noreferrer">Check PAGASA Weather Updates</a>
            @endif
        </div>
    </section>

    @unless($feedbackFeatureReady ?? true)
        <div class="fad-m-note is-watch" role="status">
            <strong>Feedback is not available right now.</strong>
            <span>You can still read this advisory.</span>
        </div>
    @endunless

    @if (($feedbackFeatureReady ?? true) && $isFarmer)
        <section class="fad-m-section fad-m-feedback" aria-labelledby="fadFeedbackTitle">
            <h3 id="fadFeedbackTitle">Was This Advice Helpful?</h3>
            <p class="fad-m-small">Your feedback helps the Municipal Agriculture Office improve future advisories.</p>
            <form method="POST" action="{{ route('planting-advisories.feedback', $advisory) }}" data-fad-feedback>
                @csrf
                <div class="fad-field">
                    <label class="fad-m-field-label" for="fadFeedbackComment">Comment (Optional)</label>
                    <textarea id="fadFeedbackComment" name="comment" class="fad-input" rows="3" maxlength="2000" placeholder="Tell us if this advice was helpful or if you need more information.">{{ $myFeedback?->comment }}</textarea>
                </div>
                <div class="fad-m-vote">
                    <button type="submit" name="is_helpful" value="1" class="fad-btn is-vote is-yes" aria-pressed="{{ $myFeedback?->is_helpful === true ? 'true' : 'false' }}">Yes, Helpful</button>
                    <button type="submit" name="is_helpful" value="0" class="fad-btn is-vote is-no" aria-pressed="{{ $myFeedback && $myFeedback->is_helpful === false ? 'true' : 'false' }}">No, Not Helpful</button>
                </div>
                <p class="fad-m-saved" role="status" aria-live="polite" data-fad-feedback-status>@if($myFeedback)Your answer is saved. You can change it.@endif</p>
            </form>
            @if ($myFeedback?->mao_response)
                <div class="fad-m-note is-good"><strong>Reply from MAO:</strong> <span>{{ $myFeedback->mao_response }}</span></div>
            @endif
        </section>
    @endif
</div>

<footer class="fad-m-foot">
    @if ($canManage)
        <a class="fad-btn is-quiet" href="{{ route('planting-advisories.show', $advisory) }}">Open Full Page</a>
    @endif
    <button type="button" class="fad-btn is-primary" data-fad-close>Close</button>
</footer>
