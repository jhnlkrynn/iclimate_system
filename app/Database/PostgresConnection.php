<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use PDO;

/**
 * With PDO::ATTR_EMULATE_PREPARES enabled (see config/database.php), PDO
 * inlines integer bindings unquoted, so `where is_read = false` becomes
 * `is_read = 0` and Postgres rejects it (boolean = integer). Native pgsql
 * prepares send every parameter as untyped text and let Postgres infer the
 * type, so binding everything as a quoted string keeps the same semantics
 * while still getting the single round trip of emulated prepares.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new PostgresSchemaBuilder($this);
    }

    public function bindValues($statement, $bindings)
    {
        if (! ($this->getConfig('options')[PDO::ATTR_EMULATE_PREPARES] ?? false)) {
            parent::bindValues($statement, $bindings);

            return;
        }

        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_resource($value) ? PDO::PARAM_LOB : PDO::PARAM_STR,
            );
        }
    }
}
