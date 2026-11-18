<x-app-layout>
<div class="container py-4">
    <h1>{{ $report->reportingPeriod->label }}</h1>
    <p>{{ $assignment->association->name }} · {{ str_replace('_', ' ', $report->status) }}</p>
    <dl>
        <dt>Production</dt><dd>{{ $report->production_tons ?? 'Not supplied' }} metric tons</dd>
        <dt>Harvested area</dt><dd>{{ $report->area_harvested_hectares ?? 'Not supplied' }} hectares</dd>
        <dt>Submitted</dt><dd>{{ $report->submitted_at?->timezone('Asia/Manila')->format('M d, Y H:i') ?? 'Draft' }}</dd>
        <dt>Remarks</dt><dd>{{ $report->remarks ?: 'None' }}</dd>
    </dl>
    @include('association-chairman._reviews', ['reviews' => $report->reviews])
    @if (in_array($report->status, ['draft', 'needs_revision'], true))
        <a class="btn btn-primary" href="{{ route('association-chairman.reports.create', $report->reportingPeriod) }}">Revise report</a>
    @endif
    <a class="btn btn-outline-secondary" href="{{ route('association-chairman.dashboard') }}">Association reports</a>
</div>
</x-app-layout>
