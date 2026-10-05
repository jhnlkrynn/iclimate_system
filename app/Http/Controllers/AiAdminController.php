<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Support\AiAdminResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * IT Expert / MAO management of the chatbot's vocabulary and language filter.
 * Routes are limited to those roles; one controller serves every resource
 * described in AiAdminResources. Records are activated and deactivated, not
 * deleted, so nothing an admin added is lost by accident.
 */
class AiAdminController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $config = $this->config($resource);
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $query = $config['model']::query()->with($config['with'] ?? [])->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($config, $search): void {
                foreach ($config['search'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return view('ai-admin.index', [
            'resource' => $resource,
            'config' => $config,
            'resources' => AiAdminResources::all(),
            'rows' => $query->paginate(25)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $data = $this->validated($request, $resource, $config, null);

        $model = $config['model']::query()->create($data);
        $this->audit($config, 'created', $resource, $model, $request);

        return back()->with('success', $config['label'].': entry added.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $model = $config['model']::query()->findOrFail($id);

        $model->update($this->validated($request, $resource, $config, $id));
        $this->audit($config, 'updated', $resource, $model, $request);

        return back()->with('success', $config['label'].': entry updated.');
    }

    public function toggle(Request $request, string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $model = $config['model']::query()->findOrFail($id);

        $model->update(['is_active' => ! $model->is_active]);
        $this->audit($config, $model->is_active ? 'activated' : 'deactivated', $resource, $model, $request);

        return back()->with('success', $config['label'].': entry '.($model->is_active ? 'activated' : 'deactivated').'.');
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function config(string $resource): array
    {
        return AiAdminResources::find($resource) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $resource, array $config, ?int $ignoreId): array
    {
        $rules = [];
        $input = $request->all();

        foreach ($config['fields'] as $name => $field) {
            if ($field['type'] === 'list') {
                // One item per line in the form; stored as an array.
                $input[$name] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($input[$name] ?? '')) ?: [])));
                $rules[$name.'.*'] = $field['item_rules'] ?? ['string'];
            }

            if ($field['type'] === 'bool') {
                $input[$name] = $request->boolean($name);
            }

            $rules[$name] = $field['rules'] ?? ($field['type'] === 'bool' ? ['boolean'] : ['nullable']);

            if (! empty($field['unique'])) {
                $rules[$name][] = Rule::unique($config['model']::query()->getModel()->getTable(), $name)->ignore($ignoreId);
            }
        }

        $validator = Validator::make($input, $rules);

        if (isset($config['validate'])) {
            $validator->after(function ($validator) use ($config, $input, $ignoreId): void {
                if ($validator->errors()->isEmpty() && ($problem = $config['validate']($input, $ignoreId)) !== null) {
                    $validator->errors()->add('form', $problem);
                }
            });
        }

        $data = $validator->validate();

        return array_intersect_key($data, $config['fields']);
    }

    /** Records who changed what. Blocked-word content is never written to the log. */
    private function audit(array $config, string $action, string $resource, Model $model, Request $request): void
    {
        SystemLog::create([
            'user_id' => $request->user()->id,
            'action' => 'ai_vocabulary.'.$action,
            'details' => json_encode(['resource' => $resource, 'id' => $model->getKey()]),
        ]);
    }
}
