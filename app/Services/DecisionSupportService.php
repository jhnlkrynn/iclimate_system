<?php

namespace App\Services;

class DecisionSupportService
{
    public function evaluate(array $input): array
    {
        $rainfall = (float) ($input['rainfall'] ?? 0);
        $windSpeed = (float) ($input['wind_speed'] ?? 0);
        $humidity = (float) ($input['humidity'] ?? 0);
        $yield = $input['predicted_yield'] ?? null;
        $yield = $yield !== null ? (float) $yield : null;
        $season = (string) ($input['season'] ?? 'Wet');
        $farmType = (string) ($input['farm_type'] ?? 'Rainfed');
        $barangay = (string) ($input['barangay'] ?? '');

        $planting = $this->plantingAdvisory($rainfall, $season, $yield);
        $irrigation = $this->irrigationRecommendation($farmType, $rainfall);
        $weatherWarnings = $this->weatherWarnings($rainfall, $windSpeed, $humidity);
        $yieldAdvisory = $this->yieldAdvisory($yield);
        $risk = $this->riskLevel($rainfall, $yield);
        $score = $this->decisionSupportScore($rainfall, $yield, $season, $farmType);
        $stressFactors = $this->stressFactors($rainfall, $windSpeed, $humidity, $yield, $season, $farmType);
        $confidence = $this->confidenceLevel($rainfall, $yield, $stressFactors);
        $notifications = $this->notifications($planting, $irrigation, $weatherWarnings, $yieldAdvisory);

        return [
            'barangay' => $barangay,
            'farm_type' => $farmType,
            'weather' => [
                'rainfall' => round($rainfall, 2),
                'wind_speed' => round($windSpeed, 2),
                'humidity' => round($humidity, 2),
                'summary' => $this->weatherSummary($rainfall, $windSpeed, $humidity),
                'warnings' => $weatherWarnings,
            ],
            'planting' => $planting,
            'irrigation' => $irrigation,
            'yield' => $yieldAdvisory,
            'risk' => $risk,
            'score' => $score,
            'stress_factors' => $stressFactors,
            'confidence' => $confidence,
            'notifications' => $notifications,
            'overall_recommendation' => $this->overallRecommendation($barangay, $planting, $irrigation, $weatherWarnings, $yieldAdvisory, $risk, $score),
        ];
    }

    private function plantingAdvisory(float $rainfall, string $season, ?float $yield): array
    {
        $recommendations = [];

        if ($rainfall >= 180 && $rainfall <= 280) {
            $recommendations[] = 'Ideal planting period.';
        } elseif ($rainfall >= 281 && $rainfall <= 350) {
            $recommendations[] = 'Plant with caution and monitor field drainage.';
        } elseif ($rainfall > 350) {
            $recommendations[] = 'Delay planting due to flood risk.';
        } elseif ($rainfall < 80) {
            $recommendations[] = 'Wait for sufficient rainfall before planting.';
        }

        if ($yield !== null && $yield < 3) {
            $recommendations[] = 'Consider adjusting the planting schedule because expected yield is low.';
        }

        if (strtolower($season) === 'dry' && $rainfall < 100) {
            $recommendations[] = 'Delay planting during dry season until rainfall improves.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Planting is possible with routine weather monitoring.';
        }

        return [
            'module' => 'Planting Advisory',
            'recommendation' => implode(' ', $recommendations),
            'items' => $recommendations,
        ];
    }

    private function irrigationRecommendation(string $farmType, float $rainfall): array
    {
        $type = strtolower($farmType);

        if ($type === 'rainfed' && $rainfall < 100) {
            $recommendation = 'Increase irrigation or provide supplemental water support.';
        } elseif ($type === 'rainfed' && $rainfall <= 180) {
            $recommendation = 'Monitor soil moisture closely.';
        } elseif ($type === 'rainfed' && $rainfall > 250) {
            $recommendation = 'Irrigation is not necessary right now.';
        } elseif ($type === 'irrigated' && $rainfall > 300) {
            $recommendation = 'Reduce irrigation to avoid waterlogging.';
        } elseif ($type === 'irrigated' && $rainfall < 150) {
            $recommendation = 'Maintain the irrigation schedule.';
        } else {
            $recommendation = 'Maintain normal irrigation monitoring.';
        }

        return [
            'module' => 'Irrigation Recommendation',
            'recommendation' => $recommendation,
        ];
    }

