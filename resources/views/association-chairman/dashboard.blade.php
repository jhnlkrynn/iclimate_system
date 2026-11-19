<x-app-layout>
<div class="container py-4"><h1>{{ $assignment->association->name }}</h1><p class="text-muted">{{ $assignment->association->barangay }} · Provisional reporting profile</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<h2 class="h5 mt-4">Reporting periods</h2><table class="table"><thead><tr><th>Period</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($periods as $period) @php($report=$reports->firstWhere('reporting_period_id',$period->id))<tr><td>{{ $period->label }}</td><td>{{ optional($period->due_at)->timezone(config('app.timezone'))->format('M d, Y H:i') }}</td><td>{{ $report?->status ?? 'Not Submitted' }}</td><td><a class="btn btn-sm btn-primary" href="{{ $report && ! in_array($report->status, ['draft', 'needs_revision'], true) ? route('association-chairman.reports.show', $report) : route('association-chairman.reports.create', $period) }}">{{ $report?->status === 'needs_revision' ? 'Revise' : 'Open' }}</a></td></tr>@endforeach
</tbody></table></div>
</x-app-layout>
