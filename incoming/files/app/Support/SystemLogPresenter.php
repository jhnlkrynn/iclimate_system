<?php

namespace App\Support;

use App\Models\SystemLog;

/**
 * Turns a raw system_logs row (action + JSON details) into human-readable fields.
 * Nothing is stored or removed; the raw data stays available for the technical view.
 */
class SystemLogPresenter
{
    private const ACTION_LABELS = [
        'user login' => 'Logged In',
        'user logout' => 'Logged Out',
        'login' => 'Logged In',
        'logout' => 'Logged Out',
    ];

    /** @return array<string, mixed> */
    public static function parse(?string $details): array
    {
        if ($details === null || $details === '') {
            return [];
        }

        $decoded = json_decode($details, true);

        return is_array($decoded) ? $decoded : ['note' => $details];
    }

    public static function actionLabel(string $action): string
    {
        $key = strtolower(trim($action));

        if (isset(self::ACTION_LABELS[$key])) {
            return self::ACTION_LABELS[$key];
        }

        return str($action)->replace(['_', '-'], ' ')->headline()->toString();
    }

    /** Icon key used by the view: login, logout, create, update, delete, prediction, other. */
    /** Both legacy "login" and "User Login" mean the same event. Each row has one action, so no double-counting. */
    public const LOGIN_ACTIONS = ['user login', 'login'];

    public static function isLogin(string $action): bool
    {
        return in_array(strtolower(trim($action)), self::LOGIN_ACTIONS, true);
    }

    public static function actionKind(string $action): string
    {
        $a = strtolower($action);

        return match (true) {
            str_contains($a, 'logout') => 'logout',
            str_contains($a, 'login') => 'login',
            str_contains($a, 'delet') || str_contains($a, 'archiv') || str_contains($a, 'reject') => 'delete',
            str_contains($a, 'creat') => 'create',
            str_contains($a, 'updat') || str_contains($a, 'publish') || str_contains($a, 'marked') => 'update',
            str_contains($a, 'predict') || str_contains($a, 'analytic') || str_contains($a, 'report') => 'prediction',
            default => 'other',
        };
    }

    /**
     * The table has no status column, so we never claim success. "Failed" is shown only when the
     * log itself explicitly says so (in the action name or in its recorded context).
     */
    public static function status(string $action, array $payload): string
    {
        $pattern = '/\b(fail(ed|ure)?|denied|error|forbidden|unauthori[sz]ed)\b/i';
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];

        if (preg_match($pattern, str_replace('_', ' ', $action))) {
            return 'Failed';
        }
        foreach (['status', 'result', 'outcome'] as $key) {
            if (isset($context[$key]) && is_string($context[$key]) && preg_match($pattern, $context[$key])) {
                return 'Failed';
            }
        }

        return isset($context['error']) && $context['error'] !== '' && $context['error'] !== false ? 'Failed' : 'Recorded';
    }

    public static function userName(SystemLog $log, array $payload): string
    {
        return $log->user?->name ?? ($payload['user'] ?? 'System');
    }

    /** Role stored in the log when present (role at the time); otherwise the user's current role. */
    public static function roleInfo(SystemLog $log, array $payload): array
    {
        if (! empty($payload['role']) && is_string($payload['role'])) {
            return ['role' => $payload['role'], 'stored' => true];
        }

        return ['role' => $log->user?->role ?? '—', 'stored' => false];
    }

    /** One plain sentence describing what happened. */
    public static function description(SystemLog $log, array $payload): string
    {
        $kind = self::actionKind($log->action);
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $module = $context['module'] ?? null;
        $recordType = isset($context['record_type']) ? str($context['record_type'])->headline()->lower()->toString() : null;
        $recordId = $context['record_id'] ?? null;

        if ($kind === 'login') {
            return 'Login through the system.';
        }
        if ($kind === 'logout') {
            return 'Signed out of the system.';
        }
        if ($recordType) {
            $what = $recordType.($recordId ? " #{$recordId}" : '');

            return ucfirst(self::actionLabel($log->action)).' — '.($module ? "{$module}: " : '').$what.'.';
        }
        if (isset($payload['note'])) {
            return (string) $payload['note'];
        }

        return self::actionLabel($log->action).'.';
    }

    /** @return array<string, mixed> Row + modal data in one structure. */
    public static function present(SystemLog $log): array
    {
        $payload = self::parse($log->details);
        $label = self::actionLabel($log->action);
        $when = $log->created_at?->timezone(config('app.timezone'));
        $role = self::roleInfo($log, $payload);

        return [
            'id' => $log->id,
            'action' => $label,
            'kind' => self::actionKind($log->action),
            'user' => self::userName($log, $payload),
            'role' => $role['role'],
            'status' => self::status($log->action, $payload),
            'is_login' => self::isLogin($log->action),
            'short' => $when?->format('M j, Y, g:i A') ?? '—',
            'long' => $when?->format('F j, Y, g:i A') ?? '—',
            'description' => self::description($log, $payload),
            'technical' => [
                'IP address' => $payload['ip'] ?? '—',
                'Request method' => $payload['method'] ?? '—',
                'Route / path' => isset($payload['path']) ? '/'.ltrim($payload['path'], '/') : '—',
                'User ID' => $log->user_id ?? '—',
                'Original action' => $log->action,
                'Role source' => $role['stored']
                    ? 'Role recorded in the log at the time of the activity.'
                    : ($log->user ? "Role shown is the user's current role; historical role was not stored in this log." : 'No role was stored in this log.'),
            ],
            'raw' => $payload !== [] ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($log->details ?? ''),
        ];
    }
}
