<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Panel revision 4.4 C: a record remembers who created it (created_by) and who last edited it (updated_by), set from
 * the signed-in account. Shown as "Created by: Jean L. Laviste on Oct 10, 2026, 4:16 PM" (App\Support\MaoStamp).
 */
trait RecordsAuthor
{
    public static function bootRecordsAuthor(): void
    {
        static::creating(function ($model): void {
            $id = auth()->id();
            if ($id && $model->authorColumnExists('created_by') && empty($model->created_by)) {
                $model->created_by = $id;
            }
            if ($id && $model->authorColumnExists('updated_by') && empty($model->updated_by)) {
                $model->updated_by = $id;
            }
        });

        static::updating(function ($model): void {
            $id = auth()->id();
            if ($id && $model->authorColumnExists('updated_by') && $model->isDirty() && ! $model->isDirty('updated_by')) {
                $model->updated_by = $id;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    private function authorColumnExists(string $column): bool
    {
        return SchemaCache::hasColumn($this->getTable(), $column);
    }
}
