<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single respondent's System Usability Scale (SUS) submission — the
 * standard 10-item, 5-point Likert instrument (Brooke, 1996) used to
 * satisfy the "assessing the system's functionality and usability" half
 * of the study's Objective 4. The other half (predictive-model accuracy)
 * is handled separately by ModelEvaluationService.
 *
 * This class provides the instrument and the scoring/reporting
 * infrastructure. It cannot manufacture real respondents — actually
 * collecting responses from real farmers/MAO/IT staff is fieldwork for
 * the research team, same as the real MAO/PAGASA historical dataset.
 */
class UsabilitySurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'item_1',
        'item_2',
        'item_3',
        'item_4',
        'item_5',
        'item_6',
        'item_7',
        'item_8',
        'item_9',
        'item_10',
        'comments',
    ];

    protected $casts = [
        'item_1' => 'integer',
        'item_2' => 'integer',
        'item_3' => 'integer',
        'item_4' => 'integer',
        'item_5' => 'integer',
        'item_6' => 'integer',
        'item_7' => 'integer',
        'item_8' => 'integer',
        'item_9' => 'integer',
        'item_10' => 'integer',
    ];

    /**
     * The standard 10 SUS statements (Brooke, 1996), unaltered — changing
     * the wording would invalidate comparison against the published norms
     * used for adjectiveRating()/isAboveBenchmark(). `positive` marks the
     * odd-numbered items, which score high-agreement = good; the
     * even-numbered items are worded negatively and are reverse-scored in
     * score().
     *
     * @var array<int, array{text: string, positive: bool}>
     */
    public const SUS_ITEMS = [
        1 => ['text' => 'I think that I would like to use this system frequently.', 'positive' => true],
        2 => ['text' => 'I found the system unnecessarily complex.', 'positive' => false],
        3 => ['text' => 'I thought the system was easy to use.', 'positive' => true],
        4 => ['text' => 'I think that I would need the support of a technical person to be able to use this system.', 'positive' => false],
        5 => ['text' => 'I found the various functions in this system were well integrated.', 'positive' => true],
        6 => ['text' => 'I thought there was too much inconsistency in this system.', 'positive' => false],
        7 => ['text' => 'I would imagine that most people would learn to use this system very quickly.', 'positive' => true],
        8 => ['text' => 'I found the system very cumbersome/awkward to use.', 'positive' => false],
        9 => ['text' => 'I felt very confident using the system.', 'positive' => true],
        10 => ['text' => 'I needed to learn a lot of things before I could get going with this system.', 'positive' => false],
    ];

    /**
     * The published "industry average" SUS score (Sauro, 2011, based on a
     * meta-analysis of ~500 studies) — the standard benchmark cited when a
     * thesis needs to say whether its own mean score is above or below
     * average, not just report a raw number.
     */
    public const AVERAGE_BENCHMARK = 68.0;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The standard SUS scoring formula: for each item, convert the 1-5
     * response to a 0-4 contribution (subtracting 1 for positively-worded
     * items, or subtracting the response from 5 for negatively-worded
     * ones), sum all ten (0-40), then multiply by 2.5 to land on the
     * conventional 0-100 scale.
     */
    public function score(): float
    {
        $total = 0;

        foreach (self::SUS_ITEMS as $number => $item) {
            $response = (int) $this->{"item_{$number}"};
            $total += $item['positive'] ? ($response - 1) : (5 - $response);
        }

        return round($total * 2.5, 1);
    }

    public function isAboveBenchmark(): bool
    {
        return $this->score() >= self::AVERAGE_BENCHMARK;
    }

    /**
     * The Bangor, Kortum & Miller (2009) adjective rating scale — the
     * standard qualitative label attached to a SUS score range in
     * usability literature, so a raw number ("74.2") comes with the
     * interpretation a thesis would actually report ("Good").
     */
    public static function adjectiveRating(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Best Imaginable',
            $score >= 80 => 'Excellent',
            $score >= 68 => 'Good',
            $score >= 51 => 'OK',
            $score >= 25 => 'Poor',
            default => 'Worst Imaginable',
        };
    }
}
