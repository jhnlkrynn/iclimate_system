{{-- Thesis objective 2: RMSE, MAE and R² of each algorithm under each condition (wet/dry season, irrigated/rainfed).
     Computed by python_scripts/evaluation_service.py from the same cross-validated predictions as the table above. --}}
@php
    $breakdown = (array) ($evaluation['breakdown'] ?? []);
    $titles = ['season' => 'By season (wet / dry)', 'irrigation_type' => 'By farming system (irrigated / rainfed)'];
    $format = fn ($value): string => is_numeric($value) ? number_format((float) $value, 4) : 'N/A';
@endphp
@if (array_filter($breakdown))
    <div class="me-panel mb-4" data-condition-breakdown>
        <div class="me-panel-header">
            <h2 class="h5 fw-bold mb-1">Results Under Varying Conditions</h2>
            <div class="small text-muted">
                The same predictions as above, scored separately for each season and farming system.
                @unless ($verified)
                    <strong>Unverified:</strong> these figures describe the records on file, not model accuracy.
                @endunless
                R² shows N/A when a group has fewer than two records or all its yields are equal.
            </div>
        </div>
        <div class="me-panel-body">
            @foreach ($titles as $column => $title)
                @continue(empty($breakdown[$column]))
                <h3 class="h6 fw-bold mt-2">{{ $title }}</h3>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0 me-table" data-breakdown="{{ $column }}">
                        <thead>
                            <tr><th>Condition</th><th>Algorithm</th><th class="text-end">Records</th><th class="text-end">RMSE</th><th class="text-end">MAE</th><th class="text-end">R²</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdown[$column] as $condition => $rows)
                                @foreach ((array) $rows as $row)
                                    <tr>
                                        @if ($loop->first)
                                            <td class="fw-bold" rowspan="{{ count($rows) }}">{{ $condition }}</td>
                                        @endif
                                        <td>{{ $row['algorithm'] ?? '' }}</td>
                                        <td class="text-end">{{ (int) ($row['n'] ?? 0) }}</td>
                                        <td class="text-end">{{ $format($row['rmse'] ?? null) }}</td>
                                        <td class="text-end">{{ $format($row['mae'] ?? null) }}</td>
                                        <td class="text-end">{{ $format($row['r2'] ?? null) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
@endif
