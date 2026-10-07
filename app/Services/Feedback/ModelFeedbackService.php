<?php

namespace App\Services\Feedback;

use App\Models\FarmerSurveyResponse;
use App\Models\MlModel;
use App\Models\ModelFeedbackDecision;
use App\Models\YieldPredictionRequest;
use App\Services\Risk\BarangayWeekRiskService;
use App\Support\BusinessClock;
use App\Support\SchemaCache;
use App\Support\YieldCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): reads the farmers' survey answers for the IT Expert.
 *
 *   Farmer → Survey → IT Expert → Model Evaluation → Maintain or Retrain → Updated Insights
 *
 * Everything is counted from saved rows each time it is asked for, so a new survey answer, prediction, forecast or
 * model result shows on the next read (the Model Feedback page re-reads its Insights every few seconds). No provider is
 * called and no model is run here. The service only RECOMMENDS: a model is never retrained or replaced without the IT
 * Expert's decision (ModelFeedbackDecision), and activating a model stays on Model Registry.
 *
 * The rule, on the answers about the model and its predictions from the last WINDOW_DAYS days:
 *   - fewer than MIN_RESPONSES answers            → "Not enough feedback yet" (no recommendation is made from a handful);
 *   - RETRAIN_NEGATIVE_PERCENT % or more negative,
 *     or an average "accurate" answer of
 *     RETRAIN_ACCURACY_UP_TO or lower             → "Needs Retraining";
 *   - otherwise                                   → "Maintain Current Model".
 * An answer is positive when its three answers average 4 or more, negative when they average 2 or less.
 */
class ModelFeedbackService
{
    public const WINDOW_DAYS = 90;

    public const MIN_RESPONSES = 5;

    public const RETRAIN_NEGATIVE_PERCENT = 40;

    public const RETRAIN_ACCURACY_UP_TO = 2.5;

    /** The categories that speak about the model itself. */
    public const MODEL_CATEGORIES = [FarmerSurveyResponse::CATEGORY_MODEL, FarmerSurveyResponse::CATEGORY_PREDICTIONS];

    /** A barangay with this many negative answers in the window is listed under "areas needing attention". */
    public const ATTENTION_NEGATIVE_ANSWERS = 2;

    public function __construct(private readonly BarangayWeekRiskService $weekRisk) {}

    public static function ready(): bool
    {
        return SchemaCache::hasTable('farmer_survey_responses') && SchemaCache::hasTable('model_feedback_decisions');
    }

