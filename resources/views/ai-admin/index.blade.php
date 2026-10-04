<x-app-layout>
    <div class="container-fluid py-3">
        <section class="page-hero">
            <div class="eyebrow mb-2 d-flex align-items-center gap-2"><img src="{{ asset('images/climora-ai-icon.png') }}" alt="" width="28" height="28" style="border-radius:6px;background:#fff;">Climora AI</div>
            <h1 class="h3 mb-1">Chatbot knowledge and language filter</h1>
            <p class="mb-0">Manage what Climora understands about rice farming and weather, and which language it refuses. Changes apply to the next chat message. Farmers cannot see or change any of this.</p>
        </section>

        <ul class="nav nav-pills ic-pill-tabs mb-3 flex-wrap gap-1">
            @foreach ($resources as $key => $item)
                <li class="nav-item">
                    <a class="nav-link {{ $key === $resource ? 'active' : '' }}" href="{{ route('ai-admin.index', $key) }}">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-info">{{ $config['help'] }}</div>

        @if (! empty($config['sensitive']))
            <div class="alert alert-warning py-2">This page lists offensive words so they can be managed. It is visible only to IT Experts and MAO personnel.</div>
        @endif

        @php
            $renderField = function (string $name, array $field, $row = null, string $prefix = '') {
                $id = $prefix.$name;
                $value = old($name, $row?->{$name} ?? ($field['type'] === 'bool' ? ($row === null || $row->{$name}) : ''));
                if ($field['type'] === 'list') {
                    $value = is_array($value) ? implode("\n", $value) : (string) $value;
                }
                return compact('id', 'value');
            };
        @endphp

        <details class="card mb-3">
            <summary class="card-header fw-semibold" style="cursor:pointer">Add an entry</summary>
            <div class="card-body">
                <form method="POST" action="{{ route('ai-admin.store', $resource) }}" class="row g-2">
                    @csrf
                    @foreach ($config['fields'] as $name => $field)
                        @php(['id' => $id, 'value' => $value] = $renderField($name, $field, null, 'new-'))
                        @include('ai-admin._field', ['name' => $name, 'field' => $field, 'id' => $id, 'value' => $value])
                    @endforeach
                    <div class="col-12"><button class="btn btn-success btn-sm">Add</button></div>
                </form>
            </div>
        </details>

        <form method="GET" class="filter-panel rounded-3 p-2 mb-3 d-flex gap-2">
            <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search" style="max-width: 20rem">
            <button class="btn btn-outline-secondary btn-sm">Search</button>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach ($config['columns'] as $column)
                                <th>{{ ucwords(str_replace(['_', '.'], ' ', $column)) }}</th>
                            @endforeach
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    @php($cell = data_get($row, $column))
                                    <td>{{ \Illuminate\Support\Str::limit(is_array($cell) ? implode(', ', $cell) : (string) $cell, 60) }}</td>
                                @endforeach
                                <td>{!! $row->is_active ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' !!}</td>
                                <td class="text-nowrap">
                                    <form method="POST" action="{{ route('ai-admin.toggle', [$resource, $row->id]) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-outline-secondary btn-sm">{{ $row->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                    <a class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" href="#edit-{{ $row->id }}">Edit</a>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-{{ $row->id }}">
                                <td colspan="{{ count($config['columns']) + 2 }}">
                                    <form method="POST" action="{{ route('ai-admin.update', [$resource, $row->id]) }}" class="row g-2">
                                        @csrf
                                        @method('PUT')
                                        @foreach ($config['fields'] as $name => $field)
                                            @php(['id' => $id, 'value' => $value] = $renderField($name, $field, $row, 'row'.$row->id.'-'))
                                            @include('ai-admin._field', ['name' => $name, 'field' => $field, 'id' => $id, 'value' => $value])
                                        @endforeach
                                        <div class="col-12"><button class="btn btn-primary btn-sm">Save</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($config['columns']) + 2 }}" class="text-center text-muted py-4">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
