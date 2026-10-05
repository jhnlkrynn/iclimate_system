<x-app-layout>
    @if ($routeName === 'farmer-profiles')
        <style>
            .farmer-org-chart-shell { position: relative; overflow-x: auto; overflow-y: hidden; padding: .25rem; }
            .farmer-org-chart-inner { min-width: 320px; width: 100%; height: 380px; }
            .farmer-org-chart-inner canvas { width: 100% !important; height: 100% !important; }
            .farmer-org-chart-state { color: #64748B; font-size: .85rem; font-weight: 600; }
            .farmer-org-legend { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .4rem .5rem; margin-top: .65rem; padding-top: .6rem; border-top: 1px solid rgba(95,143,120,.2); max-height: 180px; overflow-y: auto; }
            .farmer-org-legend-item { appearance: none; width: 100%; display: grid; grid-template-columns: 12px minmax(0, 1fr) auto; align-items: center; gap: .45rem; min-width: 0; padding: .35rem .5rem; border: 1px solid rgba(0,0,0,.08); border-radius: 6px; background: #fff; color: #1F2937; font-family: 'Inter', system-ui, sans-serif; font-size: .78rem; line-height: 1.3; text-align: left; cursor: pointer; transition: background .15s, border-color .15s; }
            .farmer-org-legend-item:hover { border-color: rgba(31,90,70,.4); background: rgba(247,251,248,.9); }
            .farmer-org-legend-item:focus-visible { outline: 2px solid rgba(31,90,70,.28); outline-offset: 2px; border-color: #1F5A46; }
            .farmer-org-legend-swatch { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(13,31,24,.2); flex-shrink: 0; }
            .farmer-org-legend-name { min-width: 0; overflow-wrap: anywhere; line-height: 1.3; }
            .farmer-org-legend-count { color: #1F2937; font-size: .8rem; font-weight: 800; white-space: nowrap; }
            .farmer-org-chart-status { margin-top: .5rem; color: #1F2937; font-size: .82rem; font-weight: 600; }
            @media (max-width: 575.98px) { .farmer-org-legend { grid-template-columns: 1fr; } }
        </style>
    @endif

    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Records</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }}s</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Find, view, and update records here.</p>
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

    @if ($routeName === 'farmer-profiles' && isset($farmerOrganizationChart))
        <details class="ic-collapse mb-4" open>
            <summary>
                <span class="ic-collapse-title">
                    Farmers by Organization
                    <span class="ic-collapse-count">{{ number_format((int) ($farmerOrganizationSummary['totalFarmers'] ?? 0)) }}</span>
                </span>
            </summary>
            <div class="card no-lift">
                <div class="card-body p-3">
                    <p class="text-muted small mb-3">Registered farmers grouped by their Farmer's Association.</p>
                    <div class="farmer-org-chart-shell">
                        <div class="farmer-org-chart-inner">
                            <div class="farmer-org-chart-state" data-organization-chart-state role="status">Loading farmer groups...</div>
                            <canvas id="farmersByOrganizationChartCompact" aria-label="Farmers by organization bar chart. Use the legend below for full organization names and counts." aria-busy="true" role="img" tabindex="0"></canvas>
                        </div>
                    </div>
                    <div id="farmersByOrganizationLegendCompact" class="farmer-org-legend" role="list" aria-label="Farmers by organization legend"></div>
                    <div id="farmersByOrganizationStatusCompact" class="farmer-org-chart-status" aria-live="polite">Loading farmer groups...</div>
                </div>
            </div>
        </details>
        @include('dashboards.partials.farmer-organization-chart', [
            'chartData' => $farmerOrganizationChart,
            'canvasId' => 'farmersByOrganizationChartCompact',
            'legendId' => 'farmersByOrganizationLegendCompact',
            'statusId' => 'farmersByOrganizationStatusCompact',
            'compact' => true,
        ])
    @endif

    @php
        $activeFilterCount = (($search ?? '') !== '' ? 1 : 0)
            + collect(array_keys($filterable))->filter(fn ($field) => request()->filled($field))->count();
    @endphp
    <details class="ic-collapse" @if($activeFilterCount) data-force-open="true" @endif>
        <summary>
            <span class="ic-collapse-title">
                Filters
                @if($activeFilterCount)
                    <span class="ic-collapse-count">{{ $activeFilterCount }}</span>
                @endif
            </span>
        </summary>
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
                                    <option value="{{ $actual }}" @selected(is_string(request($field)) && (string) request($field) === (string) $actual)>{{ $label }}</option>
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
    </details>

    <div class="card no-lift">
        <div class="card-header border-0 d-flex flex-column flex-md-row justify-content-between gap-2 py-3">
            <div class="fw-semibold">Records</div>
            <div class="small text-muted">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</div>
        </div>
        @if ($records->count())
            <div class="table-responsive ic-table-hide-mobile">
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
                                        @elseif ($field === 'farm_type' && data_get($record, $field))
                                            <span class="badge text-bg-{{ data_get($record, $field) === 'Irrigated' ? 'info' : 'secondary' }}">{{ data_get($record, $field) }}</span>
                                        @else
                                            {{ filled(data_get($record, $field)) ? data_get($record, $field) : 'Not set' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route($routeName.'.show', $record) }}" data-ic-view data-ic-size="normal" @if ($routeName === 'notifications') data-ic-notif-item @endif aria-haspopup="dialog">View</a>
                                    @if ($routeName === 'notifications' && ! $record->is_read)
                                        <form class="d-inline" method="POST" action="{{ route('notifications.mark-read', $record) }}" data-loading="true">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-success" type="submit" data-loading-text="Updating...">Mark Read</button>
                                        </form>
                                    @endif
                                    @if ($canManage)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                                        <form class="d-inline" method="POST" action="{{ route($routeName.'.destroy', $record) }}" data-loading="true" data-ic-confirm="Delete this record? This action cannot be undone.">
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
            <div class="card-body ic-table-cards">
                @foreach ($records as $record)
                    <div class="ic-table-card">
                        @foreach ($columns as $field => $label)
                            <div class="ic-table-card-row">
                                <div class="ic-table-card-label">{{ $label }}</div>
                <div class="ic-table-card-value">
                                    @if ($field === 'is_read')
                                        <span class="badge text-bg-{{ $record->is_read ? 'success' : 'warning' }}">{{ $record->is_read ? 'Read' : 'Unread' }}</span>
                                    @elseif ($field === 'farm_type' && data_get($record, $field))
                                        <span class="badge text-bg-{{ data_get($record, $field) === 'Irrigated' ? 'info' : 'secondary' }}">{{ data_get($record, $field) }}</span>
                                    @else
                                        {{ filled(data_get($record, $field)) ? data_get($record, $field) : 'Not set' }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <div class="ic-table-card-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route($routeName.'.show', $record) }}" data-ic-view data-ic-size="normal" @if ($routeName === 'notifications') data-ic-notif-item @endif aria-haspopup="dialog">View</a>
                            @if ($routeName === 'notifications' && ! $record->is_read)
                                <form method="POST" action="{{ route('notifications.mark-read', $record) }}" data-loading="true">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-success" type="submit" data-loading-text="Updating...">Mark Read</button>
                                </form>
                            @endif
                            @if ($canManage)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                                <form method="POST" action="{{ route($routeName.'.destroy', $record) }}" data-loading="true" data-ic-confirm="Delete this record? This action cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-loading-text="Deleting...">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-body border-top d-flex justify-content-center justify-content-md-end">
                {{ $records->links() }}
            </div>
        @else
            <div class="card-body">
                <div class="empty-state text-center p-5">
                    <div class="h5 mb-2">No records found</div>
                    <p class="text-muted mb-3">Try a different search or filter.</p>
                    <a class="btn btn-outline-primary" href="{{ route($routeName.'.index') }}">Clear Filters</a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>