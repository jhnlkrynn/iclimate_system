<?php

namespace App\Support;

final class LogScrubber
{
    /**
     * Redact URL credentials, key=value secrets, and configured runtime passwords/keys from log strings.
     */
    public static function scrub(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Redact URI user:password@host patterns (e.g. mysql://user:pass@host, pgsql://..., https://token@...)
        $scrubbed = (string) preg_replace('#([a-z][a-z0-9+.-]*://[^:\s/@]+:)([^@\s]+)(@)#i', '$1[REDACTED]$3', $text);

        // Redact key=value / key: value secrets
        $scrubbed = (string) preg_replace(
            '/\b(password|passwd|pwd|db_password|secret|app_key|api_key|groq_api_key|ml_admin_token|reverb_app_secret|token|authorization)\s*([=:])\s*(?:"[^"]*"|\'[^\']*\'|[^\s;,\]]+)/i',
            '$1$2[REDACTED]',
            $scrubbed
        );

        // Redact Bearer tokens
        $scrubbed = (string) preg_replace('/\bBearer\s+[A-Za-z0-9._\-+=]+/i', 'Bearer [REDACTED]', $scrubbed);

        // Redact any configured runtime secret values (>= 6 chars to avoid false-positive masking)
        $secrets = array_filter([
            config('database.connections.mysql.password'),
            config('database.connections.mysql_backup.password'),
            config('database.connections.pgsql.password'),
            config('app.key'),
            config('services.groq.api_key'),
            config('broadcasting.connections.reverb.secret'),
            config('services.farming_ai.admin_token'),
        ], fn ($v) => is_string($v) && strlen($v) >= 6);

        if ($secrets !== []) {
            $scrubbed = str_replace(array_values(array_unique($secrets)), '[REDACTED]', $scrubbed);
        }

        return $scrubbed;
    }
}
