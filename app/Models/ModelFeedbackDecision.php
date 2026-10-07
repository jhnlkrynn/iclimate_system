<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): the IT Expert's decision for the rice-yield model after reading
 * the farmers' feedback. The system only recommends; this row is the approval.
 */
class ModelFeedbackDecision extends Model
{
    public const MAINTAIN = 'maintain';

    public const NEEDS_RETRAINING = 'needs_retraining';

    /** Shown while there are too few answers to say either way. */
    public const NOT_ENOUGH = 'not_enough';

    public const LABELS = [
        self::MAINTAIN => 'Maintain Current Model',
        self::NEEDS_RETRAINING => 'Needs Retraining',
        self::NOT_ENOUGH => 'Not enough feedback yet',
    ];

    protected $fillable = [
        'decision', 'recommended', 'model_version', 'responses', 'positive', 'negative', 'retrain_algorithm', 'note', 'decided_by',
    ];

    protected $casts = ['responses' => 'integer', 'positive' => 'integer', 'negative' => 'integer'];

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->decision] ?? $this->decision;
    }
}
