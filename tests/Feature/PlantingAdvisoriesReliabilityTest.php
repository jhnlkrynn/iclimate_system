<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\JsonColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression for the /planting-advisories 500 on the live database: "SQLSTATE[42883] operator does not exist:
 * text ->> unknown". planting_advisories.metadata (like every json column there) is text on Supabase, and the farmer
 * scope read it with Laravel's "metadata->scope_type" path. The SQLite test database accepts that, so these tests
 * check the SQL PostgreSQL would receive.
 */
class PlantingAdvisoriesReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        // A pgsql connection that is never opened: only its grammar is used to compile SQL.
        config(['database.connections.pgsql_sql_only' => ['driver' => 'pgsql', 'host' => 'invalid.invalid', 'database' => 'x', 'username' => 'x', 'password' => '', 'prefix' => '']]);
    }

    private function pgsql(): \Illuminate\Database\Eloquent\Builder
    {
        return PlantingAdvisory::on('pgsql_sql_only');
    }

    public function test_the_farmer_scope_casts_metadata_to_jsonb_on_postgres(): void
    {
        $sql = $this->pgsql()->forBarangay('Binubusan')->toSql();

        $this->assertStringNotContainsString('"metadata"->>', $sql);
        $this->assertStringNotContainsString('"metadata"->', $sql);
        $this->assertStringContainsString("CAST(NULLIF(CAST(\"metadata\" AS text), '') AS jsonb) ->> 'scope_type'", $sql);
        $this->assertStringContainsString("-> 'pagasa_barangay_matches', '[]'::jsonb) @> ?::jsonb", $sql);

        $this->assertStringNotContainsString('"metadata"->>', $this->pgsql()->forBarangay(null)->toSql());   // no barangay
    }

    public function test_json_column_helpers_compile_casts_on_postgres_and_native_paths_elsewhere(): void
    {
        $pg = JsonColumn::where($this->pgsql()->getQuery(), 'input', 'target_date', 'like', '2026-10%')->toSql();
        $this->assertSame("select * from \"planting_advisories\" where (CAST(NULLIF(CAST(\"input\" AS text), '') AS jsonb) ->> 'target_date') LIKE ?", $pg);

        config(['database.connections.mysql_sql_only' => ['driver' => 'mysql', 'host' => 'invalid.invalid', 'database' => 'x', 'username' => 'x', 'password' => '', 'prefix' => '']]);
        $my = JsonColumn::where(PlantingAdvisory::on('mysql_sql_only')->getQuery(), 'metadata', 'bulletin_family', '=', 'abc')->toSql();
        $this->assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(NULLIF(`metadata`, ''), '$.bulletin_family')) = ?", $my);   // XAMPP

        // SQLite (the test database) runs them.
        PlantingAdvisory::factory()->create(['metadata' => ['bulletin_family' => 'abc', 'scope_type' => 'province']]);
        $this->assertSame(1, JsonColumn::where(PlantingAdvisory::query(), 'metadata', 'bulletin_family', '=', 'abc')->count());
        $this->assertSame(1, PlantingAdvisory::query()->where(fn ($q) => JsonColumn::whereKeyMissingOrNot($q, 'metadata', 'scope_type', 'barangay'))->count());
    }

    public function test_no_code_reads_a_json_column_with_a_raw_path(): void
    {
        $offenders = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getFilename() === 'JsonColumn.php') {
                continue;
            }
            // where('col->key' ...), whereNull('col->key'), whereJsonContains(...), orderBy('col->key') and friends.
            if (preg_match_all("/->(?:or)?(?:where|whereNull|whereNotNull|whereIn|whereJsonContains|whereJsonLength|orderBy|groupBy|pluck)\\(\\s*'[a-z_]+->[a-z_]/i", $file->getContents(), $m)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Read JSON columns through App\Support\JsonColumn: they are text on the live PostgreSQL database.');
    }

    public function test_a_farmer_without_a_barangay_opens_advisories_while_pagasa_times_out(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => null]);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PUBLISHED, 'target_barangay' => null, 'title' => 'Lian-wide drainage check', 'metadata' => ['scope_type' => 'municipality']]);

        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->assertSee('Lian-wide drainage check');
        $this->actingAs($farmer)->post(route('planting-advisories.refresh-pagasa'))->assertStatus(302);   // refresh fails softly
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk();
    }

    public function test_a_malformed_stored_advisory_does_not_break_the_page(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Binubusan']);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PUBLISHED, 'target_barangay' => null, 'title' => 'Readable advisory']);
        // As an older writer or a hand edit could leave a row: empty metadata text, no dates, no summary.
        $broken = PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PUBLISHED, 'target_barangay' => null, 'title' => 'Half-written advisory']);
        DB::table('planting_advisories')->where('id', $broken->id)->update(['metadata' => '', 'content' => '', 'valid_from' => null, 'valid_until' => null]);

        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->assertSee('Readable advisory');
    }
}