    /** @return Collection<int, FarmerSurveyResponse> the answers of the window, newest first */
    public function responses(): Collection
    {
        return FarmerSurveyResponse::query()
            ->where('created_at', '>=', CarbonImmutable::now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    /**
     * Counts, percentages and the average of each question for a set of answers.
     *
     * @param  Collection<int, FarmerSurveyResponse>  $rows
     * @return array{total: int, positive: int, neutral: int, negative: int, positive_percent: ?int, negative_percent: ?int, useful: ?float, accurate: ?float, helpful: ?float}
     */
    public function tally(Collection $rows): array
    {
        $total = $rows->count();
        $by = $rows->countBy(fn (FarmerSurveyResponse $r): string => $r->sentiment());
        $average = fn (string $question): ?float => $total === 0 ? null : round((float) $rows->avg($question), 2);

        return [
            'total' => $total,
            'positive' => (int) ($by['positive'] ?? 0),
            'neutral' => (int) ($by['neutral'] ?? 0),
            'negative' => (int) ($by['negative'] ?? 0),
            'positive_percent' => $total === 0 ? null : (int) round(($by['positive'] ?? 0) / $total * 100),
            'negative_percent' => $total === 0 ? null : (int) round(($by['negative'] ?? 0) / $total * 100),
            'useful' => $average('useful'),
            'accurate' => $average('accurate'),
            'helpful' => $average('helpful'),
        ];
    }

    /**
     * Everything the Model Feedback page shows above the list: all answers, each category, and the model's status.
     *
     * @return array<string, mixed>
     */
    public function summary(?Collection $rows = null): array
    {
        $rows ??= $this->responses();
        $categories = [];
        foreach (FarmerSurveyResponse::CATEGORIES as $key => $label) {
            $categories[$key] = ['label' => $label] + $this->tally($rows->where('category', $key));
        }

        return [
            'window_days' => self::WINDOW_DAYS,
            'all' => $this->tally($rows),
            'categories' => $categories,
            'unreviewed' => $rows->whereNull('reviewed_at')->count(),
            'model' => $this->modelStatus($rows),
        ];
    }

    /**
     * What the feedback says about the rice-yield model, and what the IT Expert last decided.
     *
     * @return array<string, mixed>
     */
    public function modelStatus(?Collection $rows = null): array
    {
        $rows ??= $this->responses();
        $about = $rows->whereIn('category', self::MODEL_CATEGORIES);
        $tally = $this->tally($about);

        $recommended = match (true) {
            $tally['total'] < self::MIN_RESPONSES => ModelFeedbackDecision::NOT_ENOUGH,
            $tally['negative_percent'] >= self::RETRAIN_NEGATIVE_PERCENT, $tally['accurate'] <= self::RETRAIN_ACCURACY_UP_TO => ModelFeedbackDecision::NEEDS_RETRAINING,
            default => ModelFeedbackDecision::MAINTAIN,
        };
        $reason = match ($recommended) {
            ModelFeedbackDecision::NOT_ENOUGH => $tally['total'].' of the '.self::MIN_RESPONSES.' answers needed in the last '.self::WINDOW_DAYS.' days.',
            ModelFeedbackDecision::NEEDS_RETRAINING => $tally['negative_percent'].'% of '.$tally['total'].' answers are negative; farmers rate accuracy '.number_format((float) $tally['accurate'], 1).' of 5.',
            default => $tally['positive_percent'].'% of '.$tally['total'].' answers are positive; farmers rate accuracy '.number_format((float) $tally['accurate'], 1).' of 5.',
        };

        $active = rescue(fn () => MlModel::query()->where('is_active', true)->first(), null, false);
        $decision = ModelFeedbackDecision::query()->with('decidedBy:id,name')->latest('id')->first();
        $newSince = $decision ? $about->filter(fn (FarmerSurveyResponse $r): bool => $r->created_at->gt($decision->created_at))->count() : $tally['total'];

        return [
            'recommended' => $recommended,
            'recommended_label' => ModelFeedbackDecision::LABELS[$recommended],
            'reason' => $reason,
            'tally' => $tally,
            'active' => $active ? ['name' => $active->name, 'version' => $active->version, 'type' => $active->model_type, 'rmse' => $active->rmse, 'mae' => $active->mae, 'r2' => $active->r2] : null,
            'version' => self::modelVersion($active),
            'decision' => $decision,
            // Answers about the model that arrived after the last decision: the IT Expert has not weighed them yet.
            'new_since_decision' => $newSince,
            // The status shown for the model: the IT Expert's decision once made, else the recommendation.
            'status' => $decision?->decision ?? $recommended,
            'status_label' => $decision ? $decision->label() : ModelFeedbackDecision::LABELS[$recommended],
            'approved' => $decision !== null,
        ];
    }

    /** The active model's version, or the deployed file's name while none is registered as active. */
    public static function modelVersion(?MlModel $active = null): string
    {
        $active ??= rescue(fn () => MlModel::query()->where('is_active', true)->first(), null, false);

        return $active?->version ?: 'rice_yield_model_final (deployed)';
    }

    /**
     * Live Insights: short, plain summaries built from the saved survey answers, predictions, the week's risk ratings
     * and the model's results. `signature` changes whenever any of them does.
     *
     * @return array{generated_at: string, signature: string, items: array<int, array{key: string, tone: string, title: string, text: string}>}
     */
    public function insights(): array
    {
        $rows = $this->responses();
        $summary = $this->summary($rows);
        $all = $summary['all'];
        $model = $summary['model'];
        $items = [];

        // 1. Model performance feedback
        $mt = $model['tally'];
        $error = rescue(fn (): array => YieldCorrections::summary(), ['count' => 0, 'error' => null], false);
        $items[] = [
            'key' => 'model',
            'tone' => $mt['total'] === 0 ? 'info' : ($model['recommended'] === ModelFeedbackDecision::NEEDS_RETRAINING ? 'danger' : 'good'),
            'title' => 'Model performance feedback',
            'text' => ($mt['total'] === 0
                ? 'No farmer has answered about the predictions yet.'
                : $mt['positive'].' positive and '.$mt['negative'].' negative of '.$mt['total'].' answers about the predictions ('.$mt['positive_percent'].'% positive). Accuracy is rated '.number_format((float) $mt['accurate'], 1).' of 5.')
                .($error['count'] > 0 ? ' In '.$error['count'].' recorded '.($error['count'] === 1 ? 'harvest' : 'harvests').' the prediction missed by '.number_format((float) $error['error'], 2).' t/ha on average.' : ''),
        ];

        // 2. Farmer satisfaction / usefulness
        $items[] = [
            'key' => 'satisfaction',
            'tone' => $all['total'] === 0 ? 'info' : ($all['negative_percent'] >= self::RETRAIN_NEGATIVE_PERCENT ? 'danger' : ($all['positive_percent'] >= 60 ? 'good' : 'watch')),
            'title' => 'Farmer satisfaction',
            'text' => $all['total'] === 0
                ? 'No survey answers in the last '.self::WINDOW_DAYS.' days.'
                : $all['positive_percent'].'% of '.$all['total'].' answers are positive. Useful '.number_format((float) $all['useful'], 1).', accurate '.number_format((float) $all['accurate'], 1).', helpful '.number_format((float) $all['helpful'], 1).' (of 5).',
        ];

        // 3. Current weather-related risks (the Heat Map's rating for this week)
        $risk = rescue(fn (): array => $this->weekRisk->forRange('week'), ['state' => 'nodata', 'barangays' => []], false);
        $high = collect($risk['barangays'] ?? [])->where('level', 'high')->keys();
        $moderate = collect($risk['barangays'] ?? [])->where('level', 'moderate')->keys();
        $items[] = [
            'key' => 'weather',
            'tone' => ($risk['state'] ?? '') !== 'ok' ? 'info' : ($high->isNotEmpty() ? 'danger' : ($moderate->isNotEmpty() ? 'watch' : 'good')),
            'title' => 'Current weather risks',
            'text' => match (true) {
                ($risk['state'] ?? '') !== 'ok' => 'No saved forecast to rate yet.',
                $high->isNotEmpty() => $high->count().' '.($high->count() === 1 ? 'barangay is' : 'barangays are').' at high risk this week: '.$high->take(5)->implode(', ').($high->count() > 5 ? ' and '.($high->count() - 5).' more' : '').'.',
                $moderate->isNotEmpty() => 'No barangay is at high risk this week; '.$moderate->count().' at moderate risk.',
                default => 'All barangays are at low risk this week.',
            },
        ];

        // 4. Areas needing attention: barangays whose farmers answered negatively, and feedback kinds going badly
        $badBarangays = $rows->filter(fn (FarmerSurveyResponse $r): bool => $r->sentiment() === 'negative' && filled($r->barangay))
            ->countBy('barangay')->filter(fn (int $count): bool => $count >= self::ATTENTION_NEGATIVE_ANSWERS)->sortDesc();
        $badCategories = collect($summary['categories'])->filter(fn (array $c): bool => $c['total'] >= self::MIN_RESPONSES && $c['negative_percent'] >= self::RETRAIN_NEGATIVE_PERCENT)->pluck('label');
        $attention = array_values(array_filter([
            $badBarangays->isNotEmpty() ? 'Negative answers from '.$badBarangays->map(fn (int $count, string $name): string => $name.' ('.$count.')')->implode(', ').'.' : null,
            $badCategories->isNotEmpty() ? 'Mostly negative: '.$badCategories->implode(', ').'.' : null,
            $summary['unreviewed'] > 0 ? $summary['unreviewed'].' '.($summary['unreviewed'] === 1 ? 'answer is' : 'answers are').' waiting for review.' : null,
        ]));
        $items[] = [
            'key' => 'attention',
            'tone' => $badBarangays->isNotEmpty() || $badCategories->isNotEmpty() ? 'danger' : ($summary['unreviewed'] > 0 ? 'watch' : 'good'),
            'title' => 'Areas needing attention',
            'text' => $attention === [] ? 'Nothing needs attention right now.' : implode(' ', $attention),
        ];

        // 5. Maintain, or review for retraining
        $decision = $model['decision'];
        $items[] = [
            'key' => 'recommendation',
            'tone' => match ($model['recommended']) {
                ModelFeedbackDecision::NEEDS_RETRAINING => 'danger',
                ModelFeedbackDecision::MAINTAIN => 'good',
                default => 'info',
            },
            'title' => 'Model: '.$model['recommended_label'],
            'text' => $model['reason'].' '.match (true) {
                $decision === null && $model['recommended'] === ModelFeedbackDecision::NEEDS_RETRAINING => 'Waiting for the IT Expert to review before any retraining.',
                $decision === null => 'No IT decision recorded yet.',
                default => 'IT decision on '.$decision->created_at->copy()->timezone(BusinessClock::timezone())->format('M j, Y').': '.$decision->label().'.'
                    .($model['new_since_decision'] > 0 ? ' '.$model['new_since_decision'].' new '.($model['new_since_decision'] === 1 ? 'answer' : 'answers').' since then.' : ''),
            },
        ];

        // 6. Predictions and model results on record
        $requests = rescue(fn (): array => SchemaCache::hasTable('yield_prediction_requests') ? [
            'week' => YieldPredictionRequest::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'failed' => YieldPredictionRequest::query()->where('created_at', '>=', now()->subDays(7))->where('status', YieldPredictionRequest::FAILED)->count(),
        ] : ['week' => 0, 'failed' => 0], ['week' => 0, 'failed' => 0], false);
        $latest = rescue(fn () => MlModel::query()->orderByDesc('trained_at')->orderByDesc('id')->first(), null, false);
        $items[] = [
            'key' => 'results',
            'tone' => $requests['failed'] > 0 ? 'watch' : 'info',
            'title' => 'Predictions and model results',
            'text' => $requests['week'].' yield '.($requests['week'] === 1 ? 'prediction' : 'predictions').' requested in the last 7 days'.($requests['failed'] > 0 ? ' ('.$requests['failed'].' failed)' : '').'. '
                .($model['active'] ? 'Active model: '.$model['active']['name'].' '.$model['active']['version'].($model['active']['rmse'] !== null ? ', RMSE '.number_format((float) $model['active']['rmse'], 3) : '').'.' : 'The deployed model is in use (none marked active in the registry).')
                .($latest && ! $latest->is_active ? ' Newest in the registry: '.$latest->name.' '.$latest->version.' ('.$latest->status.').' : ''),
        ];

        return [
            'generated_at' => CarbonImmutable::now(BusinessClock::timezone())->format('g:i:s A'),
            'signature' => md5(json_encode(array_column($items, 'text'))),
            'items' => $items,
        ];
    }
}
