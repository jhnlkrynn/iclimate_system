<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): one farmer's answers about a prediction, the weather forecast,
 * an advisory or the system (see the migration). Stored answers only: nothing here retrains or replaces a model.
 */
class FarmerSurveyResponse extends Model
{
    public const SUBJECT_PREDICTION = 'prediction';

    public const SUBJECT_WEATHER = 'weather';

    public const SUBJECT_ADVISORY = 'advisory';

    public const SUBJECT_SYSTEM = 'system';

    public const SUBJECTS = [self::SUBJECT_PREDICTION, self::SUBJECT_WEATHER, self::SUBJECT_ADVISORY, self::SUBJECT_SYSTEM];

    public const CATEGORY_MODEL = 'ml_model';

    public const CATEGORY_WEATHER = 'weather_data';

    public const CATEGORY_PREDICTIONS = 'predictions';

    public const CATEGORY_ADVISORIES = 'advisories';

    /** Category => the name the IT Expert reads. */
    public const CATEGORIES = [
        self::CATEGORY_MODEL => 'ML model',
        self::CATEGORY_WEATHER => 'Weather data',
        self::CATEGORY_PREDICTIONS => 'Predictions',
        self::CATEGORY_ADVISORIES => 'Advisories',
    ];

    /** The category a new answer starts in; the IT Expert can move it. */
    public const SUBJECT_CATEGORY = [
        self::SUBJECT_PREDICTION => self::CATEGORY_PREDICTIONS,
        self::SUBJECT_WEATHER => self::CATEGORY_WEATHER,
        self::SUBJECT_ADVISORY => self::CATEGORY_ADVISORIES,
        self::SUBJECT_SYSTEM => self::CATEGORY_MODEL,
    ];

    /** The three questions, each answered 1 to 5. */
    public const QUESTIONS = ['useful', 'accurate', 'helpful'];

    /** An answer's average from this up is positive; down to NEGATIVE_UP_TO is negative; between is neutral. */
    public const POSITIVE_FROM = 4.0;

    public const NEGATIVE_UP_TO = 2.0;

    protected $fillable = [
        'user_id', 'subject', 'category', 'planting_advisory_id', 'yield_prediction_request_id', 'barangay',
        'useful', 'accurate', 'helpful', 'comment', 'model_version', 'answered_on', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'useful' => 'integer',
        'accurate' => 'integer',
        'helpful' => 'integer',
        'answered_on' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(PlantingAdvisory::class, 'planting_advisory_id');
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(YieldPredictionRequest::class, 'yield_prediction_request_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The average of the three answers, 1.0 to 5.0. */
    public function score(): float
    {
        return round(($this->useful + $this->accurate + $this->helpful) / 3, 2);
    }

    /** positive | neutral | negative */
    public function sentiment(): string
    {
        return self::sentimentOf($this->score());
    }

    public static function sentimentOf(float $score): string
    {
        return match (true) {
            $score >= self::POSITIVE_FROM => 'positive',
            $score <= self::NEGATIVE_UP_TO => 'negative',
            default => 'neutral',
        };
    }
}