    private function weatherWarnings(float $rainfall, float $windSpeed, float $humidity): array
    {
        $warnings = [];

        if ($rainfall > 400) {
            $warnings[] = 'Possible flooding.';
        } elseif ($rainfall > 300) {
            $warnings[] = 'Heavy rainfall warning.';
        } elseif ($rainfall < 100) {
            $warnings[] = 'Drought warning.';
        }

        if ($windSpeed > 20) {
            $warnings[] = 'Strong wind advisory.';
        }

        if ($humidity > 90) {
            $warnings[] = 'High humidity alert.';
        }

        return $warnings;
    }

    private function yieldAdvisory(?float $yield): array
    {
        if ($yield === null) {
            return [
                'module' => 'Yield Advisory',
                'predicted_yield' => null,
                'advisory' => 'No predicted yield is available yet.',
            ];
        }

        if ($yield < 2) {
            $advisory = 'Immediate agricultural assistance recommended.';
        } elseif ($yield < 3) {
            $advisory = 'Low expected yield.';
        } elseif ($yield < 4) {
            $advisory = 'Average expected yield.';
        } else {
            $advisory = 'High expected yield.';
        }

        return [
            'module' => 'Yield Advisory',
            'predicted_yield' => round($yield, 2),
            'advisory' => $advisory,
        ];
    }

    private function riskLevel(float $rainfall, ?float $yield): array
    {
        if (($yield !== null && $yield < 3) || $rainfall > 350) {
            return ['level' => 'High', 'label' => 'High Risk', 'color' => 'red'];
        }

        if ($rainfall > 300) {
            return ['level' => 'Moderate', 'label' => 'Moderate Risk', 'color' => 'yellow'];
        }

        if ($rainfall >= 180 && $rainfall <= 280 && ($yield === null || $yield > 4)) {
            return ['level' => 'Low', 'label' => 'Low Risk', 'color' => 'green'];
        }

        return ['level' => 'Moderate', 'label' => 'Moderate Risk', 'color' => 'yellow'];
    }

    private function stressFactors(float $rainfall, float $windSpeed, float $humidity, ?float $yield, string $season, string $farmType): array
    {
        $factors = [];

        if ($rainfall < 80) {
            $factors[] = [
                'label' => 'Drought stress',
                'severity' => 'High',
                'detail' => 'Rainfall is below the safer range for rice establishment.',
            ];
        } elseif ($rainfall < 120) {
            $factors[] = [
                'label' => 'Limited rainfall',
                'severity' => 'Moderate',
                'detail' => 'Rainfed fields may need supplemental water or delayed planting.',
            ];
        } elseif ($rainfall > 350) {
            $factors[] = [
                'label' => 'Flooding risk',
                'severity' => 'High',
                'detail' => 'Rainfall is high enough to require drainage monitoring.',
            ];
        } elseif ($rainfall > 280) {
            $factors[] = [
                'label' => 'Wet field risk',
                'severity' => 'Moderate',
                'detail' => 'Field drainage should be checked before planting.',
            ];
        }

        if ($windSpeed > 20) {
            $factors[] = [
                'label' => 'Strong wind',
                'severity' => 'Moderate',
                'detail' => 'Wind may increase lodging or storm-related field damage.',
            ];
        }

        if ($humidity > 90) {
            $factors[] = [
                'label' => 'Disease pressure',
                'severity' => 'Moderate',
                'detail' => 'High humidity may increase pest and disease monitoring needs.',
            ];
        }

        if ($yield !== null && $yield < 3) {
            $factors[] = [
                'label' => 'Low yield outlook',
                'severity' => 'High',
                'detail' => 'Predicted yield is below the preferred planning threshold.',
            ];
        } elseif ($yield !== null && $yield < 4) {
            $factors[] = [
                'label' => 'Average yield outlook',
                'severity' => 'Moderate',
                'detail' => 'Yield is workable but should be monitored with climate conditions.',
            ];
        }

        if (strtolower($season) === 'dry' && strtolower($farmType) === 'rainfed' && $rainfall < 120) {
            $factors[] = [
                'label' => 'Dry-season rainfed exposure',
                'severity' => 'High',
                'detail' => 'Rainfed farms are more vulnerable when dry-season rainfall is low.',
            ];
        }

        return $factors;
    }

