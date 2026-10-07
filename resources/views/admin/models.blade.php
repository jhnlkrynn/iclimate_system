<x-app-layout>
    <x-slot:title>ML Models</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: ModelManagementController. --}}
    @php
        $tz = \App\Support\BusinessClock::timezone();
        $m = fn ($v) => $v === null ? '—' : number_format((float) $v, 3);
    @endphp
    <div class="mfd itd itp" data-model-management>
        <x-it.page-header kicker="IT Personnel" title="ML Models" lede="The active rice-yield model, a comparison of three algorithms, retraining, uploads and the training history.">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('model-registry.index') }}">Model Registry (validate / activate)</a>
        </x-it.page-header>
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        @if ($last)
            <div @class(['alert', 'alert-success' => $last['state'] === 'done', 'alert-danger' => $last['state'] === 'failed', 'alert-info' => $last['state'] === 'running']) data-model-last>
                <strong>Last {{ $last['action'] }}:</strong> {{ $last['state'] === 'running' ? 'running…' : ($last['message'] ?? '') }}
            </div>
        @endif

        <section class="itp-section" aria-labelledby="mmActive">
            <div class="itp-section-head"><h2 id="mmActive">Active model</h2></div>
            <div class="mfd-card">
                @if ($active)
                    <strong>{{ $active->name }}</strong> · {{ $active->model_type }} · version {{ $active->version }}
                    <div class="small text-muted">RMSE {{ $m($active->rmse) }} · MAE {{ $m($active->mae) }} · R² {{ $m($active->r2) }}@if ($active->trained_at) · trained {{ $active->trained_at->copy()->timezone($tz)->format('M j, Y') }}@endif</div>
                @else
                    <p class="mb-0">No model is marked active in the registry. The prediction service uses its deployed model (rice_yield_model_final.pkl, Random Forest). Register it with <code>php artisan ml:register-existing</code>.</p>
                @endif
            </div>
        </section>

        <section class="itp-section" aria-labelledby="mmCompare">
            <div class="itp-section-head">
                <h2 id="mmCompare">Model comparison</h2>
                <p>Multiple Linear Regression, Random Forest and Gradient Boosting on the same data ({{ $dataset }}{{ $datasetUploaded ? ', uploaded' : '' }}): trained on the earlier years, tested on the newest year, and a leave-one-year-out check. Lower RMSE / MAE is better; R² closer to 1 is better.</p>
            </div>
            <form method="POST" action="{{ route('admin.models.compare') }}" class="mb-2" data-loading="true">@csrf<button class="btn btn-primary btn-sm" type="submit" data-loading-text="Starting...">Run comparison</button></form>
            @if ($report)
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-model-comparison>
                        <thead><tr><th>Algorithm</th><th>Hold-out RMSE</th><th>MAE</th><th>R²</th><th>Leave-one-year-out RMSE</th><th>MAE</th><th>R²</th></tr></thead>
                        <tbody>
                            @foreach ($report['results'] as $r)
                                <tr @if ($r['key'] === $report['best']) class="table-success" @endif>
                                    <td><strong>{{ $r['name'] }}</strong>@if ($r['key'] === $report['best']) <span class="badge text-bg-success">Best</span>@endif</td>
                                    <td>{{ $m($r['holdout']['rmse']) }}</td><td>{{ $m($r['holdout']['mae']) }}</td><td>{{ $m($r['holdout']['r2']) }}</td>
                                    <td>{{ $m($r['leave_one_year_out']['rmse']) }}</td><td>{{ $m($r['leave_one_year_out']['mae']) }}</td><td>{{ $m($r['leave_one_year_out']['r2']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2">{{ $report['rows'] }} months; hold-out year {{ $report['results'][0]['holdout_year'] ?? '—' }}; run {{ \Illuminate\Support\Carbon::parse($report['ran_at'])->timezone($tz)->format('M j, Y g:i A') }}. "Best" = lowest leave-one-year-out RMSE. A negative R² means the model does worse than always guessing the average: with only a few years of monthly data, treat these as early results.</p>
            @else
                <p class="small text-muted">No comparison run yet.</p>
            @endif
        </section>

        <div class="row g-3">
            <div class="col-lg-4">
                <section class="mfd-card h-100" aria-labelledby="mmRetrain">
                    <h2 class="h6 fw-bold" id="mmRetrain">Retrain with the newest data</h2>
                    <p class="small text-muted">Trains on every year of the dataset and saves a new candidate version (the deployed model is not changed).</p>
                    <form method="POST" action="{{ route('admin.models.retrain') }}" data-loading="true">
                        @csrf
                        <select class="form-select form-select-sm mb-2" name="algorithm" aria-label="Algorithm">@foreach ($algorithms as $key => $label)<option value="{{ $key }}" @selected($key === ($report['best'] ?? 'rf'))>{{ $label }}</option>@endforeach</select>
                        <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Starting...">Retrain</button>
                    </form>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="mfd-card h-100" aria-labelledby="mmDataset">
                    <h2 class="h6 fw-bold" id="mmDataset">Upload a training dataset</h2>
                    <p class="small text-muted">CSV with the modeling dataset's columns ({{ implode(', ', \App\Http\Controllers\ModelManagementController::DATASET_COLUMNS) }}).</p>
                    <form method="POST" action="{{ route('admin.models.dataset') }}" enctype="multipart/form-data" data-loading="true">
                        @csrf
                        <input class="form-control form-control-sm mb-2" type="file" name="dataset" accept=".csv" required>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Upload dataset</button>
                    </form>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="mfd-card h-100" aria-labelledby="mmUpload">
                    <h2 class="h6 fw-bold" id="mmUpload">Upload a model version</h2>
                    <form method="POST" action="{{ route('admin.models.upload') }}" enctype="multipart/form-data" data-loading="true">
                        @csrf
                        <input class="form-control form-control-sm mb-1" type="file" name="model" accept=".pkl,.joblib" required aria-label="Model file">
                        <input class="form-control form-control-sm mb-1" name="name" placeholder="Name" required>
                        <div class="d-flex gap-1 mb-1"><input class="form-control form-control-sm" name="model_type" placeholder="Type (e.g. Random Forest)" required><input class="form-control form-control-sm" name="version" placeholder="Version" required></div>
                        <div class="d-flex gap-1 mb-2"><input class="form-control form-control-sm" name="rmse" placeholder="RMSE" inputmode="decimal"><input class="form-control form-control-sm" name="mae" placeholder="MAE" inputmode="decimal"><input class="form-control form-control-sm" name="r2" placeholder="R²" inputmode="decimal"></div>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Upload model</button>
                    </form>
                </section>
            </div>
        </div>
        @if ($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif

        <section class="itp-section mt-3" aria-labelledby="mmHistory">
            <div class="itp-section-head"><h2 id="mmHistory">Training history</h2><p>Every model in the registry, newest first. Validate and activate on Model Registry.</p></div>
            @if ($history->isEmpty())
                <p class="small text-muted">No model registered yet.</p>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-model-history>
                        <thead><tr><th>Model</th><th>Version</th><th>Status</th><th>RMSE</th><th>MAE</th><th>R²</th><th>Trained</th></tr></thead>
                        <tbody>
                            @foreach ($history as $model)
                                <tr><td>{{ $model->name }}<div class="small text-muted">{{ $model->model_type }}</div></td><td class="small">{{ $model->version }}</td><td><span @class(['badge', 'text-bg-success' => $model->is_active, 'text-bg-info' => ! $model->is_active && $model->status === 'validated', 'text-bg-light' => ! $model->is_active && $model->status !== 'validated'])>{{ $model->is_active ? 'Active' : ucfirst($model->status) }}</span></td><td>{{ $m($model->rmse) }}</td><td>{{ $m($model->mae) }}</td><td>{{ $m($model->r2) }}</td><td class="small">{{ $model->trained_at?->copy()->timezone($tz)->format('M j, Y') ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
