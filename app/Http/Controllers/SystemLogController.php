<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SystemLogController extends CrudController
{
    protected string $model = SystemLog::class;

    protected string $routeName = 'system-logs';

    protected string $title = 'System Log';

    protected array $columns = ['action' => 'Action', 'user.name' => 'Actor', 'details' => 'Details', 'created_at' => 'Date'];

    protected array $searchable = ['action', 'details'];

    protected bool $viewOnly = true;

    protected function baseQuery(Request $request): Builder
    {
        return SystemLog::query()->with('user');
    }

    protected function authorizeView(Request $request): void
    {
        if ($request->user()->role !== User::ROLE_IT_EXPERT) {
            abort(403);
        }
    }
}
