<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmerProfileController extends CrudController
{
    protected string $model = FarmerProfile::class;
    protected string $routeName = 'farmer-profiles';
    protected string $title = 'Farmer Profile';
    protected array $columns = ['full_name' => 'Full Name', 'contact_number' => 'Contact', 'barangay' => 'Barangay', 'farm_area' => 'Farm Area', 'farm_type' => 'Farm Type'];
    protected array $searchable = ['full_name', 'contact_number', 'address', 'barangay', 'farm_type'];
    protected array $filterable = ['barangay' => ['Binubusan', 'Lumaniag', 'Malaruhatan', 'Matabungkay', 'Poblacion', 'Prenza'], 'farm_type' => ['Rainfed', 'Irrigated']];
    protected bool $farmerOwnRecordsOnly = true;

    public function __construct()
    {
        $this->fields = [
            ['name' => 'user_id', 'label' => 'User', 'type' => 'select', 'options' => $this->userOptions([User::ROLE_FARMER])],
            ['name' => 'full_name', 'label' => 'Full Name'],
            ['name' => 'contact_number', 'label' => 'Contact Number'],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'barangay', 'label' => 'Barangay'],
            ['name' => 'farm_area', 'label' => 'Farm Area', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'farm_type', 'label' => 'Farm Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'user_id' => ['required', 'exists:users,id', Rule::unique('farmer_profiles', 'user_id')->ignore($id)],
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'barangay' => ['required', 'string', 'max:255'],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
            'farm_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
        ];
    }
}