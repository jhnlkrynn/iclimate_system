<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsRun;
use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Model Evaluation (MAO, thesis objective 2): Multiple Linear Regression, Random Forest and Gradient Boosting compared
 * on RMSE, MAE and R², overall and per season (wet/dry) and farming system (irrigated/rainfed).
 *
 * Restored for MAO staff on 2026-11-20 (owner decision); the 2026-11-18 release had removed it with the IT role.
 * The page only reports metrics computed by the daily dataset run (iclimate:process-datasets, or "Recompute" here).
 * It never trains, replaces or changes the deployed rice-yield model. ACCURACY NOT VERIFIED.
 */
class ModelEvaluationController extends Controller
{
    public function index(Request $request, AnalyticsDispatcher $analytics): View|RedirectResponse
    {
        if ($request->boolean('refresh')) {
            $run = $analytics->dispatch('dataset_processing', ['schema' => 'rice_yield_v1'], $request->user());

            return redirect()->route('model-evaluation.index', ['run' => $run->uuid]);
        }

        $run = $request->filled('run')
            ? AnalyticsRun::query()
                ->where('uuid', $request->query('run'))
                ->where('type', 'dataset_processing')
                ->where('requested_by', $request->user()->id)
                ->firstOrFail()
            : AnalyticsRun::query()
                ->where('type', 'dataset_processing')
                ->where('status', AnalyticsRun::STATUS_COMPLETED)
                ->latest('completed_at')
                ->first();

        return view('model-evaluation.index', [
            'evaluation' => (array) data_get($run?->result, 'evaluation', []),
            'analyticsRun' => $run,
            // Harvests where the farmer's actual yield can be compared with the predicted one.
            'retraining' => rescue(fn (): array => \App\Support\YieldCorrections::summary(), ['count' => 0, 'rows' => collect()], false),
        ]);
    }
}
