<?php

namespace App\Services\Risk;

final class AgriculturalRiskScorer
{
    private const BARANGAY_EXPOSURE = [
        'Bagong Pook' => 0.55,
        'Balibago' => 0.70,
        'Barangay 1 (Pob.)' => 0.50,
        'Barangay 2 (Pob.)' => 0.50,
        'Barangay 3 (Pob.)' => 0.55,
        'Barangay 4 (Pob.)' => 0.55,
        'Barangay 5 (Pob.)' => 0.50,
        'Binubusan' => 0.45,
        'Bungahan' => 0.65,
        'Cumba' => 0.80,
        'Humayingan' => 0.75,
        'Kapito' => 0.70,
        'Lumaniag' => 0.55,
        'Luyahan' => 0.75,
        'Malaruhatan' => 0.80,
        'Matabungkay' => 0.90,
        'Prenza' => 0.65,
        'Puting-Kahoy' => 0.75,
        'San Diego' => 0.85,
    ];

    /**
     * @param  array{rainfall: float, temperature: float, predicted_yield: ?float, farm_type: string, barangay: string, stress_factor_count: int}  $input
     * @return array{score: float, level: string, label: string, color: string}
     */
    public function score(array $input): array
    {
        $rainfall = (float) ($input['rainfall'] ?? 0);
        $temperature = (float) ($input['temperature'] ?? 0);
        $yield = $input['predicted_yield'] ?? null;
        $yield = $yield !== null ? (float) $yield : null;
        $farmType = (string) ($input['farm_type'] ?? 'Rainfed');
        $barangay = (string) ($input['barangay'] ?? '');
        $stressFactorCount = (int) ($input['stress_factor_count'] ?? 0);

        $rainfallRisk = match (true) {
            $rainfall <= 0 => 0.60,
            $rainfall < 80 => 0.95,
            $rainfall < 120 => 0.75,
            $rainfall <= 280 => 0.22,
            $rainfall <= 350 => 0.62,
            default => 0.92,
        };

        $yieldRisk = match (true) {
            $yield === null => 0.55,
            $yield < 2 => 0.95,
            $yield < 3 => 0.78,
            $yield < 4 => 0.48,
            default => 0.18,
        };

        $temperatureRisk = match (true) {
            $temperature >= 35 => 0.86,
            $temperature >= 32 => 0.64,
            $temperature < 22 && $temperature > 0 => 0.52,
            default => 0.24,
        };

        $exposure = self::BARANGAY_EXPOSURE[$barangay] ?? 0.55;
        $farmExposure = strtolower($farmType) === 'rainfed' ? 0.10 : 0.03;
        $stressPenalty = min(0.12, $stressFactorCount * 0.025);

        $score = ($rainfallRisk * 0.34)
            + ($yieldRisk * 0.27)
            + ($temperatureRisk * 0.14)
            + ($exposure * 0.18)
            + $farmExposure
            + $stressPenalty;

        $score = round(max(0.05, min(0.98, $score)), 2);
        $level = $this->levelFromScore($score);

        return [
            'score' => $score,
            'level' => $level,
            'label' => $level.' Risk',
            'color' => match ($level) {
                'Severe', 'High' => 'red',
                'Moderate' => 'yellow',
                default => 'green',
            },
        ];
    }

    public function exposureLabel(string $barangay): string
    {
        $exposure = self::BARANGAY_EXPOSURE[$barangay] ?? 0.55;

        return match (true) {
            $exposure >= 0.80 => 'high',
            $exposure >= 0.60 => 'moderate',
            default => 'low',
        };
    }

    private function levelFromScore(float $score): string
    {
        return match (true) {
            $score >= 0.85 => 'Severe',
            $score >= 0.68 => 'High',
            $score >= 0.45 => 'Moderate',
            default => 'Low',
        };
    }
}
