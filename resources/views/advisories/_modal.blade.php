{{-- Advisory details as shown in the Advisories page popup (PlantingAdvisoryController@show with ?modal=1).
     Same data as advisories/show.blade.php; the full page stays for direct links, notifications and MAO tools. --}}
@php
    $tz = \App\Support\BusinessClock::timezone();
    $local = fn ($time) => $time?->copy()->timezone($tz);
    $isPagasa = in_array($advisory->source, \App\Models\PlantingAdvisory::pagasaOnlineSources(), true);
    $sourceShort = $isPagasa ? 'PAGASA (official advisory)' : ($advisory->generated_automatically ? 'iClimate guidance from forecast data' : $advisory->plainSourceLabel());
    $tone = fn (?string $bootstrap) => ['success' => 'good', 'warning' => 'watch', 'danger' => 'danger', 'secondary' => 'muted', 'dark' => 'muted'][$bootstrap] ?? 'info';
    $severityTone = ['information' => 'info', 'low' => 'good', 'moderate' => 'watch', 'high' => 'danger', 'critical' => 'danger'][$advisory->severity] ?? 'info';
    $wx = $advisoryWeather;
    $wxTime = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(\App\Services\Advisories\AdvisoryWeatherService::TIMEZONE)->format('M j, Y g:i A') : null;
    $isFarmer = auth()->user()->role === \App\Models\User::ROLE_FARMER;
    $issued = $local($advisory->created_at);
@endphp

<header class="fad-m-head">
    <div class="fad-m-head-text">
        <p class="fad-label">{{ $advisory->typeLabel() }} &middot; {{ $advisory->statusLabel() }}</p>
        <h2 id="fadModalTitle" tabindex="-1">{{ $advisory->title }}</h2>
        <p class="fad-m-meta">Source: {{ $sourceShort }}@if($issued) &middot; Issued {{ $issued->format('F j, Y') }}@endif</p>
    </div>
    <button type="button" class="fad-m-x" data-fad-close aria-label="Close advisory">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</header>

