@php
    $statusClass = fn (string $s): string => match ($s) {
        'active' => 'text-bg-success',
        'validated' => 'text-bg-primary',
        'candidate' => 'text-bg-secondary',
        'failed' => 'text-bg-danger',
        default => 'text-bg-light border',
    };
    $metric = fn ($v) => $v === null ? 'Not recorded' : rtrim(rtrim(number_format($v, 4), '0'), '.');
@endphp

<x-app-layout>
    <div class="container-fluid py-3">
        <section class="page-hero d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="eyebrow mb-2">System Health</div>
                <h1 class="h3 mb-1">ML Model Registry</h1>
                <p class="mb-0">Rice yield models and their recorded results. Nothing becomes active until an IT Expert validates and activates it.</p>
            </div>
            <a class="btn btn-outline-light btn-sm" href="{{ route('system-health.index') }}">Back to System Health</a>
        </section>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-model-registry>
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Model</th>
                            <th>Target</th>
                            <th>Training years</th>
                            <th>Test year</th>
                            <th>MAE</th>
                            <th>RMSE</th>
                            <th>R²</th>
                            <th>Compatibility</th>
                            <th>Status</th>
                            <th>Active</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($models as $model)
                            @php
                                $years = $model->training_years;
                                $compat = $model->compatibility ?? [];
                            @endphp
                            <tr data-model-id="{{ $model->id }}">
                                <td class="fw-semibold">v{{ $model->version }}</td>
                                <td>
                                    {{ $model->model_type }}
                                    <div class="small text-muted">{{ $model->fileName() }}</div>
                                </td>
                                <td>{{ $model->target_variable ?? 'Not recorded' }}</td>
                                <td>{{ $years ? (min($years) === max($years) ? min($years) : min($years).'–'.max($years)) : 'Not recorded' }}</td>
                                <td>{{ $model->test_year ?? 'Not recorded' }}</td>
                                <td>{{ $metric($model->mae) }}</td>
                                <td>{{ $metric($model->rmse) }}</td>
                                <td>{{ $metric($model->r2) }}</td>
                                <td class="small">
                                    @if ($compat)
                                        {{ $compat['estimator_class'] ?? 'Unknown estimator' }}<br>
                                        scikit-learn {{ $compat['checked_with_sklearn'] ?? 'not checked' }}<br>
                                        @if (($compat['loads_in_current_environment'] ?? null) === true)
                                            <span class="text-success">Loads in this environment</span>
                                        @elseif (($compat['loads_in_current_environment'] ?? null) === false)
                                            <span class="text-danger">Did not load when checked</span>
                                        @else
                                            <span class="text-muted">Load not checked</span>
                                        @endif
                                        @if (! empty($compat['sha256']))
                                            <br><span class="text-muted">SHA-256 {{ substr($compat['sha256'], 0, 12) }}</span>
                                        @endif
                                    @else
                                        Not recorded
                                    @endif
                                </td>
                                <td><span class="badge {{ $statusClass($model->status) }}">{{ ucfirst($model->status) }}</span></td>
                                <td>{{ $model->is_active ? 'Yes' : 'No' }}</td>
                                <td>{{ $model->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</td>
                                <td class="text-nowrap">
                                    @if ($model->status === 'candidate')
                                        <form method="POST" action="{{ route('model-registry.validate', $model) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-primary btn-sm">Validate</button>
                                        </form>
                                    @endif
                                    @if (in_array($model->status, ['validated', 'retired'], true) && $model->mae !== null)
                                        <form method="POST" action="{{ route('model-registry.activate', $model) }}" class="d-inline"
                                              data-ic-confirm="Make version {{ $model->version }} the active model? The current one will be retired.">
                                            @csrf
                                            <button class="btn btn-success btn-sm">Activate</button>
                                        </form>
                                    @endif
                                    @if (! $model->is_active && $model->status !== 'failed')
                                        <form method="POST" action="{{ route('model-registry.fail', $model) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-danger btn-sm">Mark failed</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center text-muted py-4">No models are registered yet. Run <code>php artisan ml:register-existing</code>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
