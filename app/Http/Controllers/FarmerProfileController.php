<?php

namespace App\Http\Controllers;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerProfileController extends CrudController
{
    protected string $model = FarmerProfile::class;

    protected string $routeName = 'farmer-profiles';

    protected string $title = 'Farmer Profile';

    protected bool $printable = true;

    // MAO list (2026-10): no contact number or address; total_farm_area and account_status are FarmerProfile accessors.
    protected array $columns = ['full_name' => 'Name', 'barangay' => 'Barangay', 'farm_type' => 'Farm Type', 'total_farm_area' => 'Total Farm Area', 'account_status' => 'Status'];

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
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::options(false)],
            ['name' => 'farm_area', 'label' => 'Farm Area', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'farm_type', 'label' => 'Farm Type', 'type' => 'select', 'options' => ['Rainfed' => 'Rainfed', 'Irrigated' => 'Irrigated']],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        // Selecting an existing farmer from the modify form is an update workflow even when the
        // request arrives at store (for example after a stale form submission). Let it target the
        // same unique profile row instead of producing a duplicate-key validation error.
        $existingId = $id ?? FarmerProfile::query()->where('user_id', $request->input('user_id'))->value('id');

        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn (QueryBuilder $query) => $query->where('role', User::ROLE_FARMER)),
                Rule::unique('farmer_profiles', 'user_id')->ignore($existingId),
            ],
            'farmer_organization_id' => ['nullable', 'exists:farmer_organizations,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            // A new profile needs the farm size (barangay risk, the yield forecast and reports all use it). An older
            // profile without one can still be corrected (e.g. its barangay) before the size is known.
            'farm_area' => [$id ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:10000'],
            'farm_type' => ['required', Rule::in(['Rainfed', 'Irrigated'])],
        ];
    }

    public function create(Request $request): View
    {
        $this->authorizeManage($request);

        $record = null;
        if ($request->filled('user_id')) {
            $user = User::query()
                ->whereKey($request->integer('user_id'))
                ->where('role', User::ROLE_FARMER)
                ->firstOrFail();

            $record = FarmerProfile::query()
                ->with(['user', 'farmerOrganization'])
                ->where('user_id', $user->id)
                ->first() ?? (object) ['user_id' => $user->id];
        }

        return view('crud.form', [
            'title' => 'Modify '.$this->title,
            'routeName' => $this->routeName,
            'fields' => $this->fields,
            'record' => $record,
            'method' => $record instanceof FarmerProfile ? 'PUT' : 'POST',
            'action' => $record instanceof FarmerProfile
                ? route($this->routeName.'.update', $record)
                : route($this->routeName.'.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);

        $data = $this->prepareData($request, $this->validated($request));
        $record = FarmerProfile::query()->where('user_id', $data['user_id'])->first();

        if ($record) {
            $record->update($data);
            SystemAuditLogger::forModel('updated', $record, $request, ['module' => $this->title]);

            return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' updated successfully.');
        }

        $record = FarmerProfile::query()->create($data);
        SystemAuditLogger::forModel('created', $record, $request, ['module' => $this->title]);

        return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' created successfully.');
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

    /** Staff "View" pop-up (?modal=1): the farmer's details and farms, with [Mensahe]; every other case is unchanged. */
    public function show(Request $request, int $id): View
    {
        if (! $request->boolean('modal') || $request->user()->role === User::ROLE_FARMER) {
            return parent::show($request, $id);
        }
        $this->authorizeView($request);

        return view('farmer-profiles._modal', ['record' => $this->findRecord($request, $id), 'canManage' => $this->canManage($request)]);
    }

    protected function baseQuery(Request $request): Builder
    {
        return FarmerProfile::query()
            // farmerOrganization is shown per row; loading it here is one query for the page, not one per row.
            ->with(['user', 'farmBoundary', 'farmerOrganization', 'farms'])
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
