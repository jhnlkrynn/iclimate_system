<?php

namespace Tests\Concerns;

use App\Support\PythonRuntime;
use Symfony\Component\Process\Process;

/**
 * Finds a Python that can run the rice-yield candidate model on any OS, or skips the test with the reason.
 * Looks at the project virtual environment first (Windows: .venv/Scripts/python.exe, macOS/Linux: .venv/bin/python),
 * then at the interpreter the app itself would use (PythonRuntime). An interpreter only counts as usable when the
 * model's packages import, so a bare system Python without joblib/pandas/scikit-learn skips instead of erroring.
 */
trait RunsProjectPython
{
    private const REQUIRED_PYTHON_MODULES = ['joblib', 'pandas', 'sklearn'];

    /** @var array{python: ?string, reason: string}|null */
    private static ?array $resolvedPython = null;

    protected function projectPythonOrSkip(): string
    {
        self::$resolvedPython ??= $this->resolveProjectPython();

        if (self::$resolvedPython['python'] === null) {
            $this->markTestSkipped(self::$resolvedPython['reason']);
        }

        return self::$resolvedPython['python'];
    }

    /** @return array<string, string|false> */
    protected function pythonEnvironment(string $python): array
    {
        return PythonRuntime::environment($python);
    }

    /** @return array{python: ?string, reason: string} */
    private function resolveProjectPython(): array
    {
        $candidates = array_values(array_unique(array_filter([
            base_path('.venv/Scripts/python.exe'),
            base_path('.venv/bin/python'),
            PythonRuntime::executable(),
        ])));

        $tried = [];
        foreach ($candidates as $python) {
            if (! is_file($python)) {
                $tried[] = $python.' (not found)';

                continue;
            }

            $probe = new Process([$python, '-c', 'import '.implode(', ', self::REQUIRED_PYTHON_MODULES)], base_path(), $this->pythonEnvironment($python));
            $probe->setTimeout(60);
            $probe->run();

            if ($probe->isSuccessful()) {
                return ['python' => $python, 'reason' => ''];
            }

            $tried[] = $python.' (missing '.implode('/', self::REQUIRED_PYTHON_MODULES).')';
        }

        return [
            'python' => null,
            'reason' => 'No usable Python environment for the rice-yield ML integration test. Tried: '.implode('; ', $tried)
                .'. Create .venv and run `pip install -r requirements.txt` to run it.',
        ];
    }
}
