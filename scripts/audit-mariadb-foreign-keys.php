<?php

declare(strict_types=1);

/** Read-only MariaDB foreign-key and orphan audit. */

const EXPECTED_FOREIGN_KEYS = 87;

function envOr(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function quoteIdentifier(string $value): string
{
    return '`'.str_replace('`', '``', $value).'`';
}

function failAudit(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$host = envOr('ICLIMATE_LOCAL_DB_HOST', '127.0.0.1');
$port = envOr('ICLIMATE_LOCAL_DB_PORT', '3306');
$database = envOr('ICLIMATE_LOCAL_DB_DATABASE', 'iclimate_staging_20261103_fix3');
$user = envOr('ICLIMATE_LOCAL_DB_USERNAME', 'root');
$password = getenv('ICLIMATE_LOCAL_DB_PASSWORD');
$password = $password === false ? null : $password;
$jsonPath = envOr('ICLIMATE_FK_AUDIT_JSON', 'storage/app/reconciliation/mariadb-foreign-key-audit.json');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
} catch (Throwable $exception) {
    failAudit('MariaDB connection failed: '.preg_replace('/password=[^;]*/i', 'password=[REDACTED]', $exception->getMessage()));
}

$constraints = $pdo->query(<<<'SQL'
select kcu.constraint_name,
       kcu.table_schema as child_schema,
       kcu.table_name as child_table,
       kcu.column_name as child_column,
       kcu.referenced_table_schema as parent_schema,
       kcu.referenced_table_name as parent_table,
       kcu.referenced_column_name as parent_column,
       kcu.ordinal_position,
       rc.update_rule,
       rc.delete_rule
from information_schema.key_column_usage kcu
join information_schema.referential_constraints rc
  on rc.constraint_schema = kcu.constraint_schema
 and rc.constraint_name = kcu.constraint_name
 and rc.table_name = kcu.table_name
where kcu.constraint_schema = database()
  and kcu.referenced_table_name is not null
order by kcu.constraint_name, kcu.ordinal_position
SQL)->fetchAll();

$grouped = [];
foreach ($constraints as $column) {
    $key = $column['child_schema'].'|'.$column['constraint_name'].'|'.$column['child_table'];
    $grouped[$key]['constraint_name'] = $column['constraint_name'];
    $grouped[$key]['child_schema'] = $column['child_schema'];
    $grouped[$key]['child_table'] = $column['child_table'];
    $grouped[$key]['parent_schema'] = $column['parent_schema'];
    $grouped[$key]['parent_table'] = $column['parent_table'];
    $grouped[$key]['update_rule'] = $column['update_rule'];
    $grouped[$key]['delete_rule'] = $column['delete_rule'];
    $grouped[$key]['columns'][] = [
        'child' => $column['child_column'],
        'parent' => $column['parent_column'],
        'ordinal_position' => (int) $column['ordinal_position'],
    ];
}

$audits = [];
$totalOrphans = 0;
foreach ($grouped as $constraint) {
    $child = quoteIdentifier($constraint['child_schema']).'.'.quoteIdentifier($constraint['child_table']);
    $parent = quoteIdentifier($constraint['parent_schema']).'.'.quoteIdentifier($constraint['parent_table']);
    $nonNull = [];
    $joins = [];
    foreach ($constraint['columns'] as $column) {
        $childColumn = quoteIdentifier($column['child']);
        $parentColumn = quoteIdentifier($column['parent']);
        $nonNull[] = "c.{$childColumn} is not null";
        $joins[] = "p.{$parentColumn} = c.{$childColumn}";
    }
    $sql = "select count(*) from {$child} c where ".implode(' and ', $nonNull).
        " and not exists (select 1 from {$parent} p where ".implode(' and ', $joins).')';
    $orphans = (int) $pdo->query($sql)->fetchColumn();
    $totalOrphans += $orphans;
    $audits[] = [
        'constraint' => $constraint['constraint_name'],
        'child_table' => $constraint['child_schema'].'.'.$constraint['child_table'],
        'parent_table' => $constraint['parent_schema'].'.'.$constraint['parent_table'],
        'columns' => $constraint['columns'],
        'update_rule' => $constraint['update_rule'],
        'delete_rule' => $constraint['delete_rule'],
        'orphan_records' => $orphans,
    ];
}

$importantTables = ['users', 'farms', 'farmer_profiles', 'farmer_planting_schedules', 'yield_predictions'];
$important = array_values(array_filter($audits, static function (array $audit) use ($importantTables): bool {
    foreach ($importantTables as $table) {
        if (str_ends_with($audit['child_table'], '.'.$table) || str_ends_with($audit['parent_table'], '.'.$table)) return true;
    }
    return false;
}));

$result = [
    'generated_at' => gmdate('c'),
    'database' => $database,
    'expected_foreign_keys' => EXPECTED_FOREIGN_KEYS,
    'actual_foreign_keys' => count($grouped),
    'relationships_audited' => count($audits),
    'orphan_records' => $totalOrphans,
    'count_matches_expected' => count($grouped) === EXPECTED_FOREIGN_KEYS,
    'all_orphan_checks_passed' => $totalOrphans === 0,
    'important_relationships' => $important,
    'constraints' => $audits,
];

$directory = dirname($jsonPath);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) failAudit("Cannot create audit directory: {$directory}");
file_put_contents($jsonPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

printf("DATABASE=%s\nEXPECTED_FOREIGN_KEYS=%d\nACTUAL_FOREIGN_KEYS=%d\nRELATIONSHIPS_AUDITED=%d\nORPHAN_RECORDS=%d\nCOUNT_MATCHES_EXPECTED=%s\nALL_ORPHAN_CHECKS_PASSED=%s\nJSON_REPORT=%s\n",
    $database,
    EXPECTED_FOREIGN_KEYS,
    count($grouped),
    count($audits),
    $totalOrphans,
    count($grouped) === EXPECTED_FOREIGN_KEYS ? 'YES' : 'NO',
    $totalOrphans === 0 ? 'YES' : 'NO',
    $jsonPath,
);
