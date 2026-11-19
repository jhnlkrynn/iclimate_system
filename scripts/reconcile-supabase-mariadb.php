<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Support/ReconciliationMetadataStore.php';

/**
 * Read-only cross-database reconciliation worker.
 *
 * The shell wrapper restores the backup into a uniquely named disposable
 * PostgreSQL database, then invokes this file against that database and the
 * existing MariaDB target. No passwords are printed and row values are never
 * written to the report; record keys, hashes, and changed column names are.
 */

const APP_SCHEMA = 'iclimate_laravel';

/** Return null for unset or deliberately blank optional environment values. */
function optionalEnv(string $name): ?string
{
    $value = getenv($name);

    return $value === false || $value === '' ? null : $value;
}

$backup = optionalEnv('RECON_BACKUP') ?? '';
$reportPath = optionalEnv('RECON_REPORT') ?? 'docs/SUPABASE_TO_MARIADB_DATA_RECONCILIATION.md';
$jsonPath = optionalEnv('RECON_JSON') ?? '';
$pgHost = optionalEnv('RECON_PG_HOST') ?? '127.0.0.1';
$pgPort = optionalEnv('RECON_PG_PORT') ?? '5432';
$pgDatabase = optionalEnv('RECON_PG_DATABASE') ?? 'postgres';
$pgUser = optionalEnv('RECON_PG_USER') ?? (optionalEnv('PGUSER') ?? get_current_user());
$pgPassword = optionalEnv('RECON_PG_PASSWORD');
$mysqlHost = optionalEnv('RECON_MYSQL_HOST') ?? '127.0.0.1';
$mysqlPort = optionalEnv('RECON_MYSQL_PORT') ?? '3306';
$mysqlDatabase = optionalEnv('RECON_MYSQL_DATABASE') ?? 'iclimate_staging_20261103_fix3';
$mysqlUser = optionalEnv('RECON_MYSQL_USER') ?? 'root';
$mysqlPassword = optionalEnv('RECON_MYSQL_PASSWORD');

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function identPg(string $name): string { return '"'.str_replace('"', '""', $name).'"'; }
function identMy(string $name): string { return '`'.str_replace('`', '``', $name).'`'; }

function connectPdo(string $dsn, ?string $user, ?string $password): PDO
{
    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
        ];
        if (str_starts_with($dsn, 'mysql:')) {
            // PHP 8.5 moved MySQL driver attributes to Pdo\Mysql. Resolve the
            // legacy constant dynamically so PHP 8.5 never evaluates its
            // deprecated symbol, while older supported PHP versions retain
            // unbuffered-query behavior.
            if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
                $options[constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')] = false;
            } elseif (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $options[constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')] = false;
            }
        }

        return new PDO($dsn, $user ?: null, $password, $options);
    } catch (Throwable $e) {
        fail('database connection failed: '.preg_replace('/password=[^;]*/i', 'password=[REDACTED]', $e->getMessage()));
    }
}

function pgMeta(PDO $db, string $table): array
{
    $stmt = $db->prepare(<<<'SQL'
        select column_name, data_type, udt_name, coalesce(datetime_precision, numeric_scale, 0) as numeric_precision_value,
               ordinal_position
        from information_schema.columns
        where table_schema = :schema and table_name = :table
        order by ordinal_position
    SQL);
    $stmt->execute(['schema' => APP_SCHEMA, 'table' => $table]);
    $columns = [];
    foreach ($stmt as $row) {
        $columns[$row['column_name']] = [
            'type' => strtolower($row['data_type'].' '.$row['udt_name']),
            'precision' => (int) $row['numeric_precision_value'],
        ];
    }
    $stmt = $db->prepare(<<<'SQL'
        select kcu.column_name
        from information_schema.table_constraints tc
        join information_schema.key_column_usage kcu
          on kcu.constraint_name = tc.constraint_name
         and kcu.table_schema = tc.table_schema
         and kcu.table_name = tc.table_name
        where tc.table_schema = :schema and tc.table_name = :table and tc.constraint_type = 'PRIMARY KEY'
        order by kcu.ordinal_position
    SQL);
    $stmt->execute(['schema' => APP_SCHEMA, 'table' => $table]);
    $pk = array_map(static fn (array $row): string => $row['column_name'], $stmt->fetchAll());
    return ['columns' => $columns, 'pk' => $pk];
}

