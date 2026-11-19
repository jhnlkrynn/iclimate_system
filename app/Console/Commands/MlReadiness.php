<?php

namespace App\Console\Commands;

use App\Support\PythonRuntime;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Runs the isolated, read-only Python ML readiness gates.
 *
 * This command intentionally does not bootstrap prediction services, dispatch
 * jobs, or open a database connection. The Python process reads only the
 * explicitly supplied artifact and CSV paths and writes JSON to stdout.
 */
class MlReadiness extends Command
{
    protected $signature = 'iclimate:ml-readiness
        {--model=storage/models/rice_yield_model_final.pkl : Model artifact to inspect}
        {--climate= : Climate CSV to inspect}
        {--agricultural= : Agricultural CSV to inspect}
        {--target-date= : Date used for freshness checks (YYYY-MM-DD)}';

    protected $description = 'Run read-only automated ML readiness gates without predictions, writes, or retraining.';

    public function handle(): int
    {
        $python = PythonRuntime::executable();
        $script = base_path('python_scripts/run_ml_readiness.py');

        if (! $python || ! is_file($script)) {
            $this->error('The configured Python runtime or ML readiness runner is unavailable.');

            return self::FAILURE;
        }

        $command = [
            $python,
            $script,
            '--model', base_path((string) $this->option('model')),
            '--climate', base_path((string) ($this->option('climate') ?: 'database/data/Ambulong Monthly Data.csv')),
            '--agricultural', base_path((string) ($this->option('agricultural') ?: 'database/seeders/data/mao-planting-summary-2020-2025.csv')),
        ];

        if ($this->option('target-date')) {
            $command[] = '--target-date';
            $command[] = (string) $this->option('target-date');
        }

        $process = new Process($command, base_path(), PythonRuntime::environment($python));
        $exitCode = $process->run();

        if ($process->getOutput() !== '') {
            $this->output->write($process->getOutput());
        }

        if ($exitCode !== 0) {
            $this->error(trim($process->getErrorOutput()) ?: 'The ML readiness runner failed.');
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
