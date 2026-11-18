<?php

namespace App\Support;

use PDO;
use RuntimeException;

/** Disk-backed key/hash index used by the read-only reconciliation worker. */
final class ReconciliationMetadataStore
{
    private PDO $db;

    private string $path;

    public function __construct()
    {
        $path = tempnam(sys_get_temp_dir(), 'iclimate-reconcile-');
        if ($path === false) {
            throw new RuntimeException('Unable to create reconciliation metadata spool.');
        }
        $this->path = $path;
        $this->db = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec(<<<'SQL'
            CREATE TABLE target_rows (
                table_name TEXT NOT NULL,
                row_key TEXT NOT NULL,
                row_hash TEXT NOT NULL,
                primary_key_values TEXT NOT NULL,
                PRIMARY KEY (table_name, row_key)
            )
        SQL);
    }

    public function clearTable(string $table): void
    {
        $stmt = $this->db->prepare('DELETE FROM target_rows WHERE table_name = :table');
        $stmt->execute(['table' => $table]);
    }

    public function put(string $table, string $key, string $hash, array $primaryKeyValues): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            INSERT INTO target_rows (table_name, row_key, row_hash, primary_key_values)
            VALUES (:table, :key, :hash, :primary_key_values)
        SQL);
        $stmt->execute([
            'table' => $table,
            'key' => $key,
            'hash' => $hash,
            'primary_key_values' => json_encode($primaryKeyValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return array{hash: string, primary_key_values: array<string, mixed>}|null */
    public function find(string $table, string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT row_hash, primary_key_values FROM target_rows WHERE table_name = :table AND row_key = :key');
        $stmt->execute(['table' => $table, 'key' => $key]);
        $row = $stmt->fetch();
        if ($row === false) return null;

        return [
            'hash' => $row['row_hash'],
            'primary_key_values' => json_decode($row['primary_key_values'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    public function remove(string $table, string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM target_rows WHERE table_name = :table AND row_key = :key');
        $stmt->execute(['table' => $table, 'key' => $key]);
    }

    /** @return \Generator<int, string> */
    public function extraKeys(string $table): \Generator
    {
        $stmt = $this->db->prepare('SELECT row_key FROM target_rows WHERE table_name = :table ORDER BY row_key');
        $stmt->execute(['table' => $table]);
        while (($row = $stmt->fetch()) !== false) yield $row['row_key'];
    }

    public function close(): void
    {
        unset($this->db);
        if (is_file($this->path)) @unlink($this->path);
    }

    public function __destruct()
    {
        $this->close();
    }
}
