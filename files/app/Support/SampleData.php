<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * One-liner used by dashboards/statistics to leave seeded sample rows and
 * impossible readings out of every figure they show.
 */
final class SampleData
{
    /**
     * @param  EloquentBuilder|Builder  $query
     * @return EloquentBuilder|Builder
     */
    public static function without($query, string $table)
    {
        $ids = app(SampleDataDetector::class)->excludedIds($table);

        return $ids === [] ? $query : $query->whereNotIn($table.'.id', $ids);
    }
}
