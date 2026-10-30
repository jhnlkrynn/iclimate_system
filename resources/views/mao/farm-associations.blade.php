<x-app-layout>
    <x-slot:title>Association Requests</x-slot:title>
    {{-- FarmAssociationReviewController: farms whose farmer said "my association isn't listed". Link the farm to an
         association of its barangay, add the association and link it, or reject the name with a note for the farmer. --}}
    <div class="mfd itd itp">
        <x-it.page-header kicker="Municipal Agriculture Office" title="Association Requests" lede="Farmers who could not find their association in the list. Link the farm to an association, add the association, or reject the request with a note." />

        @include('partials.manuscript-tabs', ['group' => 'farmers', 'label' => 'Farmers'])

        <form class="itd-filters mb-3" method="GET" action="{{ route('mao.farm-associations.index') }}">
            <label>Barangay
                <select name="barangay" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($barangays as $name)<option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
        </form>

        <section class="itp-section" aria-labelledby="faaTitle">
            <div class="itp-section-head">
                <h2 id="faaTitle">Waiting for review <span class="ic-tab-count" data-pending-count>{{ number_format($pendingCount) }}</span></h2>
                <p>Oldest first. Only associations in the farm's barangay can be linked.</p>
            </div>

            @if ($farms->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No association requests are waiting.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-farm-associations>
                        <thead>
                            <tr><th>Farmer</th><th>Farm</th><th>Association named</th><th>Sent</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($farms as $farm)
                                @php
                                    $choices = \App\Services\Farms\FarmAssociations::optionsFor($farm->barangay, null, $associations);
                                @endphp
                                <tr data-farm-id="{{ $farm->id }}">
                                    <td><strong>{{ $farm->user?->name ?? '—' }}</strong><div class="small text-muted">{{ $farm->user?->contact_number ?: $farm->user?->email }}</div></td>
                                    <td>{{ $farm->farm_name }}<div class="small text-muted">{{ implode(' · ', array_filter([$farm->barangay, $farm->farm_type])) }}</div></td>
                                    <td><strong>{{ $farm->association_other_name }}</strong></td>
                                    <td>{{ $farm->updated_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            @if ($choices->isNotEmpty())
                                                <details>
                                                    <summary class="btn btn-primary btn-sm">Link</summary>
                                                    <form method="POST" action="{{ route('mao.farm-associations.link', $farm) }}" class="mt-2 text-start" data-loading="true">
                                                        @csrf
                                                        <label class="form-label small" for="org{{ $farm->id }}">Association in {{ $farm->barangay }}</label>
                                                        <select class="form-select form-select-sm" id="org{{ $farm->id }}" name="farmer_organization_id" required>
                                                            @foreach ($choices as $choice)<option value="{{ $choice['id'] }}">{{ $choice['name'] }}</option>@endforeach
                                                        </select>
                                                        <button class="btn btn-primary btn-sm mt-2" type="submit" data-loading-text="Linking...">Link farm</button>
                                                    </form>
                                                </details>
                                            @endif
                                            <details>
                                                <summary class="btn btn-outline-primary btn-sm">Add association</summary>
                                                <form method="POST" action="{{ route('mao.farm-associations.store', $farm) }}" class="mt-2 text-start" data-loading="true">
                                                    @csrf
                                                    <label class="form-label small" for="name{{ $farm->id }}">Name</label>
                                                    <input class="form-control form-control-sm" id="name{{ $farm->id }}" name="name" maxlength="255" value="{{ $farm->association_other_name }}" required>
                                                    <label class="form-label small mt-2" for="brgy{{ $farm->id }}">Barangay</label>
                                                    <select class="form-select form-select-sm" id="brgy{{ $farm->id }}" name="barangay" required>
                                                        <option value="{{ \App\Support\LianBarangays::canonicalName($farm->barangay) }}">{{ $farm->barangay }}</option>
                                                    </select>
                                                    <label class="form-label small mt-2" for="type{{ $farm->id }}">Farm type</label>
                                                    <select class="form-select form-select-sm" id="type{{ $farm->id }}" name="farm_type" required>
                                                        @foreach (['Irrigated', 'Rainfed'] as $type)<option value="{{ $type }}" @selected(($farm->farm_type ?: 'Irrigated') === $type)>{{ $type }}</option>@endforeach
                                                    </select>
                                                    <button class="btn btn-primary btn-sm mt-2" type="submit" data-loading-text="Saving...">Add and link</button>
                                                </form>
                                            </details>
                                            <details>
                                                <summary class="btn btn-outline-danger btn-sm">Reject</summary>
                                                <form method="POST" action="{{ route('mao.farm-associations.reject', $farm) }}" class="mt-2 text-start" data-loading="true">
                                                    @csrf
                                                    <label class="form-label small" for="note{{ $farm->id }}">Note for the farmer (shown on their farm)</label>
                                                    <textarea class="form-control form-control-sm" id="note{{ $farm->id }}" name="note" rows="2" maxlength="500" required></textarea>
                                                    <button class="btn btn-danger btn-sm mt-2" type="submit" data-loading-text="Rejecting...">Reject request</button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $farms->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
