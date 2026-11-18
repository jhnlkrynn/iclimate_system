<?php

declare(strict_types=1);

/**
 * Preserve the few records that exist only in Supabase (written there after the MariaDB copy was taken).
 *
 * Read-only on Supabase (default_transaction_read_only). Dry run unless --apply is given. Idempotent: a record
 * already imported (same provenance marker or same natural key) is skipped, so running it twice adds nothing.
 * Never reuses an ID: MariaDB already uses system_logs 541/542 and backup_verifications 31 for different
 * records, so imported rows get new AUTO_INCREMENT IDs and the old -> new mapping is written to the report.
 * Existing MariaDB rows are never updated, except users.last_login_at / last_active_at, which only move forward
 * (the Supabase value is applied only when it is later than MariaDB's).
 *
 * Usage (passwords only through the environment; nothing secret is printed):
 *   SOURCE_DB_PASSWORD=... TARGET_DB_PASSWORD=... php scripts/reconcile-supabase-only-records.php \
 *     --target-database=iclimate_restore_20261110_040000 [--target-port=3399 --target-user=root] \
 *     [--apply] [--report=path.json]
 * Writing to the active application database also needs --approved-active-target.
 */

$options = getopt('', [
    'source-host::', 'source-port::', 'source-user::', 'source-schema::',
    'target-host::', 'target-port::', 'target-user::', 'target-database:',
    'active-database::', 'archive::', 'report::', 'apply', 'approved-active-target',
]);

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$source = [
    'host' => $options['source-host'] ?? 'aws-0-ap-northeast-1.pooler.supabase.com',
    'port' => $options['source-port'] ?? '5432',
    'user' => $options['source-user'] ?? 'postgres.ilwbgawaalodtrwuargp',
    'schema' => $options['source-schema'] ?? 'iclimate_laravel',
    'password' => getenv('SOURCE_DB_PASSWORD') ?: fail('SOURCE_DB_PASSWORD is required in the environment.'),
];
$target = [
    'host' => $options['target-host'] ?? '127.0.0.1',
    'port' => $options['target-port'] ?? '3306',
    'user' => $options['target-user'] ?? 'root',
    'database' => $options['target-database'] ?? fail('--target-database is required.'),
    'password' => (string) getenv('TARGET_DB_PASSWORD'),
];
$apply = array_key_exists('apply', $options);
$activeDatabase = $options['active-database'] ?? 'iclimate_staging_20261103_fix3';
$archive = $options['archive'] ?? null;
$reportPath = $options['report'] ?? null;

if (! preg_match('/^[A-Za-z0-9_]+$/', $target['database']) || ! preg_match('/^[A-Za-z0-9_]+$/', $source['schema'])) {
    fail('Database and schema names may contain only letters, digits and underscores.');
}
if ($apply && $target['database'] === $activeDatabase && ! array_key_exists('approved-active-target', $options)) {
    fail("Refusing to write to the active database {$activeDatabase} without --approved-active-target.");
}

$pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try {
    $pg = new PDO("pgsql:host={$source['host']};port={$source['port']};dbname=postgres;sslmode=require", $source['user'], $source['password'], $pdoOptions);
    $pg->exec('SET default_transaction_read_only = on');
    $pg->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $my = new PDO("mysql:host={$target['host']};port={$target['port']};dbname={$target['database']};charset=utf8mb4", $target['user'], $target['password'] !== '' ? $target['password'] : null, $pdoOptions);
} catch (Throwable $e) {
    fail('Database connection failed ('.get_class($e).'). Check host, port, account and that the server is running.');
}

$s = $source['schema'];
/** Supabase timestamps (with or without time zone) as the UTC wall-clock string MariaDB stores for Laravel. */
$utc = static function (?string $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
};

$plan = ['generated_at' => gmdate('c'), 'mode' => $apply ? 'apply' : 'dry-run', 'target_database' => $target['database'],
    'archive' => $archive ? basename($archive) : null, 'inserted' => [], 'skipped' => [], 'merged' => [], 'archive_only' => []];

