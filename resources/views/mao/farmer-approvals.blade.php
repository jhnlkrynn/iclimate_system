<x-app-layout>
    <x-slot:title>Account Requests</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 1: FarmerApprovalController. Farmer sign-ups wait here as Pending until the
         MAO approves them (they can then log in) or rejects them with a reason. --}}
    <div class="mfd itd itp">
        <x-shared.page-header kicker="Municipal Agriculture Office" title="Account Requests" lede="New farmer sign-ups wait here. Approve a farmer so they can log in, or reject the sign-up with a reason." />

        @include('partials.manuscript-tabs', ['group' => 'approvals', 'label' => 'Approvals and reports'])

        <nav class="itp-subtabs" aria-label="Show sign-ups" data-live-region="approval-tabs" data-live-areas="users,farmers">
            @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                <a href="{{ route('mao.farmer-approvals.index', array_filter(['show' => $key, 'barangay' => $barangay, 'search' => $search])) }}" @class(['itp-subtab', 'is-active' => $show === $key]) @if ($show === $key) aria-current="page" @endif>
                    {{ $label }}@if ($key === 'pending')<span class="itp-subtab-count" data-pending-count>{{ number_format($pendingCount) }}</span>@endif
                </a>
            @endforeach
        </nav>

        <form class="itd-filters mb-3" method="GET" action="{{ route('mao.farmer-approvals.index') }}">
            <input type="hidden" name="show" value="{{ $show }}">
            <label>Search<input type="search" name="search" value="{{ $search }}" placeholder="Farmer name or email" maxlength="120"></label>
            <label>Barangay
                <select name="barangay" data-autosubmit>
                    <option value="">All</option>
                    @foreach ($barangays as $name)<option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Search</button>
        </form>

        {{-- data-live-region: a new sign-up appears here without reloading (resources/js/live-page.js). --}}
        <section class="itp-section" aria-label="{{ ['pending' => 'Waiting for approval', 'approved' => 'Approved sign-ups', 'rejected' => 'Rejected sign-ups'][$show] }}" data-live-region="approvals" data-live-areas="users,farmers">
            @if ($users->isEmpty())
                <div class="mfd-card p-4 text-muted" role="status">{{ $show === 'pending' ? 'No sign-ups are waiting.' : 'Nothing to show.' }}</div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-farmer-approvals>
                        <thead>
                            <tr><th>Farmer</th><th>Barangay &amp; Farm</th><th>Signed up</th><th>Review</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $farmer)
                                @php
                                    $contact = $farmer->contact_number ?: $farmer->farmerProfile?->contact_number;
                                    $reviewedAt = $farmer->reviewed_at?->timezone('Asia/Manila')->format('M j, Y H:i')
                                        ?: $farmer->approved_at?->timezone('Asia/Manila')->format('M j, Y H:i');
                                    $reviewer = $farmer->accountReviewer?->name ?: $farmer->approver?->name;
                                @endphp
                                <tr data-user-id="{{ $farmer->id }}">
                                    <td>
                                        <strong>{{ $farmer->name }}</strong>
                                        <div class="small text-muted">{{ $farmer->email }}@if ($contact) · {{ $contact }}@endif</div>
                                    </td>
                                    <td>
                                        <div>{{ $farmer->barangay ?: 'Barangay not set' }}</div>
                                        @if ($farmer->farmerProfile?->farm_type || $farmer->farmerProfile?->farm_area)
                                            <div class="small text-muted">{{ $farmer->farmerProfile?->farm_type ?: '—' }}@if ($farmer->farmerProfile?->farm_area) · {{ rtrim(rtrim(number_format((float) $farmer->farmerProfile->farm_area, 2), '0'), '.') }} ha @endif</div>
                                        @endif
                                    </td>
                                    <td class="itd-nowrap">{{ $farmer->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</td>
                                    <td>
                                        <div>{{ $farmer->applicationStatus() }}</div>
                                        @if ($reviewedAt || $reviewer)
                                            <div class="small text-muted">{{ $reviewedAt ?: '' }}@if ($reviewedAt && $reviewer) · @endif{{ $reviewer ?: '' }}</div>
                                        @endif
                                        @if ($farmer->status === \App\Models\User::STATUS_REJECTED && $farmer->rejection_reason)
                                            <div class="small text-danger">{{ $farmer->rejection_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm mb-2" href="{{ route('mao.farmer-approvals.show', $farmer) }}">View Details</a>
                                        @if ($show === 'rejected')
                                            <form method="POST" action="{{ route('mao.farmer-approvals.reconsider', $farmer) }}" class="text-start">
                                                @csrf
                                                <input type="hidden" name="revision" value="{{ (int) $farmer->registration_revision }}">
                                                <label for="reconsider{{ $farmer->id }}">Reason for reconsideration</label>
                                                <textarea id="reconsider{{ $farmer->id }}" name="resubmission_note" required minlength="10" maxlength="2000" class="form-control form-control-sm"></textarea>
                                                <button class="btn btn-outline-primary btn-sm mt-1">Return to Pending</button>
                                            </form>
                                        @endif
                                        @if ($show === 'pending')
                                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                <form method="POST" action="{{ route('mao.farmer-approvals.approve', $farmer) }}" data-loading="true">
                                                    @csrf
                                                    <input type="hidden" name="revision" value="{{ (int) $farmer->registration_revision }}">
                                                    <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Approving...">Approve</button>
                                                </form>
                                                @if ($show === 'pending')
                                                    <details>
                                                        <summary class="btn btn-outline-danger btn-sm">Reject</summary>
                                                        <form method="POST" action="{{ route('mao.farmer-approvals.reject', $farmer) }}" class="mt-2 text-start" data-loading="true">
                                                            @csrf
                                                    <input type="hidden" name="revision" value="{{ (int) $farmer->registration_revision }}">
                                                            {{-- A reason is required (config/user_rejection.php); the note is optional except for "Other". The farmer is emailed the reason in their language, with the note under it. --}}
                                                            <label class="form-label small" for="reason{{ $farmer->id }}">Reason</label>
                                                            <select class="form-select form-select-sm" id="reason{{ $farmer->id }}" name="reason" required data-reject-reason>
                                                                <option value="">Choose a reason</option>
                                                                @foreach (array_keys((array) config('user_rejection.reasons', [])) as $reasonKey)
                                                                    <option value="{{ $reasonKey }}">{{ __('user_rejection.reasons.'.$reasonKey) }}</option>
                                                                @endforeach
                                                            </select>
                                                            <label class="form-label small mt-2" for="note{{ $farmer->id }}">Note for the farmer (optional; required for "Other")</label>
                                                            <textarea class="form-control form-control-sm" id="note{{ $farmer->id }}" name="note" rows="2" maxlength="450"></textarea>
                                                            <button class="btn btn-danger btn-sm mt-2" type="submit" data-loading-text="Rejecting...">Reject sign-up</button>
                                                        </form>
                                                    </details>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
