@if ($report->submissions->isNotEmpty())
<section class="my-3" aria-labelledby="submissionHistory">
    <h2 id="submissionHistory" class="h5">Submission history</h2>
    @foreach ($report->submissions->sortByDesc('version') as $submission)
    <article class="border rounded p-3 mb-2" data-submission-version="{{ $submission->version }}">
        <h3 class="h6">Version {{ $submission->version }}</h3>
        <p>{{ $submission->submitter?->name }} · {{ $submission->submitted_at->timezone('Asia/Manila')->format('M d, Y H:i') }}</p>
        <dl><dt>Production</dt><dd>{{ $submission->snapshot['production_tons'] ?? 'Not supplied' }} metric tons</dd>
        <dt>Harvested area</dt><dd>{{ $submission->snapshot['area_harvested_hectares'] ?? 'Not supplied' }} hectares</dd>
        <dt>Remarks</dt><dd>{{ $submission->snapshot['remarks'] ?? 'None' }}</dd></dl>
        @if (!empty($submission->snapshot['supporting_document_path']))
        <a href="{{ route('association-reports.document', [$report, $submission->id]) }}">Download supporting document for version {{ $submission->version }}</a>
        @endif
    </article>
    @endforeach
</section>
@endif
