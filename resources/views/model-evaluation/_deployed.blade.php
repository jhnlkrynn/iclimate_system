{{-- The deployed rice-yield model: what is on record about it, and nothing more. A fact that was never recorded
     (for example test metrics) is simply left out; nothing is run from this page. --}}
@php
    $path = (string) config('prediction.rice_yield.model_path');
    $file = rescue(fn () => base_path($path), null, false);
    $onDisk = $file && is_file($file);
    $record = rescue(fn () => \App\Models\MlModel::query()->where('purpose', \App\Models\MlModel::PURPOSE_RICE_YIELD)->where('is_active', true)->first(), null, false);
    $inputs = array_values(array_filter((array) ($record?->feature_list ?: [])))
        ?: array_values(array_filter(array_map(fn (array $f) => $f['model_keys']['deployed'] ?? null, \App\Services\Prediction\FeatureCatalog::forSchema(\App\Services\Prediction\FeatureCatalog::SCHEMA_DEPLOYED))));
    $metrics = array_filter(['RMSE' => $record?->rmse, 'MAE' => $record?->mae, 'R²' => $record?->r2], fn ($value) => $value !== null);
    $facts = array_filter([
        'Model' => $record?->name ?: config('prediction.rice_yield.model_name'),
        'Type' => $record?->model_type,
        'Version' => $record?->version,
        'File' => basename($path),
        'File saved' => $onDisk ? \Illuminate\Support\Carbon::createFromTimestamp(filemtime($file))->timezone('Asia/Manila')->format('M j, Y') : null,
        'Trained' => $record?->trained_at?->timezone('Asia/Manila')->format('M j, Y'),
        'Training years' => $record?->training_years ? implode(', ', (array) $record->training_years) : null,
        'Test year' => $record?->test_year,
    ], fn ($value) => filled($value));
@endphp
<div class="col-12" id="deployed-model" data-deployed-model>
    <div class="me-panel">
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Deployed Model</h2>
            <div class="small text-muted">The rice-yield model the system uses for its estimates.</div>
        </div>
        <div class="me-panel-body">
            <div class="row g-3">
                @foreach ($facts as $label => $value)
                    <div class="col-sm-6 col-lg-3">
                        <div class="me-label">{{ $label }}</div>
                        <div class="fw-bold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            @if ($metrics)
                <div class="mt-3" data-deployed-metrics>
                    <div class="me-label">Metrics on record</div>
                    <div class="small">
                        @foreach ($metrics as $name => $value)
                            {{ $name }} <strong>{{ number_format((float) $value, 4) }}</strong>@if (! $loop->last) · @endif
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($inputs)
                <div class="mt-3">
                    <div class="me-label">Inputs</div>
                    <div class="small"><code>{{ implode(', ', $inputs) }}</code></div>
                </div>
            @endif
        </div>
    </div>
</div>
