{{-- IT, Users list: a row's Edit and Delete (Delete opens the Delete account dialog, _delete-modal), or Restore for a
     deleted account still within config user_deletion.restore_days. $user: the row. --}}
@if ($user->isDeleted())
    @if ($user->canBeRestored())
        <form class="d-inline" method="POST" action="{{ route('users.restore', $user->id) }}" data-loading="true"
              data-ic-confirm="{{ __('user_deletion.modal.restore_confirm', ['name' => $user->name]) }}">
            @csrf
            <button class="btn btn-sm btn-outline-success" type="submit" data-user-restore="{{ $user->id }}" data-loading-text="Restoring...">{{ __('user_deletion.modal.restore') }}</button>
        </form>
    @else
        <span class="small text-muted" data-user-restore-closed>{{ __('user_deletion.modal.restore_closed') }}</span>
    @endif
@else
    @php
        $deleteData = [
            'action' => route('users.destroy', array_filter(['user' => $user->id, 'status' => request('status')])),
            'name' => $user->name,
            'role' => \App\Models\User::labelForRole((string) $user->role),
            'barangay' => $user->barangay ?: 'Not set',
            'email' => $user->email ?: 'Not set',
            'recipient' => \App\Services\Accounts\AccountEmails::recipient($user),
            'language' => $user->mailLocale(),
            'maoHead' => (bool) $user->is_mao_head && $user->role === \App\Models\User::ROLE_MAO,
        ];
    @endphp
    <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user->id) }}">Edit</a>
    <button type="button" class="btn btn-sm btn-outline-danger" data-user-delete="{{ json_encode($deleteData) }}" aria-haspopup="dialog">Delete</button>
@endif
