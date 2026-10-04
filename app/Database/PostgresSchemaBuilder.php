<?php

namespace App\Database;

use Illuminate\Database\Schema\PostgresBuilder;
use Illuminate\Support\Facades\Cache;

/**
 * Feature checks like Schema::hasTable('advisory_feedback') run on many page
 * loads, and each one is a round trip to the remote database. Only "exists"
 * answers are cached: a table created by a new migration is seen at once,
 * and every migration run bumps VERSION_KEY so dropped tables are re-checked
 * (see AppServiceProvider).
 */
class PostgresSchemaBuilder extends PostgresBuilder
{
    public const VERSION_KEY = 'schema:has-table:version';

    public function hasTable($table)
    {
        $key = 'schema:has-table:'.Cache::get(self::VERSION_KEY, 0).':'.$this->connection->getName().':'.$table;

        if (Cache::get($key) === true) {
            return true;
        }

        $exists = parent::hasTable($table);

        if ($exists) {
            Cache::put($key, true, now()->addDay());
        }

        return $exists;
    }
}
