<x-app-layout>
    <style>
        .sus-results-page { display: grid; gap: 1rem; }
        .sus-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
        .sus-stat { border: 1px solid var(--ic-border); border-radius: 18px; background: var(--ic-panel); padding: 1rem; }
        .sus-stat-label { color: var(--ic-muted); font-size: .75rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
        .sus-stat-value { color: var(--ic-ink); font-size: 1.8rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
        .sus-stat-sub { color: var(--ic-muted); font-size: .78rem; margin-top: .3rem; }
        .sus-panel { border: 1px solid var(--ic-border); border-radius: 18px; background: var(--ic-panel); overflow: hidden; }
        .sus-panel-header { padding: 1rem; border-bottom: 1px solid var(--ic-border); }
        .sus-panel-body { padding: 1rem; }
        .sus-item-row { display: grid; grid-template-columns: minmax(0, 1fr) 90px; gap: 1rem; align-items: center; padding: .55rem 0; }
        .sus-item-row + .sus-item-row { border-top: 1px solid var(--ic-border); }
        .sus-item-row-text { color: var(--ic-ink); font-size: .88rem; }
        .sus-item-row-text .badge { margin-right: .4rem; }
        .sus-bar-track { height: 8px; border-radius: 999px; background: var(--ic-sand-dark); margin-top: .35rem; overflow: hidden; }
        .sus-bar-fill { height: 100%; background: var(--ic-green-500); }
        .sus-item-mean { text-align: right; font-weight: 900; color: var(--ic-ink); }
        .sus-role-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; }
        .sus-role-card { border: 1px solid var(--ic-border); border-radius: 12px; padding: .85rem; }
        .sus-role-card .role-name { font-weight: 800; color: var(--ic-ink); }
        .sus-role-card .role-score { font-size: 1.4rem; font-weight: 900; color: var(--ic-ink); margin-top: .2rem; }
        @media (max-width: 767.98px) {
            .sus-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>

    <div class="sus-results-page">
        <section class="page-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="eyebrow mb-2">System Usability Scale — Aggregate Results</div>
                    <h1 class="h2 fw-bold mb-2">System Evaluation Results</h1>
                    <p class="mb-0" style="color: var(--ic-ink-mid); max-width: 760px;">
                        Standard SUS scoring: each response is converted to a 0-100 score. The published average across ~500 studies (Sauro, 2011) is {{ number_format(\App\Models\UsabilitySurveyResponse::AVERAGE_BENCHMARK, 0) }} — use it as the "is this above or below typical" reference point, not a pass/fail line.
                    </p>
                </div>
                <a href="{{ route('usability-survey.export') }}" class="btn btn-outline-success fw-bold">Download Raw Responses (CSV)</a>
            </div>
        </section>

        @if ($respondentCount === 0)
            <div class="sus-panel">
                <div class="sus-panel-body text-center py-5">
                    <div class="h5 mb-2">No evaluations submitted yet</div>
                    <div class="text-muted">Results will appear here once farmers, MAO personnel, or IT staff complete the <a href="{{ route('usability-survey.create') }}">System Evaluation</a> form.</div>
                </div>
            </div>
        @else
            <div class="sus-summary-grid">
                <div class="sus-stat">
                    <div class="sus-stat-label">Respondents</div>
                    <div class="sus-stat-value">{{ number_format($respondentCount) }}</div>
                </div>
                <div class="sus-stat">
                    <div class="sus-stat-label">Mean SUS Score</div>
                    <div class="sus-stat-value">{{ number_format($meanScore, 1) }}</div>
                    <div class="sus-stat-sub">{{ \App\Models\UsabilitySurveyResponse::adjectiveRating($meanScore) }} &middot; {{ $meanScore >= \App\Models\UsabilitySurveyResponse::AVERAGE_BENCHMARK ? 'Above' : 'Below' }} the {{ number_format(\App\Models\UsabilitySurveyResponse::AVERAGE_BENCHMARK, 0) }} average</div>
                </div>
                <div class="sus-stat">
                    <div class="sus-stat-label">Median Score</div>
                    <div class="sus-stat-value">{{ number_format($medianScore, 1) }}</div>
                </div>
                <div class="sus-stat">
                    <div class="sus-stat-label">Range</div>
                    <div class="sus-stat-value">{{ number_format($minScore, 0) }}&ndash;{{ number_format($maxScore, 0) }}</div>
                    <div class="sus-stat-sub">Lowest to highest individual score</div>
                </div>
            </div>

            <div class="sus-panel">
                <div class="sus-panel-header">
                    <h2 class="h5 fw-bold mb-1">Respondents By Role</h2>
                    <div class="small text-muted">Segment counts and mean score per role.</div>
                </div>
                <div class="sus-panel-body sus-role-grid">
                    @foreach ($roleBreakdown as $role => $stats)
                        <div class="sus-role-card">
                            <div class="role-name">{{ $role }}</div>
                            <div class="role-score">{{ number_format($stats['mean_score'], 1) }}</div>
                            <div class="sus-stat-sub">{{ $stats['count'] }} respondent(s)</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="sus-panel">
                <div class="sus-panel-header">
                    <h2 class="h5 fw-bold mb-1">Per-Item Mean Score</h2>
                    <div class="small text-muted">Average raw response (1&ndash;5) per statement. Items marked <span class="badge text-bg-secondary">reverse-scored</span> are negatively worded, so a lower raw average here is the good direction.</div>
                </div>
                <div class="sus-panel-body">
                    @foreach ($itemMeans as $number => $item)
                        <div class="sus-item-row">
                            <div>
                                <div class="sus-item-row-text">
                                    @unless($item['positive'])
                                        <span class="badge text-bg-secondary">reverse-scored</span>
                                    @endunless
                                    {{ $number }}. {{ $item['text'] }}
                                </div>
                                <div class="sus-bar-track"><div class="sus-bar-fill" style="width: {{ $item['mean'] !== null ? ($item['mean'] / 5) * 100 : 0 }}%;"></div></div>
                            </div>
                            <div class="sus-item-mean">{{ $item['mean'] !== null ? number_format($item['mean'], 2) : 'N/A' }} / 5</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="sus-panel">
                <div class="sus-panel-header">
                    <h2 class="h5 fw-bold mb-1">Individual Responses</h2>
                    <div class="small text-muted">Raw scores per respondent. Download the CSV above for full item-level data and comments.</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Respondent</th><th>Role</th><th>Submitted</th><th class="text-end">SUS Score</th><th>Rating</th></tr></thead>
                        <tbody>
                            @foreach ($responses as $response)
                                <tr>
                                    <td>{{ $response->user?->name ?? 'Unknown' }}</td>
                                    <td>{{ $response->role }}</td>
                                    <td>{{ $response->created_at?->format('M d, Y') }}</td>
                                    <td class="text-end fw-bold">{{ number_format($response->score(), 1) }}</td>
                                    <td>{{ \App\Models\UsabilitySurveyResponse::adjectiveRating($response->score()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
