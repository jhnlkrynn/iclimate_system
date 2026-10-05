<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;

final class PythonRuntime
{
    private function __construct() {}

    /** Resolve the Python runtime without trusting a path copied from another OS. */
    public static function executable(): ?string
    {
        $configured = trim((string) config('iclimate.python_binary', ''), " \t\n\r\0\x0B\"'");

        if ($configured !== '') {
            if (is_file($configured)) {
                return $configured;
            }

            // Ignore an incompatible Windows path on Unix, but preserve other
            // explicit paths so health checks can report them accurately.
            if (self::isWindows() || ! preg_match('/^[A-Za-z]:[\\\\\/]/', $configured)) {
                return $configured;
            }
        }

        foreach ([base_path('.venv/bin/python'), base_path('.venv/Scripts/python.exe')] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder();

        return $finder->find('python3') ?? $finder->find('python');
    }

    /**
     * Environment overrides for running an ML script with $python (Symfony Process merges them over the inherited
     * environment). Built only from $python, the project folder and this machine's own variables, never from a path
     * on one developer's computer, so it works the same on Windows, macOS and a Linux VPS.
     *
     * @return array<string, string|false>
     */
    public static function environment(string $python): array
    {
        $binDir = dirname($python);
        $venv = base_path('.venv');

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

        // Only an interpreter inside the project's virtualenv is isolated from user site-packages. Compared with one
        // separator: base_path('.venv/Scripts/python.exe') is "C:\...\.venv/Scripts/python.exe" on Windows.
        $slashes = fn (string $path): string => str_replace('\\', '/', $path);
        if (str_starts_with($slashes($python), $slashes($venv).'/')) {
            $environment['VIRTUAL_ENV'] = $venv;
            $environment['PYTHONNOUSERSITE'] = '1';
        }

        if (self::isWindows()) {
            // A short, explicit PATH avoids the XAMPP DLL clashes (WinError 10106) an inherited PATH causes.
            $windowsRoot = getenv('SystemRoot') ?: 'C:\Windows';
            $home = self::venvHome($venv);
            $path = implode(PATH_SEPARATOR, array_filter([
                $binDir,
                $home,
                $home !== null ? $home.DIRECTORY_SEPARATOR.'Scripts' : null,
                $windowsRoot.DIRECTORY_SEPARATOR.'System32',
                $windowsRoot,
            ]));

            return $environment + [
                'PATH' => $path,
                'Path' => $path,
                'SYSTEMROOT' => $windowsRoot,
                'SystemRoot' => $windowsRoot,
                'WINDIR' => $windowsRoot,
            ];
        }

        return $environment + ['PATH' => $binDir.PATH_SEPARATOR.(getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin')];
    }

    /** Overridable in tests through the "iclimate.python_os_family" config key. */
    private static function isWindows(): bool
    {
        return (config('iclimate.python_os_family') ?: PHP_OS_FAMILY) === 'Windows';
    }

    /** The base interpreter a Windows virtualenv was created from (pyvenv.cfg "home"), for its DLLs. */
    private static function venvHome(string $venv): ?string
    {
        $cfg = $venv.DIRECTORY_SEPARATOR.'pyvenv.cfg';
        if (! is_file($cfg)) {
            return null;
        }

        return preg_match('/^home\s*=\s*(.+)$/mi', (string) file_get_contents($cfg), $m) ? trim($m[1]) : null;
    }
}
