<?php

namespace App\Services;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemAuditLogger
{
    public static function record(string $action, ?Request $request = null, array $context = [], ?User $user = null): void
    {
        if (! Schema::hasTable('system_logs')) {
            return;
        }

        try {
            $actor = $user ?? $request?->user();

            SystemLog::query()->create([
                'user_id' => $actor?->id,
                'action' => $action,
                'details' => self::details($request, $context),
            ]);
        } catch (Throwable) {
            report(new \RuntimeException('Unable to write system audit log for action: '.$action));
        }
    }

    public static function forModel(string $verb, Model $model, ?Request $request = null, array $context = []): void
    {
        self::record(str($verb.' '.class_basename($model))->headline()->toString(), $request, [
            'record_type' => class_basename($model),
            'record_id' => $model->getKey(),
        ] + $context);
    }

    private static function details(?Request $request, array $context): string
    {
        $payload = array_filter([
            'user' => $request?->user()?->email,
            'role' => $request?->user()?->role,
            'method' => $request?->method(),
            'path' => $request?->path(),
            'ip' => $request?->ip(),
            'context' => $context,
        ], fn ($value) => $value !== null && $value !== []);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
