<x-app-layout>
<div class="container py-4">
    <h1>Account Request</h1>
    <h2 class="h4">{{ $farmer->name }}</h2>
    <dl class="row">
        <dt class="col-sm-3">Application status</dt><dd class="col-sm-9">{{ $farmer->applicationStatus() }}</dd>
        <dt class="col-sm-3">Barangay</dt><dd class="col-sm-9">{{ $farmer->barangay ?: 'Not supplied' }}</dd>
        <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $farmer->email }}</dd>
        <dt class="col-sm-3">Contact number</dt><dd class="col-sm-9">{{ $farmer->contact_number ?: $farmer->farmerProfile?->contact_number ?: 'Not supplied' }}</dd>
        <dt class="col-sm-3">Registered</dt><dd class="col-sm-9">{{ $farmer->created_at?->timezone('Asia/Manila')->format('M d, Y H:i') }}</dd>
        <dt class="col-sm-3">Reviewed</dt><dd class="col-sm-9">{{ $farmer->reviewed_at?->timezone('Asia/Manila')->format('M d, Y H:i') ?: $farmer->approved_at?->timezone('Asia/Manila')->format('M d, Y H:i') ?: ($farmer->status === \App\Models\User::STATUS_PENDING ? 'Awaiting review' : 'Not recorded') }} {{ $farmer->accountReviewer?->name ?: $farmer->approver?->name }}</dd>
    </dl>
    @if ($farmer->resubmission_note)<p><strong>Submitted corrections</strong> {{ $farmer->resubmission_note }}</p>@endif
    @if ($farmer->rejection_reason)<p><strong>Previous rejection reason</strong> {{ $farmer->rejection_reason }}</p>@endif
    @if ($farmer->hasApplicationAccess())
        <section class="mt-4" data-account-recovery>
            <h2 class="h5">Account recovery</h2>
            @if (session('recovery_code'))
                <p class="alert alert-warning" role="status">Recovery code: <strong data-recovery-code>{{ session('recovery_code') }}</strong>. It is shown only now.</p>
            @endif
            <p class="small mb-2">Use this when the farmer cannot sign in or their password is not safe. First check that it is really them. The farmer enters the code at <strong>{{ route('account.recover') }}</strong>.</p>
            <form method="POST" action="{{ route('mao.farmer-approvals.recovery-code', $farmer) }}">
                @csrf
                <label for="verificationMethod" class="form-label">How you checked who they are</label>
                <select id="verificationMethod" name="verification_method" class="form-select mb-2" required>
                    @foreach (\App\Services\Accounts\AccountRecovery::VERIFICATION_METHODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <label for="recoveryNote" class="form-label">Note (optional)</label>
                <input id="recoveryNote" name="note" maxlength="300" class="form-control mb-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="identityConfirmed" name="identity_confirmed" value="1" required>
                    <label class="form-check-label" for="identityConfirmed">I checked that this is the account owner.</label>
                </div>
                <x-input-error :messages="$errors->get('verification_method')" />
                <x-input-error :messages="$errors->get('identity_confirmed')" />
                <button class="btn btn-warning">Give a one-time recovery code</button>
            </form>
        </section>
    @endif
    <h2 class="h5 mt-4">Review history</h2>
    @forelse ($farmer->accountReviews as $review)
        <article class="border rounded p-3 mb-2">
            <strong>{{ $review->decision }}</strong> · {{ $review->reviewer?->name }} · {{ $review->created_at->timezone('Asia/Manila')->format('M d, Y H:i') }}
            @if ($review->reason)<p class="mb-0">{{ $review->reason }}</p>@endif
            @if (data_get($review->application, 'resubmission_note'))<p class="mb-0">{{ data_get($review->application, 'resubmission_note') }}</p>@endif
        </article>
    @empty
        <p>Earlier decisions retain their existing account and audit records.</p>
    @endforelse
    <a class="btn btn-outline-primary" href="{{ route('mao.farmer-approvals.index', ['show' => strtolower($farmer->applicationStatus()) === 'approved' ? 'approved' : strtolower($farmer->status)]) }}">Back to Account Requests</a>
</div>
</x-app-layout>
