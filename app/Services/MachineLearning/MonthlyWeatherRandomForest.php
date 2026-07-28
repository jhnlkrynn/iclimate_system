<?php

namespace App\Services\MachineLearning;

use App\Models\ClimateRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MonthlyWeatherRandomForest
{
    private const TARGETS = ['rainfall', 'temperature', 'humidity', 'wind_speed'];

    public function predict(CarbonImmutable $targetMonth): array
    {
        return Cache::remember(
            'weather-random-forest:'.$targetMonth->format('Y-m').':'.$this->climateRecordVersion(),
            now()->addMinutes(15),
            fn () => $this->buildPrediction($targetMonth)
        );
    }

    private function climateRecordVersion(): string
    {
        return implode(':', [
            ClimateRecord::query()->count(),
            (string) (ClimateRecord::query()->max('updated_at') ?? 'none'),
        ]);
    }

    private function buildPrediction(CarbonImmutable $targetMonth): array
    {
        $monthly = $this->monthlyClimateRecords();

        if ($monthly->count() < 4) {
            return [
                'ready' => false,
                'message' => 'At least 4 months of climate records are required to train the monthly Random Forest model.',
                'months_available' => $monthly->count(),
                'target_month' => $targetMonth,
                'predictions' => [],
                'training' => [],
            ];
        }

        [$samples, $latestContext] = $this->buildTrainingSamples($monthly);

        if (count($samples) < 3) {
            return [
                'ready' => false,
                'message' => 'The available records must span at least 4 usable monthly observations.',
                'months_available' => $monthly->count(),
                'target_month' => $targetMonth,
                'predictions' => [],
                'training' => [],
            ];
        }

        $features = $this->featuresForTargetMonth($targetMonth, $latestContext, $monthly->count());
        $predictions = [];
        $training = [];

        foreach (self::TARGETS as $target) {
            $forest = new RandomForestRegressor(treeCount: 45, maxDepth: 7, minSamplesSplit: 2, seed: crc32($target));
            $forest->train($samples, $target);
            $predictions[$target] = round($forest->predict($features), 2);
            $training[$target] = [
                'trees' => $forest->treeCount(),
                'samples' => count($samples),
                'features' => count($features),
                'stability' => $this->targetStability($samples, $target),
            ];
        }

        $predictions['season'] = $this->seasonForMonth((int) $targetMonth->format('n'));
        $confidence = $this->confidence($monthly->count(), $training);

        return [
            'ready' => true,
            'message' => 'Prediction generated using monthly climate history and Random Forest regression.',
            'months_available' => $monthly->count(),
            'target_month' => $targetMonth,
            'predictions' => $predictions,
            'training' => $training,
            'confidence' => $confidence,
            'insights' => $this->insights($predictions, $confidence),
        ];
    }

    private function monthlyClimateRecords(): Collection
    {
        return ClimateRecord::query()
            ->orderBy('record_date')
            ->get()
            ->groupBy(fn (ClimateRecord $record) => $record->record_date->format('Y-m'))
            ->map(function (Collection $records, string $key): array {
                $month = CarbonImmutable::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();

                return [
                    'month' => $month,
                    'rainfall' => (float) $records->avg('rainfall'),
                    'temperature' => (float) $records->avg('temperature'),
                    'humidity' => (float) $records->avg('humidity'),
                    'wind_speed' => (float) $records->avg('wind_speed'),
                    'season' => $this->seasonForMonth((int) $month->format('n')),
                    'records' => $records->count(),
                ];
            })
            ->values();
    }

    private function buildTrainingSamples(Collection $monthly): array
    {
        $samples = [];

        for ($i = 1; $i < $monthly->count(); $i++) {
            $current = $monthly[$i];
            $previous = $monthly[$i - 1];
            $history = $monthly->slice(max(0, $i - 3), min(3, $i));
            $features = $this->featureVector($current['month'], $previous, $history, $i);

            foreach (self::TARGETS as $target) {
                $features[$target] = $current[$target];
            }

            $samples[] = $features;
        }

        $latest = $monthly->last();
        $history = $monthly->slice(max(0, $monthly->count() - 3), min(3, $monthly->count()));

        return [$samples, ['previous' => $latest, 'history' => $history]];
    }

    private function featuresForTargetMonth(CarbonImmutable $targetMonth, array $context, int $index): array
    {
        return $this->featureVector($targetMonth->startOfMonth(), $context['previous'], $context['history'], $index);
    }

    private function featureVector(CarbonImmutable $month, array $previous, Collection $history, int $index): array
    {
        $monthNumber = (int) $month->format('n');
        $angle = 2 * pi() * ($monthNumber / 12);

        return [
            'month' => $monthNumber,
            'month_sin' => sin($angle),
            'month_cos' => cos($angle),
            'year' => (int) $month->format('Y'),
            'time_index' => $index,
            'is_wet_season' => $this->seasonForMonth($monthNumber) === 'Wet' ? 1 : 0,
            'prev_rainfall' => (float) $previous['rainfall'],
            'prev_temperature' => (float) $previous['temperature'],
            'prev_humidity' => (float) $previous['humidity'],
            'prev_wind_speed' => (float) $previous['wind_speed'],
            'avg3_rainfall' => (float) $history->avg('rainfall'),
            'avg3_temperature' => (float) $history->avg('temperature'),
            'avg3_humidity' => (float) $history->avg('humidity'),
            'avg3_wind_speed' => (float) $history->avg('wind_speed'),
        ];
    }

    private function seasonForMonth(int $month): string
    {
        return in_array($month, [5, 6, 7, 8, 9, 10], true) ? 'Wet' : 'Dry';
    }

    private function targetStability(array $samples, string $target): array
    {
        $values = array_map(fn (array $sample): float => (float) $sample[$target], $samples);
        $mean = array_sum($values) / max(count($values), 1);
        $variance = array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $values)) / max(count($values), 1);
        $deviation = sqrt($variance);
        $ratio = $mean > 0 ? $deviation / $mean : 1;

        return [
            'mean' => round($mean, 2),
            'deviation' => round($deviation, 2),
            'label' => $ratio <= .12 ? 'Stable' : ($ratio <= .24 ? 'Variable' : 'Highly variable'),
        ];
    }

    private function confidence(int $monthsAvailable, array $training): array
    {
        $score = min(92, 48 + ($monthsAvailable * 4));

        foreach ($training as $metric) {
            $label = $metric['stability']['label'] ?? 'Variable';
            if ($label === 'Highly variable') {
                $score -= 6;
            } elseif ($label === 'Variable') {
                $score -= 3;
            }
        }

        $score = max(45, min(95, $score));

        return [
            'value' => $score,
            'label' => match (true) {
                $score >= 80 => 'High confidence',
                $score >= 65 => 'Moderate confidence',
                default => 'Low confidence',
            },
            'note' => $monthsAvailable >= 12
                ? 'The model has at least one full year of monthly climate history.'
                : 'Add more monthly climate records to improve forecast confidence.',
        ];
    }

    private function insights(array $predictions, array $confidence): array
    {
        $insights = [];

        if (($predictions['rainfall'] ?? 0) > 300) {
            $insights[] = 'Rainfall is elevated; prioritize drainage and flood monitoring.';
        } elseif (($predictions['rainfall'] ?? 0) < 100) {
            $insights[] = 'Rainfall is low; rainfed farms may need delayed planting or water support.';
        } else {
            $insights[] = 'Rainfall is within a workable planning range.';
        }

        if (($predictions['temperature'] ?? 0) >= 32) {
            $insights[] = 'Temperature may create heat stress during sensitive rice growth stages.';
        }

        if (($predictions['humidity'] ?? 0) >= 88) {
            $insights[] = 'Humidity is high; monitor for pest and disease pressure.';
        }

        if (($predictions['wind_speed'] ?? 0) >= 18) {
            $insights[] = 'Wind speed is elevated; watch for storm or lodging risk.';
        }

        $insights[] = $confidence['note'];

        return array_values(array_unique($insights));
    }
}

