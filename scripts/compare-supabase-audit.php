<?php

declare(strict_types=1);

/**
 * Compare a sanitized Supabase SQL Editor CSV export with local MySQL.
 *
 * Expected CSV columns: table_name,row_count,primary_key_columns
 * The Supabase export must contain metadata only; do not include row values.
 * This script opens a read-only MySQL transaction and never connects to
 * Supabase. It reports table-set, count, and primary-key differences.
 *
 * Usage:
 *   php scripts/compare-supabase-audit.php --source-report=/path/counts.csv
 *   php scripts/compare-supabase-audit.php --source-report=/path/counts.csv \
 *       --output=/path/comparison.json
 */

$options = getopt('', ['source-report:', 'output::']);
$sourcePath = $options['source-report'] ?? null;
if (! is_string($sourcePath) || ! is_file($sourcePath)) {
    fwrite(STDERR, "Usage: php scripts/compare-supabase-audit.php --source-report=/path/counts.csv [--output=/path/report.json]\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sourceRows = [];
$handle = fopen($sourcePath, 'rb');
$header = fgetcsv($handle);
if ($header === false) {
    throw new RuntimeException('The source CSV is empty.');
}
$header = array_map(static fn ($value): string => strtolower(trim((string) $value)), $header);
$required = ['table_name', 'row_count', 'primary_key_columns'];
if (array_diff($required, $header) !== []) {
    throw new RuntimeException('Source CSV must contain table_name,row_count,primary_key_columns.');
}
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) !== count($header)) {
        throw new RuntimeException('Malformed source CSV row.');
    }
    $item = array_combine($header, $row);
    $table = trim((string) $item['table_name']);
    if ($table === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new RuntimeException('Invalid table name in source CSV.');
    }
    $sourceRows[$table] = [
        'row_count' => (int) $item['row_count'],
        'primary_key_columns' => trim((string) $item['primary_key_columns']),
    ];
}
fclose($handle);

$connection = Illuminate\Support\Facades\DB::connection();
$connection->statement("SET SESSION TRANSACTION READ ONLY");
$connection->beginTransaction();
try {
    $localTables = $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
    $localRows = [];
    foreach ($localTables as $tableRow) {
        $table = (string) $tableRow->table_name;
        $pk = $connection->selectOne(
            "SELECT GROUP_CONCAT(column_name ORDER BY ordinal_position SEPARATOR ',') AS columns
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = 'PRIMARY'",
            [$table],
        );
        $count = $connection->selectOne("SELECT COUNT(*) AS row_count FROM `".str_replace('`', '``', $table)."`");
        $localRows[$table] = [
            'row_count' => (int) $count->row_count,
            'primary_key_columns' => (string) ($pk->columns ?? ''),
        ];
    }
    $connection->rollBack();
} catch (Throwable $exception) {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    throw $exception;
}

$sourceTables = array_keys($sourceRows);
$localTableNames = array_keys($localRows);
sort($sourceTables, SORT_STRING);
sort($localTableNames, SORT_STRING);
$result = [
    'generated_at' => gmdate('c'),
    'source_csv' => basename($sourcePath),
    'source_table_count' => count($sourceRows),
    'local_table_count' => count($localRows),
    'tables_only_in_source' => array_values(array_diff($sourceTables, $localTableNames)),
    'tables_only_in_local' => array_values(array_diff($localTableNames, $sourceTables)),
    'count_differences' => [],
    'primary_key_differences' => [],
];
foreach (array_intersect($sourceTables, $localTableNames) as $table) {
    if ($sourceRows[$table]['row_count'] !== $localRows[$table]['row_count']) {
        $result['count_differences'][$table] = ['source' => $sourceRows[$table]['row_count'], 'local' => $localRows[$table]['row_count']];
    }
    if ($sourceRows[$table]['primary_key_columns'] !== $localRows[$table]['primary_key_columns']) {
        $result['primary_key_differences'][$table] = ['source' => $sourceRows[$table]['primary_key_columns'], 'local' => $localRows[$table]['primary_key_columns']];
    }
}
$result['result'] = ($result['tables_only_in_source'] === [] && $result['tables_only_in_local'] === [] && $result['count_differences'] === [] && $result['primary_key_differences'] === []) ? 'MATCH' : 'DIFFERENT';

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
if (isset($options['output'])) {
    file_put_contents((string) $options['output'], $json, LOCK_EX);
    echo "Wrote read-only comparison report to ".$options['output'].PHP_EOL;
} else {
    echo $json;
}
exit($result['result'] === 'MATCH' ? 0 : 1);
