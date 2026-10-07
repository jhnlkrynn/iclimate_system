<?php

namespace App\Services\MachineLearning;

use App\Models\MlModel;
use App\Support\PythonRuntime;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: ML Model Management. Runs python_scripts/model_management.py (compare
 * Multiple Linear Regression, Random Forest and Gradient Boosting; retrain one) and records what it made: the comparison
 * report, and a retrained model as a candidate in the Model Registry (IT validates and activates it there).
 */
class ModelManagementService
{
    public const ALGORITHMS = ['mlr' => 'Multiple Linear Regression', 'rf' => 'Random Forest', 'gb' => 'Gradient Boosting'];

    public static function reportPath(): string
    {
        // Tests never write into the real models folder.
        return app()->environment('testing') ? storage_path('framework/testing/algorithm_comparison_report.json') : storage_path('models/algorithm_comparison_report.json');
    }

    /** The dataset compare / retrain read: the one IT uploaded last, else the project's modeling dataset. */
    public static function datasetPath(): string
    {
        $uploaded = SystemSettings::get('ml_dataset_path');

        return $uploaded && is_file(base_path($uploaded)) ? base_path($uploaded) : storage_path('datasets/iClimate_Modeling_Ready_TimeAware.csv');
    }

    public static function lastReport(): ?array
    {
        $report = is_file(self::reportPath()) ? json_decode((string) file_get_contents(self::reportPath()), true) : null;

        return is_array($report) ? $report : null;
    }

    public function compare(): array
    {
        $report = $this->run(['compare', self::datasetPath()]);
        file_put_contents(self::reportPath(), json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }

    public function retrain(string $algorithm): MlModel
    {
        if (! isset(self::ALGORITHMS[$algorithm])) {
            throw new RuntimeException('Unknown algorithm.');
        }
        $result = $this->run(['retrain', $algorithm, self::datasetPath()]);

        return MlModel::query()->create([
            'purpose' => MlModel::PURPOSE_RICE_YIELD,
            'name' => $result['name'].' (retrained)',
            'model_type' => $result['name'],
            'version' => $algorithm.'-'.$result['version'],
            'target_variable' => 'AVERAGE_YIELD',
            'file_path' => $result['model_path'],
            'metadata_path' => $result['metadata_path'],
            'training_years' => $result['training_years'],
            'test_year' => $result['holdout_year'],
            'feature_list' => $result['features'],
            'compatibility' => ['feature_set' => 'candidate (10 features)', 'leave_one_year_out' => $result['leave_one_year_out']],
            'mae' => $result['metrics']['mae'],
            'rmse' => $result['metrics']['rmse'],
            'r2' => $result['metrics']['r2'],
            'trained_at' => now(),
            'status' => MlModel::STATUS_CANDIDATE,
            'is_active' => false,
        ]);
    }

    /** @return array<string, mixed> the script's JSON */
    private function run(array $arguments): array
    {
        $python = PythonRuntime::executable() ?? throw new RuntimeException('Python for the ML scripts was not found (set PYTHON_BINARY).');
        $result = Process::env(PythonRuntime::environment($python))->timeout(600)
            ->run([$python, '-I', base_path('python_scripts/model_management.py'), ...$arguments]);
        $json = json_decode(trim((string) collect(explode("\n", trim($result->output())))->last()), true);

        if (! is_array($json)) {
            throw new RuntimeException('The model script did not answer (exit code '.$result->exitCode().').');
        }
        if (isset($json['error'])) {
            throw new RuntimeException((string) $json['error']);
        }

        return $json;
    }
}
