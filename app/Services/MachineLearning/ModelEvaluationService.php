<?php

namespace App\Services\MachineLearning;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ModelEvaluationService
{
    private const TRAINED_MODEL_RESULTS = [
        [
            'algorithm' => 'Multiple Linear Regression',
            'rmse' => 0.863691,
            'mae' => 0.651078,
            'r2' => 0.120158,
            'status' => 'Compared Model',
            'details' => [
                'summary' => 'Baseline statistical model used to compare how a simple linear relationship performs against ensemble models.',
                'interpretation' => 'It assumes the relationship between weather inputs and rice yield is mostly straight-line. Rice yield is affected by mixed conditions, so this model had higher error than Random Forest.',
                'reason' => 'Kept as a comparison model because the manuscript requires Multiple Linear Regression in the algorithm evaluation.',
            ],
        ],
        [
            'algorithm' => 'Random Forest',
            'rmse' => 0.802549,
            'mae' => 0.591967,
            'r2' => 0.240319,
            'status' => 'Selected Model',
            'details' => [
                'summary' => 'Ensemble regression model that combines multiple decision trees to produce a more stable rice yield prediction.',
                'interpretation' => 'Random Forest is the best model here because it made the smallest average prediction errors. It has the lowest RMSE and MAE, meaning its yield predictions were closest to the actual values. It also has the highest R², meaning it explained more of the yield pattern than the other models.',
                'reason' => 'Selected as the deployed rice yield model because it performed best overall in the trained model selection notebook and is saved as storage/models/rice_yield_model_final.pkl.',
            ],
        ],
        [
            'algorithm' => 'Gradient Boosting',
            'rmse' => 0.897037,
            'mae' => 0.653737,
            'r2' => 0.050907,
            'status' => 'Compared Model',
            'details' => [
                'summary' => 'Sequential ensemble model that builds trees one after another to correct previous prediction errors.',
                'interpretation' => 'It can be powerful, but in this trained model evaluation it produced higher error than Random Forest and explained less of the rice yield pattern.',
                'reason' => 'Kept as a comparison model because the manuscript requires Gradient Boosting in the model evaluation.',
            ],
        ],
    ];

    public function evaluate(): array
    {
        return Cache::remember('model-evaluation:rice-yield', now()->addMinutes(30), fn () => $this->buildEvaluation());
    }

    private function buildEvaluation(): array
    {
        $samples = $this->samples();

        return [
            'ready' => true,
            'message' => 'Evaluation metrics come from the trained model selection notebook used to produce the deployed Random Forest rice yield model.',
            'sample_count' => $samples->count(),
            'training_count' => null,
            'testing_count' => null,
            'features' => $this->featureLabels(),
            'rows' => self::TRAINED_MODEL_RESULTS,
            'best_algorithm' => 'Random Forest',
            'source' => [
                'name' => 'iClimate_ML_Model_Training.ipynb',
                'type' => 'Trained model selection notebook',
                'dataset_files' => [
                    'Ambulong Monthly Data.csv',
                    'planting summary 2020-2025',
                    'irrigated and non irrigated farm types location',
                ],
                'deployed_model' => storage_path('models/rice_yield_model_final.pkl'),
            ],
            'generated_at' => now(),
        ];
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

    private function splitSamples(Collection $samples): array
    {
        $all = $samples->values()->all();
        $testingCount = max(2, (int) ceil(count($all) * 0.3));

        return [
            array_slice($all, 0, count($all) - $testingCount),
            array_slice($all, -$testingCount),
        ];
    }

    private function metrics(array $actual, array $predicted): array
    {
        $count = max(count($actual), 1);
        $errors = [];
        $absolute = [];

        foreach ($actual as $index => $value) {
            $error = ((float) $predicted[$index]) - ((float) $value);
            $errors[] = $error ** 2;
            $absolute[] = abs($error);
        }

        $mean = array_sum($actual) / $count;
        $total = array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $actual));
        $residual = array_sum($errors);

        return [
            'rmse' => round(sqrt(array_sum($errors) / $count), 3),
            'mae' => round(array_sum($absolute) / $count, 3),
            'r2' => round($total > 0 ? 1 - ($residual / $total) : 0, 3),
        ];
    }

    private function featureLabels(): array
    {
        return ['Rainfall', 'Temperature', 'Humidity', 'Wind Speed', 'Farm Area', 'Season', 'Irrigation Type'];
    }
}

interface EvaluatesYieldModel
{
    public function train(array $samples): void;

    public function predict(array $features): float;
}

class SimpleLinearRegressionModel implements EvaluatesYieldModel
{
    private array $weights = [];

    private array $means = [];

    private array $scales = [];

    public function train(array $samples): void
    {
        $names = array_keys($samples[0]['features']);
        $this->means = $this->means($samples, $names);
        $this->scales = $this->scales($samples, $names, $this->means);
        $this->weights = array_fill(0, count($names) + 1, 0.0);
        $rate = 0.04;

        for ($step = 0; $step < 1200; $step++) {
            $gradient = array_fill(0, count($this->weights), 0.0);

            foreach ($samples as $sample) {
                $x = $this->vector($sample['features'], $names);
                $error = $this->dot($this->weights, $x) - $sample['target'];

                foreach ($x as $index => $value) {
                    $gradient[$index] += $error * $value;
                }
            }

            foreach ($this->weights as $index => $weight) {
                $this->weights[$index] = $weight - ($rate * ($gradient[$index] / count($samples)));
            }
        }
    }

    public function predict(array $features): float
    {
        return $this->dot($this->weights, $this->vector($features, array_keys($features)));
    }

