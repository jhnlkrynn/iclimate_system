@php
    $fb = $weatherFeedbackSummary;
    $fbFilters = $weatherFeedbackFilters ?? [];
    $fbLabels = ['accurate' => 'Accurate', 'partly' => 'Partly accurate', 'different' => 'Different'];
@endphp
<section class="mao-panel" id="farmer-weather-feedback">
    <div class="mao-panel-header">
        <div>
            <h2 class="mao-panel-title">Farmer Weather Feedback</h2>
            <p class="mao-panel-sub">What farmers reported about past weather forecasts. Reports are a guide, not proof.</p>
        </div>
    </div>
    <div class="mao-panel-body">
        @if($fb['enough_data'])
            <div class="d-flex flex-wrap gap-3 mb-2">
                @foreach($fbLabels as $key => $label)
                    <div><div class="mao-value">{{ $fb['percentages'][$key] }}%</div><div class="small text-muted">{{ $label }}</div></div>
                @endforeach
            </div>
            <p class="mb-1">{{ $fb['message'] }}</p>
            <div class="small text-muted">Based on {{ $fb['total'] }} farmer {{ \Illuminate\Support\Str::plural('report', $fb['total']) }}.</div>
        @else
            <p class="mb-1"><strong>{{ $fb['message'] }}</strong></p>
            <div class="small text-muted">{{ $fb['total'] }} of at least {{ $fb['min_reports'] }} reports received so far.</div>
        @endif

        <details class="mt-3">
            <summary class="fw-bold" style="cursor:pointer">View Details</summary>
            <form method="GET" action="{{ route('mao.operations') }}#farmer-weather-feedback" class="row g-2 align-items-end mt-2">
                <div class="col-md-3">
                    <label class="small text-muted" for="fb_barangay">Barangay</label>
                    <select id="fb_barangay" name="fb_barangay" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(\App\Support\LianBarangays::all() as $name)
                            <option value="{{ $name }}" @selected(($fbFilters['barangay'] ?? null) === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><label class="small text-muted" for="fb_from">From</label><input id="fb_from" type="date" name="fb_from" value="{{ $fbFilters['from'] ?? '' }}" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="small text-muted" for="fb_to">To</label><input id="fb_to" type="date" name="fb_to" value="{{ $fbFilters['to'] ?? '' }}" class="form-control form-control-sm"></div>
                <div class="col-md-3">
                    <label class="small text-muted" for="fb_condition">Weather</label>
                    <select id="fb_condition" name="fb_condition" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(\App\Models\WeatherForecastFeedback::OBSERVED_CONDITIONS as $condition)
                            <option value="{{ $condition }}" @selected(($fbFilters['condition'] ?? null) === $condition)>{{ $condition }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Filter</button></div>
            </form>
            <div class="table-responsive mt-3">
                <table class="table mao-monitoring-table mb-0">
                    <thead><tr><th>Barangay</th><th>Forecast month</th><th>Forecast</th><th>Reports</th><th>Accurate</th><th>Partly</th><th>Different</th><th>Note</th></tr></thead>
                    <tbody>
                        @forelse($fb['groups'] as $group)
                            <tr>
                                <td>{{ $group['barangay'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($group['forecast_date'])->format('F Y') }}</td>
                                <td>{{ \App\Support\WeatherConditionLabel::display($group['forecast_condition']) }}</td>
                                <td>{{ $group['total'] }}</td>
                                <td>{{ $group['counts']['accurate'] }}</td>
                                <td>{{ $group['counts']['partly'] }}</td>
                                <td>{{ $group['counts']['different'] }}</td>
                                <td>@if($group['low_confidence'])Few reports @elseif($group['conflicting'])Reports disagree @else {{ $fbLabels[$group['dominant']] ?? '' }} @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">No farmer reports match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</section>
