<x-app-layout>
    <style>
        .advisory-hero-actions {
            background: transparent;
            border: 0;
            padding: 0;
        }
        .advisory-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-width: 112px;
            white-space: nowrap;
            border-color: var(--ic-border) !important;
            background: rgba(31,42,36,.04) !important;
            color: var(--ic-ink) !important;
            box-shadow: 0 .55rem 1.2rem rgba(13,31,24,.06);
        }
        .advisory-back-btn:hover,
        .advisory-back-btn:focus {
            background: rgba(31,42,36,.08) !important;
            border-color: var(--ic-green-500) !important;
            color: var(--ic-ink) !important;
        }
    </style>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Advisory Details</div>
                <h1 class="h2 fw-bold mb-2">{{ $advisory->title }}</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">{{ $advisory->displaySummary() }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2 advisory-hero-actions">
                <a class="btn btn-outline-light advisory-back-btn" href="{{ url()->previous() }}">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back</span>
                </a>
                @if($canManage)
                    <a class="btn btn-light" href="{{ route('planting-advisories.edit', $advisory) }}">Edit</a>
                @endif
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card no-lift mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span>
                        <span class="badge text-bg-light">{{ $advisory->horizonLabel() }}</span>
                        <span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span>
                        <span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span>
                        <span class="badge text-bg-{{ $verification['tone'] }}" title="{{ $verification['detail'] }}">{{ $verification['badge'] }}</span>
                    </div>
                    <div class="small text-muted mb-3">{{ $verification['detail'] }}</div>
                    <h2 class="h5">What Is Happening</h2>
                    <div class="advisory-full-text mb-4" style="white-space: pre-line; line-height: 1.65; font-size: .95rem; color: var(--ic-ink);">{{ $advisory->displayMessage() }}</div>
                    <h2 class="h5 mt-4">What To Do</h2>
                    <p style="white-space: pre-line; line-height: 1.6; font-size: .95rem; color: var(--ic-ink);">{{ $advisory->recommended_action ?: 'Inspect actual field conditions and consult the Municipal Agriculture Office before acting.' }}</p>
                </div>
            </div>

            <details class="ic-collapse">
                <summary><span class="ic-collapse-title">Weather Behind This Advice</span></summary>
            <div class="card no-lift">
                <div class="card-body">
                    @php($basis = $advisory->metadata['weather_basis'] ?? [])
                    <dl class="row mb-0 details-list g-3">
                        <dt class="col-sm-4">Expected rainfall</dt><dd class="col-sm-8">{{ data_get($basis, 'expected_rainfall_mm', 'N/A') }} mm</dd>
                        <dt class="col-sm-4">Rain probability</dt><dd class="col-sm-8">{{ data_get($basis, 'rain_probability_percent', 'N/A') }}%</dd>
                        <dt class="col-sm-4">Maximum temperature</dt><dd class="col-sm-8">{{ data_get($basis, 'maximum_temperature_c', 'N/A') }}°C</dd>
                        <dt class="col-sm-4">Strongest wind</dt><dd class="col-sm-8">{{ data_get($basis, 'maximum_wind_speed_kmh', 'N/A') }} km/h</dd>
                        <dt class="col-sm-4">Forecast period</dt><dd class="col-sm-8">{{ data_get($basis, 'forecast_period', 'N/A') }}</dd>
                    </dl>
                </div>
            </div>
            </details>

            @unless($feedbackFeatureReady ?? true)
                <div class="alert alert-warning mt-4" role="status">
                    <strong>Advisory feedback is temporarily unavailable.</strong>
                    You can still read this advisory while the database update is completed.
                </div>
            @endunless

            @if (($feedbackFeatureReady ?? true) && auth()->user()->role === \App\Models\User::ROLE_FARMER)
                <div class="card no-lift mt-4">
                    <div class="card-body">
                        <h2 class="h5">Was This Advisory Helpful?</h2>
                        <p class="text-muted small mb-3">Your feedback helps MAO know which advisories are working and which need a closer look.</p>
                        <form method="POST" action="{{ route('planting-advisories.feedback', $advisory) }}" class="d-flex flex-column gap-3" data-loading="true">
                            @csrf
                            <div class="d-flex gap-2">
                                <button type="submit" name="is_helpful" value="1" class="btn {{ $myFeedback?->is_helpful === true ? 'btn-success' : 'btn-outline-success' }}">Yes, helpful</button>
                                <button type="submit" name="is_helpful" value="0" class="btn {{ $myFeedback && $myFeedback->is_helpful === false ? 'btn-danger' : 'btn-outline-danger' }}">No, not helpful</button>
                            </div>
                            <div>
                                <label class="form-label fw-semibold" for="advisoryFeedbackComment">Optional comment</label>
                                <textarea id="advisoryFeedbackComment" name="comment" class="form-control" rows="2" maxlength="2000" placeholder="Tell MAO more about your experience with this advisory...">{{ old('comment', $myFeedback?->comment) }}</textarea>
                            </div>
                        </form>
                        @if ($myFeedback?->mao_response)
                            <div class="alert alert-success mt-3 mb-0">
                                <strong>MAO responded:</strong> {{ $myFeedback->mao_response }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if (($feedbackFeatureReady ?? true) && $canManage)
                <div class="card no-lift mt-4">
                    <div class="card-body">
                        <h2 class="h5">Farmer Feedback ({{ $advisory->feedback->count() }})</h2>
                        @forelse ($advisory->feedback as $feedbackItem)
                            <div class="border rounded p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <span class="badge text-bg-{{ $feedbackItem->is_helpful ? 'success' : 'danger' }}">{{ $feedbackItem->is_helpful ? 'Helpful' : 'Not helpful' }}</span>
                                        <strong class="ms-2">{{ $feedbackItem->user?->name }}</strong>
                                    </div>
                                    <span class="small text-muted">{{ $feedbackItem->created_at?->shortDateTime('M d, Y') }}</span>
                                </div>
                                @if ($feedbackItem->comment)
                                    <p class="mb-2 mt-2">{{ $feedbackItem->comment }}</p>
                                @endif
                                @if ($feedbackItem->mao_response)
                                    <div class="alert alert-light border small mb-0">
                                        <strong>Your response ({{ $feedbackItem->respondedBy?->name }}):</strong> {{ $feedbackItem->mao_response }}
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('advisory-feedback.respond', $feedbackItem) }}" class="mt-2 d-flex gap-2" data-loading="true">
                                        @csrf
                                        <input type="text" name="mao_response" class="form-control form-control-sm" placeholder="Reply to this farmer..." required maxlength="2000">
                                        <button type="submit" class="btn btn-sm btn-primary">Send</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No farmer feedback yet for this advisory.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <details class="ic-collapse">
                <summary><span class="ic-collapse-title">View Details</span></summary>
            <div class="card no-lift mb-4">
                <div class="card-body">
                    <div class="details-list">
                        <dt>Applies to</dt><dd>{{ $advisory->targetLabel() }}</dd>
                        @if(auth()->user()->role === \App\Models\User::ROLE_IT_EXPERT)<dt>Source</dt><dd>{{ $advisory->source ?: 'Open-Meteo + iClimate Rules' }}</dd>@else<dt>Source</dt><dd>{{ $advisory->source && stripos($advisory->source, 'pagasa') !== false ? 'PAGASA' : 'Live weather and iClimate guidance' }}</dd>@endif
                        <dt>Period</dt><dd>{{ $advisory->horizonLabel() }} - {{ data_get($advisory->metadata, 'advisory_horizon_description', 'Forecast outlook') }}</dd>
                        <dt>Issued on</dt><dd>{{ $advisory->created_at?->shortDateTime('F d, Y') }}</dd>
                        <dt>Valid</dt><dd>{{ $advisory->valid_from?->shortDateTime('M d, Y') }} to {{ $advisory->valid_until?->shortDateTime('M d, Y') }}</dd>
                        <dt>Review status</dt><dd>{{ $advisory->approved_at ? 'Approved by '.$advisory->approvedBy?->name : $advisory->statusLabel() }}</dd>
                    </div>
                </div>
            </div>
            </details>
            <div class="alert alert-warning shadow-sm">
                <strong>Disclaimer.</strong>
                {{ $advisory->metadata['disclaimer'] ?? 'This advisory is based on the weather forecast and iClimate guidance. Actual farm conditions may differ. Consult the Municipal Agriculture Office and follow official PAGASA or local government warnings during severe weather.' }}
            </div>
            <div class="card no-lift">
                <div class="card-body">
                    <h2 class="h6 fw-bold">Official Advisories</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="https://www.pagasa.dost.gov.ph/" target="_blank" rel="noopener">PAGASA Website</a>
                        <a class="btn btn-outline-primary btn-sm" href="https://www.facebook.com/PAGASA.DOST.GOV.PH" target="_blank" rel="noopener">PAGASA Updates</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
