<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A deleted account (User::STATUS_DELETED) is left out of every user query: lists, counts, MAO coverage, messaging,
 * dashboards, notifications and prediction inputs, and the signed-in user lookup (so its sessions end at once).
 * The few places that must still see it say so with User::withDeleted(): login (to tell the person why), the Users
 * list's Deleted filter, restore, and authorship / history relations ("Created by", logs, old messages).
 */
class HideDeletedUsers implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $status = $model->qualifyColumn('status');
        $builder->where(fn (Builder $q) => $q->whereNull($status)->orWhere($status, '!=', User::STATUS_DELETED));
    }
}