    private function vector(array $features, array $names): array
    {
        $vector = [1.0];

        foreach ($names as $name) {
            $vector[] = (((float) $features[$name]) - ($this->means[$name] ?? 0)) / max($this->scales[$name] ?? 1, 0.0001);
        }

        return $vector;
    }

    private function dot(array $a, array $b): float
    {
        return array_sum(array_map(fn ($x, $y): float => $x * $y, $a, $b));
    }

    private function means(array $samples, array $names): array
    {
        return array_combine($names, array_map(fn (string $name): float => array_sum(array_map(fn ($sample) => $sample['features'][$name], $samples)) / count($samples), $names));
    }

    private function scales(array $samples, array $names, array $means): array
    {
        return array_combine($names, array_map(function (string $name) use ($samples, $means): float {
            $variance = array_sum(array_map(fn ($sample) => (((float) $sample['features'][$name]) - $means[$name]) ** 2, $samples)) / count($samples);

            return sqrt($variance) ?: 1.0;
        }, $names));
    }
}

class SimpleRandomForestModel implements EvaluatesYieldModel
{
    private array $trees = [];

    public function __construct(private readonly int $treeCount = 30) {}

    public function train(array $samples): void
    {
        $this->trees = [];
        $features = array_keys($samples[0]['features']);

        for ($i = 0; $i < $this->treeCount; $i++) {
            mt_srand(900 + $i);
            $tree = new SimpleRegressionTree(maxDepth: 5, minSamples: 2, featureCount: max(2, (int) floor(sqrt(count($features)))));
            $tree->train($this->bootstrap($samples), $features);
            $this->trees[] = $tree;
        }
    }

    public function predict(array $features): float
    {
        return array_sum(array_map(fn (SimpleRegressionTree $tree): float => $tree->predict($features), $this->trees)) / max(count($this->trees), 1);
    }

    private function bootstrap(array $samples): array
    {
        $boot = [];

        for ($i = 0; $i < count($samples); $i++) {
            $boot[] = $samples[mt_rand(0, count($samples) - 1)];
        }

        return $boot;
    }
}

class SimpleGradientBoostingModel implements EvaluatesYieldModel
{
    private float $base = 0.0;

    private array $trees = [];

    private float $learningRate = 0.12;

    public function __construct(private readonly int $rounds = 36) {}

    public function train(array $samples): void
    {
        $this->trees = [];
        $features = array_keys($samples[0]['features']);
        $this->base = array_sum(array_map(fn ($sample) => $sample['target'], $samples)) / count($samples);
        $working = $samples;

        for ($i = 0; $i < $this->rounds; $i++) {
            foreach ($working as $index => $sample) {
                $working[$index]['target'] = $sample['target'] - $this->predict($sample['features']);
            }

            $tree = new SimpleRegressionTree(maxDepth: 2, minSamples: 2, featureCount: count($features));
            $tree->train($working, $features);
            $this->trees[] = $tree;
        }
    }

    public function predict(array $features): float
    {
        $value = $this->base;

        foreach ($this->trees as $tree) {
            $value += $this->learningRate * $tree->predict($features);
        }

        return $value;
    }
}

class SimpleRegressionTree
{
    private array $root = [];

    public function __construct(
        private readonly int $maxDepth,
        private readonly int $minSamples,
        private readonly int $featureCount,
    ) {}

    public function train(array $samples, array $features): void
    {
        $this->root = $this->build($samples, $features, 0);
    }

    public function predict(array $features): float
    {
        $node = $this->root;

        while (($node['type'] ?? 'leaf') === 'split') {
            $node = ($features[$node['feature']] <= $node['threshold']) ? $node['left'] : $node['right'];
        }

        return (float) ($node['value'] ?? 0);
    }

    private function build(array $samples, array $features, int $depth): array
    {
        if ($depth >= $this->maxDepth || count($samples) < $this->minSamples || $this->variance($samples) < 0.000001) {
            return ['type' => 'leaf', 'value' => $this->mean($samples)];
        }

        $split = $this->bestSplit($samples, $this->featureSubset($features));

        if (! $split) {
            return ['type' => 'leaf', 'value' => $this->mean($samples)];
        }

        return [
            'type' => 'split',
            'feature' => $split['feature'],
            'threshold' => $split['threshold'],
            'left' => $this->build($split['left'], $features, $depth + 1),
            'right' => $this->build($split['right'], $features, $depth + 1),
        ];
    }

    private function bestSplit(array $samples, array $features): ?array
    {
        $best = null;
        $bestScore = INF;

        foreach ($features as $feature) {
            $values = array_values(array_unique(array_map(fn ($sample): float => (float) $sample['features'][$feature], $samples)));
            sort($values);

            for ($i = 1; $i < count($values); $i++) {
                $threshold = ($values[$i - 1] + $values[$i]) / 2;
                $left = array_values(array_filter($samples, fn ($sample): bool => $sample['features'][$feature] <= $threshold));
                $right = array_values(array_filter($samples, fn ($sample): bool => $sample['features'][$feature] > $threshold));

                if ($left === [] || $right === []) {
                    continue;
                }

                $score = (count($left) * $this->variance($left) + count($right) * $this->variance($right)) / count($samples);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = compact('feature', 'threshold', 'left', 'right');
                }
            }
        }

        return $best;
    }

    private function featureSubset(array $features): array
    {
        shuffle($features);

        return array_slice($features, 0, min($this->featureCount, count($features)));
    }

    private function mean(array $samples): float
    {
        return array_sum(array_map(fn ($sample): float => (float) $sample['target'], $samples)) / max(count($samples), 1);
    }

    private function variance(array $samples): float
    {
        $mean = $this->mean($samples);

        return array_sum(array_map(fn ($sample): float => (((float) $sample['target']) - $mean) ** 2, $samples)) / max(count($samples), 1);
    }
}
