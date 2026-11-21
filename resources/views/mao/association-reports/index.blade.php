<x-app-layout>
<div class="container py-4"><h1>Farmers Associations — Rice Yield Monitoring</h1>@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('mao.association-reports.periods.store') }}" class="mb-4">
@csrf
<div class="row g-2">
<div class="col"><label for="periodKey">Period key</label><input id="periodKey" class="form-control" name="period_key" required maxlength="80"></div>
<div class="col"><label for="periodLabel">Reporting period</label><input id="periodLabel" class="form-control" name="label" required maxlength="160"></div>
<div class="col"><label for="reportYear">Year</label><input id="reportYear" class="form-control" type="number" name="reporting_year" value="{{ now()->year }}" min="2000" max="2200" required></div>
<div class="col"><label for="reportDue">Deadline</label><input id="reportDue" class="form-control" type="datetime-local" name="due_at" required></div>
</div>
<button class="btn btn-primary mt-2">Create reporting period</button>
</form>
@if($periods->isNotEmpty())
<form method="GET" action="{{ route('mao.association-reports.index') }}" class="mb-3" data-period-picker>
<label for="periodPicker">Reporting period</label>
<select id="periodPicker" name="period" class="form-control" data-autosubmit>
@foreach($periods as $option)<option value="{{ $option->id }}" @selected($period?->id === $option->id)>{{ $option->label }} — due {{ $option->due_at->format('M j, Y') }}{{ $option->due_at->isPast() ? ' (overdue)' : '' }}</option>@endforeach
</select>
<noscript><button class="btn btn-secondary mt-1">Show</button></noscript>
</form>
@endif
<p>{{ $associations->count() }} associations. MAO acceptance is separate from research verification.</p><table class="table"><thead><tr><th>Barangay</th><th>Association</th><th>Chairman</th><th>Report</th><th>Action</th></tr></thead><tbody>@foreach($associations as $association) @php($report=$association->yieldReports->first())<tr><td>{{ $association->barangay }}</td><td>{{ $association->name }}</td><td>{{ $association->chairmanAssignments->first()?->user?->name ?? ($association->chairman_name ?: 'Unverified') }}</td><td>{{ $report?->status ?? 'Not Submitted' }}</td><td>@if($report)<a href="{{ route('mao.association-reports.reports.show',$report) }}">View</a>@else—@endif<form method="POST" action="{{ route('mao.association-reports.associations.assign', $association) }}" class="mt-2">
@csrf
<label for="chairman-{{ $association->id }}">Assign existing Farmer account ID</label>
<input id="chairman-{{ $association->id }}" type="number" min="1" name="user_id" required class="form-control">
<button class="btn btn-primary mt-1">Assign chairman</button>
</form></td></tr>@endforeach</tbody></table></div>
</x-app-layout>
