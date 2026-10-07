<?php

namespace App\Http\Controllers;

use App\Jobs\RunModelManagement;
use App\Models\FarmerSurveyResponse;
use App\Models\ModelFeedbackDecision;
use App\Services\Feedback\ModelFeedbackService;
use App\Services\MachineLearning\ModelManagementService;
use App\Services\SystemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): Settings → Model Feedback (IT Expert). The farmers' survey
 * answers by category with positive / negative counts and percentages, the recent answers (the IT Expert can move one
 * to another category and mark it reviewed), Live Insights, and the model decision: "Maintain Current Model" or "Needs
 * Retraining". The page only recommends. A retraining starts only from the IT Expert's decision here, and it saves a
 * candidate: the model in use changes only on Model Registry, by the IT Expert.
 */
class ModelFeedbackController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request, ModelFeedbackService $feedback): View
    {
        $filters = [
            'category' => in_array($request->query('category'), array_keys(FarmerSurveyResponse::CATEGORIES), true) ? (string) $request->query('category') : '',
            'answer' => in_array($request->query('answer'), ['positive', 'negative', 'neutral'], true) ? (string) $request->query('answer') : '',
            'review' => $request->query('review') === 'waiting' ? 'waiting' : '',
        ];
        // The three answers average 4 or more when they add up to 12 or more, and 2 or less when they add up to 6 or less.
        $sum = '(useful + accurate + helpful)';
        $recent = FarmerSurveyResponse::query()
            ->with(['user:id,name', 'advisory:id,title', 'prediction:id,season,barangay,predicted_yield,target_month', 'reviewedBy:id,name'])
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['answer'] === 'positive', fn ($q) => $q->whereRaw($sum.' >= 12'))
            ->when($filters['answer'] === 'negative', fn ($q) => $q->whereRaw($sum.' <= 6'))
            ->when($filters['answer'] === 'neutral', fn ($q) => $q->whereRaw($sum.' > 6')->whereRaw($sum.' < 12'))
            ->when($filters['review'] === 'waiting', fn ($q) => $q->whereNull('reviewed_at'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.feedback', [
            'summary' => $feedback->summary(),
            'insights' => $feedback->insights(),
            'recent' => $recent,
            'filters' => $filters,
            'decisions' => ModelFeedbackDecision::query()->with('decidedBy:id,name')->latest('id')->limit(10)->get(),
            'algorithms' => ModelManagementService::ALGORITHMS,
        ]);
    }

    /** Live Insights: read again by the page every few seconds; counted from saved rows each time. */
    public function insights(ModelFeedbackService $feedback): JsonResponse
    {
        $insights = $feedback->insights();
        $model = $feedback->modelStatus();

        return response()->json($insights + [
            'html' => view('admin._feedback-insights', ['insights' => $insights])->render(),
            'model' => ['status' => $model['status'], 'label' => $model['status_label'], 'recommended' => $model['recommended'], 'recommended_label' => $model['recommended_label']],
        ]);
    }

    /** Move one answer to another category and/or mark it reviewed. */
    public function update(Request $request, FarmerSurveyResponse $response): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(FarmerSurveyResponse::CATEGORIES))],
            'reviewed' => ['nullable', 'boolean'],
        ]);
        $reviewed = $request->boolean('reviewed');
        $response->forceFill([
            'category' => $data['category'],
            'reviewed_at' => $reviewed ? ($response->reviewed_at ?? now()) : null,
            'reviewed_by' => $reviewed ? ($response->reviewed_by ?? $request->user()->getKey()) : null,
        ])->save();
        SystemAuditLogger::record('Reviewed Farmer Feedback', $request, ['response' => $response->getKey(), 'category' => $data['category'], 'reviewed' => $reviewed]);

        return back()->with('success', 'Feedback updated.');
    }

    /**
     * The IT Expert's decision for the model. "Needs Retraining" may also start a retraining, which saves a new
     * candidate version; the model in use is not changed.
     */
    public function decide(Request $request, ModelFeedbackService $feedback): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([ModelFeedbackDecision::MAINTAIN, ModelFeedbackDecision::NEEDS_RETRAINING])],
            'note' => ['nullable', 'string', 'max:1000'],
            'start_retraining' => ['nullable', 'boolean'],
            'algorithm' => ['nullable', 'required_if:start_retraining,1', Rule::in(array_keys(ModelManagementService::ALGORITHMS))],
            // The IT Expert confirms the feedback was read before a decision is saved.
            'reviewed_feedback' => ['accepted'],
        ], ['reviewed_feedback.accepted' => 'Confirm that you reviewed the farmer feedback before saving a decision.']);

        $model = $feedback->modelStatus();
        $retrain = $data['decision'] === ModelFeedbackDecision::NEEDS_RETRAINING && $request->boolean('start_retraining');

        $decision = ModelFeedbackDecision::query()->create([
            'decision' => $data['decision'],
            'recommended' => $model['recommended'],
            'model_version' => $model['version'],
            'responses' => $model['tally']['total'],
            'positive' => $model['tally']['positive'],
            'negative' => $model['tally']['negative'],
            'retrain_algorithm' => $retrain ? $data['algorithm'] : null,
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            'decided_by' => $request->user()->getKey(),
        ]);
        SystemAuditLogger::record('Model Feedback Decision', $request, ['decision' => $decision->decision, 'recommended' => $decision->recommended, 'retraining_started' => $retrain]);

        if ($retrain) {
            RunModelManagement::dispatch('retrain', $data['algorithm'], $request->user()->getKey());
            SystemAuditLogger::record('Requested Model Retrain', $request, ['algorithm' => $data['algorithm'], 'from' => 'model feedback']);
        }

        return redirect()->route('admin.feedback.index')->with('success', $retrain
            ? 'Decision saved: Needs Retraining. '.ModelManagementService::ALGORITHMS[$data['algorithm']].' retraining started; the new model will appear on ML Models as a candidate. The model in use is not changed.'
            : 'Decision saved: '.$decision->label().'.');
    }
}