function myMeta(PDO $db, string $table): array
{
    $stmt = $db->prepare(<<<'SQL'
        select column_name, data_type, column_type, coalesce(datetime_precision, numeric_scale, 0) as numeric_precision_value,
               ordinal_position
        from information_schema.columns
        where table_schema = database() and table_name = :table
        order by ordinal_position
    SQL);
    $stmt->execute(['table' => $table]);
    $columns = [];
    foreach ($stmt as $row) {
        $columns[$row['column_name']] = [
            'type' => strtolower($row['data_type'].' '.$row['column_type']),
            'precision' => (int) $row['numeric_precision_value'],
        ];
    }
    $stmt = $db->prepare(<<<'SQL'
        select column_name
        from information_schema.key_column_usage
        where table_schema = database() and table_name = :table and constraint_name = 'PRIMARY'
        order by ordinal_position
    SQL);
    $stmt->execute(['table' => $table]);
    return ['columns' => $columns, 'pk' => array_map(static fn (array $row): string => $row['column_name'], $stmt->fetchAll())];
}

function listTables(PDO $db, bool $postgres): array
{
    $sql = $postgres
        ? "select table_name from information_schema.tables where table_schema = '".APP_SCHEMA."' and table_type = 'BASE TABLE' order by table_name"
        : "select table_name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE' order by table_name";
    return array_map(static fn (array $row): string => $row['table_name'], $db->query($sql)->fetchAll());
}

function canonical(mixed $value, array $sourceType, array $targetType): string
{
    if ($value === null) return 'null';
    if (is_bool($value)) return 'bool:'.($value ? '1' : '0');
    $type = strtolower(($sourceType['type'] ?? '').' '.($targetType['type'] ?? ''));
    $text = (string) $value;
    if (str_contains($type, 'json') || preg_match('/^\s*[\[{]/', $text)) {
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sort = static function (&$item) use (&$sort): void {
                if (!is_array($item)) return;
                if (array_keys($item) !== range(0, count($item) - 1)) ksort($item);
                foreach ($item as &$child) $sort($child);
            };
            $sort($decoded);
            return 'json:'.json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
    }
    if (str_contains($type, 'bool') || $text === 't' || $text === 'f') {
        return 'bool:'.((filter_var($value, FILTER_VALIDATE_BOOLEAN)) ? '1' : '0');
    }
    if (str_contains($type, 'date') || str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
        try {
            $date = new DateTimeImmutable($text, new DateTimeZone('UTC'));
            if (str_contains($type, 'date') && !str_contains($type, 'time')) return 'date:'.$date->format('Y-m-d');
            $precision = min((int)($sourceType['precision'] ?? 6), (int)($targetType['precision'] ?? 6));
            $formatted = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            return 'time:'.($precision > 0 ? substr($formatted, 0, 20 + $precision).'Z' : substr($formatted, 0, 19).'Z');
        } catch (Throwable) { /* preserve unparseable values below */ }
    }
    if (is_numeric($value) && preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $text)) {
        $text = ltrim($text, '+');
        if (str_contains(strtolower($text), 'e')) $text = sprintf('%.17g', (float) $text);
        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '-');
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $text = $whole.($fraction === '' ? '' : '.'.$fraction);
        return 'number:'.($text === '0' ? '0' : ($negative ? '-'.$text : $text));
    }
    return 'text:'.$text;
}

