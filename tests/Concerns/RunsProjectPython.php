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
        $binDir = dirname($python);
        $inVenv = str_starts_with($python, base_path('.venv').DIRECTORY_SEPARATOR);

        $environment = [
            'PYTHONHOME' => false,
            'PYTHONPATH' => false,
            'PYTHONUTF8' => '1',
            'PYTHONIOENCODING' => 'utf-8',
            // Keep prediction single-process.
            'JOBLIB_MULTIPROCESSING' => '0',
            'LOKY_MAX_CPU_COUNT' => '1',
            'OMP_NUM_THREADS' => '1',
            'OPENBLAS_NUM_THREADS' => '1',
            'MKL_NUM_THREADS' => '1',
            'NUMEXPR_NUM_THREADS' => '1',
        ];

        if ($inVenv) {
            $environment['VIRTUAL_ENV'] = base_path('.venv');
            $environment['PYTHONNOUSERSITE'] = '1';
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // A short, explicit PATH avoids the XAMPP DLL clashes (WinError 10106) the candidate controller works around.
            $windowsRoot = getenv('SystemRoot') ?: 'C:\Windows';
            $home = $this->venvHome();
            $path = implode(PATH_SEPARATOR, array_filter([
                $binDir,
                $home,
                $home ? $home.DIRECTORY_SEPARATOR.'Scripts' : null,
                $windowsRoot.DIRECTORY_SEPARATOR.'System32',
                $windowsRoot,
            ]));

            return $environment + [
                'PATH' => $path,
                'Path' => $path,
                'SYSTEMROOT' => $windowsRoot,
                'SystemRoot' => $windowsRoot,
                'WINDIR' => $windowsRoot,
                'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
                'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            ];
        }

        return $environment + ['PATH' => $binDir.PATH_SEPARATOR.(getenv('PATH') ?: '/usr/bin:/bin')];
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

    /** The base interpreter a Windows venv was created from (pyvenv.cfg "home"), for its DLLs. */
    private function venvHome(): ?string
    {
        $cfg = base_path('.venv/pyvenv.cfg');
        if (! is_file($cfg)) {
            return null;
        }

        return preg_match('/^home\s*=\s*(.+)$/mi', (string) file_get_contents($cfg), $m) ? trim($m[1]) : null;
    }
}
