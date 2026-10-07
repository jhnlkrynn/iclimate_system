<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Same as Laravel's Postgres connector, except the schema (search_path) is
 * given to the server as a connection startup option instead of a separate
 * "SET search_path" statement after connecting.
 *
 * Why: that statement is one extra network round trip on every request
 * (about 350 ms to Supabase), and it is the only thing left to pay once the
 * connection itself is reused. With the option, a request that reuses its
 * connection costs nothing before its first real query.
 *
 * It only applies when it is switched on ('startup_search_path') and every
 * schema name is a plain identifier; otherwise the standard SET statement is
 * used, so behaviour never depends on the option being accepted.
 */
class StartupSearchPathPostgresConnector extends PostgresConnector
{
    /** How long one failed connect answers for the next ones (seconds). */
    private const UNREACHABLE_FOR = 10;

    /** @var array<string, array{float, \PDOException}> the last failed connect per server, in this PHP process */
    private static array $unreachable = [];

    /**
     * A server that could not be reached is not tried again for the rest of the request.
     *
     * Why: a page makes several separate database calls, and many of them catch a failure and carry on. Each one
     * would wait the whole connect timeout again (login: 2 x 3 s, measured 2026-10-07), and together they reach
     * PHP's request time limit. A web request ends long before the wait is over; a queue worker tries again after it.
     */
    public function connect(array $config)
    {
        $server = ($config['host'] ?? '').':'.($config['port'] ?? '');
        [$failedAt, $failure] = self::$unreachable[$server] ?? [null, null];

        if ($failure !== null && microtime(true) - $failedAt < self::UNREACHABLE_FOR) {
            throw new \PDOException($failure->getMessage(), (int) $failure->getCode(), $failure);
        }

        try {
            $connection = parent::connect($config);
        } catch (\PDOException $exception) {
            // SQLSTATE class 08: the server could not be reached (a wrong password or database is not remembered).
            if (str_contains($exception->getMessage(), 'SQLSTATE[08')) {
                self::$unreachable[$server] = [microtime(true), $exception];
            }

            throw $exception;
        }

        unset(self::$unreachable[$server]);

        return $connection;
    }

    /** Forget the remembered failures (tests). */
    public static function forgetUnreachable(): void
    {
        self::$unreachable = [];
    }

    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);
        $searchPath = $this->startupSearchPath($config);

        return $searchPath === null ? $dsn : $dsn.";options='-c search_path=".$searchPath."'";
    }

    protected function configureSearchPath($connection, $config)
    {
        if ($this->startupSearchPath($config) !== null) {
            return; // the server already applied it when the connection was opened
        }

        parent::configureSearchPath($connection, $config);
    }

    private function startupSearchPath(array $config): ?string
    {
        if (! ($config['startup_search_path'] ?? false)) {
            return null;
        }

        $raw = $config['search_path'] ?? $config['schema'] ?? null;

        if (! is_string($raw) && ! is_array($raw)) {
            return null;
        }

        $schemas = $this->parseSearchPath($raw);

        if ($schemas === []) {
            return null;
        }

        foreach ($schemas as $schema) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $schema) !== 1) {
                return null;
            }
        }

        return implode(',', $schemas);
    }
}
