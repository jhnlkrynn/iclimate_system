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
            if (! preg_match('/^[A-Za-z]:[\\\\\/]/', $configured)) {
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
}
