<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Cross-database row identity and hashing for read-only migration comparisons. */
final class DeterministicRowHash
{
    /** @param array<string, array{source: string, target: string}> $types */
    public static function row(array $row, array $columns, array $types): string
    {
        $values = [];
        foreach ($columns as $column) {
            $values[] = [$column, self::value($row[$column] ?? null, $types[$column] ?? ['source' => '', 'target' => ''])];
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $primaryKey @param array<string, array{source: string, target: string}> $types */
    public static function key(array $row, array $primaryKey, array $types): string
    {
        $values = [];
        foreach ($primaryKey as $column) {
            $values[$column] = self::value($row[$column] ?? null, $types[$column] ?? ['source' => '', 'target' => '']);
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array{source: string, target: string} $types */
    public static function value(mixed $value, array $types): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (is_bool($value)) {
            return 'bool:'.($value ? '1' : '0');
        }

        $sourceType = strtolower($types['source'] ?? '');
        $targetType = strtolower($types['target'] ?? '');
        $type = $sourceType.' '.$targetType;

        if (str_contains($type, 'json') || self::looksLikeJson($value)) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                self::sortJson($decoded);

                return 'json:'.json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }
        if (str_contains($type, 'bool') || $value === 't' || $value === 'f') {
            return 'bool:'.(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0');
        }
        if (str_contains($type, 'date') || str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
            try {
                $parsed = CarbonImmutable::parse((string) $value, 'UTC');

                preg_match('/precision=(\d+)/', $sourceType, $sourcePrecision);
                preg_match('/precision=(\d+)/', $targetType, $targetPrecision);
                $precision = min((int) ($sourcePrecision[1] ?? 6), (int) ($targetPrecision[1] ?? 6));
                $formatted = $parsed->utc()->format('Y-m-d\\TH:i:s.u\\Z');

                return str_contains($type, 'date') && ! str_contains($type, 'time')
                    ? 'date:'.$parsed->format('Y-m-d')
                    : 'time:'.($precision > 0
                        ? substr($formatted, 0, 20 + $precision).'Z'
                        : substr($formatted, 0, 19).'Z');
            } catch (\Throwable) {
                // Keep an unparseable value visible in the hash rather than silently treating it as equal.
            }
        }
        if (is_numeric($value) && preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/', (string) $value) === 1) {
            return 'number:'.self::number((string) $value);
        }

        return 'text:'.(string) $value;
    }

    private static function number(string $value): string
    {
        $value = ltrim($value, '+');
        if (str_contains(strtolower($value), 'e')) {
            $value = sprintf('%.17g', (float) $value);
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $result = $whole.($fraction === '' ? '' : '.'.$fraction);

        return $result === '0' ? '0' : ($negative ? '-'.$result : $result);
    }

    private static function looksLikeJson(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private static function sortJson(mixed &$value): void
    {
        if (! is_array($value)) {
            return;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            self::sortJson($item);
        }
    }
}
