<x-app-layout>
    <x-slot:title>Import {{ $template['title'] }}</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 2: RecordImportController. Upload a CSV; each row passes the Create form's
         checks; skipped rows are listed with the reason. --}}
    <div class="mfd itd itp" data-record-import-page="{{ $type }}">
        <x-it.page-header kicker="Records" :title="'Import '.$template['title']" lede="Add many records at once from a CSV file, for example a PAGASA dataset or the MAO's seasonal production report.">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route($type.'.index') }}">Back to {{ $template['title'] }}</a>
        </x-it.page-header>

        @if ($result = session('import_result'))
            <section class="itp-section" aria-labelledby="impResult" data-import-result>
                <div class="itp-section-head">
                    <h2 id="impResult">Result: {{ $result['file'] }}</h2>
                    <p><strong>{{ number_format($result['imported']) }}</strong> rows imported · <strong>{{ number_format($result['skipped_total']) }}</strong> rows skipped. Imported rows are marked Not validated until you check them.</p>
                </div>
                @if ($result['skipped'] !== [])
                    <div class="table-responsive mfd-card p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Line</th><th>Why it was skipped</th></tr></thead>
                            <tbody>
                                @foreach ($result['skipped'] as $row)<tr><td>{{ $row['line'] }}</td><td>{{ $row['reason'] }}</td></tr>@endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($result['skipped_total'] > count($result['skipped']))<p class="small text-muted mt-2">The first {{ count($result['skipped']) }} are listed.</p>@endif
                @endif
            </section>
        @endif

        <section class="itp-section" aria-labelledby="impUpload">
            <div class="itp-section-head">
                <h2 id="impUpload">Upload a CSV file</h2>
                <p>{{ $template['help'] }}</p>
            </div>
            <div class="mfd-card">
                <p class="mb-2"><strong>Columns:</strong> <code>{{ implode(', ', $template['columns']) }}</code></p>
                <p class="mb-3"><a href="{{ route('records.import.template', $type) }}" data-fast-nav-bound="true">Download the template</a> (with one example row to replace). Up to {{ number_format($maxRows) }} rows per file. A row that is already in the records is skipped.</p>
                <form method="POST" action="{{ route('records.import.store', $type) }}" enctype="multipart/form-data" data-loading="true">
                    @csrf
                    <label class="form-label fw-semibold" for="importFile">CSV file</label>
                    <input class="form-control @error('file') is-invalid @enderror" id="importFile" type="file" name="file" accept=".csv,text/csv" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-primary mt-3" type="submit" data-loading-text="Importing...">Import</button>
                </form>
            </div>
        </section>

        <section class="itp-section" aria-labelledby="impValidate">
            <div class="itp-section-head">
                <h2 id="impValidate">Validate records</h2>
                <p>After checking imported records against their source, mark them as validated. Single records can be validated from the list.</p>
            </div>
            <form method="POST" action="{{ route('records.validate-all', $type) }}" data-loading="true" data-ic-confirm="Mark every record that is not validated yet as validated?">
                @csrf
                <button class="btn btn-outline-primary" type="submit" data-loading-text="Saving...">Validate all records not yet validated</button>
            </form>
        </section>
    </div>
</x-app-layout>
