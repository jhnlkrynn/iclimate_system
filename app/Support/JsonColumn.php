<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Database-side reads of a JSON column that work whatever type the column has in the database.
 *
 * The migrations declare these columns json, but on the live Supabase schema they are text (planting_advisories.metadata,
 * analytics_runs.result, heatmap_areas.risk_components, ...). Laravel's "column->key" paths compile to the ->> operator,
 * which PostgreSQL rejects on text ("operator does not exist: text ->> unknown", a 500). On PostgreSQL the value is
 * cast to jsonb first, which works for text, json and jsonb alike; SQLite (the test database) and MySQL (XAMPP) use
 * their own JSON functions.
 * Either way an empty value counts as "no JSON" instead of failing as malformed.
 */
final class JsonColumn
{
    private function __construct() {}

    /** where column->key <operator> value (a null key is "missing"; see whereKeyMissingOrNot). */
    public static function where(BuilderContract $query, string $column, string $key, string $operator, mixed $value, string $boolean = 'and'): BuilderContract
    {
        return $query->whereRaw(self::text($query, $column, $key).' '.self::operator($operator).' ?', [(string) $value], $boolean);
    }

    /** column->key is missing/null, or differs from $value. */
    public static function whereKeyMissingOrNot(BuilderContract $query, string $column, string $key, string $value): BuilderContract
    {
        return $query->where(function ($q) use ($column, $key, $value) {
            $q->whereRaw(self::text($q, $column, $key).' IS NULL')->orWhereRaw(self::text($q, $column, $key).' <> ?', [$value]);
        });
    }

    /** The JSON array at column->key contains $value. */
    public static function orWhereContains(BuilderContract $query, string $column, string $key, string $value): BuilderContract
    {
        return match (self::driver($query)) {
            'pgsql' => $query->orWhereRaw('COALESCE('.self::pgJson($column).' -> '.self::quoteKey($key).", '[]'::jsonb) @> ?::jsonb", [json_encode([$value])]),
            'sqlite' => $query->orWhereRaw('EXISTS (SELECT 1 FROM json_each(NULLIF('.self::quoteColumn($column).", ''), '$.".self::pathKey($key)."') WHERE json_each.value = ?)", [$value]),
            default => $query->orWhereJsonContains($column.'->'.$key, $value),       // MySQL / MariaDB (XAMPP): native json
        };
    }

    private static function driver(BuilderContract $query): string
    {
        $queryBuilder = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        if (! $queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Unsupported database query builder: '.$query::class);
        }

        return $queryBuilder->getConnection()->getDriverName();
    }

    /** The key's value as text. An empty column value is "no JSON", not malformed JSON. */
    private static function text(BuilderContract $query, string $column, string $key): string
    {
        return match (self::driver($query)) {
            'pgsql' => '('.self::pgJson($column).' ->> '.self::quoteKey($key).')',
            'sqlite' => 'json_extract(NULLIF('.self::quoteColumn($column).", ''), '$.".self::pathKey($key)."')",
            // MySQL / MariaDB (XAMPP).
            default => 'JSON_UNQUOTE(JSON_EXTRACT(NULLIF(`'.str_replace('`', '``', $column)."`, ''), '$.".self::pathKey($key)."'))",
        };
    }

    private static function pgJson(string $column): string
    {
        // Through text first: json has no equality operator (NULLIF), and an empty text value is not valid jsonb.
        return 'CAST(NULLIF(CAST('.self::quoteColumn($column)." AS text), '') AS jsonb)";
    }

    private static function quoteColumn(string $column): string
    {
        return '"'.str_replace('"', '""', $column).'"';
    }

    private static function pathKey(string $key): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException("Unsupported JSON key: {$key}");
        }

        return $key;
    }

    private static function quoteKey(string $key): string
    {
        return "'".str_replace("'", "''", $key)."'";
    }

    private static function operator(string $operator): string
    {
        return match ($operator) {
            '=', '<>', '<', '>', '<=', '>=' => $operator,
            '!=' => '<>',
            'like' => 'LIKE',
            default => throw new \InvalidArgumentException("Unsupported operator for a JSON key: {$operator}"),
        };
    }
}
