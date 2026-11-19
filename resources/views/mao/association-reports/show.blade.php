<x-app-layout>
<div class="container py-4"><h1>{{ $report->association->name }}</h1><p>Status: {{ $report->status }} · Research provenance: {{ $report->research_provenance }}</p><dl><dt>Production</dt><dd>{{ $report->production_tons }} metric tons</dd><dt>Harvested area</dt><dd>{{ $report->area_harvested_hectares }} hectares</dd><dt>Calculated average yield</dt><dd>{{ $report->average_yield_t_per_hectare }} tons/hectare</dd></dl>@if ($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if ($report->supporting_document_path)<p><a href="{{ route('association-reports.document', $report) }}">Download supporting document</a></p>@endif
    @include('association-chairman._submissions')
@include('association-chairman._reviews', ['reviews' => $report->reviews])
@if (in_array($report->status, ['submitted', 'under_review'], true))
<form method="POST" action="{{ route('mao.association-reports.reports.review',$report) }}">@csrf <textarea class="form-control" name="notes" placeholder="Review notes"></textarea><button class="btn btn-secondary mt-2" name="action" value="needs_revision">Request revision</button> <button class="btn btn-success mt-2" name="action" value="accept">Accept report</button></form>
@else<p>No review is pending for the current report status.</p>@endif
</div>
</x-app-layout>
