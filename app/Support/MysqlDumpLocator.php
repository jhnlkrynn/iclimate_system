<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;

/** Finds a dump client compatible with the configured local MySQL/MariaDB installation. */
final class MysqlDumpLocator
{
    public static function find(?string $configured = null): ?string
    {
        $configured = trim((string) $configured);
        if ($configured !== '' && (is_file($configured) || ! preg_match('#[\\\\/]#', $configured))) {
            return $configured;
        }

        // XAMPP's client should win over a separately installed MySQL client when present:
        // it is built for the MariaDB server that XAMPP starts.
        foreach ([
            '/Applications/XAMPP/xamppfiles/bin/mysqldump',
            '/Applications/XAMPP/xamppfiles/bin/mariadb-dump',
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp/mysql/bin/mariadb-dump.exe',
        ] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return (new ExecutableFinder)->find('mysqldump')
            ?? (new ExecutableFinder)->find('mariadb-dump');
    }
}
