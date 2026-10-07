<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasTable() / hasColumn() are catalog queries: one full network round trip to Supabase each, and the
 * dashboard builds ask about a dozen tables. Here one catalog query lists every table of the current schema, and
 * one query per table lists its columns; the answers (a "no" included) come from that list.
 *
 * The lists are kept until the next migration: every migration run bumps the version they are stored under
 * (AppServiceProvider listens for MigrationsEnded), so a table or column a migration adds or drops is seen at once.
 * They also lapse after a few hours.
 */
final class SchemaCache
{
    public const VERSION_KEY = 'schema-cache:version';

    private const KEEP_HOURS = 6;

    public static function hasTable(string $table): bool
    {
        return in_array(strtolower($table), self::tables(), true);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), self::columns($table), true);
    }

    /** A migration added, changed or dropped something: every list is read again. */
    public static function forget(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /** @return list<string> lower-case names of the tables in the connection's current schema */
    private static function tables(): array
    {
        return self::remember('tables', static fn (): array => array_map(
            static fn (array $table): string => strtolower((string) $table['name']),
            Schema::getTables(Schema::getCurrentSchemaName()),
        ));
    }

    /** @return list<string> lower-case column names (empty when the table does not exist) */
    private static function columns(string $table): array
    {
        if (! self::hasTable($table)) {
            return [];
        }

        return self::remember('columns:'.strtolower($table), static fn (): array => array_map(strtolower(...), Schema::getColumnListing($table)));
    }

    /** @param \Closure(): list<string> $read */
    private static function remember(string $name, \Closure $read): array
    {
        $key = 'schema-cache:'.config('database.default').':v'.(int) Cache::get(self::VERSION_KEY, 1).':'.$name;

        return Cache::remember($key, now()->addHours(self::KEEP_HOURS), $read);
    }
}
