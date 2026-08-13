<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Record Studio</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }}s</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Search, filter, and manage records from one responsive workspace.</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 align-self-start align-self-xl-end action-cluster">
                @if ($routeName === 'notifications')
                    <form method="POST" action="{{ route('notifications.mark-all-read') }}" data-loading="true">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit" data-loading-text="Updating...">Mark All Read</button>
                    </form>
                @endif
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route($routeName.'.create') }}">Create {{ $title }}</a>
                @endif
            </div>
        </div>
    </section>

    <div class="card filter-panel no-lift mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" data-loading="true">
                <div class="col-lg-{{ $filterable ? '4' : '8' }}">
                    <label class="form-label fw-semibold">Search</label>
                    <input class="form-control form-control-lg" name="search" value="{{ $search }}" placeholder="Search {{ strtolower($title) }} records">
                </div>
                @foreach ($filterable as $field => $options)
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-semibold">{{ \Illuminate\Support\Str::headline($field) }}</label>
                        <select class="form-select form-select-lg" name="{{ $field }}">
                            <option value="">All</option>
                            @foreach ($options as $value => $label)
                                @php $actual = is_int($value) ? $value : $value; @endphp
                                <option value="{{ $actual }}" @selected((string) request($field) === (string) $actual)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-lg-auto d-flex gap-2">
                    <button class="btn btn-outline-primary btn-lg" type="submit" data-loading-text="Filtering...">Apply</button>
                    <a class="btn btn-outline-secondary btn-lg" href="{{ route($routeName.'.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card no-lift">
        <div class="card-header border-0 d-flex flex-column flex-md-row justify-content-between gap-2 py-3">
            <div class="fw-semibold">Records</div>
            <div class="small text-muted">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</div>
        </div>
        @if ($records->count())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach ($columns as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                @foreach ($columns as $field => $label)
                                    <td>
                                        @if ($field === 'is_read')
                                            <span class="badge text-bg-{{ $record->is_read ? 'success' : 'warning' }}">{{ $record->is_read ? 'Read' : 'Unread' }}</span>
                                        @else
                                            {{ data_get($record, $field) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route($routeName.'.show', $record) }}">View</a>
                                    @if ($routeName === 'notifications' && ! $record->is_read)
                                        <form class="d-inline" method="POST" action="{{ route('notifications.mark-read', $record) }}" data-loading="true">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-success" type="submit" data-loading-text="Updating...">Mark Read</button>
                                        </form>
                                    @endif
                                    @if ($canManage)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                                        <form class="d-inline" method="POST" action="{{ route($routeName.'.destroy', $record) }}" data-loading="true" onsubmit="return confirm('Delete this record? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" data-loading-text="Deleting...">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top d-flex justify-content-center justify-content-md-end">
                {{ $records->links() }}
            </div>
        @else
            <div class="card-body">
                <div class="empty-state text-center p-5">
                    <div class="h5 mb-2">No records found</div>
                    <p class="text-muted mb-3">Try changing your search or filter settings.</p>
                    <a class="btn btn-outline-primary" href="{{ route($routeName.'.index') }}">Clear Filters</a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>