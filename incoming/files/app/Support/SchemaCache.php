<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasTable() is a catalog query — one full network round trip
 * (~300 ms) against Supabase every time it is called, and several pages call
 * it more than once. A table that exists keeps existing, so "yes" is
 * remembered; "no" is never cached so a freshly migrated table shows up at once.
 */
final class SchemaCache
{
    public static function hasTable(string $table): bool
    {
        $exists = Cache::remember(
            'schema:has-table:'.config('database.default').':'.$table,
            now()->addHours(6),
            fn (): ?bool => Schema::hasTable($table) ?: null, // null is not stored, so a missing table is re-checked
        );

        return (bool) $exists;
    }

    /** Same idea for a column added by a later migration: "yes" is remembered, "no" is re-checked. */
    public static function hasColumn(string $table, string $column): bool
    {
        $exists = Cache::remember(
            'schema:has-column:'.config('database.default').':'.$table.'.'.$column,
            now()->addHours(6),
            fn (): ?bool => Schema::hasColumn($table, $column) ?: null,
        );

        return (bool) $exists;
    }
}