class RandomForestRegressor
{
    /** @var DecisionTreeRegressor[] */
    private array $trees = [];

    public function __construct(
        private readonly int $treeCount = 35,
        private readonly int $maxDepth = 6,
        private readonly int $minSamplesSplit = 2,
        private readonly int $seed = 1234,
    ) {}

    public function train(array $samples, string $target): void
    {
        $this->trees = [];
        $features = array_values(array_filter(array_keys($samples[0]), fn (string $key) => $key !== $target && ! in_array($key, ['rainfall', 'temperature', 'humidity', 'wind_speed'], true)));

        for ($i = 0; $i < $this->treeCount; $i++) {
            mt_srand($this->seed + $i);
            $bootstrap = $this->bootstrap($samples);
            $tree = new DecisionTreeRegressor($this->maxDepth, $this->minSamplesSplit, max(2, (int) floor(sqrt(count($features)))));
            $tree->train($bootstrap, $features, $target);
            $this->trees[] = $tree;
        }
    }

    public function predict(array $features): float
    {
        if ($this->trees === []) {
            return 0.0;
        }

        $sum = array_sum(array_map(fn (DecisionTreeRegressor $tree) => $tree->predict($features), $this->trees));

        return $sum / count($this->trees);
    }

    public function treeCount(): int
    {
        return count($this->trees);
    }