// 1. Audit log rows that exist only in Supabase: Supabase rows whose (user, action, time) MariaDB does not have.
$mariaLogs = [];
foreach ($my->query('select id, user_id, action, created_at, details from system_logs') as $row) {
    $mariaLogs[($row['user_id'] ?? '').'|'.$row['action'].'|'.$row['created_at']] = (int) $row['id'];
    $marker = json_decode((string) $row['details'], true)['_provenance']['source_id'] ?? null;
    if ($marker !== null && (json_decode((string) $row['details'], true)['_provenance']['table'] ?? null) === 'system_logs') {
        $mariaLogs['provenance|'.$marker] = (int) $row['id'];
    }
}
$supabaseLogs = $pg->query("select id, user_id, action, details, created_at::text created_at, updated_at::text updated_at from {$s}.system_logs order by id")->fetchAll();
$userIds = array_flip(array_map('intval', $my->query('select id from users')->fetchAll(PDO::FETCH_COLUMN)));

$logInserts = [];
foreach ($supabaseLogs as $row) {
    $createdAt = $utc($row['created_at']);
    $key = ($row['user_id'] ?? '').'|'.$row['action'].'|'.$createdAt;
    if (isset($mariaLogs[$key]) || isset($mariaLogs['provenance|'.$row['id']])) {
        continue; // already present (copied in the original migration, or imported earlier)
    }
    if ($row['user_id'] !== null && ! isset($userIds[(int) $row['user_id']])) {
        $plan['skipped'][] = ['table' => 'system_logs', 'source_id' => (int) $row['id'], 'reason' => 'user does not exist in MariaDB (foreign key)'];
        continue;
    }
    $details = json_decode((string) $row['details'], true);
    $details = is_array($details) ? $details : ['original_details' => $row['details']];
    $details['_provenance'] = ['source' => 'supabase', 'table' => 'system_logs', 'source_id' => (int) $row['id'], 'archive' => $plan['archive']];
    $logInserts[] = ['source_id' => (int) $row['id'], 'values' => [
        'user_id' => $row['user_id'] === null ? null : (int) $row['user_id'], 'action' => $row['action'],
        'details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'created_at' => $createdAt, 'updated_at' => $utc($row['updated_at']),
    ]];
}

// 2. Backup verification records that exist only in Supabase (natural key: file, status, time).
$mariaChecks = [];
foreach ($my->query('select id, backup_filename, status, created_at from backup_verifications') as $row) {
    $mariaChecks[$row['backup_filename'].'|'.$row['status'].'|'.$row['created_at']] = (int) $row['id'];
}
$checkColumns = ['backup_filename', 'backup_size_bytes', 'backup_sha256', 'verified_at', 'status', 'archive_entry_count', 'failure_reason', 'created_at', 'updated_at'];
$checkInserts = [];
foreach ($pg->query("select id, ".implode(', ', array_map(fn ($c) => in_array($c, ['verified_at', 'created_at', 'updated_at'], true) ? "{$c}::text {$c}" : $c, $checkColumns))." from {$s}.backup_verifications order by id") as $row) {
    $values = [];
    foreach ($checkColumns as $column) {
        $values[$column] = in_array($column, ['verified_at', 'created_at', 'updated_at'], true) ? $utc($row[$column]) : $row[$column];
    }
    if (isset($mariaChecks[$values['backup_filename'].'|'.$values['status'].'|'.$values['created_at']])) {
        continue;
    }
    $checkInserts[] = ['source_id' => (int) $row['id'], 'values' => $values];
}

// 3. Login times newer in Supabase move forward; nothing else on the user changes.
$loginMerges = [];
$supabaseUsers = $pg->query("select id, last_login_at::text last_login_at, last_active_at::text last_active_at from {$s}.users")->fetchAll();
$mariaUsers = [];
foreach ($my->query('select id, last_login_at, last_active_at from users') as $row) {
    $mariaUsers[(int) $row['id']] = $row;
}
foreach ($supabaseUsers as $row) {
    $id = (int) $row['id'];
    if (! isset($mariaUsers[$id])) {
        continue;
    }
    foreach (['last_login_at', 'last_active_at'] as $column) {
        $from = $mariaUsers[$id][$column];
        $to = $utc($row[$column]);
        if ($to !== null && ($from === null || strtotime($to.' UTC') > strtotime($from.' UTC'))) {
            $loginMerges[] = ['user_id' => $id, 'column' => $column, 'from' => $from, 'to' => $to];
        }
    }
}

