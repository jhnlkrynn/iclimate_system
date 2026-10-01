<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasTable() is a database round trip (about 340 ms to Supabase) and
 * several pages ran it on every load to check that a migrated table exists.
 * A table that exists keeps existing, so the answer is remembered. Only a
 * positive answer is cached: a table that is missing is asked about again, so
 * running the migration takes effect immediately.
 */
class SchemaCache
{
    public static function hasTable(string $table): bool
    {
        $key = 'schema:has-table:'.config('database.default').':'.$table;

        if (Cache::get($key) === true) {
            return true;
        }

        $exists = Schema::hasTable($table);

        if ($exists) {
            Cache::put($key, true, now()->addHours(6));
        }

        return $exists;
    }

    public static function forget(string $table): void
    {
        Cache::forget('schema:has-table:'.config('database.default').':'.$table);
    }
}
