<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends CrudController
{
    protected string $model = User::class;

    protected string $routeName = 'users';

    protected string $title = 'User';

    // Manuscript – Naicel: Last Login (users.last_login_at, set at each sign-in).
    protected array $columns = ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'barangay' => 'Barangay', 'status' => 'Status', 'last_login_at' => 'Last Login'];

    protected array $searchable = ['name', 'email', 'role', 'contact_number', 'address', 'barangay', 'status'];

    protected array $filterable = ['role' => [User::ROLE_FARMER => User::ROLE_FARMER, User::ROLE_MAO => User::ROLE_MAO, User::ROLE_IT_EXPERT => 'IT Personnel'], 'status' => User::STATUSES];   // Manuscript – Naicel

    protected array $manageableRoles = [User::ROLE_IT_EXPERT];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'name', 'label' => 'Name'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => [User::ROLE_FARMER => User::ROLE_FARMER, User::ROLE_MAO => User::ROLE_MAO, User::ROLE_IT_EXPERT => 'IT Personnel']],
            ['name' => 'contact_number', 'label' => 'Contact Number'],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::userManagementOptions()],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_combine(User::STATUSES, User::STATUSES)],   // Manuscript – Naicel
        ];
    }

    protected function authorizeView(Request $request): void
    {
        if ($request->user()->role !== User::ROLE_IT_EXPERT) {
            abort(403);
        }
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        $rules = $this->userRules($id);
        $rules['barangay'] = ['nullable', 'string', 'max:255', Rule::in(LianBarangays::userManagementInputValues())];

        return $rules;
    }

    protected function prepareData(Request $request, array $data): array
    {
        if (array_key_exists('barangay', $data) && $data['barangay'] !== null && $data['barangay'] !== '') {
            $data['barangay'] = LianBarangays::canonicalName($data['barangay']) ?? $data['barangay'];
        }

        return $this->hashPasswordWhenPresent($data);
    }

    /**
     * The system must always keep a working administrator: IT cannot delete, deactivate or demote their own
     * account, and the last active IT Expert account cannot be deleted, deactivated or demoted by anyone.
     */
    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request);
        $target = $this->findRecord($request, $id);
        $losesAccess = $request->input('role', $target->role) !== User::ROLE_IT_EXPERT
            || $request->input('status', $target->status) !== User::STATUS_ACTIVE;

        if ($losesAccess && ($message = $this->protectedAccountMessage($request, $target, 'change the role or status of'))) {
            return back()->withInput($request->except('password'))->withErrors(['status' => $message]);
        }

        return parent::update($request, $id);
    }

    public function destroy(Request $request, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request);
        $target = $this->findRecord($request, $id);

        if ($message = $this->protectedAccountMessage($request, $target, 'delete')) {
            return redirect()->route('users.index')->with('error', $message);
        }

        return parent::destroy($request, $id);
    }

    private function protectedAccountMessage(Request $request, User $target, string $verb): ?string
    {
        if ($target->is($request->user())) {
            return "You cannot {$verb} your own account. Ask another IT Expert to do it.";
        }

        return self::isLastActiveItExpert($target)
            ? "You cannot {$verb} the last active IT Expert account. Create another IT Expert account first."
            : null;
    }

    public static function isLastActiveItExpert(User $user): bool
    {
        return $user->role === User::ROLE_IT_EXPERT && $user->status === User::STATUS_ACTIVE
            && ! User::query()->where('role', User::ROLE_IT_EXPERT)->where('status', User::STATUS_ACTIVE)->whereKeyNot($user->getKey())->exists();
    }

    protected function baseQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::baseQuery($request);

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
