<x-app-layout>
    <x-slot:title>MAO Activity Log</x-slot:title>
    {{-- Panel revision 4.4 D (MaoActivityLogController). The Municipal Agriculturist sees every MAO user; others their own. --}}
    @php
        $actionTone = ['created' => 'is-green', 'edited' => 'is-blue', 'verified' => 'is-green', 'approved' => 'is-green', 'rejected' => 'is-red', 'deleted' => 'is-red', 'other' => 'is-gray'];
    @endphp
    <div class="mfd itd itp" data-mao-activity-log>
        <x-it.page-header kicker="Municipal Agriculture Office" title="MAO Activity Log"
            :lede="$isHead ? 'Everything MAO personnel created, edited, verified, approved or deleted.' : 'Everything you created, edited, verified, approved or deleted. The Municipal Agriculturist sees every MAO user\'s log.'" />

        @include('partials.manuscript-tabs', ['group' => 'mao-office', 'label' => 'MAO Office'])

        <form method="GET" class="mfd-card pr-filters" role="search" aria-label="Filter the activity log">
            @if ($isHead)
                <div>
                    <label class="form-label" for="alUser">MAO user</label>
                    <select class="form-select" id="alUser" name="user">
                        <option value="">All MAO users</option>
                        @foreach ($maoUsers as $maoUser)
                            <option value="{{ $maoUser->id }}" @selected((string) ($filters['user'] ?? '') === (string) $maoUser->id)>{{ $maoUser->name }}{{ $maoUser->position ? ' – '.$maoUser->position : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="form-label" for="alAction">Action</label>
                <select class="form-select" id="alAction" name="action">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ ucfirst($action) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="alBarangay">Barangay</label>
                <select class="form-select" id="alBarangay" name="barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay }}" @selected(($filters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="alFrom">From</label>
                <input class="form-control" id="alFrom" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div>
                <label class="form-label" for="alTo">To</label>
                <input class="form-control" id="alTo" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="pr-filter-actions">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('mao.activity-log.index') }}">Clear</a>
            </div>
        </form>

        <section class="mfd-card p-0" aria-label="Activity">
            @if ($entries->isEmpty())
                <p class="p-4 mb-0 text-muted">No MAO activity matches these filters yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 pr-log">
                        <thead>
                            <tr><th>Date and time</th><th>MAO user</th><th>Position</th><th>Action</th><th>Affected record</th><th>Barangay</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td data-label="Date and time"><span class="text-nowrap">{{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y, g:i A') }}</span></td>
                                    <td data-label="MAO user"><strong>{{ $entry->user_name }}</strong></td>
                                    <td data-label="Position">{{ $entry->user_position ?: '—' }}</td>
                                    <td data-label="Action"><span class="pr-badge {{ $actionTone[$entry->action] ?? 'is-gray' }}">{{ ucfirst($entry->action) }}</span><div class="small text-muted">{{ $entry->description }}</div></td>
                                    <td data-label="Affected record">{{ $entry->record_type ?: '—' }}@if ($entry->record_label)<div class="small">{{ $entry->record_label }}</div>@endif</td>
                                    <td data-label="Barangay">{{ $entry->barangay ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <div class="mt-3">{{ $entries->links() }}</div>
    </div>
</x-app-layout>
