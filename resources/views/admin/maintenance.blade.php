<x-app-layout>
    <x-slot:title>Maintenance Records</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: MaintenanceRecordController. --}}
    @php $tz = \App\Support\BusinessClock::timezone(); @endphp
    <div class="mfd itd itp" data-maintenance-records>
        <x-it.page-header kicker="IT Personnel" title="Maintenance Records" lede="Log system updates, fixes, backups and other maintenance; the history below is the record." />
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        <details class="ic-collapse mb-3" @if ($errors->any()) open @endif>
            <summary><span class="ic-collapse-title">+ Add maintenance record</span></summary>
            <form class="mfd-card" method="POST" action="{{ route('admin.maintenance.store') }}" data-loading="true">
                @csrf
                @include('admin._maintenance-fields', ['record' => null, 'prefix' => 'new'])
                <button class="btn btn-primary mt-2" type="submit" data-loading-text="Saving...">Add</button>
            </form>
        </details>

        <form class="itd-filters mb-3" method="GET" action="{{ route('admin.maintenance.index') }}">
            <label>Type
                <select name="category" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach
                </select>
            </label>
        </form>

        <section class="itp-section" aria-labelledby="mrHistory">
            <div class="itp-section-head"><h2 id="mrHistory">Maintenance history</h2><p>Newest first.</p></div>
            @if ($records->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No maintenance recorded yet.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>When</th><th>Work</th><th>Type</th><th>Status</th><th>By</th><th class="text-end"></th></tr></thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td class="small text-nowrap">{{ $record->performed_at?->copy()->timezone($tz)->format('M j, Y g:i A') }}</td>
                                    <td><strong>{{ $record->title }}</strong>@if ($record->description)<div class="small text-muted">{{ $record->description }}</div>@endif</td>
                                    <td class="small">{{ $categories[$record->category] ?? $record->category }}</td>
                                    <td><span @class(['badge', 'text-bg-success' => $record->status === 'done', 'text-bg-warning' => $record->status === 'in_progress', 'text-bg-light' => $record->status === 'planned'])>{{ $statuses[$record->status] ?? $record->status }}</span></td>
                                    <td class="small">{{ $record->performer?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <details>
                                            <summary class="btn btn-outline-primary btn-sm">Edit</summary>
                                            <form class="text-start mt-2" method="POST" action="{{ route('admin.maintenance.update', $record) }}" data-loading="true" style="min-width: 280px;">
                                                @csrf @method('PUT')
                                                @include('admin._maintenance-fields', ['record' => $record, 'prefix' => 'e'.$record->id])
                                                <button class="btn btn-primary btn-sm mt-2" type="submit">Save</button>
                                            </form>
                                            <form class="mt-2" method="POST" action="{{ route('admin.maintenance.destroy', $record) }}" data-loading="true" data-ic-confirm="Delete this maintenance record?">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $records->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
