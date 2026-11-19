<?php

namespace Tests\Unit\Support;

use App\Support\DeterministicRowHash;
use PHPUnit\Framework\TestCase;

class DeterministicRowHashTest extends TestCase
{
    private const TYPES = [
        'id' => ['source' => 'integer', 'target' => 'bigint'],
        'amount' => ['source' => 'numeric', 'target' => 'decimal'],
        'at' => ['source' => 'timestamp without time zone', 'target' => 'datetime'],
        'meta' => ['source' => 'jsonb', 'target' => 'longtext'],
        'nullable' => ['source' => 'text', 'target' => 'text'],
    ];

    public function test_equivalent_cross_database_values_have_the_same_hash(): void
    {
        $columns = array_keys(self::TYPES);
        $postgres = ['id' => '7', 'amount' => '24.5000', 'at' => '2026-11-03 16:00:00+00', 'meta' => '{"b":2,"a":1}', 'nullable' => null];
        $mysql = ['id' => 7, 'amount' => '24.5', 'at' => '2026-11-03 16:00:00.000000', 'meta' => '{"a":1,"b":2}', 'nullable' => null];

        $this->assertSame(
            DeterministicRowHash::row($postgres, $columns, self::TYPES),
            DeterministicRowHash::row($mysql, $columns, self::TYPES),
        );
    }

    public function test_primary_key_and_row_changes_remain_visible(): void
    {
        $row = ['id' => 7, 'amount' => '24.5', 'at' => '2026-11-03 16:00:00', 'meta' => '{}', 'nullable' => null];
        $changed = $row;
        $changed['amount'] = '25.5';

        $this->assertSame(
            DeterministicRowHash::key($row, ['id'], self::TYPES),
            DeterministicRowHash::key($changed, ['id'], self::TYPES),
        );
        $this->assertNotSame(
            DeterministicRowHash::row($row, array_keys(self::TYPES), self::TYPES),
            DeterministicRowHash::row($changed, array_keys(self::TYPES), self::TYPES),
        );
    }
}
