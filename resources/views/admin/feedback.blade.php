<x-app-layout>
    <x-slot:title>Model Feedback</x-slot:title>
    {{-- Farmer Survey & Model Feedback – Naicel (2026-10-09): ModelFeedbackController. Farmer → Survey → IT Expert →
         Model Evaluation → Maintain or Retrain → Updated Insights. The page recommends; only the IT Expert decides, and
         a retraining saves a candidate (the model in use changes only on Model Registry). --}}
    @php
        $tz = \App\Support\BusinessClock::timezone();
        $model = $summary['model'];
        $all = $summary['all'];
        $pct = fn ($v) => $v === null ? '—' : $v.'%';
        $avg = fn ($v) => $v === null ? '—' : number_format((float) $v, 1);
        $tone = ['maintain' => 'success', 'needs_retraining' => 'danger', 'not_enough' => 'secondary'];
        $sentimentBadge = ['positive' => 'text-bg-success', 'neutral' => 'text-bg-secondary', 'negative' => 'text-bg-danger'];
    @endphp
    <style>
        .mfb-insights { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .mfb-insight { padding: 14px 16px; border: 1px solid var(--line, var(--sage-100)); border-left: 5px solid #6b7f8c; border-radius: 8px; background: var(--card, #fff); }
        .mfb-insight h3 { margin: 0 0 4px; font-size: .95rem; font-weight: 700; }
        .mfb-insight p { margin: 0; font-size: .92rem; }
        .mfb-insight.is-good { border-left-color: #198754; }
        .mfb-insight.is-watch { border-left-color: #d99a1e; }
        .mfb-insight.is-danger { border-left-color: #c0392b; }
        .mfb-live { display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; color: var(--muted, #5b6b63); }
        .mfb-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--sage-700); }
        .mfb-live.is-stale .mfb-dot { background: #c0392b; }
        .mfb-stat { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
        .mfb-bar { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: var(--line, var(--sage-100)); }
        .mfb-bar > span:first-child { background: var(--sage-700); }
        .mfb-bar > span:last-child { background: #c0392b; margin-left: auto; }
        html[data-theme="dark"] body .mfb-insight { background: var(--dk-surface-2); border-color: var(--dk-border-strong); }
    </style>
    <div class="mfd itd itp" data-model-feedback>
        <x-it.page-header kicker="IT Personnel" title="Model Feedback" lede="What farmers answered about the predictions, the weather data and the advisories, and what it means for the rice-yield model. Answers from the last {{ $summary['window_days'] }} days.">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.models.index') }}">ML Models</a>
        </x-it.page-header>
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

        {{-- 1. Live Insights --}}
        <section class="itp-section" aria-labelledby="mfbInsights">
            <div class="itp-section-head d-flex flex-wrap justify-content-between align-items-end gap-2">
                <div>
                    <h2 id="mfbInsights">Live Insights</h2>
                    <p>Counted again from the saved surveys, predictions, weather ratings and model results every 20 seconds while this page is open.</p>
                </div>
                <span class="mfb-live" data-insights-live role="status"><span class="mfb-dot" aria-hidden="true"></span><span data-insights-updated>Updated {{ $insights['generated_at'] }}</span></span>
            </div>
            <div class="mfb-insights" data-insights data-insights-url="{{ route('admin.feedback.insights') }}" data-signature="{{ $insights['signature'] }}" aria-live="polite">
                @include('admin._feedback-insights', ['insights' => $insights])
            </div>
        </section>

        {{-- 2. The model: what the feedback shows, and the IT Expert's decision --}}
        <section class="itp-section" aria-labelledby="mfbModel">
            <div class="itp-section-head"><h2 id="mfbModel">Rice-yield model</h2><p>From the answers in the "ML model" and "Predictions" categories. The system only recommends: nothing is retrained or replaced without your decision.</p></div>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="mfd-card h-100" data-model-status="{{ $model['status'] }}">
                        <div class="small text-muted">Model in use</div>
                        <strong>{{ $model['active'] ? $model['active']['name'].' · '.$model['active']['type'].' · version '.$model['active']['version'] : 'Deployed model (rice_yield_model_final, Random Forest); none marked active in the registry' }}</strong>
                        <hr>
                        <div class="small text-muted">What the feedback shows</div>
                        <p class="mb-1"><span class="badge text-bg-{{ $tone[$model['recommended']] }} fs-6" data-model-recommended="{{ $model['recommended'] }}">{{ $model['recommended_label'] }}</span></p>
                        <p class="mb-2">{{ $model['reason'] }}</p>
                        <div class="small text-muted">Model status</div>
                        <p class="mb-0">
                            <span class="badge text-bg-{{ $tone[$model['status']] }} fs-6" data-model-status-label>{{ $model['status_label'] }}</span>
                            @if ($model['approved'])
                                <span class="small text-muted">decided by {{ $model['decision']->decidedBy?->name ?? 'IT' }} on {{ $model['decision']->created_at->copy()->timezone($tz)->format('M j, Y g:i A') }}</span>
                                @if ($model['new_since_decision'] > 0)<div class="small text-warning-emphasis mt-1">{{ $model['new_since_decision'] }} new {{ $model['new_since_decision'] === 1 ? 'answer' : 'answers' }} since that decision.</div>@endif
                            @else
                                <span class="small text-muted">recommended only; no IT decision yet</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <form class="mfd-card h-100" method="POST" action="{{ route('admin.feedback.decide') }}" data-loading="true" data-model-decision>
                        @csrf
                        <h3 class="h6 fw-bold">Your decision</h3>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" id="mfbMaintain" value="maintain" required @checked(old('decision', $model['recommended'] === 'needs_retraining' ? '' : 'maintain') === 'maintain')>
                            <label class="form-check-label" for="mfbMaintain"><strong>Maintain Current Model</strong>: farmers find it useful; keep it.</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="decision" id="mfbRetrain" value="needs_retraining" @checked(old('decision', $model['recommended'] === 'needs_retraining' ? 'needs_retraining' : '') === 'needs_retraining')>
                            <label class="form-check-label" for="mfbRetrain"><strong>Needs Retraining</strong>: not useful or often inaccurate.</label>
                        </div>
                        <div class="border rounded p-2 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="start_retraining" id="mfbStart" value="1" @checked(old('start_retraining'))>
                                <label class="form-check-label" for="mfbStart">Also start retraining now (only with "Needs Retraining")</label>
                            </div>
                            <select class="form-select form-select-sm mt-1" name="algorithm" aria-label="Algorithm to retrain">@foreach ($algorithms as $key => $label)<option value="{{ $key }}" @selected(old('algorithm', 'rf') === $key)>{{ $label }}</option>@endforeach</select>
                            <div class="small text-muted mt-1">Retraining saves a new candidate on ML Models. The model in use stays until you validate and activate another one on Model Registry.</div>
                        </div>
                        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" maxlength="1000" placeholder="Note (optional): why this decision">{{ old('note') }}</textarea>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="reviewed_feedback" id="mfbReviewed" value="1" required>
                            <label class="form-check-label" for="mfbReviewed">I reviewed the farmer feedback below.</label>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Saving...">Save decision</button>
                    </form>
                </div>
            </div>
        </section>

        {{-- 3. Counts and percentages, all answers and each category --}}
        <section class="itp-section" aria-labelledby="mfbCounts">
            <div class="itp-section-head"><h2 id="mfbCounts">Feedback by category</h2><p>Positive: the three answers average 4 or more of 5. Negative: 2 or less. The rest are neutral.</p></div>
            <div class="table-responsive mfd-card p-0">
                <table class="table table-sm align-middle mb-0" data-feedback-categories>
                    <thead><tr><th>Category</th><th>Answers</th><th>Positive</th><th>Negative</th><th style="min-width: 140px">Share</th><th>Useful</th><th>Accurate</th><th>Helpful</th></tr></thead>
                    <tbody>
                        @foreach (['all' => ['label' => 'All feedback'] + $all] + $summary['categories'] as $key => $row)
                            <tr data-feedback-category="{{ $key }}" @if ($key === 'all') class="table-light fw-semibold" @endif>
                                <td>@if ($key === 'all'){{ $row['label'] }}@else<a href="{{ route('admin.feedback.index', ['category' => $key]) }}#mfbRecent">{{ $row['label'] }}</a>@endif</td>
                                <td data-total>{{ $row['total'] }}</td>
                                <td data-positive>{{ $row['positive'] }} <span class="text-muted">({{ $pct($row['positive_percent']) }})</span></td>
                                <td data-negative>{{ $row['negative'] }} <span class="text-muted">({{ $pct($row['negative_percent']) }})</span></td>
                                <td>@if ($row['total'] > 0)<div class="mfb-bar" role="img" aria-label="{{ $row['positive_percent'] }}% positive, {{ $row['negative_percent'] }}% negative"><span style="width: {{ $row['positive_percent'] }}%"></span><span style="width: {{ $row['negative_percent'] }}%"></span></div>@else<span class="text-muted small">No answers</span>@endif</td>
                                <td>{{ $avg($row['useful']) }}</td><td>{{ $avg($row['accurate']) }}</td><td>{{ $avg($row['helpful']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Recent feedback: read, move to another category, mark reviewed --}}
        <section class="itp-section" aria-labelledby="mfbRecent">
            <div class="itp-section-head"><h2 id="mfbRecent">Recent feedback</h2><p>{{ $summary['unreviewed'] }} waiting for review.</p></div>
            <form method="GET" action="{{ route('admin.feedback.index') }}" class="d-flex flex-wrap gap-2 mb-2" data-feedback-filters>
                <select class="form-select form-select-sm w-auto" name="category" aria-label="Category"><option value="">All categories</option>@foreach (\App\Models\FarmerSurveyResponse::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>@endforeach</select>
                <select class="form-select form-select-sm w-auto" name="answer" aria-label="Answer"><option value="">Positive and negative</option>@foreach (['positive' => 'Positive', 'neutral' => 'Neutral', 'negative' => 'Negative'] as $key => $label)<option value="{{ $key }}" @selected($filters['answer'] === $key)>{{ $label }}</option>@endforeach</select>
                <select class="form-select form-select-sm w-auto" name="review" aria-label="Review"><option value="">Reviewed or not</option><option value="waiting" @selected($filters['review'] === 'waiting')>Waiting for review</option></select>
                <button class="btn btn-outline-primary btn-sm" type="submit">Filter</button>
                @if (array_filter($filters))<a class="btn btn-link btn-sm" href="{{ route('admin.feedback.index') }}">Clear</a>@endif
            </form>
            @if ($recent->isEmpty())
                <p class="small text-muted">{{ array_filter($filters) ? 'No feedback matches these filters.' : 'No farmer has answered the survey yet. Farmers open it from their Dashboard, an advisory or a yield prediction.' }}</p>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-feedback-recent>
                        <thead><tr><th>Date</th><th>Farmer</th><th>About</th><th>Answer</th><th>Comment</th><th style="min-width: 250px">Category and review</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $r)
                                <tr data-feedback-row="{{ $r->id }}">
                                    <td class="small text-nowrap">{{ $r->created_at->copy()->timezone($tz)->format('M j, Y') }}<div class="text-muted">{{ $r->created_at->copy()->timezone($tz)->format('g:i A') }}</div></td>
                                    <td>{{ $r->user?->name ?? 'Deleted account' }}<div class="small text-muted">{{ $r->barangay ?: 'No barangay' }}</div></td>
                                    <td class="small">
                                        {{ ucfirst($r->subject) }}
                                        @if ($r->advisory)<div class="text-muted">Advisory: {{ \Illuminate\Support\Str::limit($r->advisory->title, 60) }}</div>@endif
                                        @if ($r->prediction)<div class="text-muted">Prediction: {{ $r->prediction->season }}, {{ number_format((float) $r->prediction->predicted_yield, 2) }} t/ha</div>@endif
                                        @if ($r->model_version)<div class="text-muted">Model: {{ $r->model_version }}</div>@endif
                                    </td>
                                    <td><span class="badge {{ $sentimentBadge[$r->sentiment()] }}">{{ ucfirst($r->sentiment()) }}</span><div class="small text-muted text-nowrap">Useful {{ $r->useful }} · Accurate {{ $r->accurate }} · Helpful {{ $r->helpful }}</div></td>
                                    <td class="small">{{ $r->comment ?: '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.feedback.update', $r) }}" class="d-flex flex-wrap align-items-center gap-2">
                                            @csrf @method('PUT')
                                            <select class="form-select form-select-sm w-auto" name="category" aria-label="Category">@foreach (\App\Models\FarmerSurveyResponse::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected($r->category === $key)>{{ $label }}</option>@endforeach</select>
                                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="reviewed" value="1" id="mfbRev{{ $r->id }}" @checked($r->reviewed_at)><label class="form-check-label small" for="mfbRev{{ $r->id }}">Reviewed</label></div>
                                            <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                        </form>
                                        @if ($r->reviewed_at)<div class="small text-muted">by {{ $r->reviewedBy?->name ?? 'IT' }}, {{ $r->reviewed_at->copy()->timezone($tz)->format('M j') }}</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">{{ $recent->links() }}</div>
            @endif
        </section>

        {{-- 5. Decision history --}}
        <section class="itp-section" aria-labelledby="mfbHistory">
            <div class="itp-section-head"><h2 id="mfbHistory">Decision history</h2></div>
            @if ($decisions->isEmpty())
                <p class="small text-muted">No decision recorded yet.</p>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-feedback-decisions>
                        <thead><tr><th>Date</th><th>Decision</th><th>Feedback then</th><th>Model</th><th>Retraining</th><th>By</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach ($decisions as $d)
                                <tr>
                                    <td class="small text-nowrap">{{ $d->created_at->copy()->timezone($tz)->format('M j, Y g:i A') }}</td>
                                    <td><span class="badge text-bg-{{ $tone[$d->decision] ?? 'secondary' }}">{{ $d->label() }}</span></td>
                                    <td class="small">{{ $d->positive }} positive, {{ $d->negative }} negative of {{ $d->responses }}<div class="text-muted">showed: {{ \App\Models\ModelFeedbackDecision::LABELS[$d->recommended] ?? $d->recommended }}</div></td>
                                    <td class="small">{{ $d->model_version }}</td>
                                    <td class="small">{{ $d->retrain_algorithm ? 'Started: '.($algorithms[$d->retrain_algorithm] ?? $d->retrain_algorithm) : '—' }}</td>
                                    <td class="small">{{ $d->decidedBy?->name ?? '—' }}</td>
                                    <td class="small">{{ $d->note ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <script>
        (() => {
            // Live Insights: read again every 20 seconds while the tab is visible. Only the cards are replaced, so a form
            // being filled in below is never disturbed. A failed read keeps the last cards and shows the dot in red.
            const box = document.querySelector('[data-insights]');
            if (!box) return;
            const live = document.querySelector('[data-insights-live]');
            const updated = document.querySelector('[data-insights-updated]');
            const EVERY_MS = 20000;
            const read = async () => {
                if (document.visibilityState !== 'visible') return;
                try {
                    const res = await fetch(box.dataset.insightsUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    if (!res.ok) throw new Error('status ' + res.status);
                    const data = await res.json();
                    if (data.signature !== box.dataset.signature) { box.innerHTML = data.html; box.dataset.signature = data.signature; }
                    if (updated) updated.textContent = 'Updated ' + data.generated_at;
                    live?.classList.remove('is-stale');
                } catch (e) {
                    live?.classList.add('is-stale');
                    if (updated && !updated.textContent.includes('(not refreshed)')) updated.textContent += ' (not refreshed)';
                }
            };
            setInterval(read, EVERY_MS);
            document.addEventListener('visibilitychange', read);
        })();
    </script>
</x-app-layout>
