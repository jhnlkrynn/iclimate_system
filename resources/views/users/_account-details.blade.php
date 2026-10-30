{{-- IT, a user's detail page: how the account was closed (when deleted) and the account's last email. IT-only page,
     so the internal note and who deleted it are shown here; the person never sees either. $user --}}
@php
    $lastEmail = \App\Services\Accounts\AccountEmails::last($user);
    $at = fn ($date) => $date?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y g:i A');
@endphp
<div class="card no-lift mt-4" data-user-account>
    <div class="card-header border-0"><div class="fw-semibold">Account</div></div>
    <div class="card-body">
        <dl class="row mb-0 details-list g-3">
            @if ($user->isDeleted())
                @php $deletedBy = $user->deleted_by ? \App\Models\User::withDeleted()->find($user->deleted_by, ['id', 'name']) : null; @endphp
                <dt class="col-sm-3">Deleted</dt>
                <dd class="col-sm-9" data-user-deleted>{{ $at($user->deleted_at) ?? 'Not set' }}{{ $deletedBy ? ' by '.$deletedBy->name : '' }}</dd>
                <dt class="col-sm-3">Reason</dt>
                <dd class="col-sm-9">{{ \App\Services\Accounts\AccountDeletion::reasonLabel($user->deleted_reason, 'en') }}@if (filled($user->deleted_reason_other)): {{ $user->deleted_reason_other }}@endif</dd>
                @if (filled($user->deleted_note_internal))
                    <dt class="col-sm-3">Internal note <span class="small text-muted d-block">Only IT can see this</span></dt>
                    <dd class="col-sm-9" data-user-internal-note>{{ $user->deleted_note_internal }}</dd>
                @endif
                <dt class="col-sm-3">Restore</dt>
                <dd class="col-sm-9">
                    @if ($user->canBeRestored())
                        Possible until {{ $user->deleted_at->copy()->addDays((int) config('user_deletion.restore_days', 30))->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}
                        <form class="d-inline ms-2" method="POST" action="{{ route('users.restore', $user->id) }}" data-loading="true"
                              data-ic-confirm="{{ __('user_deletion.modal.restore_confirm', ['name' => $user->name]) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-success" type="submit" data-loading-text="Restoring...">{{ __('user_deletion.modal.restore') }}</button>
                        </form>
                    @else
                        {{ __('user_deletion.modal.restore_closed') }} (record only)
                    @endif
                </dd>
            @endif
            <dt class="col-sm-3">Last email</dt>
            <dd class="col-sm-9" data-last-email>
                @if ($lastEmail)
                    {{ ucfirst($lastEmail->type === 'rejected' ? 'not approved' : $lastEmail->type) }} · {{ $lastEmail->recipient ?: 'no address' }} · {{ $lastEmail->statusLabel() }} · {{ $at($lastEmail->sent_at ?? $lastEmail->created_at) }}
                @else
                    None yet
                @endif
            </dd>
        </dl>
    </div>
</div>
