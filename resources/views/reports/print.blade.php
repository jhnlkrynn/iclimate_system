<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>@media print { .no-print { display: none !important; } body { background: #fff; } }</style>
    </head>
    <body class="bg-light">
        <main class="container py-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div><h1 class="h3 mb-1">{{ $title }}</h1><p class="text-muted mb-0">Generated {{ now()->format('Y-m-d H:i') }}</p></div>
                <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>@forelse ($rows as $row)<tr>@foreach ($columns as $field => $label)<td>{{ data_get($row, $field) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}" class="text-center text-muted">No records found.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
        </main>
    </body>
</html>