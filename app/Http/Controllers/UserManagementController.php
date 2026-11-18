<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Accounts\AccountDeletion;
use App\Support\LianBarangays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends CrudController
{
    protected string $model = User::class;

    protected string $routeName = 'users';

    protected string $title = 'User';

    // Manuscript – Naicel: Last Login (users.last_login_at, set at each sign-in).
    // status_display: the status, or "Deceased" for an account deleted for that reason (User::getStatusDisplayAttribute).
    protected array $columns = ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'barangay' => 'Barangay', 'status_display' => 'Status', 'last_login_at' => 'Last Login'];

    protected array $searchable = ['name', 'email', 'role', 'contact_number', 'address', 'barangay', 'status'];

    protected array $filterable = ['role' => [User::ROLE_FARMER => 'Farmer', User::ROLE_MAO => 'MAO Staff'], 'status' => [...User::STATUSES, User::STATUS_DELETED]];   // Manuscript – Naicel; Deleted: closed accounts (hidden otherwise)

    protected array $manageableRoles = [User::ROLE_MAO];

    protected array $sortOptions = ['name_asc' => 'Name A–Z', 'name_desc' => 'Name Z–A', 'newest' => 'Newest', 'oldest' => 'Oldest'];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'name', 'label' => 'Name'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => [User::ROLE_FARMER => 'Farmer', User::ROLE_MAO => 'MAO Staff']],
            ['name' => 'is_mao_head', 'label' => 'Municipal Agriculturist (MAO Head)', 'type' => 'checkbox'],
            ['name' => 'contact_number', 'label' => 'Contact Number'],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::userManagementOptions()],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_combine(User::STATUSES, User::STATUSES)],   // Manuscript – Naicel
        ];
    }

    protected function authorizeView(Request $request): void
    {
        if (! $request->user()->isMaoHead()) {
            abort(403);
        }
    }

    protected function canManage(Request $request): bool
    {
        return $request->user()?->isMaoHead() === true;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        $rules = $this->userRules($id);
        $rules['barangay'] = ['nullable', 'string', 'max:255', Rule::in(LianBarangays::userManagementInputValues())];
        $rules['is_mao_head'] = ['nullable', 'boolean'];
        if ($request->input('role') === User::ROLE_MAO) {
            $rules['status'] = ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])];
        }

        return $rules;
    }

    protected function prepareData(Request $request, array $data): array
    {
        if (array_key_exists('barangay', $data) && $data['barangay'] !== null && $data['barangay'] !== '') {
            $data['barangay'] = LianBarangays::canonicalName($data['barangay']) ?? $data['barangay'];
        }

        $data = $this->hashPasswordWhenPresent($data);
        if (($data['role'] ?? null) !== User::ROLE_MAO) $data['is_mao_head'] = false;
        return $data;
    }

    /**
     * The system must always keep a working administrator: IT cannot delete, deactivate or demote their own
     * account, and the last active MAO Head account cannot be deleted, deactivated or demoted by anyone.
     */
    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request);
        $target = $this->findRecord($request, $id);
        if ($target->isDeleted()) {
            return redirect()->route('users.show', $id)->with('error', 'This account is deleted. Restore it before editing.');
        }
        $losesAccess = $request->input('role', $target->role) !== User::ROLE_MAO || ! $request->boolean('is_mao_head')
            || $request->input('status', $target->status) !== User::STATUS_ACTIVE;

        if ($losesAccess && ($message = $this->protectedAccountMessage($request, $target, 'change the role or status of'))) {
            return back()->withInput($request->except('password'))->withErrors(['status' => $message]);
        }

        return DB::transaction(function () use ($request, $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse {
            if ($request->input('role') === User::ROLE_MAO && $request->boolean('is_mao_head')) {
                User::query()->where('role', User::ROLE_MAO)->whereKeyNot($id)->update(['is_mao_head' => false]);
                \App\Services\SystemAuditLogger::record('Changed MAO Head assignment', $request, ['new_head_user_id' => $id]);
            }
            return parent::update($request, $id);
        });
    }

    /**
     * Deletes (closes) the account with a stated reason: the Delete account dialog (crud.index, _user-delete-modal).
     * Never a hard delete: see App\Services\Accounts\AccountDeletion.
     */
    public function destroy(Request $request, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request);
        $target = $this->findRecord($request, $id);

        if ($message = AccountDeletion::refusal($target, $request->user())) {
            return redirect()->route('users.index')->with('error', $message);
        }

        $min = (int) config('user_deletion.other_min_length', 10);
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(AccountDeletion::reasons()))],
            // Only for "Other": text left in the hidden field after picking another reason is ignored.
            'reason_other' => ['exclude_unless:reason,other', 'required', 'string', 'min:'.$min, 'max:1000'],
            'note_internal' => ['nullable', 'string', 'max:2000'],
            'confirm_name' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($target): void {
                if (trim((string) $value) !== trim((string) $target->name)) {
                    $fail('Type the name exactly as shown to confirm.');
                }
            }],
        ], [
            'reason.required' => 'Choose a reason for deleting this account.',
            'reason_other.required' => 'Please specify the reason.',
            'reason_other.min' => 'Please give at least '.$min.' characters.',
        ]);

        app(AccountDeletion::class)->delete($target, $request->user(), $data, $request);

        return redirect()->route('users.index', array_filter(['status' => $request->query('status')]))
            ->with('success', __('user_deletion.modal.done', ['name' => $target->name]));
    }

    /** Restore account (Deleted filter): within config user_deletion.restore_days of the deletion. */
    public function restore(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage($request);
        $target = User::withDeleted()->where('status', User::STATUS_DELETED)->findOrFail($id);

        try {
            app(AccountDeletion::class)->restore($target, $request->user(), $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('users.index', ['status' => User::STATUS_DELETED])->with('error', $e->validator->errors()->first());
        }

        $message = __('user_deletion.modal.restored', ['name' => $target->name]);
        // Farms stay archived after a restore: IT is told so they can let the farmer know.
        $archived = $target->role === User::ROLE_FARMER && \App\Support\SchemaCache::hasTable('farms')
            ? \App\Models\Farm::query()->ownedBy($target)->whereNotNull('archived_at')->count() : 0;
        if ($archived > 0) {
            $message .= ' '.trans_choice('user_deletion.modal.archived_hint', $archived, ['count' => $archived]);
        }

        return redirect()->route('users.index', ['status' => User::STATUS_DELETED])->with('success', $message);
    }

    /** IT can open a deleted account's page (Deleted filter); editing it waits for a restore. */
    protected function findRecord(Request $request, int $id)
    {
        return User::withDeleted()->whereIn('role', User::ROLES)->findOrFail($id);
    }

    public function edit(Request $request, int $id): \Illuminate\View\View
    {
        if ($this->findRecord($request, $id)->isDeleted()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->route('users.show', $id)->with('error', 'This account is deleted. Restore it before editing.')
            );
        }

        return parent::edit($request, $id);
    }

    private function protectedAccountMessage(Request $request, User $target, string $verb): ?string
    {
        if ($target->is($request->user())) {
            return "You cannot {$verb} your own account. Ask another MAO Head to do it.";
        }

        return self::isLastActiveMaoHead($target)
            ? "You cannot {$verb} the last active MAO Head account. Create another MAO Head account first."
            : null;
    }

    public static function isLastActiveMaoHead(User $user): bool
    {
        return $user->role === User::ROLE_MAO && $user->isMaoHead() && $user->status === User::STATUS_ACTIVE
            && ! User::query()->where('role', User::ROLE_MAO)->where('is_mao_head', true)->where('status', User::STATUS_ACTIVE)->whereKeyNot($user->getKey())->exists();
    }

    /** Name A–Z by default, ignoring case; id breaks ties so paging never repeats or skips a user. */
    protected function applyListOrder(\Illuminate\Database\Eloquent\Builder $query, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return match ($this->currentSort($request)) {
            'name_desc' => $query->orderByRaw('LOWER(name) DESC')->orderByDesc('id'),
            'newest' => $query->latest('id'),
            'oldest' => $query->oldest('id'),
            default => $query->orderByRaw('LOWER(name)')->orderBy('id'),
        };
    }

    protected function baseQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::baseQuery($request)->whereIn('role', User::ROLES);
        // The Deleted filter is the one place deleted accounts are listed (they are hidden everywhere else).
        if ($request->query('status') === User::STATUS_DELETED) {
            $query->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);
        }

        // Links from the IT dashboard (Data Integrity and Usage Patterns).
        if ($request->query('integrity') === 'never_logged_in') {
            $query->neverLoggedIn();
        }
        if ($request->query('integrity') === 'farmers_without_profile') {
            $query->where('role', User::ROLE_FARMER)->whereDoesntHave('farmerProfile');
        }

        return $query;
    }
}
