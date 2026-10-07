<?php

namespace App\Http\Controllers;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerProfileController extends CrudController
{
    protected string $model = FarmerProfile::class;

    protected string $routeName = 'farmer-profiles';

    protected string $title = 'Farmer Profile';

    protected bool $printable = true;

    protected array $columns = ['full_name' => 'Full Name', 'contact_number' => 'Contact', 'farmerOrganization.name' => 'Organization', 'barangay' => 'Barangay', 'farm_area' => 'Farm Area', 'farm_type' => 'Farm Type'];

    protected array $searchable = ['full_name', 'contact_number', 'address', 'barangay', 'farm_type'];

    protected bool $farmerOwnRecordsOnly = true;

    public function __construct()
    {
        $this->filterable = [
            // All official names: MAO can set any of the 19 on the form below, so each must be filterable.
            'barangay' => LianBarangays::all(),
            'farm_type' => ['Rainfed', 'Irrigated'],
            'farmer_organization_id' => $this->farmerOrganizationOptions(),
        ];

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
            // A new profile needs the farm size (barangay risk, the yield forecast and reports all use it). An older
            // profile without one can still be corrected (e.g. its barangay) before the size is known.
            'farm_area' => [$id ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:10000'],
            'farm_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
        ];
    }

    /**
     * Adds the "Farmers by Organization" chart (moved here from the MAO
     * dashboard) to the standard CRUD index, visible only to staff who can
     * manage farmer profiles — farmers viewing their own single record
     * don't need a municipality-wide aggregate.
     */
    public function index(Request $request): View
    {
        $view = parent::index($request);

        if ($this->canManage($request)) {
            $summary = rescue(fn () => app(FarmerOrganizationSummaryService::class)->summarize(), [
                'farmerOrganizationSummary' => [
                    'labels' => [],
                    'counts' => [],
                    'totalFarmers' => 0,
                    'topOrganization' => null,
                ],
            ], false);
            $view->with($summary);
        }

        return $view;
    }

    protected function baseQuery(Request $request): Builder
    {
        return FarmerProfile::query()
            // farmerOrganization is shown per row; loading it here is one query for the page, not one per row.
            ->with(['user', 'farmBoundary', 'farmerOrganization'])
            ->when(
                $request->user()->role === User::ROLE_FARMER,
                fn (Builder $query) => $query->where('user_id', $request->user()->id),
            )
            // Links from the IT dashboard's Data Integrity panel.
            ->when($request->query('integrity') === 'no_organization', fn (Builder $q) => $q->whereNull('farmer_organization_id'))
            ->when($request->query('integrity') === 'missing_fields', fn (Builder $q) => $q->where(function (Builder $w) {
                $w->whereNull('barangay')->orWhere('barangay', '')->orWhereNull('full_name')->orWhere('full_name', '')
                    ->orWhereNull('farm_area')->orWhere('farm_area', '<=', 0)->orWhereNull('contact_number')->orWhere('contact_number', '');
            }))
            ->when($request->query('integrity') === 'orphaned', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('role', '!=', User::ROLE_FARMER)));
    }

    private function farmerOrganizationOptions(): array
    {
        // The same dropdown is asked for twice per page and changes rarely.
        return \App\Support\PageCache::shared('farmer-organization-options', 600, fn (): array => FarmerOrganization::query()
            ->orderBy('id')
            ->pluck('name', 'id')
            ->toArray());
    }
}
