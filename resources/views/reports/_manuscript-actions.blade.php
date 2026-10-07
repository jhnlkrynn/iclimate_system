{{-- Manuscript – Naicel (2026-10-08), Phase 7: Excel and PDF downloads, Draft / Published status and the version of
     the shown report (ReportExtrasController). Only the MAO publishes. --}}
@php $published = ($report->status ?? 'published') === \App\Models\Report::STATUS_PUBLISHED; @endphp
<a class="btn btn-sm fw-bold report-white-action" href="{{ route('reports.export.xlsx', ['report' => $report->id]) }}" data-export-xlsx>Download Excel</a>
<a class="btn btn-sm fw-bold report-white-action" href="{{ route('reports.export.pdf', ['report' => $report->id]) }}" data-export-pdf>Download PDF</a>
<span @class(['badge align-self-start', 'text-bg-success' => $published, 'text-bg-warning' => ! $published]) data-report-status>{{ $published ? 'Published' : 'Draft' }} · v{{ $report->version ?? 1 }}</span>
@if (auth()->user()?->role === \App\Models\User::ROLE_MAO)
    <form method="POST" action="{{ route('reports.publish', $report) }}" data-loading="true">
        @csrf
        <input type="hidden" name="publish" value="{{ $published ? 0 : 1 }}">
        <button class="btn btn-sm {{ $published ? 'btn-outline-secondary' : 'btn-primary' }}" type="submit" data-loading-text="Saving...">{{ $published ? 'Back to draft' : 'Publish' }}</button>
    </form>
@endif
