<?php

namespace App\Services\Advisory;

use App\Models\PlantingAdvisory;

/**
 * First-pass, rule-based verification signal for a published advisory,
 * combining farmer feedback into a single trust badge.
 *
 * This is deliberately a single injectable service with one score() entry
 * point so a future PAGASA-trained model (or one blending in a real
 * forecast-vs-actual comparison once enough historical ClimateRecord data
 * exists) can replace the internals later without any UI changes.
 */
class AdvisoryVerificationService
{
    private const MIN_FEEDBACK_FOR_VERIFICATION = 3;

    private const VERIFIED_THRESHOLD_PERCENT = 60;

    private const NEEDS_REVIEW_THRESHOLD_PERCENT = 40;

    /**
     * @return array{badge: string, tone: string, helpful_percent: ?float, feedback_count: int, detail: string}
     */
    public function score(PlantingAdvisory $advisory): array
    {
        $feedback = $advisory->relationLoaded('feedback') ? $advisory->feedback : $advisory->feedback()->get();
        $rated = $feedback->whereNotNull('is_helpful');
        $feedbackCount = $rated->count();

        if ($feedbackCount === 0) {
            return [
                'badge' => 'Unverified',
                'tone' => 'secondary',
                'helpful_percent' => null,
                'feedback_count' => 0,
                'detail' => 'No farmer feedback has been submitted for this advisory yet.',
            ];
        }

        $helpfulPercent = round(($rated->where('is_helpful', true)->count() / $feedbackCount) * 100, 1);

        if ($feedbackCount >= self::MIN_FEEDBACK_FOR_VERIFICATION && $helpfulPercent >= self::VERIFIED_THRESHOLD_PERCENT) {
            return [
                'badge' => 'Community-verified',
                'tone' => 'success',
                'helpful_percent' => $helpfulPercent,
                'feedback_count' => $feedbackCount,
                'detail' => $helpfulPercent.'% of '.$feedbackCount.' farmer(s) marked this advisory helpful.',
            ];
        }

        if ($helpfulPercent < self::NEEDS_REVIEW_THRESHOLD_PERCENT) {
            return [
                'badge' => 'Needs review',
                'tone' => 'danger',
                'helpful_percent' => $helpfulPercent,
                'feedback_count' => $feedbackCount,
                'detail' => 'Only '.$helpfulPercent.'% of '.$feedbackCount.' farmer(s) found this advisory helpful — MAO should review it.',
            ];
        }

        return [
            'badge' => 'Unverified',
            'tone' => 'secondary',
            'helpful_percent' => $helpfulPercent,
            'feedback_count' => $feedbackCount,
            'detail' => $feedbackCount.' farmer(s) have responded so far ('.$helpfulPercent.'% helpful) — not yet enough feedback for a confident verification.',
        ];
    }
}
