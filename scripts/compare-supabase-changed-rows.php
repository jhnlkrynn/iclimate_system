<?php

declare(strict_types=1);

/**
 * Read-only field-level review for changed rows from a migration comparison.
 * Emits table/PK/column names and classifications only; never emits values.
 */
$options = getopt('', ['input:', 'output:']);
$input = $options['input'] ?? null;
$output = $options['output'] ?? null;
if (! is_string($input) || ! is_file($input) || ! is_string($output)) {
    fwrite(STDERR, "Usage: php scripts/compare-supabase-changed-rows.php --input=/path/live-compare.json --output=/path/report.json\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = config('migration.source');
$target = [
    'driver' => env('MIGRATION_TARGET_DB_CONNECTION', 'mysql'),
    'host' => env('MIGRATION_TARGET_DB_HOST', '127.0.0.1'),
    'port' => env('MIGRATION_TARGET_DB_PORT', '3306'),
    'database' => env('MIGRATION_TARGET_DB_DATABASE'),
    'username' => env('MIGRATION_TARGET_DB_USERNAME'),
    'password' => env('MIGRATION_TARGET_DB_PASSWORD'),
];
config([
    'database.connections.audit_source' => [
        'driver' => $source['driver'], 'host' => $source['host'], 'port' => $source['port'],
        'database' => $source['database'], 'username' => $source['username'], 'password' => $source['password'],
        'search_path' => $source['schema'], 'sslmode' => $source['sslmode'],
    ],
    'database.connections.audit_target' => [
        'driver' => $target['driver'], 'host' => $target['host'], 'port' => $target['port'],
        'database' => $target['database'], 'username' => $target['username'], 'password' => $target['password'],
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true,
    ],
]);

$sourceDb = Illuminate\Support\Facades\DB::connection('audit_source');
$targetDb = Illuminate\Support\Facades\DB::connection('audit_target');
$compare = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
$schema = (string) ($source['schema'] ?? 'public');

$canonical = static function (mixed $value, string $column = ''): string {
    if ($value === null) return 'null';
    $text = (string) $value;
    if (in_array($column, ['is_read', 'is_mao_head', 'must_change_password'], true)) {
        return 'bool:'.(in_array(strtolower($text), ['1', 't', 'true'], true) ? '1' : '0');
    }
    if (str_ends_with($column, '_at')) {
        try {
            return 'time:'.(new DateTimeImmutable($text, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (Throwable) {
            // Preserve unparseable timestamp text below.
        }
    }
    $json = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $sort = static function (&$node) use (&$sort): void {
            if (! is_array($node)) return;
            if (array_keys($node) !== range(0, count($node) - 1)) ksort($node);
            foreach ($node as &$child) $sort($child);
        };
        $sort($json);
        return 'json:'.json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    return $text;
};

$classify = static function (string $table, array $columns, bool $targetNewer): string {
    $operational = [
        'created_at', 'updated_at', 'verified_at', 'verified_by', 'sent_at',
        'last_active_at', 'last_login_at', 'read_at', 'seen_at', 'status',
    ];
    $derivedTables = [
        'external_weather_data', 'heatmap_areas', 'monthly_weather_predictions',
        'notifications', 'planting_advisories', 'system_logs', 'analytics_runs',
        'scheduled_task_runs', 'backup_verifications',
    ];
    if ($table === 'users' && $columns === []) return 'VERIFIED EXPECTED';
    if (! $targetNewer) return 'VERIFIED INCONSISTENT';
    if (in_array($table, $derivedTables, true)) return 'VERIFIED EXPECTED';
    if ($table === 'users' && array_diff($columns, ['updated_at', 'last_active_at', 'last_login_at']) === []) {
        return 'VERIFIED EXPECTED';
    }
    if (array_diff($columns, $operational) === [] && $columns !== []) return 'VERIFIED EXPECTED';
    return 'UNVERIFIED';
};

$results = [];
foreach ($compare['tables'] ?? [] as $tableInfo) {
    if (($tableInfo['changed_rows'] ?? 0) < 1) continue;
    $table = (string) $tableInfo['source_table'];
    $pk = $tableInfo['primary_key'] ?? ['id'];
    $changedKeys = $tableInfo['changed_primary_keys'] ?? [];
    $rows = [];
    foreach ($changedKeys as $encodedKey) {
        $key = json_decode((string) $encodedKey, true, 512, JSON_THROW_ON_ERROR);
        $where = [];
        foreach ($pk as $column) {
            $value = $key[$column] ?? null;
            if (is_string($value) && str_contains($value, ':')) $value = substr($value, strpos($value, ':') + 1);
            $where[$column] = $value;
        }
        $sourceRow = $sourceDb->table($table)->where($where)->first();
        $targetRow = $targetDb->table($table)->where($where)->first();
        if ($sourceRow === null || $targetRow === null) {
            $rows[] = ['primary_key' => $where, 'changed_columns' => [], 'classification' => 'UNVERIFIED', 'row_lookup' => 'missing_on_one_side'];
            continue;
        }
        $sourceArray = (array) $sourceRow;
        $targetArray = (array) $targetRow;
        $columns = [];
        foreach (array_unique(array_merge(array_keys($sourceArray), array_keys($targetArray))) as $column) {
            if ($canonical($sourceArray[$column] ?? null, $column) !== $canonical($targetArray[$column] ?? null, $column)) $columns[] = $column;
        }
        $nonTimestamp = array_values(array_diff($columns, ['created_at', 'updated_at']));
        $targetNewer = isset($sourceArray['updated_at'], $targetArray['updated_at'])
            && (string) $targetArray['updated_at'] > (string) $sourceArray['updated_at'];
        $rows[] = [
            'primary_key' => $where,
            'changed_columns' => $columns,
            'non_timestamp_changed_columns' => $nonTimestamp,
            'source_updated_at' => $sourceArray['updated_at'] ?? null,
            'target_updated_at' => $targetArray['updated_at'] ?? null,
            'target_updated_after_source' => $targetNewer,
            'classification' => $classify($table, $nonTimestamp, $targetNewer),
        ];
    }
    $results[] = ['table' => $table, 'rows' => $rows];
}

$report = ['generated_at' => gmdate('c'), 'source_schema' => $schema, 'tables' => $results];
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
echo "Wrote sanitized changed-row report to {$output}\n";
