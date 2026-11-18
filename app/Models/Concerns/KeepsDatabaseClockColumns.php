<?php

namespace App\Models\Concerns;

/**
 * For columns declared "ON UPDATE CURRENT_TIMESTAMP" in MariaDB/MySQL.
 *
 * The database fills such a column from its own clock in the session zone (DB_TIMEZONE=+08:00, Manila wall
 * clock) whenever an UPDATE does not set it. Laravel stores UTC wall-clock strings, so that value reads back
 * 8 hours in the future and old data looks fresh (seen on external_weather_data.fetched_at on 2026-11-10, when
 * the dashboard snapshot re-saved a reading with an unchanged fetch time).
 *
 * Every Eloquent UPDATE of a model using this trait therefore writes these columns explicitly: the value the
 * application set, or the stored one unchanged. The database clock never decides them.
 *
 * Models list the columns in DATABASE_CLOCK_COLUMNS.
 */
trait KeepsDatabaseClockColumns
{
    protected function getDirtyForUpdate()
    {
        $dirty = parent::getDirtyForUpdate();

        if ($dirty === []) {
            return $dirty;
        }

        foreach (static::DATABASE_CLOCK_COLUMNS as $column) {
            if (! array_key_exists($column, $dirty) && array_key_exists($column, $this->attributes)) {
                $dirty[$column] = $this->attributes[$column];
            }
        }

        return $dirty;
    }
}
