<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsRun;
use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModelEvaluationController extends Controller
{
    public function index(Request $request, AnalyticsDispatcher $analytics): View|RedirectResponse
    {
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

        if ($request->boolean('refresh')) {
            $run = $analytics->dispatch('dataset_processing', ['schema' => 'rice_yield_v1'], $request->user());

            return redirect()->route('model-evaluation.index', ['run' => $run->uuid]);
        }

        return view('model-evaluation.index', [
            'evaluation' => (array) data_get($run?->result, 'evaluation', []),
            'analyticsRun' => $run,
        ]);
    }
}
