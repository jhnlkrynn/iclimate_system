<?php

namespace App\Console\Commands;

use App\Models\MlModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Records the model files that already exist. Files are only read (hashed and,
 * for compatibility info, opened once by Python); they are never moved,
 * rewritten or retrained, and nothing is activated except the file that is
 * already live, which is registered as active so behaviour is unchanged.
 * Metrics come only from the metadata JSON that training wrote; when none
 * exists they stay empty.
 */
class RegisterExistingMlModels extends Command
{
    protected $signature = 'ml:register-existing {--skip-compat : Do not open the pickles with Python to record library versions}';

    protected $description = 'Register the existing production and candidate rice yield model files in the model registry.';

    public function handle(): int
    {
        $production = (string) config('prediction.rice_yield.model_path', 'storage/models/rice_yield_model_final.pkl');

        $this->register([
            'version' => '1.0.0',
            'name' => 'Rice yield model (production)',
            'file_path' => $production,
            'metadata_path' => null,
            'live' => true,
        ]);

        $this->register([
            'version' => '1.1.0',
            'name' => 'Rice yield model (candidate)',
            'file_path' => 'storage/models/rice_yield_model_candidate.pkl',
            'metadata_path' => 'storage/models/rice_yield_model_candidate_metadata.json',
            'live' => false,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  array{version:string,name:string,file_path:string,metadata_path:?string,live:bool}  $entry
     */
    private function register(array $entry): void
    {
        if (MlModel::query()->where('purpose', MlModel::PURPOSE_RICE_YIELD)->where('version', $entry['version'])->exists()) {
            $this->line("Version {$entry['version']} already registered; left unchanged.");

            return;
        }

        $absolute = base_path($entry['file_path']);

        if (! is_file($absolute)) {
            $this->warn("Skipped {$entry['version']}: model file not found.");

            return;
        }

        $meta = [];

        if ($entry['metadata_path'] !== null && is_file(base_path($entry['metadata_path']))) {
            $meta = json_decode((string) file_get_contents(base_path($entry['metadata_path'])), true) ?: [];
        }

        $metrics = $meta['final_metrics'] ?? [];
        $live = $entry['live'];

        MlModel::create([
            'purpose' => MlModel::PURPOSE_RICE_YIELD,
            'name' => $entry['name'],
            'model_type' => $meta['model_name'] ?? 'Random Forest',
            'version' => $entry['version'],
            'target_variable' => $meta['target'] ?? null,
            'file_path' => $entry['file_path'],
            'metadata_path' => $entry['metadata_path'] !== null && $meta !== [] ? $entry['metadata_path'] : null,
            'training_years' => $meta['training_years'] ?? null,
            'test_year' => $meta['test_year'] ?? null,
            'feature_list' => $meta['features'] ?? null,
            'hyperparameters' => $meta['hyperparameters'] ?? null,
            'compatibility' => $this->compatibility($absolute),
            'mae' => $metrics['MAE'] ?? null,
            'rmse' => $metrics['RMSE'] ?? null,
            'r2' => $metrics['R2'] ?? null,
            'trained_at' => date('Y-m-d H:i:s', (int) filemtime($absolute)),
            'status' => $live ? MlModel::STATUS_ACTIVE : MlModel::STATUS_CANDIDATE,
            'is_active' => $live,
        ]);

        $this->info("Registered {$entry['version']} as ".($live ? 'active (already live)' : 'candidate').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function compatibility(string $absolute): array
    {
        $info = [
            'sha256' => hash_file('sha256', $absolute),
            'size_bytes' => filesize($absolute),
            'format' => 'joblib/pickle',
        ];

        if ($this->option('skip-compat')) {
            return $info;
        }

        $python = $this->pythonBinary();
        $script = 'import joblib,sklearn,sys,json;m=joblib.load(sys.argv[1]);'
            .'print(json.dumps({"estimator_class":type(m).__name__,"checked_with_sklearn":sklearn.__version__,'
            .'"checked_with_python":sys.version.split()[0]}))';

        try {
            $result = Process::timeout(60)->run([$python, '-W', 'ignore', '-c', $script, $absolute]);
            $decoded = $result->successful() ? json_decode(trim($result->output()), true) : null;
            $info = $decoded ? [...$info, ...$decoded, 'loads_in_current_environment' => true]
                : [...$info, 'loads_in_current_environment' => false];
        } catch (Throwable) {
            $info['loads_in_current_environment'] = null;
        }

        return $info;
    }

    private function pythonBinary(): string
    {
        $configured = trim((string) env('PYTHON_BINARY', ''), " \t\n\r\0\x0B\"'");

        if ($configured !== '') {
            return $configured;
        }

        foreach ([base_path('.venv/Scripts/python.exe'), base_path('.venv/bin/python')] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'python';
    }
}
