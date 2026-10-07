<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

abstract class CrudController extends Controller
{
    protected string $model;

    protected string $routeName;

    protected string $title;

    protected array $fields = [];

    protected array $columns = [];

    protected array $searchable = [];

    protected array $filterable = [];

    /** Filters whose options are keyed by their stored value (e.g. is_read => [0 => 'Unread', 1 => 'Read']). */
    protected array $indexedFilters = [];

    protected array $manageableRoles = [User::ROLE_MAO, User::ROLE_IT_EXPERT];

    /** Rows per page on every record list. */
    public const PER_PAGE = 25;

    /**
     * A Print button on the staff list (2026-10): ?print=1 opens a print page with EVERY row that matches the current
     * search and filters (not only the page shown), the office header, the print date and the active filters.
     * Off unless a module opts in.
     */
    protected bool $printable = false;

    /** The most rows one print page lists; beyond it the page says so and asks for a narrower filter. */
    public const PRINT_ROW_LIMIT = 2000;

    protected bool $farmerOwnRecordsOnly = false;

    protected bool $viewOnly = false;

    /**
     * Farmers see this module in the farmer design system (resources/views/farmer-records/<route>.blade.php and
     * farmer-records/show.blade.php) instead of the shared staff table. Same query, filters, paging and permissions;
     * MAO and IT keep crud.index. Off unless a module opts in.
     */
    protected bool $farmerPages = false;

