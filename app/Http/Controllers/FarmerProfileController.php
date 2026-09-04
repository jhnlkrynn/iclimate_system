<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\FarmerOrganization;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmerProfileController extends CrudController
{
    protected string $model = FarmerProfile::class;

    protected string $routeName = 'farmer-profiles';

    protected string $title = 'Farmer Profile';

    protected array $columns = ['full_name' => 'Full Name', 'contact_number' => 'Contact', 'farmerOrganization.name' => 'Organization', 'barangay' => 'Barangay', 'farm_area' => 'Farm Area', 'farm_type' => 'Farm Type'];

    protected array $searchable = ['full_name', 'contact_number', 'address', 'barangay', 'farm_type'];

    protected array $filterable = ['barangay' => ['Balibago', 'Binubusan', 'Bungahan', 'Kapito', 'Luyahan', 'Malaruhatan', 'Matabungkay', 'Prenza', 'San Diego'], 'farm_type' => ['Rainfed', 'Irrigated']];

    protected bool $farmerOwnRecordsOnly = true;

    public function __construct()
    {
        $this->fields = [
            ['name' => 'user_id', 'label' => 'User', 'type' => 'select', 'options' => $this->userOptions([User::ROLE_FARMER])],
            ['name' => 'farmer_organization_id', 'label' => 'Farmer Organization', 'type' => 'select', 'options' => $this->farmerOrganizationOptions()],
            ['name' => 'full_name', 'label' => 'Full Name'],
            ['name' => 'contact_number', 'label' => 'Contact Number'],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::options(false)],
            ['name' => 'farm_area', 'label' => 'Farm Area', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'farm_type', 'label' => 'Farm Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'user_id' => ['required', 'exists:users,id', Rule::unique('farmer_profiles', 'user_id')->ignore($id)],
            'farmer_organization_id' => ['nullable', 'exists:farmer_organizations,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
            'farm_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        return FarmerProfile::query()
            ->with(['user', 'farmBoundary'])
            ->when(
                $request->user()->role === User::ROLE_FARMER,
                fn (Builder $query) => $query->where('user_id', $request->user()->id),
            );
    }

    private function farmerOrganizationOptions(): array
    {
        return FarmerOrganization::query()
            ->orderBy('id')
            ->pluck('name', 'id')
            ->toArray();
    }
}
