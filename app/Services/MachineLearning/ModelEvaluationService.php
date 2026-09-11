<?php

namespace App\Services\MachineLearning;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Services\Prediction\FeatureCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ModelEvaluationService
{
    /**
     * Static narrative text only — the numbers (rmse/mae/r2/status) come live
     * from the iClimate ML service's /metrics route (python_scripts/main.py,
     * evaluation_service.py), not from here.
     */
    private const ALGORITHM_DETAILS = [
        'Multiple Linear Regression' => [
            'summary' => 'Baseline statistical model used to compare how a simple linear relationship performs against ensemble models.',
            'interpretation' => 'It assumes the relationship between weather inputs and rice yield is mostly straight-line. Rice yield is affected by mixed conditions, so this model typically has higher error than Random Forest.',
            'reason' => 'Kept as a comparison model because the manuscript requires Multiple Linear Regression in the algorithm evaluation.',
        ],
        'Random Forest' => [
            'summary' => 'Ensemble regression model that combines multiple decision trees to produce a more stable rice yield prediction.',
            'interpretation' => 'Random Forest is deployed as the production model. Compare its RMSE, MAE, and R² against the other rows to see whether it is still the best-performing option on the current data.',
            'reason' => 'Deployed as the rice yield model; saved as storage/models/rice_yield_model_final.pkl.',
        ],
        'Gradient Boosting' => [
            'summary' => 'Sequential ensemble model that builds trees one after another to correct previous prediction errors.',
            'interpretation' => 'Can be powerful, but is more prone to overfitting on small datasets than Random Forest.',
            'reason' => 'Kept as a comparison model because the manuscript requires Gradient Boosting in the model evaluation.',
        ],
    ];

    public function evaluate(): array
    {
        return Cache::remember('model-evaluation:rice-yield', now()->addMinutes(5), fn () => $this->buildEvaluation());
    }

    private function buildEvaluation(): array
    {
        $metrics = $this->fetchLiveMetrics();

        if ($metrics !== null && ($metrics['ready'] ?? false)) {
            return $this->buildReadyEvaluation($metrics);
        }

        return $this->buildNotReadyEvaluation($metrics);
    }

    private function buildReadyEvaluation(array $metrics): array
    {
        $best = $metrics['best_algorithm'] ?? null;

        $rows = collect($metrics['rows'] ?? [])
            ->map(function (array $row) use ($best): array {
                $algorithm = $row['algorithm'] ?? 'Unknown';

                return [
                    'algorithm' => $algorithm,
                    'rmse' => $row['rmse'] ?? null,
                    'mae' => $row['mae'] ?? null,
                    'r2' => $row['r2'] ?? null,
                    'status' => $algorithm === $best ? 'Selected Model' : 'Compared Model',
                    'details' => self::ALGORITHM_DETAILS[$algorithm] ?? [
                        'summary' => 'No model details available.',
                        'interpretation' => 'No interpretation available.',
                        'reason' => 'No defense note available.',
                    ],
                ];
            })
            ->all();

        $message = 'Evaluation metrics computed live from the current exported training data.';

        if (! empty($metrics['warning'])) {
            $message .= ' '.$metrics['warning'];
        }

        return [
            'ready' => true,
            'message' => $message,
            'sample_count' => $metrics['sample_count'] ?? $this->samples()->count(),
            'training_count' => null,
            'testing_count' => null,
            'features' => $this->featureLabels(),
            'rows' => $rows,
            'best_algorithm' => $best,
            'schema_caveat' => 'These metrics were computed on a candidate feature set (see '
                .'app/Services/Prediction/FeatureCatalog.php::SCHEMA_TARGET). The currently '
                .'deployed model, rice_yield_model_final.pkl, was trained on a different, older '
                .'feature set and has not yet been evaluated against this data. Do not treat '
                .'these numbers as the deployed model\'s real-world accuracy until it is '
                .'retrained on this schema (see docs/system_audit_fix_plan.md, Phase 3).',
            'source' => [
                'name' => 'iClimate ML service (python_scripts/evaluation_service.py)',
                'type' => 'Live cross-validated evaluation',
                'dataset_path' => $metrics['source']['dataset_path'] ?? null,
                'computed_live' => true,
                'deployed_model' => storage_path('models/rice_yield_model_final.pkl'),
            ],
            'generated_at' => $metrics['generated_at'] ?? now(),
        ];
    }