<div class="fad-m-scroll">
    <div class="fad-m-grid">
        <div class="fad-m-main">
            <div class="fad-m-tags">
                <span class="fad-badge is-type" data-type="{{ strtolower((string) ($advisory->advisory_type ?: $advisory->type)) }}">{{ $advisory->typeLabel() }}</span>
                <span class="fad-badge is-muted">{{ $advisory->horizonLabel() }}</span>
                <span class="fad-badge is-{{ $severityTone }}">@include('dashboards.mao.icon', ['name' => $severityTone === 'info' ? 'info' : ($severityTone === 'good' ? 'check' : 'alert')])<span>{{ $advisory->severityLabel() }}</span></span>
                <span class="fad-badge is-{{ $tone($verification['tone'] ?? null) }}" title="{{ $verification['detail'] }}">{{ $verification['badge'] }}</span>
            </div>
            <p class="fad-meta">{{ $verification['detail'] }}</p>

            <section class="fad-m-section">
                <h3>Advisory Summary</h3>
                <p class="fad-m-lead">{{ $advisory->displaySummary() }}</p>
            </section>

            <section class="fad-m-section">
                <h3>What Is Happening</h3>
                <div class="fad-m-text">{{ $advisory->displayMessage() }}</div>
            </section>

            <section class="fad-m-section">
                <h3>What To Do</h3>
                <div class="fad-m-text">{{ $advisory->recommended_action ?: 'Inspect actual field conditions and consult the Municipal Agriculture Office before acting.' }}</div>
            </section>

            <section class="fad-m-section" data-advisory-weather="{{ $wx['status'] }}">
                <h3>Weather Behind This Advice</h3>
                @if ($wx['status'] === 'unavailable')
                    <div class="fad-m-note is-watch" role="status">
                        <strong>Weather data unavailable</strong>
                        <span>{{ implode(' ', $wx['messages']) }}</span>
                    </div>
                    <dl class="fad-m-facts">
                        <div><dt>Forecast period</dt><dd>{{ $wx['period'] }}</dd></div>
                        <div><dt>Location</dt><dd>{{ $wx['location'] }}</dd></div>
                    </dl>
                @else
                    <p class="fad-m-kind"><span class="fad-badge is-info">{{ $wx['kind'] }}</span><span class="fad-support">Forecast for {{ $wx['location'] }} &middot; not observed weather and not an ML prediction</span></p>
                    <dl class="fad-m-metrics">
                        @foreach ($wx['rows'] as $row)
                            <div>
                                <dt>{{ $row['label'] }}</dt>
                                @if ($row['value'] !== null)
                                    <dd><strong>{{ $row['value'] }}</strong>@if ($row['detail'])<span>{{ $row['detail'] }}</span>@endif</dd>
                                @else
                                    <dd><span>Not provided by source</span></dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                    <dl class="fad-m-facts">
                        <div><dt>Forecast period</dt><dd>{{ $wx['period'] }}</dd></div>
                    </dl>
                    @foreach ($wx['messages'] as $message)
                        <p class="fad-support">{{ $message }}</p>
                    @endforeach
                @endif
                <div class="fad-m-source" data-advisory-weather-source>
                    <p><strong>Source:</strong> {{ $wx['source'] }}@if (! empty($wx['source_url'])) &middot; <a href="{{ $wx['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ parse_url($wx['source_url'], PHP_URL_HOST) }}</a>@endif</p>
                    <p><strong>Forecast issued:</strong> {{ $wx['issued'] }}</p>
                    <p><strong>Last updated:</strong> {{ $wx['updated'] ? $wxTime($wx['updated']).' (Philippine time)' : 'Not available — no forecast was retrieved' }}{{ ! empty($wx['stale']) ? ' — the source could not be reached, so this is the last copy retrieved' : '' }}</p>
                    @if ($wx['window_note'])<p>{{ $wx['window_note'] }}</p>@endif
                </div>
            </section>
        </div>

        <aside class="fad-m-side" aria-label="Advisory information">
            <section class="fad-m-section">
                <h3>Advisory Information</h3>
                <dl class="fad-m-info">
                    <div><dt>Applies to</dt><dd>{{ $advisory->targetLabel() }}</dd></div>
                    <div><dt>Source</dt><dd>{{ auth()->user()->role === \App\Models\User::ROLE_IT_EXPERT ? ($advisory->source ?: 'Open-Meteo + iClimate Rules') : $sourceShort }}</dd></div>
                    <div><dt>Period</dt><dd>{{ $advisory->horizonLabel() }} &middot; {{ data_get($advisory->metadata, 'advisory_horizon_description', 'Forecast outlook') }}</dd></div>
                    <div><dt>Issued on</dt><dd>{{ $issued?->format('F j, Y · g:i A') ?? '—' }}</dd></div>
                    <div><dt>Valid</dt><dd>{{ $local($advisory->valid_from)?->format('M j, Y · g:i A') ?? 'Now' }} – {{ $local($advisory->valid_until)?->format('M j, Y · g:i A') ?? 'until further notice' }}</dd></div>
                    <div><dt>Review status</dt><dd>{{ $advisory->approved_at ? 'Approved by '.$advisory->approvedBy?->name : $advisory->statusLabel() }}</dd></div>
                </dl>
                <p class="fad-meta">All times are Philippine time.</p>
            </section>

            <div class="fad-m-disclaimer">
                <strong>Disclaimer</strong>
                <p>{{ $advisory->metadata['disclaimer'] ?? 'This advisory is based on the weather forecast and iClimate guidance. Actual farm conditions may differ. Consult the Municipal Agriculture Office and follow official PAGASA or local government warnings during severe weather.' }}</p>
            </div>

            <section class="fad-m-section">
                <h3>Official Advisories</h3>
                <div class="fad-m-links">
                    <a class="fad-btn is-quiet is-compact" href="https://www.pagasa.dost.gov.ph/" target="_blank" rel="noopener noreferrer">PAGASA Website</a>
                    <a class="fad-btn is-quiet is-compact" href="https://www.facebook.com/PAGASA.DOST.GOV.PH" target="_blank" rel="noopener noreferrer">PAGASA Updates</a>
                </div>
            </section>
        </aside>
    </div>

    @unless($feedbackFeatureReady ?? true)
        <div class="fad-m-note is-watch" role="status">
            <strong>Advisory feedback is temporarily unavailable.</strong>
            <span>You can still read this advisory while the database update is completed.</span>
        </div>
    @endunless

    @if (($feedbackFeatureReady ?? true) && $isFarmer)
        <section class="fad-m-section fad-m-feedback" aria-labelledby="fadFeedbackTitle">
            <h3 id="fadFeedbackTitle">Was This Advisory Helpful?</h3>
            <p class="fad-support">Your feedback helps MAO know which advisories are working and which need a closer look. Choosing Yes or No saves your answer and any comment.</p>
            <form method="POST" action="{{ route('planting-advisories.feedback', $advisory) }}" data-fad-feedback>
                @csrf
                <div class="fad-field">
                    <label class="fad-label" for="fadFeedbackComment">Optional comment</label>
                    <textarea id="fadFeedbackComment" name="comment" class="fad-input" rows="2" maxlength="2000" placeholder="Tell MAO more about your experience with this advisory...">{{ $myFeedback?->comment }}</textarea>
                </div>
                <div class="fad-m-vote">
                    <button type="submit" name="is_helpful" value="1" class="fad-btn is-vote is-yes" aria-pressed="{{ $myFeedback?->is_helpful === true ? 'true' : 'false' }}">Yes, helpful</button>
                    <button type="submit" name="is_helpful" value="0" class="fad-btn is-vote is-no" aria-pressed="{{ $myFeedback && $myFeedback->is_helpful === false ? 'true' : 'false' }}">No, not helpful</button>
                    <span class="fad-m-saved" role="status" aria-live="polite" data-fad-feedback-status>@if($myFeedback)Your answer is saved. You can change it.@endif</span>
                </div>
            </form>
            @if ($myFeedback?->mao_response)
                <div class="fad-m-note is-good"><strong>MAO responded:</strong> <span>{{ $myFeedback->mao_response }}</span></div>
            @endif
        </section>
    @endif
</div>

<footer class="fad-m-foot">
    @if ($isPagasa && $advisory->source_url)
        <a class="fad-btn is-quiet" href="{{ $advisory->source_url }}" target="_blank" rel="noopener noreferrer">Open Official PAGASA Advisory</a>
    @endif
    @if ($canManage)
        <a class="fad-btn is-quiet" href="{{ route('planting-advisories.show', $advisory) }}">Open Full Page</a>
    @endif
    <button type="button" class="fad-btn is-primary" data-fad-close>Close</button>
</footer>
