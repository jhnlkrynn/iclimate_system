{{-- Candidate rice-yield model (2026-10: the separate candidate test page was merged in here). Read-only: the recorded
     method and hold-out metrics, the month-by-month 2025 validation, and the season-calendar retraining report.
     All of it is read from files written by the Python scripts (App\Support\CandidateModelReport). --}}
@php
    $c = \App\Support\CandidateModelReport::load();
    $meta = $c['metadata'];
    $retrain = $c['retrain'];
    $metric = fn ($value, int $decimals = 4) => is_numeric($value) ? number_format((float) $value, $decimals) : 'Not recorded';
    $delta = function ($before, $after, bool $lowerIsBetter) {
        if (! is_numeric($before) || ! is_numeric($after)) {
            return ['—', ''];
        }
        $diff = (float) $after - (float) $before;
        $better = $lowerIsBetter ? $diff < 0 : $diff > 0;

        return [($diff > 0 ? '+' : '').number_format($diff, 4), abs($diff) < 0.00005 ? '' : ($better ? 'text-success' : 'text-danger')];
    };
@endphp
<div class="col-12" id="candidate-model" data-candidate-model>
    <div class="me-panel">
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Candidate Model</h2>
            <div class="small text-muted">The 10-feature Random Forest built from the time-aware modeling dataset. It is not the deployed model.</div>
        </div>
        <div class="me-panel-body">
            @if (! $meta)
                <p class="mb-0 text-muted" data-candidate-missing>No candidate model metadata is on file.</p>
            @else
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="me-label">Method</div>
                        <div class="fw-bold">{{ $meta['model_name'] ?? 'Random Forest Regressor' }}</div>
                        <div class="small text-muted">Trained on {{ implode(', ', (array) ($meta['training_years'] ?? [])) }} · tested on {{ $meta['test_year'] ?? '—' }}</div>
                    </div>
                    <div class="col-lg-4">
                        <div class="me-label">Hold-out metrics ({{ $meta['test_year'] ?? '—' }})</div>
                        <div class="small" data-candidate-metrics>
                            RMSE <strong>{{ $metric(data_get($meta, 'final_metrics.RMSE')) }}</strong> ·
                            MAE <strong>{{ $metric(data_get($meta, 'final_metrics.MAE')) }}</strong> ·
                            R² <strong>{{ $metric(data_get($meta, 'final_metrics.R2')) }}</strong>
                        </div>
                        @if (is_numeric(data_get($meta, 'final_metrics.R2')) && (float) data_get($meta, 'final_metrics.R2') < 0)
                            <div class="small text-muted">A negative R² means the model was less accurate on the test year than always predicting the average yield.</div>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <div class="me-label">Inputs</div>
                        <div class="small"><code>{{ implode(', ', (array) ($meta['features'] ?? [])) }}</code></div>
                    </div>
                </div>
            @endif

            @if ($c['validation'])
                <h3 class="h6 fw-bold mt-4 mb-2">2025 validation, month by month</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-candidate-validation>
                        <thead><tr><th>Month</th><th class="text-end">Actual (t/ha)</th><th class="text-end">Predicted (t/ha)</th><th class="text-end">Error</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($c['validation'] as $row)
                                <tr>
                                    <td>{{ $row['month'] }}</td>
                                    <td class="text-end">{{ $row['actual'] !== null ? number_format($row['actual'], 2) : '—' }}</td>
                                    <td class="text-end">{{ $row['predicted'] !== null ? number_format($row['predicted'], 2) : '—' }}</td>
                                    <td class="text-end">{{ $row['error'] !== null ? number_format($row['error'], 2) : '—' }}</td>
                                    <td>{{ $row['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="col-12" id="season-retraining" data-season-retraining>
    <div class="me-panel">
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Season Calendar Retraining (October = Wet)</h2>
            <div class="small text-muted">The same model, data and settings, trained twice: with the dataset's season (Wet = May to September) and with the iClimate calendar (Wet = May to October).</div>
        </div>
        <div class="me-panel-body">
            @if (! $retrain)
                <p class="mb-0 text-muted" data-retrain-missing>Not run yet. Run <code>python python_scripts/retrain_yield_season.py</code> to produce the report.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2" data-retrain-table>
                        <thead><tr><th>Check</th><th>Metric</th><th class="text-end">Before (May–Sep)</th><th class="text-end">After (May–Oct)</th><th class="text-end">Change</th></tr></thead>
                        <tbody>
                            @foreach (['holdout_2025' => '2025 hold-out ('.data_get($retrain, 'holdout_2025.before.test_rows', '—').' months)', 'leave_one_year_out' => 'Leave one year out ('.data_get($retrain, 'leave_one_year_out.before.rows', '—').' months)'] as $key => $label)
                                @foreach (['rmse' => ['RMSE', true], 'mae' => ['MAE', true], 'r2' => ['R²', false]] as $m => [$name, $lowerIsBetter])
                                    @php [$change, $tone] = $delta(data_get($retrain, "$key.before.$m"), data_get($retrain, "$key.after.$m"), $lowerIsBetter); @endphp
                                    <tr data-retrain-row="{{ $key }}-{{ $m }}">
                                        <td>{{ $label }}</td>
                                        <td>{{ $name }}</td>
                                        <td class="text-end">{{ $metric(data_get($retrain, "$key.before.$m")) }}</td>
                                        <td class="text-end">{{ $metric(data_get($retrain, "$key.after.$m")) }}</td>
                                        <td class="text-end {{ $tone }}">{{ $change }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="small text-muted mb-0">
                    <li>{{ data_get($retrain, 'change.rows_changed') }} of {{ data_get($retrain, 'rows') }} rows changed season (the October rows).</li>
                    <li>The "before" hold-out {{ data_get($retrain, 'reproduces_recorded_candidate') ? 'matches' : 'does NOT match' }} the candidate model's recorded metrics, so it is the same recipe.</li>
                    <li>Deployed model (<code>{{ basename((string) data_get($retrain, 'deployed_model.path')) }}</code>): not retrained. {{ data_get($retrain, 'deployed_model.reason') }}</li>
                    <li>Report made {{ rescue(fn () => \Illuminate\Support\Carbon::parse(data_get($retrain, 'generated_at'))->timezone('Asia/Manila')->format('M j, Y g:i A'), 'Not recorded', false) }} · the retrained file is saved but not activated.</li>
                </ul>
            @endif
        </div>
    </div>
</div>
