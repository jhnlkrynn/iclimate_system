<?php

namespace App\Http\Controllers;

use App\Jobs\RunModelManagement;
use App\Models\MlModel;
use App\Services\MachineLearning\ModelManagementService;
use App\Services\SystemAuditLogger;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: ML Models (IT). The active model, the comparison of Multiple Linear
 * Regression, Random Forest and Gradient Boosting (RMSE, MAE, R²), retrain with the newest dataset, upload a training
 * dataset or a model version, and the training history. Validating and switching the active model stay on Model
 * Registry, which checks the prediction service really serves it.
 */
class ModelManagementController extends Controller
{
    public const DATASET_COLUMNS = ['YEAR', 'MONTH_SIN', 'MONTH_COS', 'SEASON_WET', 'RAINFALL', 'TMAX', 'TMIN', 'RH', 'WIND_SPEED', 'WIND_DIR_SIN', 'WIND_DIR_COS', 'AVERAGE_YIELD'];

    public function index(): View
    {
        return view('admin.models', [
            'active' => MlModel::query()->where('is_active', true)->first(),
            'history' => MlModel::query()->orderByDesc('trained_at')->orderByDesc('id')->limit(30)->get(),
            'report' => ModelManagementService::lastReport(),
            'last' => Cache::get(RunModelManagement::RESULT_KEY),
            'algorithms' => ModelManagementService::ALGORITHMS,
            'dataset' => basename(ModelManagementService::datasetPath()),
            'datasetUploaded' => (bool) SystemSettings::get('ml_dataset_path'),
        ]);
    }

    public function compare(Request $request): RedirectResponse
    {
        RunModelManagement::dispatch('compare', null, $request->user()->getKey());
        SystemAuditLogger::record('Requested Model Comparison', $request);

        return redirect()->route('admin.models.index')->with('success', 'Comparison started. It takes about a minute; refresh this page.');
    }

    public function retrain(Request $request): RedirectResponse
    {
        $data = $request->validate(['algorithm' => ['required', Rule::in(array_keys(ModelManagementService::ALGORITHMS))]]);
        RunModelManagement::dispatch('retrain', $data['algorithm'], $request->user()->getKey());
        SystemAuditLogger::record('Requested Model Retrain', $request, ['algorithm' => $data['algorithm']]);

        return redirect()->route('admin.models.index')->with('success', ModelManagementService::ALGORITHMS[$data['algorithm']].' retraining started. The new model appears in the history as a candidate.');
    }

    public function uploadDataset(Request $request): RedirectResponse
    {
        $request->validate(['dataset' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $header = array_map(fn ($c) => strtoupper(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c))), (array) fgetcsv(fopen($request->file('dataset')->getRealPath(), 'r'), escape: '\\'));
        $missing = array_diff(self::DATASET_COLUMNS, $header);
        if ($missing !== []) {
            return back()->withErrors(['dataset' => 'Missing columns: '.implode(', ', $missing).'. Use the same columns as the modeling dataset.']);
        }

        $path = $request->file('dataset')->storeAs('datasets-uploaded', 'modeling_'.now()->format('Ymd_His').'.csv', 'local');
        $relative = ltrim(Str::after(str_replace('\\', '/', \Illuminate\Support\Facades\Storage::disk('local')->path($path)), str_replace('\\', '/', base_path())), '/');
        SystemSettings::set(['ml_dataset_path' => $relative], $request->user()->getKey());
        SystemAuditLogger::record('Uploaded Training Dataset', $request, ['file' => basename($path)]);

        return redirect()->route('admin.models.index')->with('success', 'Dataset uploaded. Compare and Retrain now use it.');
    }

    public function uploadModel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['required', 'file', 'max:102400'],
            'name' => ['required', 'string', 'max:120'],
            'model_type' => ['required', 'string', 'max:80'],
            'version' => ['required', 'string', 'max:40', Rule::unique('ml_models', 'version')->where('purpose', MlModel::PURPOSE_RICE_YIELD)],
            'rmse' => ['nullable', 'numeric'],
            'mae' => ['nullable', 'numeric'],
            'r2' => ['nullable', 'numeric'],
        ]);
        $extension = strtolower((string) $request->file('model')->getClientOriginalExtension());
        if (! in_array($extension, ['pkl', 'joblib'], true)) {
            return back()->withErrors(['model' => 'Upload a .pkl or .joblib model file.']);
        }

        $fileName = Str::slug($data['name'].'-'.$data['version']).'.'.$extension;
        $request->file('model')->move(storage_path('models/uploaded'), $fileName);
        $model = MlModel::query()->create([
            'purpose' => MlModel::PURPOSE_RICE_YIELD,
            'name' => $data['name'],
            'model_type' => $data['model_type'],
            'version' => $data['version'],
            'target_variable' => 'AVERAGE_YIELD',
            'file_path' => 'storage/models/uploaded/'.$fileName,
            'mae' => $data['mae'] ?? null,
            'rmse' => $data['rmse'] ?? null,
            'r2' => $data['r2'] ?? null,
            'trained_at' => now(),
            'status' => MlModel::STATUS_CANDIDATE,
            'is_active' => false,
        ]);
        SystemAuditLogger::forModel('uploaded', $model, $request);

        return redirect()->route('admin.models.index')->with('success', 'Model uploaded as a candidate. Validate and activate it on Model Registry.');
    }
}
