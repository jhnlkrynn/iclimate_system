<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    protected array $manageableRoles = [User::ROLE_MAO, User::ROLE_IT_EXPERT];

    protected bool $farmerOwnRecordsOnly = false;

    protected bool $viewOnly = false;

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $query = $this->baseQuery($request);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '' && $this->searchable !== []) {
            $query->where(function (Builder $builder) use ($search) {
                foreach ($this->searchable as $field) {
                    $builder->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        foreach ($this->filterable as $field => $options) {
            $value = $request->query($field);
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        return view('crud.index', [
            'title' => $this->title,
            'routeName' => $this->routeName,
            'records' => $query->latest('id')->paginate(10)->withQueryString(),
            'columns' => $this->columns,
            'search' => $search,
            'filterable' => $this->filterable,
            'canManage' => $this->canManage($request),
            'viewOnly' => $this->viewOnly,
        ]);
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

    public function store(Request $request): RedirectResponse
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

        return view('crud.show', [
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

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorizeManage($request);
        $record = $this->findRecord($request, $id);
        $record->update($this->prepareData($request, $this->validated($request, $record->id)));
        SystemAuditLogger::forModel('updated', $record, $request, [
            'module' => $this->title,
        ]);

        return redirect()->route($this->routeName.'.show', $record)->with('success', $this->title.' updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
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
        $key = 'crud:user-options:'.($roles === [] ? 'all' : implode('-', $roles));

        return Cache::remember($key, now()->addSeconds(30), function () use ($roles): array {
            $query = User::query()->orderBy('name');

            if ($roles !== []) {
                $query->whereIn('role', $roles);
            }

            return $query->pluck('name', 'id')->toArray();
        });
    }

    protected function userRules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'barangay' => ['nullable', 'string', 'max:255'],
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
