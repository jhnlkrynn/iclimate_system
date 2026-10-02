<?php

namespace App\Contracts;

/**
 * The agricultural-risk calculation, isolated behind one contract so the methodology
 * can be replaced (once researchers / adviser confirm a documented source) without
 * touching any dashboard. Callers depend on this interface, never on a formula.
 *
 * STATUS: REQUIRES METHODOLOGY VALIDATION. The current implementation
 * (App\Services\Risk\AgriculturalRiskScorer) uses placeholder weights and exposure values.
 */
interface RiskMethodology
{
    /** Marker shown wherever the current methodology is described. */
    public const STATUS = 'REQUIRES METHODOLOGY VALIDATION';

    /** @return array{score: float, level: string, label: string, color: string, components: array<string, mixed>} */
    public function score(array $input): array;

    /** @return array{factors: array<int, array<string, mixed>>, caveat: string} */
    public function explain(array $input): array;

    public function exposureLabel(string $barangay): string;
}
