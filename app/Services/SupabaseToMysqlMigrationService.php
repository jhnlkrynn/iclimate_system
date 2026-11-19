<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use App\Support\DeterministicRowHash;

/**
 * Read-only source inventory plus a guarded table-for-table transfer into the
 * Laravel schema. The source is never written to; the target must be local and
 * empty before import so IDs and relationships remain intact.
 */
final class SupabaseToMysqlMigrationService
{
    private const TRANSIENT_TABLES = ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'sessions'];

    private bool $freshTargetApproved = false;

    public function sourceConnection(): string
    {
        return 'migration_source';
    }

    public function targetConnection(): string
    {
        return 'migration_target';
    }

    /** @return array{ok: bool, driver: string, database: string, schema: string} */
    public function connectivityProbe(): array
    {
        $source = DB::connection($this->sourceConnection());
        $row = $source->selectOne('select 1 as ok, current_database() as database_name, current_schema() as schema_name');

        return [
            'ok' => (int) $row->ok === 1,
            'driver' => $source->getDriverName(),
            'database' => (string) $row->database_name,
            'schema' => (string) $row->schema_name,
        ];
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        $source = DB::connection($this->sourceConnection());
        $schema = (string) config('migration.source.schema', 'public');
        $tables = $source->select(
            'select table_name from information_schema.tables where table_schema = ? and table_type = ? order by table_name',
            [$schema, 'BASE TABLE'],
        );

        $tableRows = [];
        foreach ($tables as $table) {
            $name = (string) $table->table_name;
            $tableRows[] = [
                'source_table' => $name,
                'source_schema' => $schema,
                'source_rows' => (int) $source->table($name)->count(),
                'source_columns' => $this->sourceColumns($source, $schema, $name),
            ];
        }

        $schemas = $source->select(
            'select schema_name from information_schema.schemata where schema_name not like ? order by schema_name',
            ['pg_%'],
        );

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'driver' => $source->getDriverName(),
                'database' => (string) config('migration.source.database'),
                'schema' => $schema,
                'schemas' => array_map(static fn ($row): string => (string) $row->schema_name, $schemas),
            ],
            'tables' => $tableRows,
            'notes' => [
                'Supabase Auth, Storage, Realtime, policies, functions, and triggers are not application-schema tables and require separate source access review.',
                'Counts are collected from the live source; no fabricated counts are written when the source is unreachable.',
            ],
        ];
    }

    /**
     * Compare source and target by primary-key sets and canonical row hashes. This is read-only on both connections;
     * timestamps, JSON ordering, booleans and numeric scale differences are normalized before hashing.
     *
     * @return array<string, mixed>
     */
    public function compare(): array
    {
        $source = DB::connection($this->sourceConnection());
        $target = DB::connection($this->targetConnection());
        $targetSchema = $target->getDatabaseName();
        $rows = [];
        $summary = ['tables_compared' => 0, 'tables_with_differences' => 0, 'source_rows' => 0, 'target_rows' => 0,
            'missing_in_target' => 0, 'extra_in_target' => 0, 'changed_rows' => 0, 'unsupported_tables' => 0];

        foreach ($this->inventory()['tables'] as $tableInfo) {
            $table = (string) $tableInfo['source_table'];
            if (in_array($table, self::TRANSIENT_TABLES, true)) {
                $rows[] = $this->skipped($table, 'transient framework table');

                continue;
            }
            if (! Schema::connection($this->targetConnection())->hasTable($table)) {
                $rows[] = ['source_table' => $table, 'status' => 'missing_target_table'];
                $summary['tables_with_differences']++;

                continue;
            }

            $sourceColumns = $this->columnMetadata($source, (string) config('migration.source.schema'), $table);
            $targetColumns = $this->columnMetadata($target, $targetSchema, $table);
            $columns = array_values(array_intersect(array_keys($sourceColumns), array_keys($targetColumns)));
            $primaryKey = array_values(array_intersect(
                $this->primaryKey($source, (string) config('migration.source.schema'), $table),
                $this->primaryKey($target, $targetSchema, $table),
            ));
            if ($columns === [] || $primaryKey === []) {
                $rows[] = ['source_table' => $table, 'status' => 'unsupported', 'reason' => $primaryKey === [] ? 'no shared primary key' : 'no shared columns'];
                $summary['unsupported_tables']++;

                continue;
            }

            $types = [];
            foreach ($columns as $column) {
                $types[$column] = ['source' => $sourceColumns[$column], 'target' => $targetColumns[$column]];
            }
            $targetRows = [];
            foreach ($target->table($table)->select($columns)->cursor() as $record) {
                $record = (array) $record;
                $targetRows[DeterministicRowHash::key($record, $primaryKey, $types)] = DeterministicRowHash::row($record, $columns, $types);
            }
            $missing = $extra = $changed = 0;
            $missingKeys = $changedKeys = [];
            foreach ($source->table($table)->select($columns)->cursor() as $record) {
                $record = (array) $record;
                $key = DeterministicRowHash::key($record, $primaryKey, $types);
                $hash = DeterministicRowHash::row($record, $columns, $types);
                if (! array_key_exists($key, $targetRows)) {
                    $missing++;
                    $missingKeys[] = $key;
                } elseif ($targetRows[$key] !== $hash) {
                    $changed++;
                    $changedKeys[] = $key;
                    unset($targetRows[$key]);
                } else {
                    unset($targetRows[$key]);
                }
            }
            $extra = count($targetRows);
            $extraKeys = array_keys($targetRows);
            $sourceCount = (int) $tableInfo['source_rows'];
            $targetCount = $sourceCount - $missing + $extra;
            $different = $missing + $extra + $changed > 0;
            $summary['tables_compared']++;
            $summary['source_rows'] += $sourceCount;
            $summary['target_rows'] += $targetCount;
            $summary['missing_in_target'] += $missing;
            $summary['extra_in_target'] += $extra;
            $summary['changed_rows'] += $changed;
            if ($different) {
                $summary['tables_with_differences']++;
            }
            $rows[] = ['source_table' => $table, 'status' => $different ? 'different' : 'equal', 'source_rows' => $sourceCount,
                'target_rows' => $targetCount, 'missing_in_target' => $missing, 'extra_in_target' => $extra, 'changed_rows' => $changed,
                'missing_primary_keys' => $missingKeys, 'extra_primary_keys' => $extraKeys, 'changed_primary_keys' => $changedKeys,
                'primary_key' => $primaryKey, 'hashed_columns' => count($columns)];
        }

        return ['generated_at' => now()->toIso8601String(), 'source' => ['schema' => config('migration.source.schema')],
            'target' => ['database' => $targetSchema], 'summary' => $summary, 'tables' => $rows,
            'notes' => ['Comparison is read-only. Framework transient tables are excluded.']];
    }

    /** @return array<string, mixed> */
    public function migrate(): array
    {
        $source = DB::connection($this->sourceConnection());
        $target = DB::connection($this->targetConnection());
        $sourceTables = collect($this->inventory()['tables'])->pluck('source_table')->all();
        // Compare the source's bare table names with bare target names. Laravel's
        // default MySQL listing is schema-qualified (database.table), which would
        // otherwise make every supported table look absent.
        $targetTables = Schema::connection($this->targetConnection())->getTableListing(null, false);
        $rows = [];

        $target->unprepared('SET SESSION FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($sourceTables as $table) {
                if (in_array($table, self::TRANSIENT_TABLES, true)) {
                    $rows[] = $this->skipped($table, 'transient framework table');
                    continue;
                }
                if (! in_array($table, $targetTables, true)) {
                    $rows[] = $this->skipped($table, 'no matching Laravel migration table');
                    continue;
                }

                $columns = array_values(array_intersect(
                    $this->sourceColumns($source, (string) config('migration.source.schema'), $table),
                    Schema::connection($this->targetConnection())->getColumnListing($table),
                ));
                if ($columns === []) {
                    $rows[] = $this->skipped($table, 'no shared columns');
                    continue;
                }

                $imported = 0;
                $batch = [];
                foreach ($source->table($table)->select($columns)->cursor() as $record) {
                    $batch[] = $this->normalize((array) $record);
                    if (count($batch) >= 250) {
                        $target->table($table)->insert($batch);
                        $imported += count($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $target->table($table)->insert($batch);
                    $imported += count($batch);
                }
                $rows[] = ['source_table' => $table, 'imported_rows' => $imported, 'status' => 'imported'];
            }
        } finally {
            $target->unprepared('SET SESSION FOREIGN_KEY_CHECKS=1');
        }

        return ['generated_at' => now()->toIso8601String(), 'tables' => $rows];
    }

    public function assertSafeTarget(): void
    {
        $target = config('migration.target');
        if (! in_array($target['driver'] ?? null, ['mysql', 'mariadb'], true) || ! in_array($target['host'] ?? null, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('Migration target must be local MySQL/MariaDB on 127.0.0.1 or localhost.');
        }

        $schema = Schema::connection($this->targetConnection());
        if ($schema->hasTable('migrations') && $schema->getTables()) {
            throw new RuntimeException('Migration target is not empty. Refusing to overwrite existing MySQL data. Use a fresh staging database.');
        }

        $this->freshTargetApproved = true;
    }

    public function clearFreshTargetRows(): void
    {
        if (! $this->freshTargetApproved) {
            throw new RuntimeException('Target freshness must be verified before clearing migration-seeded rows.');
        }

        $target = DB::connection($this->targetConnection());
        $tables = Schema::connection($this->targetConnection())->getTableListing(null, false);

        $target->unprepared('SET SESSION FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    $target->table($table)->delete();
                }
            }
        } finally {
            $target->unprepared('SET SESSION FOREIGN_KEY_CHECKS=1');
        }
    }

    /** @return list<string> */
    private function sourceColumns($source, string $schema, string $table): array
    {
        return collect($source->select(
            'select column_name from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position',
            [$schema, $table],
        ))->pluck('column_name')->map(static fn ($name): string => (string) $name)->all();
    }

    /** @return array<string, string> */
    private function columnMetadata($connection, string $schema, string $table): array
    {
        return collect($connection->select(
            'select column_name, data_type, datetime_precision from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position',
            [$schema, $table],
        ))->mapWithKeys(static fn ($row): array => [
            (string) $row->column_name => strtolower((string) $row->data_type).';precision='.(int) ($row->datetime_precision ?? 0),
        ])->all();
    }

    /** @return list<string> */
    private function primaryKey($connection, string $schema, string $table): array
    {
        return collect($connection->select(
            'select kcu.column_name from information_schema.table_constraints tc join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name and kcu.table_schema = tc.table_schema and kcu.table_name = tc.table_name where tc.table_schema = ? and tc.table_name = ? and tc.constraint_type = ? order by kcu.ordinal_position',
            [$schema, $table, 'PRIMARY KEY'],
        ))->pluck('column_name')->map(static fn ($column): string => (string) $column)->all();
    }

    /** @return array<string, mixed> */
    private function normalize(array $row): array
    {
        return collect($row)->map(static function ($value) {
            if (is_resource($value)) {
                return stream_get_contents($value);
            }
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            if (is_array($value)) {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::?\d{2})?)$/', $value) === 1) {
                return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u');
            }
            return $value;
        })->all();
    }

    /** @return array<string, mixed> */
    private function skipped(string $table, string $reason): array
    {
        return ['source_table' => $table, 'imported_rows' => 0, 'status' => 'skipped', 'reason' => $reason];
    }
}
