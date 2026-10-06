{{-- Lower Than Previous Yield (moved here from the former Operations page, 2026-10): farmers' planting plans whose
     estimate is below the same month's past average. Shown only when there is one; read from saved estimates. --}}
@php
    $lowerYield = rescue(fn () => \App\Support\SchemaCache::hasTable('farmer_planting_schedules')
        ? \App\Models\FarmerPlantingSchedule::query()->with('farmer.farmerProfile')
            ->where('comparison_interpretation', 'lower')->whereNotNull('predicted_yield')->whereNotNull('historical_same_month_average')
            ->latest('planting_date')->limit(25)->get()
        : collect(), collect(), false);
@endphp
@if ($lowerYield->isNotEmpty())
    <div class="card no-lift" data-report-lower-yield>
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 fw-bold mb-1">Lower Than Previous Yield</h2>
                <p class="small text-muted mb-0">Planting plans expected to yield less than the same month's past average.</p>
            </div>
            <span class="badge text-bg-warning">{{ $lowerYield->count() }} {{ \Illuminate\Support\Str::plural('plan', $lowerYield->count()) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Farmer</th><th>Barangay</th><th>Planting date</th><th class="text-end">Expected</th><th class="text-end">Past average</th></tr></thead>
                <tbody>
                    @foreach ($lowerYield as $schedule)
                        <tr>
                            <td>{{ $schedule->farmer?->farmerProfile?->full_name ?: $schedule->farmer?->name ?: 'Unknown farmer' }}</td>
                            <td>{{ $schedule->barangay ?: 'Not on file' }}</td>
                            <td>{{ $schedule->planting_date?->format('M j, Y') }}</td>
                            <td class="text-end">{{ number_format((float) $schedule->predicted_yield, 2) }} t/ha</td>
                            <td class="text-end">{{ number_format((float) $schedule->historical_same_month_average, 2) }} t/ha</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
