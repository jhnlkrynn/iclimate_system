<?php

namespace App\Http\Middleware;

use App\Support\SchemaCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deployment guard: a feature whose tables are not migrated yet answers 503 ("being updated, nothing was saved")
 * before any controller code runs, instead of a 500 or a half-saved request. Usage on a route or group:
 *     ->middleware(RequireSchema::class.':table_one,table_two')
 * Once the migration runs, SchemaCache is refreshed (AppServiceProvider listens for MigrationsEnded) and this is a
 * cached in_array check.
 */
class RequireSchema
{
    public const MESSAGE = 'This part of iClimate is being updated. Nothing was saved. Please try again later.';

    public function handle(Request $request, Closure $next, string ...$tables): Response
    {
        foreach ($tables as $table) {
            if (! SchemaCache::hasTable($table)) {
                abort(503, self::MESSAGE, ['Retry-After' => '600']);
            }
        }

        return $next($request);
    }
}
