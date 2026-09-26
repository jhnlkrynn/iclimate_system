<?php

namespace App\Http\Controllers;

use App\Models\MlModel;
use App\Services\MachineLearning\ModelRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ModelRegistryController extends Controller
{
    public function index(): View
    {
        return view('model-registry.index', [
            'models' => MlModel::query()
                ->where('purpose', MlModel::PURPOSE_RICE_YIELD)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function validateModel(Request $request, MlModel $model, ModelRegistryService $registry): RedirectResponse
    {
        return $this->run(fn () => $registry->validate($model, $request->user()), 'Model validated. It is not active yet.');
    }

    public function activate(Request $request, MlModel $model, ModelRegistryService $registry): RedirectResponse
    {
        return $this->run(fn () => $registry->activate($model, $request->user()), "Version {$model->version} is now the active model.");
    }

    public function fail(Request $request, MlModel $model, ModelRegistryService $registry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->run(fn () => $registry->markFailed($model, $request->user(), (string) ($data['reason'] ?? '')), 'Model marked as failed.');
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