    private function confidenceLevel(float $rainfall, ?float $yield, array $stressFactors): array
    {
        $confidence = 82;

        if ($yield === null) {
            $confidence -= 18;
        }

        if ($rainfall <= 0) {
            $confidence -= 22;
        }

        $highFactors = count(array_filter($stressFactors, fn (array $factor): bool => $factor['severity'] === 'High'));
        $confidence -= $highFactors * 6;
        $confidence = max(35, min(95, $confidence));

        return [
            'value' => $confidence,
            'label' => match (true) {
                $confidence >= 80 => 'High confidence',
                $confidence >= 60 => 'Moderate confidence',
                default => 'Low confidence',
            },
            'note' => $confidence >= 80
                ? 'Inputs are complete and the recommendation is stable.'
                : 'Use this recommendation with field observation and MAO validation.',
        ];
    }

    private function decisionSupportScore(float $rainfall, ?float $yield, string $season, string $farmType): array
    {
        $score = 100;

        if ($rainfall < 80 || $rainfall > 350) {
            $score -= 35;
        } elseif ($rainfall < 100 || $rainfall > 300) {
            $score -= 25;
        } elseif ($rainfall < 180 || $rainfall > 280) {
            $score -= 10;
        }

        if ($yield !== null) {
            if ($yield < 2) {
                $score -= 35;
            } elseif ($yield < 3) {
                $score -= 25;
            } elseif ($yield < 4) {
                $score -= 10;
            }
        } else {
            $score -= 10;
        }

        if (strtolower($season) === 'dry' && $rainfall < 100) {
            $score -= 15;
        }

        if (strtolower($farmType) === 'rainfed' && $rainfall < 100) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        return [
            'value' => $score,
            'interpretation' => match (true) {
                $score >= 90 => 'Excellent planting conditions',
                $score >= 70 => 'Good planting conditions',
                $score >= 50 => 'Moderate risk',
                default => 'High risk',
            },
        ];
    }

    private function notifications(array $planting, array $irrigation, array $weatherWarnings, array $yieldAdvisory): array
    {
        $notifications = $weatherWarnings;

        foreach ($planting['items'] as $item) {
            if (str_contains(strtolower($item), 'delay') || str_contains(strtolower($item), 'wait')) {
                $notifications[] = $item;
            }
        }

        if (str_contains(strtolower($irrigation['recommendation']), 'increase')) {
            $notifications[] = $irrigation['recommendation'];
        }

        if (str_contains(strtolower($yieldAdvisory['advisory']), 'low') || str_contains(strtolower($yieldAdvisory['advisory']), 'assistance')) {
            $notifications[] = $yieldAdvisory['advisory'];
        }

        return array_values(array_unique($notifications));
    }

    private function weatherSummary(float $rainfall, float $windSpeed, float $humidity): string
    {
        if ($rainfall > 300) {
            return 'Heavy rainfall is expected.';
        }

        if ($rainfall < 100) {
            return 'Rainfall may be limited.';
        }

        if ($windSpeed > 20) {
            return 'Wind conditions need monitoring.';
        }

        if ($humidity > 90) {
            return 'Humidity is high and disease pressure may increase.';
        }

        return 'Weather conditions are generally workable.';
    }

    private function overallRecommendation(string $barangay, array $planting, array $irrigation, array $weatherWarnings, array $yield, array $risk, array $score): array
    {
        return [
            'barangay' => $barangay,
            'weather' => $weatherWarnings[0] ?? 'No major weather warning.',
            'yield' => $yield['predicted_yield'] !== null ? number_format((float) $yield['predicted_yield'], 2).' tons/hectare' : 'No yield prediction yet.',
            'planting' => $planting['recommendation'],
            'irrigation' => $irrigation['recommendation'],
            'risk_level' => $risk['label'],
            'score' => $score['value'].' - '.$score['interpretation'],
        ];
    }
}
