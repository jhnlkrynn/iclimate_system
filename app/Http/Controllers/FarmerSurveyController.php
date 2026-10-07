<?php

namespace App\Http\Controllers;

use App\Models\FarmerSurveyResponse;
use App\Models\PlantingAdvisory;
use App\Models\YieldPredictionRequest;
use App\Services\Feedback\ModelFeedbackService;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): the farmer's short survey. Three questions (useful, accurate,
 * helpful; 1 to 5) about a yield prediction, the weather forecast, an advisory or the system, with an optional
 * comment. Opened from Home, from an advisory ("?advisory=ID") and from Yield Prediction ("?prediction=ID"), so the
 * answer is saved with the advisory or prediction it is about. The IT Expert reads the answers on Settings → Model Feedback.
 */
class FarmerSurveyController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $advisory = $request->filled('advisory') ? PlantingAdvisory::query()->published()->find((int) $request->query('advisory')) : null;
        $prediction = $request->filled('prediction')
            ? YieldPredictionRequest::query()->where('user_id', $user->id)->where('status', YieldPredictionRequest::DONE)->find((int) $request->query('prediction'))
            : null;
        $asked = (string) $request->query('about');

        return view('farmer-records.survey', [
            'advisory' => $advisory,
            'prediction' => $prediction,
            'subject' => match (true) {
                $advisory !== null => FarmerSurveyResponse::SUBJECT_ADVISORY,
                $prediction !== null => FarmerSurveyResponse::SUBJECT_PREDICTION,
                in_array($asked, FarmerSurveyResponse::SUBJECTS, true) => $asked,
                default => FarmerSurveyResponse::SUBJECT_PREDICTION,
            },
            'mine' => ModelFeedbackService::ready()
                ? FarmerSurveyResponse::query()->where('user_id', $user->id)->with('advisory:id,title')->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scale = ['required', 'integer', 'between:1,5'];
        $data = $request->validate([
            'subject' => ['required', Rule::in(FarmerSurveyResponse::SUBJECTS)],
            'useful' => $scale,
            'accurate' => $scale,
            'helpful' => $scale,
            'comment' => ['nullable', 'string', 'max:1000'],
            'planting_advisory_id' => ['nullable', 'integer', Rule::exists('planting_advisories', 'id')->where('status', PlantingAdvisory::STATUS_PUBLISHED)],
            // Only the farmer's own finished prediction can be answered about.
            'yield_prediction_request_id' => ['nullable', 'integer', Rule::exists('yield_prediction_requests', 'id')->where('user_id', $user->id)],
        ]);

        // The related item belongs to its subject only: an advisory answer keeps the advisory, a prediction answer the prediction.
        $advisoryId = $data['subject'] === FarmerSurveyResponse::SUBJECT_ADVISORY ? ($data['planting_advisory_id'] ?? null) : null;
        $predictionId = $data['subject'] === FarmerSurveyResponse::SUBJECT_PREDICTION ? ($data['yield_prediction_request_id'] ?? null) : null;
        $today = BusinessClock::today()->toDateString();

        // One answer per farmer, subject and item per day: answering again the same day corrects the earlier answer.
        $response = FarmerSurveyResponse::query()
            ->where('user_id', $user->id)->where('subject', $data['subject'])->whereDate('answered_on', $today)
            ->where('planting_advisory_id', $advisoryId)->where('yield_prediction_request_id', $predictionId)
            ->first() ?? new FarmerSurveyResponse(['user_id' => $user->id, 'subject' => $data['subject'], 'answered_on' => $today,
                'planting_advisory_id' => $advisoryId, 'yield_prediction_request_id' => $predictionId,
                'category' => FarmerSurveyResponse::SUBJECT_CATEGORY[$data['subject']]]);

        $response->fill([
            'useful' => $data['useful'], 'accurate' => $data['accurate'], 'helpful' => $data['helpful'],
            'comment' => filled($data['comment'] ?? null) ? trim((string) $data['comment']) : null,
            'barangay' => $user->registeredBarangay(),
            'model_version' => ModelFeedbackService::modelVersion(),
            // A changed answer is read again by the IT Expert.
            'reviewed_at' => null, 'reviewed_by' => null,
        ])->save();

        return redirect()->route('farmer.survey.show')->with('success', __('farmer.survey.thanks'));
    }
}
