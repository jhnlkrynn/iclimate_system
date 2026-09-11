<?php

namespace Tests\Unit;

use App\Support\HeatMapLegend;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HeatMapLegendTest extends TestCase
{
    #[DataProvider('scoreProvider')]
    public function test_every_risk_score_receives_the_correct_label_and_color(mixed $score, string $level, string $label, string $color): void
    {
        $legend = HeatMapLegend::fromScore($score);

        $this->assertSame($level, $legend['level']);
        $this->assertSame($label, $legend['label']);
        $this->assertSame($color, $legend['color']);
    }

    public static function scoreProvider(): array
    {
        return [
            'missing score' => [null, 'Insufficient Data', 'Insufficient Data', '#6b7280'],
            'invalid score' => ['not-a-score', 'Insufficient Data', 'Insufficient Data', '#6b7280'],
            'zero is low' => [0.00, 'Low', 'Low Risk', '#2c7bb6'],
            'below moderate boundary' => [0.44, 'Low', 'Low Risk', '#2c7bb6'],
            'moderate boundary' => [0.45, 'Moderate', 'Moderate Risk', '#fff34d'],
            'below high boundary' => [0.67, 'Moderate', 'Moderate Risk', '#fff34d'],
            'high boundary' => [0.68, 'High', 'High Risk', '#fdae21'],
            'below severe boundary' => [0.84, 'High', 'High Risk', '#fdae21'],
            'severe boundary' => [0.85, 'Severe', 'Critical/Severe Risk', '#d7191c'],
            'maximum score' => [1.00, 'Severe', 'Critical/Severe Risk', '#d7191c'],
        ];
    }
}
