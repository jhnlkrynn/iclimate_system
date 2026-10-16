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
    // Panel revision 1.1: + Organizations (every organization the farmer belongs to).
    protected array $columns = ['full_name' => 'Name', 'barangay' => 'Barangay', 'organization_names' => 'Organizations', 'farm_type' => 'Farm Type', 'total_farm_area' => 'Total Farm Area', 'account_status' => 'Status'];

    // Panel revision 1.4: + RSBSA no. (the farmer ID); organization names and the record no. are searched in extraSearch().
    protected array $searchable = ['full_name', 'contact_number', 'address', 'barangay', 'farm_type', 'rsbsa_no'];

    // Panel revision 1.1: the organization filter matches ANY of the farmer's organizations.
    protected array $customFilters = ['farmer_organization_id'];

    /** Organization ids from the form, saved after the profile itself (panel revision 1.1). */
    private ?array $pendingOrganizationIds = null;

    protected bool $farmerOwnRecordsOnly = true;

    // Panel revision 4.4 B: technologists see and change the farmers of their own barangays.
    protected ?string $barangayColumn = 'barangay';

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
            ['name' => 'farmer_organization_id', 'label' => 'Primary Farmer Organization', 'type' => 'select', 'options' => $this->farmerOrganizationOptions()],
            // Panel revision 1.1: every organization the farmer is registered under.
            ['name' => 'farmer_organization_ids', 'label' => 'All Farmer Organizations (tick every one)', 'type' => 'checkboxes', 'options' => $this->farmerOrganizationOptions()],
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
            'farmer_organization_ids' => ['nullable', 'array'],
            'farmer_organization_ids.*' => ['integer', 'exists:farmer_organizations,id'],
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
                ->with(['user', 'farmerOrganization', 'organizations'])
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
        $this->authorizeBarangayValue($request, $data);
        $record = FarmerProfile::query()->where('user_id', $data['user_id'])->first();

        if ($record) {
            $this->authorizeRecordCoverage($request, $record);
            $record->update($data);
            $this->saveOrganizations($record);
            SystemAuditLogger::forModel('updated', $record, $request, ['module' => $this->title]);

            return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' updated successfully.');
        }

        $record = FarmerProfile::query()->create($data);
        $this->saveOrganizations($record);
        SystemAuditLogger::forModel('created', $record, $request, ['module' => $this->title]);

        return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $response = parent::update($request, $id);
        $this->saveOrganizations(FarmerProfile::query()->findOrFail($id));

        return $response;
    }

    /** Panel revision 1.1: the organization ticks are not a column; they are kept aside and saved as memberships. */
    protected function prepareData(Request $request, array $data): array
    {
        $data = parent::prepareData($request, $data);
        $this->pendingOrganizationIds = $request->has('farmer_organization_ids') || $request->has('farmer_organization_ids_present')
            ? array_map('intval', (array) ($data['farmer_organization_ids'] ?? []))
            : null;
        unset($data['farmer_organization_ids']);

        return $data;
    }

    private function saveOrganizations(FarmerProfile $record): void
    {
        if ($this->pendingOrganizationIds !== null) {
            $record->syncOrganizations($this->pendingOrganizationIds, $record->farmer_organization_id ? (int) $record->farmer_organization_id : null);
        }
    }

    /** Panel revision 1.1 / 1.4: search also matches organization names and the record no. */
    protected function extraSearch(Builder $builder, string $search): void
    {
        $builder->orWhereHas('organizations', fn (Builder $q) => $q->where('farmer_organizations.name', 'like', "%{$search}%"))
            ->orWhereHas('farmerOrganization', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
        if (ctype_digit($search)) {
            $builder->orWhere('farmer_profiles.id', (int) $search);
        }
    }

    /** Panel revision 1.1: a farmer is listed under every organization they belong to. */
    protected function applyCustomFilter(Builder $query, string $field, string $value): void
    {
        if ($field === 'farmer_organization_id') {
            $query->where(fn (Builder $q) => $q->where('farmer_organization_id', (int) $value)
                ->orWhereHas('organizations', fn (Builder $m) => $m->where('farmer_organizations.id', (int) $value)));
        }
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
            // Only relationships used by the list columns are loaded. Farm boundaries are used by the detail view,
            // not this list; farm totals are calculated by one correlated aggregate below.
            ->with(['farmerOrganization', 'organizations'])
            ->withAggregate('user as account_status_value', 'status')
            ->withSum([
                'farms as active_known_farm_area' => fn (Builder $farms) => $farms
                    ->whereNull('archived_at')
                    ->whereIn('farm_size_method', [\App\Models\Farm::SIZE_EXACT, \App\Models\Farm::SIZE_MAPPED]),
            ], 'farm_size_hectares')
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
