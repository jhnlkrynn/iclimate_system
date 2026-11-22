<x-app-layout>
    <x-slot:title>{{ __('yield_request.training_title') }}</x-slot:title>
    <div class="mfd itd itp">
        <x-shared.page-header :kicker="__('yield_request.kicker')" :title="__('yield_request.training_title')" :lede="__('yield_request.training_intro')" />
        <div class="fad wpx">
            @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])

            <section class="fad-card mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ __('yield_request.training_heading') }}</h2>
                        <p class="small text-muted mb-0">{{ __('yield_request.training_summary', ['total' => $total, 'ready' => $readyBuckets, 'target' => $target]) }}</p>
                    </div>
                    <a class="btn btn-primary btn-sm" href="{{ route('analysis.harvest-training.export') }}">{{ __('yield_request.training_export') }}</a>
                </div>

                @if ($buckets->isEmpty())
                    <p class="text-muted mb-0">{{ __('yield_request.training_empty') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" data-harvest-training-table>
                            <thead>
                                <tr>
                                    <th>{{ __('yield_request.barangay') }}</th>
                                    <th>{{ __('yield_request.farm_type') }}</th>
                                    <th>{{ __('yield_request.season') }}</th>
                                    <th>{{ __('yield_request.training_progress_col') }}</th>
                                    <th>{{ __('yield_request.training_avg_col') }}</th>
                                    <th>{{ __('yield_request.training_latest_col') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($buckets as $b)
                                    @php($pct = min(100, (int) round(($b['count'] / $target) * 100)))
                                    <tr>
                                        <td><strong>{{ $b['barangay'] }}</strong></td>
                                        <td>{{ $b['farm_type'] }}</td>
                                        <td>{{ $b['season'] }}</td>
                                        <td>
                                            <div>{{ __('yield_request.training_progress', ['barangay' => $b['barangay'], 'farm_type' => strtolower($b['farm_type']), 'count' => $b['count'], 'target' => $target]) }}</div>
                                            <div class="progress mt-1" style="height: 6px; max-width: 180px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $b['count'] }}" aria-valuemin="0" aria-valuemax="{{ $target }}"></div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($b['avg_yield'], 2) }} t/ha</td>
                                        <td class="small text-muted">{{ $b['latest'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