    private function buildNotReadyEvaluation(?array $metrics): array
    {
        $message = $metrics['message']
            ?? 'Could not reach the iClimate ML service to compute model metrics. Confirm it is running '
                .'(python_scripts/main.py via uvicorn) and that FARMING_AI_API_URL is set correctly.';

        return [
            'ready' => false,
            'message' => $message,
            'sample_count' => $metrics['sample_count'] ?? $this->samples()->count(),
            'training_count' => null,
            'testing_count' => null,
            'features' => $this->featureLabels(),
            'rows' => [],
            'best_algorithm' => null,
            'source' => [
                'name' => 'iClimate ML service (python_scripts/evaluation_service.py)',
                'type' => 'Live cross-validated evaluation',
                'computed_live' => false,
            ],
            'generated_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>|null null when the ML service could not be reached at all
     */
    private function fetchLiveMetrics(): ?array
    {
        $baseUrl = rtrim((string) config('services.farming_ai.url'), '/');
        $timeout = (int) config('services.farming_ai.timeout', 5);

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($baseUrl.'/metrics');
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    private function samples(): Collection
    {
        $climate = ClimateRecord::query()
            ->whereNotNull('rainfall')
            ->whereNotNull('temperature')
            ->whereNotNull('humidity')
            ->whereNotNull('wind_speed')
            ->get()
            ->groupBy(fn (ClimateRecord $record): string => $record->record_date->format('Y').'|'.$record->season)
            ->map(fn (Collection $records): array => [
                'rainfall' => (float) $records->avg('rainfall'),
                'temperature' => (float) $records->avg('temperature'),
                'humidity' => (float) $records->avg('humidity'),
                'wind_speed' => (float) $records->avg('wind_speed'),
            ]);

        return RiceProduction::query()
            ->whereNotNull('yield_per_hectare')
            ->whereNotNull('area_hectares')
            ->orderBy('year')
            ->orderBy('season')
            ->get()
            ->map(function (RiceProduction $production) use ($climate): ?array {
                $season = str($production->season)->contains('dry', true) ? ClimateRecord::SEASON_DRY : ClimateRecord::SEASON_WET;
                $climateRow = $climate->get($production->year.'|'.$season);

                if (! $climateRow) {
                    return null;
                }

                return [
                    'label' => trim("{$production->barangay} {$production->season} {$production->year}"),
                    'target' => (float) $production->yield_per_hectare,
                    'features' => [
                        'rainfall' => (float) $climateRow['rainfall'],
                        'temperature' => (float) $climateRow['temperature'],
                        'humidity' => (float) $climateRow['humidity'],
                        'wind_speed' => (float) $climateRow['wind_speed'],
                        'area_hectares' => (float) $production->area_hectares,
                        'is_wet_season' => $season === ClimateRecord::SEASON_WET ? 1.0 : 0.0,
                        'is_irrigated' => str($production->irrigation_type)->contains('irrigated', true) ? 1.0 : 0.0,
                    ],
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<int, array{label: string, unit: string, description: string, behavior: string}>
     */
    private function featureLabels(): array
    {
        return collect(FeatureCatalog::forSchema(FeatureCatalog::SCHEMA_TARGET))
            ->map(fn (array $feature): array => [
                'label' => $feature['label'],
                'unit' => $feature['unit'],
                'description' => $feature['description'],
                'behavior' => $feature['behavior'],
            ])
            ->values()
            ->all();
    }
}
