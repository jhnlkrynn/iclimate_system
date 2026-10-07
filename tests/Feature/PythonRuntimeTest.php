<?php

namespace Tests\Feature;

use App\Services\MachineLearning\RiceYieldShadowComparisonService;
use App\Support\PythonRuntime;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The ML scripts find and run Python the same way on Windows, macOS and a Linux VPS, through App\Support\PythonRuntime,
 * and no production code depends on a path from one developer's computer.
 */
class PythonRuntimeTest extends TestCase
{
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if ($this->tempDir !== null) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_no_production_code_contains_a_developer_machine_python_path(): void
    {
        $offenders = [];
        foreach (['app', 'config', 'routes', 'bootstrap', 'database'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $code = $file->getContents();
                if (preg_match('/[A-Za-z]:\\\\Users\\\\|[A-Za-z]:\/Users\/|AppData\\\\(Local|Roaming)|Programs\\\\Python/i', $code)) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'Python must be resolved through App\Support\PythonRuntime, not a hardcoded path.');
    }

    public function test_on_linux_a_system_python_runs_with_its_own_path_and_no_windows_paths(): void
    {
        config(['iclimate.python_os_family' => 'Linux']);
        // A Linux machine's own PATH (the inherited one is kept after the interpreter's folder).
        $inherited = getenv('PATH');
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');

        try {
            $env = PythonRuntime::environment('/usr/bin/python3');
        } finally {
            putenv('PATH='.$inherited);
        }

        $this->assertStringStartsWith('/usr/bin'.PATH_SEPARATOR, $env['PATH']);
        $this->assertArrayNotHasKey('VIRTUAL_ENV', $env);              // not a virtualenv: its packages stay visible
        $this->assertArrayNotHasKey('PYTHONNOUSERSITE', $env);
        $this->assertArrayNotHasKey('SYSTEMROOT', $env);
        $this->assertStringNotContainsString('C:\\', implode('|', array_filter($env, 'is_string')));
        $this->assertSame('1', $env['OMP_NUM_THREADS']);
    }

    public function test_on_linux_the_project_virtualenv_is_isolated(): void
    {
        config(['iclimate.python_os_family' => 'Linux']);
        $python = base_path('.venv/bin/python');

        $env = PythonRuntime::environment($python);

        $this->assertStringStartsWith(base_path('.venv/bin').PATH_SEPARATOR, $env['PATH']);
        $this->assertSame(base_path('.venv'), $env['VIRTUAL_ENV']);
        $this->assertSame('1', $env['PYTHONNOUSERSITE']);
    }

    public function test_on_windows_the_path_is_built_from_the_interpreter_and_the_system_root_only(): void
    {
        config(['iclimate.python_os_family' => 'Windows']);
        $python = base_path('.venv'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'python.exe');

        $env = PythonRuntime::environment($python);

        $this->assertStringStartsWith(dirname($python).PATH_SEPARATOR, $env['PATH']);
        $this->assertSame($env['PATH'], $env['Path']);
        $this->assertNotEmpty($env['SYSTEMROOT']);
        $this->assertStringNotContainsString('Luke', implode('|', array_filter($env, 'is_string')));
    }

    public function test_a_windows_python_binary_setting_is_ignored_on_linux_and_macos(): void
    {
        config(['iclimate.python_os_family' => 'Linux', 'iclimate.python_binary' => 'C:\Python312\python.exe']);

        $this->assertNotSame('C:\Python312\python.exe', PythonRuntime::executable());
    }

    public function test_on_windows_a_configured_path_that_does_not_exist_is_kept_so_the_health_check_reports_it(): void
    {
        config(['iclimate.python_os_family' => 'Windows', 'iclimate.python_binary' => 'Z:\no-such-folder\python.exe']);

        $this->assertSame('Z:\no-such-folder\python.exe', PythonRuntime::executable());
    }

    public function test_the_project_virtualenv_is_recognised_whatever_the_path_separators(): void
    {
        config(['iclimate.python_os_family' => 'Windows']);

        // How the app finds it on Windows: base_path() uses "\" but the relative part keeps "/".
        $env = PythonRuntime::environment(base_path('.venv/Scripts/python.exe'));

        $this->assertSame(base_path('.venv'), $env['VIRTUAL_ENV']);
        $this->assertSame('1', $env['PYTHONNOUSERSITE']);
    }

    public function test_the_candidate_prediction_controller_runs_the_python_that_python_runtime_resolves(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Uses a POSIX shell script as a stand-in Python.');
        }

        // A stand-in "python" that records how it was started and answers like the candidate predictor.
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'iclimate-python-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->tempDir);
        $python = $this->tempDir.DIRECTORY_SEPARATOR.'python';
        File::put($python, "#!/bin/sh\nprintf '%s\\n%s\\n%s\\n' \"\$PATH\" \"\${VIRTUAL_ENV:-none}\" \"\$1\" > \"\$(dirname \"\$0\")/started.txt\"\necho '{\"success\": true, \"predicted_yield\": 4.2}'\n");
        chmod($python, 0755);
        config(['iclimate.python_binary' => $python]);

        $this->assertSame($python, PythonRuntime::executable());

        // The candidate test page's controller was removed in the 2026-10 cleanup; the shadow comparison runs the
        // same script the same way.
        $run = new \ReflectionMethod(RiceYieldShadowComparisonService::class, 'runCandidateRiceYield');
        $result = $run->invoke(app(RiceYieldShadowComparisonService::class), ['month' => 10, 'rainfall' => 226.4]);

        $this->assertSame(4.2, (float) $result['predicted_yield']);
        [$path, $venv, $script] = explode("\n", trim(File::get($this->tempDir.DIRECTORY_SEPARATOR.'started.txt')));
        $this->assertSame(PythonRuntime::environment($python)['PATH'], $path);   // the shared environment, not a copy
        $this->assertStringStartsWith($this->tempDir.PATH_SEPARATOR, $path);
        $this->assertStringNotContainsString('C:\\', $path);
        $this->assertSame('none', $venv);                                        // not the project venv: not marked as one
        $this->assertSame(base_path('python_scripts/predict_rice_yield_candidate.py'), $script);
    }
}
