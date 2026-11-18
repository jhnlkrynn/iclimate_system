<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;

/** Finds PostgreSQL client tools without requiring a machine-specific PATH. */
final class PostgresToolLocator
{
    /** @return string|null an explicit path, PATH result, or known package-manager path */
    public static function find(string $tool, ?string $configured = null): ?string
    {
        $configured = trim((string) $configured);
        if ($configured !== '' && (is_file($configured) || ! preg_match('#[\\\\/]#', $configured))) {
            return $configured;
        }

        $fromPath = (new ExecutableFinder)->find($tool);
        if ($fromPath !== null) {
            return $fromPath;
        }

        foreach (self::candidates($tool) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function candidates(string $tool): array
    {
        return [
            '/opt/homebrew/opt/libpq/bin/'.$tool,
            '/usr/local/opt/libpq/bin/'.$tool,
            '/opt/local/lib/postgresql16/bin/'.$tool,
            '/usr/lib/postgresql/16/bin/'.$tool,
            '/usr/lib/postgresql/15/bin/'.$tool,
            'C:\\Program Files\\PostgreSQL\\17\\bin\\'.$tool.'.exe',
            'C:\\Program Files\\PostgreSQL\\16\\bin\\'.$tool.'.exe',
        ];
    }
}
