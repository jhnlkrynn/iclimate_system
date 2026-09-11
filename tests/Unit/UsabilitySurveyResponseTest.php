<?php

namespace Tests\Unit;

use App\Models\UsabilitySurveyResponse;
use PHPUnit\Framework\TestCase;

class UsabilitySurveyResponseTest extends TestCase
{
    public function test_all_neutral_threes_score_fifty(): void
    {
        // Every item at the midpoint (3) should land exactly on the
        // midpoint of the 0-100 scale, regardless of which items are
        // reverse-scored — this is a useful sanity check on the formula
        // itself (positive: 3-1=2, negative: 5-3=2, either way 2/4 * 100 = 50).
        $response = $this->makeResponse(array_fill(1, 10, 3));

        $this->assertSame(50.0, $response->score());
    }

    public function test_all_strongly_agree_does_not_score_a_perfect_hundred(): void
    {
        // A naive (non-reverse-scored) implementation would wrongly give
        // "all 5s" a perfect score. Half the items are negatively worded,
        // so answering 5 ("strongly agree") to "I found the system
        // unnecessarily complex" should hurt the score, not help it.
        $response = $this->makeResponse(array_fill(1, 10, 5));

        // Positive items (5 of them) contribute (5-1)=4 each = 20.
        // Negative items (5 of them) contribute (5-5)=0 each = 0.
        // (20 + 0) * 2.5 = 50.
        $this->assertSame(50.0, $response->score());
    }

    public function test_best_possible_answers_score_one_hundred(): void
    {
        // Strongly agree (5) on positive items, strongly disagree (1) on
        // negative items — the actual best-case response pattern.
        $answers = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $answers[$number] = $item['positive'] ? 5 : 1;
        }
        $response = $this->makeResponse($answers);

        $this->assertSame(100.0, $response->score());
    }

    public function test_worst_possible_answers_score_zero(): void
    {
        $answers = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $answers[$number] = $item['positive'] ? 1 : 5;
        }
        $response = $this->makeResponse($answers);

        $this->assertSame(0.0, $response->score());
    }

    public function test_adjective_rating_matches_bangor_bands(): void
    {
        $this->assertSame('Best Imaginable', UsabilitySurveyResponse::adjectiveRating(95));
        $this->assertSame('Excellent', UsabilitySurveyResponse::adjectiveRating(85));
        $this->assertSame('Good', UsabilitySurveyResponse::adjectiveRating(70));
        $this->assertSame('OK', UsabilitySurveyResponse::adjectiveRating(60));
        $this->assertSame('Poor', UsabilitySurveyResponse::adjectiveRating(30));
        $this->assertSame('Worst Imaginable', UsabilitySurveyResponse::adjectiveRating(10));
    }

    public function test_isAboveBenchmark_reflects_the_published_industry_average(): void
    {
        $above = $this->makeResponse(array_fill(1, 10, 5)); // scores 50, below 68
        $this->assertFalse($above->isAboveBenchmark());

        $answers = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $answers[$number] = $item['positive'] ? 5 : 1;
        }
        $perfect = $this->makeResponse($answers); // scores 100
        $this->assertTrue($perfect->isAboveBenchmark());
    }

    /**
     * @param  array<int, int>  $answers
     */
    private function makeResponse(array $answers): UsabilitySurveyResponse
    {
        $response = new UsabilitySurveyResponse();

        foreach ($answers as $number => $value) {
            $response->{"item_{$number}"} = $value;
        }

        return $response;
    }
}
