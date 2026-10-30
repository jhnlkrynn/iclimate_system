<x-app-layout>
    <x-slot:title>Farmer Approvals</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 1: FarmerApprovalController. Farmer sign-ups wait here as Pending until the
         MAO approves them (they can then log in) or rejects them with a reason. --}}
    <div class="mfd itd itp">
        <x-it.page-header kicker="Municipal Agriculture Office" title="Farmer Approvals" lede="New farmer sign-ups wait here. Approve a farmer so they can log in, or reject the sign-up with a reason." />

        @include('partials.manuscript-tabs', ['group' => 'farmers', 'label' => 'Farmers'])

        <nav class="ic-tabs mb-3" aria-label="Show sign-ups">
            @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                <a href="{{ route('mao.farmer-approvals.index', array_filter(['show' => $key, 'barangay' => $barangay])) }}" @class(['ic-tab', 'is-active' => $show === $key]) @if ($show === $key) aria-current="page" @endif>
                    {{ $label }}@if ($key === 'pending')<span class="ic-tab-count" data-pending-count>{{ number_format($pendingCount) }}</span>@endif
                </a>
            @endforeach
        </nav>

        <form class="itd-filters mb-3" method="GET" action="{{ route('mao.farmer-approvals.index') }}">
            <input type="hidden" name="show" value="{{ $show }}">
            <label>Barangay
                <select name="barangay" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($barangays as $name)<option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
        </form>

        <section class="itp-section" aria-labelledby="faTitle">
            <div class="itp-section-head">
                <h2 id="faTitle">{{ ['pending' => 'Waiting for approval', 'approved' => 'Approved sign-ups', 'rejected' => 'Rejected sign-ups'][$show] }}</h2>
                <p>{{ $show === 'pending' ? 'Oldest first. Check the name and barangay against the RSBSA list before approving.' : 'Newest first.' }}</p>
            </div>

            @if ($users->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">{{ $show === 'pending' ? 'No sign-ups are waiting.' : 'Nothing to show.' }}</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-farmer-approvals>
                        <thead>
                            <tr><th>Name</th><th>Contact</th><th>Barangay</th><th>Farm</th><th>Signed up</th><th>{{ $show === 'rejected' ? 'Reason' : ($show === 'approved' ? 'Approved' : '') }}</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $farmer)
                                <tr data-user-id="{{ $farmer->id }}">
                                    <td><strong>{{ $farmer->name }}</strong><div class="small text-muted">{{ $farmer->email }}</div></td>
                                    <td>{{ $farmer->contact_number ?: ($farmer->farmerProfile?->contact_number ?: '—') }}</td>
                                    <td>{{ $farmer->barangay ?: '—' }}</td>
                                    <td>{{ $farmer->farmerProfile?->farm_type ?: '—' }}@if ($farmer->farmerProfile?->farm_area) · {{ rtrim(rtrim(number_format((float) $farmer->farmerProfile->farm_area, 2), '0'), '.') }} ha @endif</td>
                                    <td>{{ $farmer->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</td>
                                    <td class="small">
                                        @if ($show === 'rejected'){{ $farmer->rejection_reason }}@elseif ($show === 'approved'){{ $farmer->approved_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}@endif
                                        {{-- Panel revision 4.4 C: who decided, and when. --}}
                                        @if ($show !== 'pending' && $farmer->approved_by && ($decider = \App\Models\User::query()->find($farmer->approved_by, ['id', 'name'])))
                                            <div class="small text-muted">{{ $show === 'rejected' ? 'Rejected' : 'Approved' }} by: {{ $decider->name }}{{ $farmer->approved_at ? ' on '.\App\Support\MaoStamp::format($farmer->approved_at) : '' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($show !== 'approved')
                                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                <form method="POST" action="{{ route('mao.farmer-approvals.approve', $farmer) }}" data-loading="true">
                                                    @csrf
                                                    <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Approving...">Approve</button>
                                                </form>
                                                @if ($show === 'pending')
                                                    <details>
                                                        <summary class="btn btn-outline-danger btn-sm">Reject</summary>
                                                        <form method="POST" action="{{ route('mao.farmer-approvals.reject', $farmer) }}" class="mt-2 text-start" data-loading="true">
                                                            @csrf
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