    private function bootstrap(array $samples): array
    {
        $result = [];
        $max = count($samples) - 1;

        for ($i = 0; $i < count($samples); $i++) {
            $result[] = $samples[mt_rand(0, $max)];
        }

        return $result;
    }
}

class DecisionTreeRegressor
{
    private array $root = [];

    public function __construct(
        private readonly int $maxDepth,
        private readonly int $minSamplesSplit,
        private readonly int $featureSubsetSize,
    ) {}

    public function train(array $samples, array $features, string $target): void
    {
        $this->root = $this->build($samples, $features, $target, 0);
    }

    public function predict(array $features): float
    {
        $node = $this->root;

        while (($node['type'] ?? 'leaf') === 'split') {
            $node = (($features[$node['feature']] ?? 0) <= $node['threshold']) ? $node['left'] : $node['right'];
        }

        return (float) ($node['value'] ?? 0);
    }

    private function build(array $samples, array $features, string $target, int $depth): array
    {
        if ($depth >= $this->maxDepth || count($samples) < $this->minSamplesSplit || $this->variance($samples, $target) <= 0.000001) {
            return ['type' => 'leaf', 'value' => $this->mean($samples, $target)];
        }

        $split = $this->bestSplit($samples, $this->featureSubset($features), $target);

        if ($split === null) {
            return ['type' => 'leaf', 'value' => $this->mean($samples, $target)];
        }

        return [
            'type' => 'split',
            'feature' => $split['feature'],
            'threshold' => $split['threshold'],
            'left' => $this->build($split['left'], $features, $target, $depth + 1),
            'right' => $this->build($split['right'], $features, $target, $depth + 1),
        ];
    }

    private function bestSplit(array $samples, array $features, string $target): ?array
    {
        $best = null;
        $bestScore = INF;

        foreach ($features as $feature) {
            $values = array_values(array_unique(array_map(fn (array $sample) => (float) $sample[$feature], $samples)));
            sort($values);

            if (count($values) < 2) {
                continue;
            }

            $thresholds = [];
            for ($i = 1; $i < count($values); $i++) {
                $thresholds[] = ($values[$i - 1] + $values[$i]) / 2;
            }

            foreach ($thresholds as $threshold) {
                $left = array_values(array_filter($samples, fn (array $sample) => (float) $sample[$feature] <= $threshold));
                $right = array_values(array_filter($samples, fn (array $sample) => (float) $sample[$feature] > $threshold));

                if ($left === [] || $right === []) {
                    continue;
                }

                $score = (count($left) * $this->variance($left, $target) + count($right) * $this->variance($right, $target)) / count($samples);

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
        $copy = $features;
        shuffle($copy);

        return array_slice($copy, 0, min($this->featureSubsetSize, count($copy)));
    }

    private function mean(array $samples, string $target): float
    {
        return array_sum(array_map(fn (array $sample) => (float) $sample[$target], $samples)) / max(count($samples), 1);
    }

    private function variance(array $samples, string $target): float
    {
        $mean = $this->mean($samples, $target);
        $sum = array_sum(array_map(fn (array $sample) => ((float) $sample[$target] - $mean) ** 2, $samples));

        return $sum / max(count($samples), 1);
    }
}
