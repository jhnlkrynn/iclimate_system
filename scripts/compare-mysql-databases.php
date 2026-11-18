<?php

declare(strict_types=1);

/**
 * Read-only, row-level comparison of two MySQL/MariaDB databases (e.g. the local XAMPP source and a production
 * import on Hostinger, or a MariaDB source and a MySQL 8 target). Every column of every row is hashed in primary-key
 * order after canonicalizing JSON, with both sessions at the same time zone, so engine differences in CHECKSUM TABLE
 * do not matter. Also compares foreign keys, indexes, CHECK constraints and triggers. Prints table names and counts
 * only; no row values.
 *
 *   SOURCE_DB_PASSWORD=... TARGET_DB_PASSWORD=... php scripts/compare-mysql-databases.php \
 *     --source=user@127.0.0.1:3306/iclimate_staging_20261103_fix3 --target=user@host:3306/production_db \
 *     [--ignore=cache,sessions,jobs,failed_jobs,migrations,backup_verifications]
 *
 * Exit 0 when everything outside --ignore matches, 1 otherwise.
 */

$options = getopt('', ['source:', 'target:', 'ignore::']);
$parse = static function (string $spec, string $passwordEnv): array {
    if (! preg_match('/^([^@]+)@([^:\/]+)(?::(\d+))?\/([A-Za-z0-9_]+)$/', $spec, $m)) {
        fwrite(STDERR, "FAIL: use user@host[:port]/database\n");
        exit(2);
    }

    return ['user' => $m[1], 'host' => $m[2], 'port' => $m[3] ?: '3306', 'database' => $m[4], 'password' => getenv($passwordEnv) ?: null];
};
$source = $parse($options['source'] ?? '', 'SOURCE_DB_PASSWORD');
$target = $parse($options['target'] ?? '', 'TARGET_DB_PASSWORD');
$ignore = array_filter(explode(',', (string) ($options['ignore'] ?? 'cache,cache_locks,sessions,jobs,job_batches,failed_jobs')));

$connect = static function (array $c): PDO {
    try {
        $db = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4", $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM, PDO::ATTR_STRINGIFY_FETCHES => true,
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL: could not connect to {$c['database']} (".get_class($e).")\n");
        exit(2);
    }
    $db->exec("SET time_zone = '+00:00'");
    $db->exec('SET SESSION group_concat_max_len = 16777216');
    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');

    return $db;
};
$a = $connect($source);
$b = $connect($target);

$canon = static function (?string $value): string {
    if ($value === null) {
        return "\0";
    }
    $json = json_decode($value, true);
    if (! is_array($json)) {
        return $value;
    }
    $sort = static function (&$node) use (&$sort): void {
        if (is_array($node)) {
            if ($node !== [] && array_keys($node) !== range(0, count($node) - 1)) {
                ksort($node);
            }
            foreach ($node as &$child) {
                $sort($child);
            }
        }
    };
    $sort($json);

    return (string) json_encode($json);
};

$tablesOf = static fn (PDO $db): array => $db->query("select table_name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE' order by 1")->fetchAll(PDO::FETCH_COLUMN);
$tablesA = $tablesOf($a);
$tablesB = $tablesOf($b);
// Engines sort information_schema differently (MySQL 8 vs MariaDB collations): compare as sorted sets.
sort($tablesA, SORT_STRING);
sort($tablesB, SORT_STRING);
$problems = [];
if ($tablesA !== $tablesB) {
    $problems[] = 'table sets differ: only in source ['.implode(',', array_diff($tablesA, $tablesB)).'], only in target ['.implode(',', array_diff($tablesB, $tablesA)).']';
}

$rows = 0;
foreach (array_intersect($tablesA, $tablesB) as $table) {
    if (in_array($table, $ignore, true)) {
        continue;
    }
    $columns = $a->query("select column_name from information_schema.columns where table_schema = database() and table_name = '{$table}' order by ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    $columnsB = $b->query("select column_name from information_schema.columns where table_schema = database() and table_name = '{$table}' order by ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    if ($columns !== $columnsB) {
        $problems[] = "{$table}: column lists differ";
        continue;
    }
    $key = $a->query("select group_concat(column_name order by seq_in_index) from information_schema.statistics where table_schema = database() and table_name = '{$table}' and index_name = 'PRIMARY'")->fetchColumn() ?: $columns[0];
    $sql = 'select `'.implode('`,`', $columns)."` from `{$table}` order by ".implode(',', array_map(static fn ($c) => "`{$c}`", explode(',', (string) $key)));
    $result = [];
    foreach (['a' => $a, 'b' => $b] as $side => $db) {
        $hash = hash_init('sha256');
        $count = 0;
        foreach ($db->query($sql) as $row) {
            hash_update($hash, implode("\x1f", array_map($canon, $row))."\x1e");
            $count++;
        }
        $result[$side] = [$count, hash_final($hash)];
    }
    $rows += $result['a'][0];
    if ($result['a'] !== $result['b']) {
        $problems[] = "{$table}: rows differ ({$result['a'][0]} source vs {$result['b'][0]} target)";
    }
}

$meta = static fn (PDO $db): array => [
    'foreign keys' => (int) $db->query('select count(*) from information_schema.referential_constraints where constraint_schema = database()')->fetchColumn(),
    'indexes' => (int) $db->query('select count(*) from (select distinct table_name, index_name from information_schema.statistics where table_schema = database()) t')->fetchColumn(),
    'check constraints' => (int) $db->query("select count(*) from information_schema.table_constraints where table_schema = database() and constraint_type = 'CHECK'")->fetchColumn(),
    'triggers' => (int) $db->query('select count(*) from information_schema.triggers where trigger_schema = database()')->fetchColumn(),
];
$metaA = $meta($a);
$metaB = $meta($b);
foreach ($metaA as $name => $value) {
    if ($value !== $metaB[$name]) {
        $problems[] = "{$name}: {$value} source vs {$metaB[$name]} target";
    }
}

printf("Tables: %d source / %d target | rows compared: %d | ignored: %s\n", count($tablesA), count($tablesB), $rows, implode(',', $ignore) ?: 'none');
printf("Schema: %s\n", json_encode($metaA));
if ($problems === []) {
    echo "RESULT: MATCH\n";
    exit(0);
}
echo "RESULT: DIFFERENT\n  - ".implode("\n  - ", $problems)."\n";
exit(1);
