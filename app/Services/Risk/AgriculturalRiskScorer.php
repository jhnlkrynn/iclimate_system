<?php

namespace App\Services\Risk;

/**
 * NOTE ON METHODOLOGY: BARANGAY_EXPOSURE below and the risk-weighting formula
 * in score() are engineering-judgment placeholder values, not derived from
 * cited hazard/flood-zone/elevation data. See docs/system_audit_fix_plan.md
 * (Phase 3/4) for the plan to replace these with real MDRRMO/LGU hazard data
 * once available. Do not present this as empirically-derived risk analysis
 * without disclosing that caveat to whoever is viewing it.
 */
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

    private const RAINFALL_WEIGHT = 0.34;

    private const YIELD_WEIGHT = 0.27;

    private const BARANGAY_EXPOSURE_WEIGHT = 0.18;

    private const TEMPERATURE_WEIGHT = 0.14;

    /**
     * The weighting/placeholder-data caveat from this class's own docblock,
     * reused verbatim so any user-facing explanation of the score carries it.
     */
    public const METHODOLOGY_CAVEAT = 'Barangay exposure ratings and these weights are engineering-judgment placeholders, not yet derived from validated MDRRMO/LGU hazard data. Treat this score as a planning aid, not a certified hazard assessment.';

    /**
     * @param  array{rainfall: float, temperature: float, predicted_yield: ?float, farm_type: string, barangay: string, stress_factor_count: int}  $input
     * @return array{score: float, level: string, label: string, color: string}
     */
    public function score(array $input): array
    {
        $c = $this->components($input);

        $score = ($c['rainfallRisk'] * self::RAINFALL_WEIGHT)
            + ($c['yieldRisk'] * self::YIELD_WEIGHT)
            + ($c['temperatureRisk'] * self::TEMPERATURE_WEIGHT)
            + ($c['exposure'] * self::BARANGAY_EXPOSURE_WEIGHT)
            + $c['farmExposure']
            + $c['stressPenalty'];

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

    /**
     * Breaks the score() computation down into labeled, weighted factors so
     * the UI can show *why* a score is what it is, using the exact same
     * inputs and formula — never a separately-maintained approximation.
     *
     * @param  array{rainfall: float, temperature: float, predicted_yield: ?float, farm_type: string, barangay: string, stress_factor_count: int}  $input
     * @return array{factors: list<array{key: string, label: string, weight_percent: ?int, risk_value: ?float, contribution: float, detail: string}>, caveat: string}
     */
    public function explain(array $input): array
    {
        $c = $this->components($input);

        $factors = [
            [
                'key' => 'rainfall',
                'label' => 'Rainfall level',
                'weight_percent' => (int) round(self::RAINFALL_WEIGHT * 100),
                'risk_value' => $c['rainfallRisk'],
                'contribution' => round($c['rainfallRisk'] * self::RAINFALL_WEIGHT, 3),
                'detail' => 'Based on '.number_format($c['rainfall'], 1).' mm of recorded/forecast rainfall.',
            ],
            [
                'key' => 'predicted_yield',
                'label' => 'Predicted rice yield',
                'weight_percent' => (int) round(self::YIELD_WEIGHT * 100),
                'risk_value' => $c['yieldRisk'],
                'contribution' => round($c['yieldRisk'] * self::YIELD_WEIGHT, 3),
                'detail' => $c['yield'] !== null
                    ? 'Based on a predicted yield of '.number_format($c['yield'], 2).' t/ha.'
                    : 'No yield prediction was available, so this factor defaults to a moderate risk value.',
            ],
            [
                'key' => 'barangay_exposure',
                'label' => 'Barangay exposure',
                'weight_percent' => (int) round(self::BARANGAY_EXPOSURE_WEIGHT * 100),
                'risk_value' => $c['exposure'],
                'contribution' => round($c['exposure'] * self::BARANGAY_EXPOSURE_WEIGHT, 3),
                'detail' => ($c['barangay'] !== '' ? $c['barangay'] : 'This barangay').' has a '.$this->exposureLabel($c['barangay']).' baseline exposure rating.',
            ],
            [
                'key' => 'temperature',
                'label' => 'Temperature',
                'weight_percent' => (int) round(self::TEMPERATURE_WEIGHT * 100),
                'risk_value' => $c['temperatureRisk'],
                'contribution' => round($c['temperatureRisk'] * self::TEMPERATURE_WEIGHT, 3),
                'detail' => 'Based on '.number_format($c['temperature'], 1).'°C recorded/forecast temperature.',
            ],
            [
                'key' => 'farm_type',
                'label' => 'Farm type adjustment',
                'weight_percent' => null,
                'risk_value' => null,
                'contribution' => round($c['farmExposure'], 3),
                'detail' => strtolower($c['farmType']) === 'rainfed'
                    ? 'Rainfed farms add a higher baseline exposure than irrigated farms (+0.10).'
                    : 'Irrigated farms add a lower baseline exposure than rainfed farms (+0.03).',
            ],
            [
                'key' => 'stress_factors',
                'label' => 'Compounding stress factors',
                'weight_percent' => null,
                'risk_value' => null,
                'contribution' => round($c['stressPenalty'], 3),
                'detail' => $c['stressFactorCount'] > 0
                    ? $c['stressFactorCount'].' active stress factor(s) (e.g. drought, flooding, strong wind) added a small penalty.'
                    : 'No compounding stress factors were detected.',
            ],
        ];

        return [
            'factors' => $factors,
            'caveat' => self::METHODOLOGY_CAVEAT,
        ];
    }

    /**
     * Shared computation behind score() and explain() — kept in one place so
     * the two never drift apart.
     *
     * @param  array{rainfall: float, temperature: float, predicted_yield: ?float, farm_type: string, barangay: string, stress_factor_count: int}  $input
     */
    private function components(array $input): array
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

        return [
            'rainfall' => $rainfall,
            'temperature' => $temperature,
            'yield' => $yield,
            'farmType' => $farmType,
            'barangay' => $barangay,
            'stressFactorCount' => $stressFactorCount,
            'rainfallRisk' => $rainfallRisk,
            'yieldRisk' => $yieldRisk,
            'temperatureRisk' => $temperatureRisk,
            'exposure' => $exposure,
            'farmExposure' => $farmExposure,
            'stressPenalty' => $stressPenalty,
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