    /** Column the farmer list is sorted by, newest first (the staff list sorts by id). */
    protected string $farmerSortColumn = 'id';

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $query = $this->baseQuery($request);
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        if ($search !== '' && $this->searchable !== []) {
            $query->where(function (Builder $builder) use ($search) {
                foreach ($this->searchable as $field) {
                    $builder->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        foreach ($this->filterable as $field => $options) {
            $value = $request->query($field);
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        if ($this->wantsPrint($request)) {
            return $this->printView($request, (clone $query)->latest('id')->limit(self::PRINT_ROW_LIMIT)->get(), (clone $query)->count(), $search, $this->selectFilterOptions());
        }

        $data = [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'columns' => $this->columns,
            'search' => $search,
            'filterable' => $this->selectFilterOptions(),
            'canManage' => $this->canManage($request),
            'viewOnly' => $this->viewOnly,
            'printable' => $this->printable && ! $this->usesFarmerPages($request),
        ];

        if ($this->usesFarmerPages($request)) {
            return view('farmer-records.'.$this->routeName, $data + [
                'records' => (clone $query)->latest($this->farmerSortColumn)->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            ] + $this->farmerIndexData($request, clone $query));
        }

        return view('crud.index', $data + [
            'records' => $query->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    /**
     * The staff list (crud.index) for a module whose records also come from a read-only source dataset. $rows are
     * display rows: the column fields plus search_text, sort_key, id (null for a dataset row) and is_source_dataset.
     * Search, filters, order (?sort=oldest, newest by default) and paging work on all rows together, so the count
     * is the real count for the current filters.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string, array<string, string>>  $filterable  field => [value => label]
     */
    protected function staffRowsIndex(Request $request, Collection $rows, array $filterable): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $needle = mb_strtolower($search);
        $filters = collect(array_keys($filterable))
            ->mapWithKeys(fn (string $field) => [$field => $request->query($field)])
            ->filter(fn ($value) => is_string($value) && $value !== '');

        $filtered = $rows
            ->filter(fn (object $row): bool => ($needle === '' || str_contains($row->search_text, $needle))
                && $filters->every(fn (string $value, string $field): bool => (string) ($row->{$field} ?? '') === $value))
            ->sortBy(fn (object $row): string => $row->sort_key, SORT_STRING, $request->query('sort') !== 'oldest')
            ->values();

        if ($this->wantsPrint($request)) {
            return $this->printView($request, $filtered->take(self::PRINT_ROW_LIMIT), $filtered->count(), $search, $filterable);
        }

        $perPage = self::PER_PAGE;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('crud.index', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'columns' => $this->columns,
            'search' => $search,
            'filterable' => $filterable,
            'canManage' => $this->canManage($request),
            'viewOnly' => $this->viewOnly,
            'printable' => $this->printable,
            'records' => new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]),
        ]);
    }

    /** Staff only, and only for a module that opted in: farmers print their own records from My Farm. */
    protected function wantsPrint(Request $request): bool
    {
        return $this->printable && $request->boolean('print') && ! $this->usesFarmerPages($request);
    }

    /**
     * The print page: every filtered row, with what was filtered written out.
     *
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, array<int|string, string>>  $filterable  field => [value => label]
     */
    protected function printView(Request $request, iterable $rows, int $total, string $search, array $filterable): View
    {
        $filters = [];
        if ($search !== '') {
            $filters['Search'] = $search;
        }
        foreach ($filterable as $field => $options) {
            $value = $request->query($field);
            if (is_string($value) && $value !== '') {
                $filters[(string) str($field)->headline()] = (string) ($options[$value] ?? $value);
            }
        }

        return view('crud.print', [
            'title' => $this->title.'s',
            'routeName' => $this->routeName,
            'columns' => $this->columns,
            'rows' => $rows,
            'total' => $total,
            'limit' => self::PRINT_ROW_LIMIT,
            'filters' => $filters + $this->printFilters($request),
            'printedAt' => \App\Support\BusinessClock::now(),
            'backUrl' => route($this->routeName.'.index', $request->except('print')),
        ]);
    }

    /**
     * Filters a module adds on top of search and its select filters, as label => value, for the print page.
     *
     * @return array<string, string>
     */
    protected function printFilters(Request $request): array
    {
        return [];
    }

    /** A stored number as written, without trailing zeros ("114", "25.1", "26.95"), or null when missing. */
    protected static function plainNumber(mixed $value, string $unit = ''): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');

        return $unit === '' ? $number : $number.$unit;
    }

    protected function usesFarmerPages(Request $request): bool
    {
        return $this->farmerPages && $request->user()?->role === User::ROLE_FARMER;
    }

    /**
     * Read-only figures for the farmer page, worked out from the same filtered query as the list.
     *
     * @return array<string, mixed>
     */
    protected function farmerIndexData(Request $request, Builder $filtered): array
    {
        return [];
    }

    public function create(Request $request): View
    {
        $this->authorizeManage($request);

        return view('crud.form', [
            'title' => 'Create '.$this->title,
            'routeName' => $this->routeName,
            'fields' => $this->fields,
            'record' => null,
            'method' => 'POST',
            'action' => route($this->routeName.'.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);

        $data = $this->prepareData($request, $this->validated($request));
        $record = $this->model::query()->create($data);
        SystemAuditLogger::forModel('created', $record, $request, [
            'module' => $this->title,
        ]);

        return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $this->authorizeView($request);
        $record = $this->findRecord($request, $id);

        // A farmer record page can open a record in its "View" pop-up (?modal=1): the same record without the page layout.
        $popup = 'farmer-records._modal-'.$this->routeName;
        // Staff lists open a record in the shared information pop-up (resources/js/info-modal.js) the same way.
        $view = match (true) {
            ! $this->usesFarmerPages($request) && $request->boolean('modal') => 'crud._modal',
            ! $this->usesFarmerPages($request) => 'crud.show',
            $request->boolean('modal') && view()->exists($popup) => $popup,
            default => 'farmer-records.show',
        };

        return view($view, [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'record' => $record,
            'columns' => $this->columns,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $this->authorizeManage($request);
        $record = $this->findRecord($request, $id);

        return view('crud.form', [
            'title' => 'Edit '.$this->title,
            'routeName' => $this->routeName,
            'fields' => $this->fields,
            'record' => $record,
            'method' => 'PUT',
            'action' => route($this->routeName.'.update', $record),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $record = $this->findRecord($request, $id);
        $record->update($this->prepareData($request, $this->validated($request, $record->id)));
        SystemAuditLogger::forModel('updated', $record, $request, [
            'module' => $this->title,
        ]);

        return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $record = $this->findRecord($request, $id);
        $recordId = $record->getKey();
        $record->delete();
        SystemAuditLogger::record('Deleted '.$this->title, $request, [
            'record_type' => class_basename($this->model),
            'record_id' => $recordId,
            'module' => $this->title,
        ]);

        return redirect()->route($this->routeName.'.index')->with('success', $this->title.' deleted successfully.');
    }

    protected function baseQuery(Request $request): Builder
    {
        $query = $this->model::query();

        if ($this->farmerOwnRecordsOnly && $request->user()->role === User::ROLE_FARMER) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    protected function findRecord(Request $request, int $id)
    {
        return $this->baseQuery($request)->findOrFail($id);
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        return $request->validate($this->rules($request, $id));
    }

    protected function prepareData(Request $request, array $data): array
    {
        foreach ($this->fields as $field) {
            if (($field['type'] ?? 'text') === 'checkbox') {
                $data[$field['name']] = $request->boolean($field['name']);
            }
        }

        return $data;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [];
    }

    /**
     * Filter options as value => label for the select boxes. A plain list (['Wet', 'Dry']) filters by the names
     * themselves; without this the form submitted the list position ("0") and every filtered list came back empty.
     *
     * @return array<string, array<int|string, string>>
     */
    protected function selectFilterOptions(): array
    {
        return collect($this->filterable)
            ->map(fn (array $options, string $field): array => array_is_list($options) && ! in_array($field, $this->indexedFilters, true)
                ? array_combine($options, $options)
                : $options)
            ->all();
    }

    protected function canManage(Request $request): bool
    {
        return ! $this->viewOnly && in_array($request->user()->role, $this->manageableRoles, true);
    }

    protected function authorizeView(Request $request): void
    {
        if (! $request->user()) {
            abort(403);
        }
    }

    protected function authorizeManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403);
        }
    }

    protected function userOptions(array $roles = []): array
    {
        // Built in three controllers' constructors, i.e. on EVERY request to those routes, and it lists
        // every user. Kept for ten minutes, and dropped at once when any user is added or changed.
        return \App\Support\PageCache::shared('crud-user-options', 600, function () use ($roles): array {
            $query = User::query()->orderBy('name');

            if ($roles !== []) {
                $query->whereIn('role', $roles);
            }

            return $query->pluck('name', 'id')->toArray();
        }, ['roles' => $roles]);
    }

    protected function userRules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'role' => ['required', Rule::in([User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'barangay' => ['nullable', 'string', 'max:255', Rule::in(\App\Support\LianBarangays::all())],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    protected function hashPasswordWhenPresent(array $data): array
    {
        if (array_key_exists('password', $data)) {
            if ($data['password'] === null || $data['password'] === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
        }

        return $data;
    }
}
