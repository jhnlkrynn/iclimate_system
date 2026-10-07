<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Performance Phase 4. Every count(), max() or avg() is one round trip to Supabase (about 150 ms). fetch() sends
 * several of them as one statement, each as its own sub-select:
 *
 *     select (select count(*) as aggregate from "users" where "role" = ?) as "farmers",
 *            (select max("created_at") as aggregate from "notifications") as "latest"
 *
 * Each sub-select is exactly the SQL the builder's own count()/max()/... would send, and each value gets the same
 * PHP type that method returns (count: int, sum: 0 when empty, the others as the driver returns them). Inside a
 * BuildMemo::run() build, a read already made (same SQL, same values) is not sent again.
 */
final class Aggregates
{
    public static function count(EloquentBuilder|Builder $query, string $column = '*'): array
    {
        return self::read($query, 'count', $column);
    }

    public static function max(EloquentBuilder|Builder $query, Expression|string $column): array
    {
        return self::read($query, 'max', $column);
    }

    public static function min(EloquentBuilder|Builder $query, Expression|string $column): array
    {
        return self::read($query, 'min', $column);
    }

    public static function avg(EloquentBuilder|Builder $query, Expression|string $column): array
    {
        return self::read($query, 'avg', $column);
    }

    public static function sum(EloquentBuilder|Builder $query, Expression|string $column): array
    {
        return self::read($query, 'sum', $column);
    }

    /**
     * @param  array<string, array{0: Builder, 1: string}>  $reads  name => Aggregates::count(...) / max(...) / ...
     * @return array<string, mixed> name => value, in the order given
     */
    public static function fetch(array $reads): array
    {
        $keys = [];
        $missing = [];
        $fetched = [];
        foreach ($reads as $name => [$query]) {
            $keys[$name] = $key = 'aggregate:'.$query->getConnection()->getName().':'.$query->toSql()
                .':'.json_encode($query->getConnection()->prepareBindings($query->getBindings()));
            if (! BuildMemo::has($key)) {
                $missing[$name] = $query;
            }
        }

        if ($missing !== []) {
            $outer = reset($missing)->getConnection()->query();
            $aliases = [];
            foreach (array_keys($missing) as $index => $name) {
                $outer->selectSub($missing[$name], $aliases[$name] = 'v'.$index);
            }
            $row = (array) $outer->first();
            foreach ($missing as $name => $query) {
                $fetched[$name] = $row[$aliases[$name]] ?? null;
                BuildMemo::put($keys[$name], $fetched[$name]);
            }
        }

        $values = [];
        foreach ($reads as $name => [, $function]) {
            $value = array_key_exists($name, $fetched) ? $fetched[$name] : BuildMemo::get($keys[$name]);
            $values[$name] = match ($function) {
                'count' => (int) $value,
                'sum' => $value ?: 0,
                default => $value,
            };
        }

        return $values;
    }

    /** The query exactly as Builder::aggregate() would send it. */
    private static function read(EloquentBuilder|Builder $query, string $function, Expression|string $column): array
    {
        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        if ($base->unions || $base->havings) {
            throw new \LogicException('Aggregates::'.$function.'() supports plain filtered queries only.');
        }

        $base = $base->cloneWithout(['columns'])->cloneWithoutBindings(['select']);
        $base->aggregate = ['function' => $function, 'columns' => [$column]];
        if (empty($base->groups)) {
            $base->orders = null;
            $base->bindings['order'] = [];
        }

        return [$base, $function];
    }
}