$plan['archive_only'] = [
    ['table' => 'notifications', 'id' => 96, 'reason' => 'system reminder text (FarmerMissingInfoNotifier) rewritten in Supabase on page load; MariaDB regenerates it on the next visit'],
    ['table' => 'cache, cache_locks, sessions, jobs, job_batches, failed_jobs, migrations', 'reason' => 'transient framework tables; kept in the final Supabase archive only'],
];

if ($apply) {
    $my->beginTransaction();
    try {
        $insertLog = $my->prepare('insert into system_logs (user_id, action, details, created_at, updated_at) values (:user_id, :action, :details, :created_at, :updated_at)');
        foreach ($logInserts as $item) {
            $insertLog->execute($item['values']);
            $plan['inserted'][] = ['table' => 'system_logs', 'source_id' => $item['source_id'], 'new_id' => (int) $my->lastInsertId(), 'created_at' => $item['values']['created_at'], 'action' => $item['values']['action'], 'user_id' => $item['values']['user_id']];
        }
        $insertCheck = $my->prepare('insert into backup_verifications ('.implode(', ', $checkColumns).') values (:'.implode(', :', $checkColumns).')');
        foreach ($checkInserts as $item) {
            $insertCheck->execute($item['values']);
            $plan['inserted'][] = ['table' => 'backup_verifications', 'source_id' => $item['source_id'], 'new_id' => (int) $my->lastInsertId(), 'created_at' => $item['values']['created_at'], 'status' => $item['values']['status']];
        }
        foreach ($loginMerges as $merge) {
            $update = $my->prepare("update users set {$merge['column']} = :to where id = :id and ({$merge['column']} is null or {$merge['column']} < :to2)");
            $update->execute(['to' => $merge['to'], 'to2' => $merge['to'], 'id' => $merge['user_id']]);
            if ($update->rowCount() === 1) {
                $plan['merged'][] = $merge;
            }
        }
        $my->commit();
    } catch (Throwable $e) {
        $my->rollBack();
        fail('Import rolled back ('.get_class($e).'); MariaDB was not changed.');
    }
} else {
    foreach ($logInserts as $item) {
        $plan['inserted'][] = ['table' => 'system_logs', 'source_id' => $item['source_id'], 'new_id' => 'next AUTO_INCREMENT', 'created_at' => $item['values']['created_at'], 'action' => $item['values']['action'], 'user_id' => $item['values']['user_id']];
    }
    foreach ($checkInserts as $item) {
        $plan['inserted'][] = ['table' => 'backup_verifications', 'source_id' => $item['source_id'], 'new_id' => 'next AUTO_INCREMENT', 'created_at' => $item['values']['created_at'], 'status' => $item['values']['status']];
    }
    $plan['merged'] = $loginMerges;
}

$pg->exec('COMMIT');

printf("Mode: %s | target: %s\n", $plan['mode'], $plan['target_database']);
foreach ($plan['inserted'] as $row) {
    printf("  %s %s supabase id %d -> MariaDB id %s (%s%s)\n", $apply ? 'INSERTED' : 'WOULD INSERT', $row['table'], $row['source_id'], $row['new_id'], $row['created_at'], isset($row['action']) ? ', '.$row['action'] : ', '.$row['status']);
}
foreach ($plan['merged'] as $row) {
    printf("  %s users.%s for user %d: %s -> %s\n", $apply ? 'MERGED' : 'WOULD MERGE', $row['column'], $row['user_id'], $row['from'] ?? 'null', $row['to']);
}
foreach ($plan['skipped'] as $row) {
    printf("  SKIPPED %s %d: %s\n", $row['table'], $row['source_id'], $row['reason']);
}
printf("Totals: %d insert(s), %d merge(s), %d skipped, %d archive-only item(s).\n", count($plan['inserted']), count($plan['merged']), count($plan['skipped']), count($plan['archive_only']));

if ($reportPath) {
    $old = umask(0077);
    file_put_contents($reportPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    umask($old);
    echo "Report: {$reportPath}\n";
}
