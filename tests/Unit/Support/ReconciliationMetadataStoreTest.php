<?php

namespace Tests\Unit\Support;

use App\Support\ReconciliationMetadataStore;
use PHPUnit\Framework\TestCase;

final class ReconciliationMetadataStoreTest extends TestCase
{
    public function test_large_metadata_index_keeps_exact_unmatched_keys_without_retaining_rows(): void
    {
        $store = new ReconciliationMetadataStore();
        for ($id = 1; $id <= 10000; $id++) {
            $key = json_encode(['id' => "number:{$id}"], JSON_THROW_ON_ERROR);
            $store->put('large_table', $key, hash('sha256', $key), ['id' => $id]);
        }

        $store->remove('large_table', json_encode(['id' => 'number:1'], JSON_THROW_ON_ERROR));
        $store->remove('large_table', json_encode(['id' => 'number:2'], JSON_THROW_ON_ERROR));

        $extras = iterator_to_array($store->extraKeys('large_table'), false);

        self::assertCount(9998, $extras);
        self::assertNull($store->find('large_table', json_encode(['id' => 'number:1'], JSON_THROW_ON_ERROR)));
        self::assertSame(['hash' => hash('sha256', json_encode(['id' => 'number:3'], JSON_THROW_ON_ERROR)), 'primary_key_values' => ['id' => 3]], $store->find('large_table', json_encode(['id' => 'number:3'], JSON_THROW_ON_ERROR)));

        $store->close();
    }
}
