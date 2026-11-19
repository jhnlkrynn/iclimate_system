<?php

namespace Tests\Unit;

use App\Support\SchemaCache;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Verification 2026-11-18: one request asked the cache store ~200 times; the answers are now kept for the request. */
class SchemaCacheMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_questions_in_one_request_read_the_cache_store_once(): void
    {
        SchemaCache::forget();
        $reads = 0;
        Event::listen([CacheHit::class, CacheMissed::class], function () use (&$reads): void { $reads++; });

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue(SchemaCache::hasTable('users'));
            $this->assertTrue(SchemaCache::hasColumn('users', 'email'));
            $this->assertFalse(SchemaCache::hasColumn('users', 'no_such_column'));
        }

        $this->assertLessThanOrEqual(3, $reads, 'version, table list and one column list');
    }

    public function test_a_migration_is_seen_at_once_after_forget(): void
    {
        $this->assertFalse(SchemaCache::hasTable('schema_cache_probe'));

        Schema::create('schema_cache_probe', fn (Blueprint $table) => $table->id());
        SchemaCache::forget();   // what AppServiceProvider does when migrations end

        $this->assertTrue(SchemaCache::hasTable('schema_cache_probe'));
        $this->assertTrue(SchemaCache::hasColumn('schema_cache_probe', 'id'));
    }

    public function test_a_long_running_process_reads_again_after_the_memo_lapses(): void
    {
        $this->assertFalse(SchemaCache::hasTable('schema_cache_probe_two'));
        Schema::create('schema_cache_probe_two', fn (Blueprint $table) => $table->id());
        \Illuminate\Support\Facades\Cache::forever(SchemaCache::VERSION_KEY, 7);   // another process ran a migration

        $memo = app('schema-cache.memo');
        $memo->expires = microtime(true) - 1;   // the in-memory copy has lapsed in this worker

        $this->assertTrue(SchemaCache::hasTable('schema_cache_probe_two'), 'another process bumped the version');
    }
}
