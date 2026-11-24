<x-app-layout>
    <x-slot:title>Farm Verification</x-slot:title>
    <div class="mfd itd itp" data-mao-farm-verification>
        <x-shared.page-header
            kicker="Municipal Agriculture Office"
            title="Farm Verification"
            :lede="$isFiltered
                ? 'Verify farmer-submitted farms in your assigned barangays before they count toward yields and dashboards.'
                : 'Verify farmer-submitted farms across Lian before they count toward yields and dashboards.'" />

        <div class="ic-tabs mb-3" role="tablist" aria-label="Verification status">
            <a href="{{ route('mao.farm-verification.index', ['show' => 'pending', 'barangay' => $barangay, 'search' => $search]) }}"
                @class(['ic-tab', 'is-active' => $show === 'pending'])>
                Pending @if ($pendingCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>@endif
            </a>
            <a href="{{ route('mao.farm-verification.index', ['show' => 'approved', 'barangay' => $barangay, 'search' => $search]) }}"
                @class(['ic-tab', 'is-active' => $show === 'approved'])>Approved</a>
            <a href="{{ route('mao.farm-verification.index', ['show' => 'rejected', 'barangay' => $barangay, 'search' => $search]) }}"
                @class(['ic-tab', 'is-active' => $show === 'rejected'])>Rejected</a>
        </div>

        <form method="GET" class="mfd-card pr-filters mb-3" role="search" aria-label="Filter farm verification queue">
            <input type="hidden" name="show" value="{{ $show }}">
            <div>
                <label class="form-label" for="fvSearch">Farmer or farm name</label>
                <input class="form-control" id="fvSearch" type="search" name="search" value="{{ $search }}" placeholder="Search...">
            </div>
            <div>
                <label class="form-label" for="fvBarangay">Barangay</label>
                <select class="form-select" id="fvBarangay" name="barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $b)
                        <option value="{{ $b }}" @selected($barangay === $b)>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pr-filter-actions">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('mao.farm-verification.index', ['show' => $show]) }}">Clear</a>
            </div>
        </form>

        <section class="mfd-card p-0" aria-label="Farm verification list">
            @if ($farms->isEmpty())
                <div class="p-4 text-muted">
                    No {{ $show }} farms found{{ $isFiltered ? ' in your assigned barangays' : '' }}.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Farmer &amp; Farm</th>
                                <th>Barangay &amp; Location</th>
                                <th>Details</th>
                                <th>Association</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($farms as $farm)
                                @php
                                    $farmer = $farm->user;
                                    $size = \App\Support\FarmSize::describe($farm);
                                    $otherCount = max(0, (int) ($farmCountsByUser[$farm->user_id] ?? 1) - 1);
                                    $isNewAccount = $farmer && ($farmer->status === \App\Models\User::STATUS_PENDING || $farmer->created_at?->gt(now()->subDays(7)));
                                    $coords = $farm->boundary?->boundary_coordinates;
                                    $firstPt = is_array($coords) && isset($coords[0]['lat'], $coords[0]['lng']) ? $coords[0] : null;
                                    $lat = $farm->latitude ?? ($firstPt['lat'] ?? null);
                                    $lng = $farm->longitude ?? ($firstPt['lng'] ?? null);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $farm->farm_name }}</div>
                                        <div class="small text-muted">
                                            {{ $farmer?->name ?? 'Unknown' }}
                                            @if ($isNewAccount)
                                                <span class="badge bg-info text-dark ms-1">New account</span>
                                            @endif
                                        </div>
                                        @if ($otherCount > 0)
                                            <div class="small text-muted">Other farms: {{ $otherCount }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $farm->barangay ?: '—' }}</div>
                                        @if ($lat !== null && $lng !== null)
                                            <div class="small text-muted">
                                                {{ number_format((float) $lat, 5) }}, {{ number_format((float) $lng, 5) }}
                                                · <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=16/{{ $lat }}/{{ $lng }}" target="_blank" rel="noopener">View on map</a>
                                            </div>
                                        @elseif ($farm->location_description)
                                            <div class="small text-muted">{{ $farm->location_description }}</div>
                                        @else
                                            <div class="small text-muted">No map pin</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $size['text'] }}</div>
                                        <div class="small text-muted">{{ $farm->farm_type ?: 'Not set' }}</div>
                                    </td>
                                    <td>
                                        @if ($farm->associationState() === \App\Models\Farm::ASSOCIATION_MEMBER && $farm->organization)
                                            {{ $farm->organization->name }}
                                        @elseif ($farm->association_status === \App\Models\Farm::ASSOCIATION_NOT_MEMBER)
                                            Not a member
                                        @else
                                            <span class="text-muted">Not declared</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($farm->isApproved())
                                            <span class="badge bg-success">Approved</span>
                                        @elseif ($farm->isRejected())
                                            <span class="badge bg-danger">Rejected</span>
                                            @if ($farm->rejection_reason)
                                                <div class="small text-danger mt-1">{{ $farm->rejection_reason }}</div>
                                            @endif
                                        @else
                                            <span class="badge bg-warning text-dark">Pending MAO review</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($farm->isPendingVerification() || $farm->isRejected())
                                            <form method="POST" action="{{ route('mao.farm-verification.approve', $farm) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                        @endif
                                        @if ($farm->isPendingVerification() || $farm->isApproved())
                                            <form method="POST" action="{{ route('mao.farm-verification.reject', $farm) }}" class="mt-2 d-flex gap-1">
                                                @csrf
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Rejection reason (required)" minlength="3" maxlength="500" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($farms->hasPages())
                    <div class="p-3 border-top">
                        {{ $farms->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
