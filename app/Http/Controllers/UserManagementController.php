<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends CrudController
{
    protected string $model = User::class;

    protected string $routeName = 'users';

    protected string $title = 'User';

    protected array $columns = ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'barangay' => 'Barangay', 'status' => 'Status'];

    protected array $searchable = ['name', 'email', 'role', 'contact_number', 'address', 'barangay', 'status'];

    protected array $filterable = ['role' => [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT], 'status' => [User::STATUS_ACTIVE, User::STATUS_INACTIVE]];

    protected array $manageableRoles = [User::ROLE_IT_EXPERT];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'name', 'label' => 'Name'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => [User::ROLE_FARMER => User::ROLE_FARMER, User::ROLE_MAO => User::ROLE_MAO, User::ROLE_IT_EXPERT => User::ROLE_IT_EXPERT]],
            ['name' => 'contact_number', 'label' => 'Contact Number'],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'barangay', 'label' => 'Barangay'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [User::STATUS_ACTIVE => User::STATUS_ACTIVE, User::STATUS_INACTIVE => User::STATUS_INACTIVE]],
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
        return $this->userRules($id);
    }

    protected function prepareData(Request $request, array $data): array
    {
        return $this->hashPasswordWhenPresent($data);
    }
}