function rowHash(array $row, array $columns, array $types): string
{
    $values = [];
    foreach ($columns as $column) $values[] = [$column, canonical($row[$column] ?? null, $types[$column]['source'] ?? [], $types[$column]['target'] ?? [])];
    return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function rowKey(array $row, array $pk, array $types): string
{
    $values = [];
    foreach ($pk as $column) $values[$column] = canonical($row[$column] ?? null, $types[$column]['source'] ?? [], $types[$column]['target'] ?? []);
    return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function fetchTargetRow(PDO $db, string $table, array $columns, array $primaryKey, array $primaryKeyValues): ?array
{
    $where = implode(' and ', array_map(static fn (string $column): string => identMy($column).' = ?', $primaryKey));
    $select = implode(', ', array_map('identMy', $columns));
    $stmt = $db->prepare('select '.$select.' from '.identMy($table).' where '.$where.' limit 1');
    $stmt->execute(array_values(array_map(static fn (string $column): mixed => $primaryKeyValues[$column] ?? null, $primaryKey)));
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

$pg = connectPdo("pgsql:host={$pgHost};port={$pgPort};dbname={$pgDatabase}", $pgUser, $pgPassword);
$my = connectPdo("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDatabase};charset=utf8mb4", $mysqlUser, $mysqlPassword);
$sourceTables = listTables($pg, true);
$targetTables = listTables($my, false);
$targetSet = array_fill_keys($targetTables, true);
$sourceTotal = 0;
$targetTotal = 0;
$targetCounts = [];
foreach ($targetTables as $table) $targetCounts[$table] = (int) $my->query('select count(*) from '.identMy($table))->fetchColumn();
foreach ($sourceTables as $table) $sourceTotal += (int) $pg->query('select count(*) from '.identPg(APP_SCHEMA).'.'.identPg($table))->fetchColumn();
$targetTotal = array_sum($targetCounts);
$metadataStore = new \App\Support\ReconciliationMetadataStore();

$results = [];
$summary = ['source_tables' => count($sourceTables), 'target_tables' => count($targetTables), 'source_rows' => $sourceTotal,
    'target_rows' => $targetTotal, 'tables_compared' => 0, 'matching_tables' => 0, 'tables_with_differences' => 0,
    'missing_tables' => 0, 'extra_tables' => count(array_diff($targetTables, $sourceTables)), 'missing_records' => 0,
    'extra_records' => 0, 'changed_records' => 0, 'unverified_tables' => 0];

foreach ($sourceTables as $table) {
    if (!isset($targetSet[$table])) {
        $results[$table] = ['status' => 'missing_target_table', 'source_rows' => (int)$pg->query('select count(*) from '.identPg(APP_SCHEMA).'.'.identPg($table))->fetchColumn()];
        $summary['missing_tables']++;
        continue;
    }
    $sourceMeta = pgMeta($pg, $table);
    $targetMeta = myMeta($my, $table);
    $columns = array_values(array_intersect(array_keys($sourceMeta['columns']), array_keys($targetMeta['columns'])));
    $pk = $sourceMeta['pk'];
    $types = [];
    foreach ($columns as $column) $types[$column] = ['source' => $sourceMeta['columns'][$column], 'target' => $targetMeta['columns'][$column]];
    $targetPk = $targetMeta['pk'];
    if (!$columns || !$pk || $pk !== $targetPk) {
        $results[$table] = ['status' => 'unverified', 'source_rows' => (int)$pg->query('select count(*) from '.identPg(APP_SCHEMA).'.'.identPg($table))->fetchColumn(),
            'target_rows' => $targetCounts[$table], 'source_primary_key' => $pk, 'target_primary_key' => $targetPk, 'shared_columns' => count($columns)];
        $summary['unverified_tables']++;
        continue;
    }
    $select = implode(', ', array_map('identPg', $columns));
    $targetSelect = implode(', ', array_map('identMy', $columns));
    $metadataStore->clearTable($table);
    $targetStatement = $my->query('select '.$targetSelect.' from '.identMy($table));
    while (($row = $targetStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $key = rowKey($row, $pk, $types);
        $primaryKeyValues = [];
        foreach ($pk as $column) $primaryKeyValues[$column] = $row[$column] ?? null;
        $metadataStore->put($table, $key, rowHash($row, $columns, $types), $primaryKeyValues);
    }
    $missing = $extra = $changed = 0; $missingKeys = $extraKeys = $changedKeys = []; $changedColumns = [];
    $sourceStatement = $pg->query('select '.$select.' from '.identPg(APP_SCHEMA).'.'.identPg($table));
    while (($row = $sourceStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $key = rowKey($row, $pk, $types); $hash = rowHash($row, $columns, $types);
        $targetMetadata = $metadataStore->find($table, $key);
        if ($targetMetadata === null) { $missing++; $missingKeys[] = $key; continue; }
        if ($targetMetadata['hash'] !== $hash) {
            $changed++;
            $changedKeys[] = $key;
            $changedColumns[$key] = [];
            $targetRow = fetchTargetRow($my, $table, $columns, $pk, $targetMetadata['primary_key_values']);
            if ($targetRow === null) fail('Target row disappeared during read-only comparison for '.$table.' '.$key);
            foreach ($columns as $column) {
                $sourceValue = canonical($row[$column] ?? null, $types[$column]['source'] ?? [], $types[$column]['target'] ?? []);
                $targetValue = canonical($targetRow[$column] ?? null, $types[$column]['source'] ?? [], $types[$column]['target'] ?? []);
                if ($sourceValue !== $targetValue) $changedColumns[$key][] = $column;
            }
        }
        $metadataStore->remove($table, $key);
    }
    unset($sourceStatement);
    foreach ($metadataStore->extraKeys($table) as $extraKey) { $extra++; $extraKeys[] = $extraKey; }
    $sourceRows = (int)$pg->query('select count(*) from '.identPg(APP_SCHEMA).'.'.identPg($table))->fetchColumn();
    $different = $missing + $extra + $changed > 0;
    $results[$table] = ['status' => $different ? 'different' : 'equal', 'source_rows' => $sourceRows, 'target_rows' => $targetCounts[$table],
        'missing_records' => $missing, 'extra_records' => $extra, 'changed_records' => $changed, 'primary_key' => $pk,
        'shared_columns' => count($columns), 'missing_primary_keys' => $missingKeys, 'extra_primary_keys' => $extraKeys, 'changed_primary_keys' => $changedKeys,
        'changed_columns_by_primary_key' => $changedColumns];
    $summary['tables_compared']++; $summary['missing_records'] += $missing; $summary['extra_records'] += $extra; $summary['changed_records'] += $changed;
    if ($different) $summary['tables_with_differences']++; else $summary['matching_tables']++;
}

$overall = $summary['unverified_tables'] > 0 ? 'INCOMPLETE' : (($summary['tables_with_differences'] > 0 || $summary['missing_tables'] > 0 || $summary['extra_tables'] > 0) ? 'FAIL' : 'PASS');
$payload = ['generated_at' => gmdate('c'), 'overall' => $overall, 'source_database' => $pgDatabase, 'target_database' => $mysqlDatabase,
    'summary' => $summary, 'tables' => $results, 'notes' => ['Row values are not written to this report. Hashes compare canonicalized values; primary keys and changed-key lists are retained.',
        'Canonicalization covers JSON object ordering, booleans, timestamps, and numeric scale. Arrays, strings, nulls, identifiers, and non-equivalent timestamps remain significant.']];
if ($jsonPath !== '') file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

$lines = ["# Supabase-to-MariaDB data reconciliation", '', "**Overall snapshot reconciliation: {$overall}**", '',
    'Generated: '.gmdate('Y-m-d\TH:i:s\Z'), '', '| Measure | Result |', '|---|---:|', "| Source PostgreSQL tables | {$summary['source_tables']} |", "| Target MariaDB tables | {$summary['target_tables']} |", "| Source rows | {$summary['source_rows']} |", "| Target rows | {$summary['target_rows']} |", "| Tables compared | {$summary['tables_compared']} |", "| Matching tables | {$summary['matching_tables']} |", "| Tables with differences | {$summary['tables_with_differences']} |", "| Missing target records | {$summary['missing_records']} |", "| Extra target records | {$summary['extra_records']} |", "| Changed records | {$summary['changed_records']} |", "| Unverified tables | {$summary['unverified_tables']} |", '', '## Table results', '', '| Table | Status | Source rows | Target rows | Missing | Extra | Changed |', '|---|---|---:|---:|---:|---:|---:|'];
foreach ($results as $table => $result) $lines[] = sprintf('| `%s` | %s | %s | %s | %s | %s | %s |', $table, $result['status'], $result['source_rows'] ?? '-', $result['target_rows'] ?? '-', $result['missing_records'] ?? '-', $result['extra_records'] ?? '-', $result['changed_records'] ?? '-');
$lines[] = ''; $lines[] = '## Record-level differences'; $lines[] = '';
foreach ($results as $table => $result) {
    if (($result['status'] ?? '') === 'equal') continue;
    $lines[] = "### `{$table}`";
    $lines[] = ''; $lines[] = '- Status: `'.$result['status'].'`';
    foreach (['missing_primary_keys' => 'Missing primary keys', 'extra_primary_keys' => 'Extra primary keys', 'changed_primary_keys' => 'Changed primary keys'] as $key => $label) {
        if (!empty($result[$key])) $lines[] = '- '.$label.': `'.implode('`, `', $result[$key]).'`';
    }
    if (!empty($result['changed_columns_by_primary_key'])) {
        foreach ($result['changed_columns_by_primary_key'] as $key => $columnsChanged) {
            $lines[] = '- Changed columns for `'.$key.'`: `'.implode('`, `', $columnsChanged).'`';
        }
    }
    if (($result['status'] ?? '') === 'unverified') $lines[] = '- Primary-key or shared-column metadata did not permit a safe comparison.';
    $lines[] = '';
}
$lines[] = '## Normalization and safety'; $lines[] = ''; $lines[] = '- Read-only SQL was used against MariaDB and the disposable PostgreSQL restore.'; $lines[] = '- JSON object key order, boolean spellings, timestamp timezone/precision, and numeric scale are normalized.'; $lines[] = '- Raw row values are intentionally excluded from the report to avoid exposing credentials, password hashes, tokens, or personal data.'; $lines[] = '- This report compares the dated backup snapshot, not current live Supabase state.';
file_put_contents($reportPath, implode("\n", $lines)."\n");
printf("OVERALL=%s SOURCE_TABLES=%d TARGET_TABLES=%d SOURCE_ROWS=%d TARGET_ROWS=%d MISSING=%d EXTRA=%d CHANGED=%d UNVERIFIED=%d\n", $overall, $summary['source_tables'], $summary['target_tables'], $summary['source_rows'], $summary['target_rows'], $summary['missing_records'], $summary['extra_records'], $summary['changed_records'], $summary['unverified_tables']);
