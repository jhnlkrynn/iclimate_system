<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migration 2026_11_03_000005 keeps Supabase's "refresh on update" behaviour with MySQL's native
 * ON UPDATE CURRENT_TIMESTAMP attribute, never with a trigger: a trigger overwrote the time the application
 * wrote and used the server's local time zone (8 hours ahead on a Philippine XAMPP server).
 * These checks need MySQL/MariaDB; on SQLite the migration does nothing.
 */
class TimestampRefreshMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'external_weather_data' => 'fetched_at',
        'monthly_weather_predictions' => 'generated_at',
        'typhoon_safety_responses' => 'responded_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/MariaDB schema behaviour.');
        }
    }

    public function test_no_triggers_are_created_and_the_columns_refresh_natively(): void
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertSame(0, (int) DB::selectOne(
            'select count(*) c from information_schema.triggers where trigger_schema = ? and event_object_table in (?, ?, ?)',
            [$database, ...array_keys(self::COLUMNS)],
        )->c);

        foreach (self::COLUMNS as $table => $column) {
            $extra = (string) DB::selectOne(
                'select extra as extra from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
                [$database, $table, $column],
            )->extra;
            $this->assertStringContainsStringIgnoringCase('on update current_timestamp', $extra, $table.'.'.$column);
        }
    }

    public function test_planting_advisory_metadata_has_no_json_check_left_on_either_engine(): void
    {
        $database = DB::connection()->getDatabaseName();
        $names = collect(DB::select(
            "select constraint_name as constraint_name from information_schema.table_constraints where table_schema = ? and table_name = 'planting_advisories' and constraint_type = 'CHECK'",
            [$database],
        ))->pluck('constraint_name');

        foreach ($names as $name) {
            $clauses = collect(DB::select('select check_clause as check_clause from information_schema.check_constraints where constraint_schema = ? and constraint_name = ?', [$database, $name]))->pluck('check_clause');
            $this->assertFalse($clauses->contains(fn ($clause): bool => str_contains((string) $clause, 'metadata')), "check {$name} still tests metadata");
        }

        $this->assertSame('longtext', strtolower((string) DB::selectOne(
            "select data_type as data_type from information_schema.columns where table_schema = ? and table_name = 'planting_advisories' and column_name = 'metadata'",
            [$database],
        )->data_type));
    }

    public function test_json_columns_keep_the_written_text_on_every_engine(): void
    {
        $database = DB::connection()->getDatabaseName();

        // Migration 2026_11_10_000001: MySQL's native JSON re-sorts keys; MariaDB stores LONGTEXT. None may be native.
        $this->assertSame(0, (int) DB::selectOne("select count(*) as c from information_schema.columns where table_schema = ? and data_type = 'json'", [$database])->c);

        $id = DB::table('analytics_runs')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'report_analytics', 'status' => 'queued', 'input' => '{"report_type":"rice_production","filters":{"year":2026,"season":"Dry"}}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame('{"report_type":"rice_production","filters":{"year":2026,"season":"Dry"}}', DB::table('analytics_runs')->where('id', $id)->value('input'));
    }

    public function test_an_explicit_fetch_time_written_by_the_application_is_kept(): void
    {
        $id = DB::table('external_weather_data')->insertGetId([
            'source' => 'open_meteo', 'location_name' => 'Lian', 'latitude' => 14.03, 'longitude' => 120.65,
            'forecast_date' => '2026-11-10', 'temperature' => 29.5, 'fetched_at' => '2026-11-10 01:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $explicit = Carbon::parse('2026-11-10 02:30:00', 'UTC');
        DB::table('external_weather_data')->where('id', $id)->update(['temperature' => 30.1, 'fetched_at' => $explicit]);

        $this->assertSame($explicit->toDateTimeString(), Carbon::parse(DB::table('external_weather_data')->where('id', $id)->value('fetched_at'))->toDateTimeString());
    }

    public function test_the_migration_removes_triggers_left_by_its_first_version_without_touching_rows(): void
    {
        // CREATE/DROP TRIGGER commit implicitly on MySQL, so RefreshDatabase cannot roll this test back: it cleans up itself.
        $id = DB::table('external_weather_data')->insertGetId([
            'source' => 'open_meteo', 'location_name' => 'Lian', 'latitude' => 14.03, 'longitude' => 120.65,
            'forecast_date' => '2026-11-11', 'temperature' => 28.0, 'fetched_at' => '2026-11-10 01:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            DB::unprepared('CREATE TRIGGER `iclimate_external_weather_data_timestamp` BEFORE UPDATE ON `external_weather_data` FOR EACH ROW SET NEW.`fetched_at` = CURRENT_TIMESTAMP');
            $before = DB::table('external_weather_data')->orderBy('id')->get()->toArray();

            $migration = require database_path('migrations/2026_11_03_000005_create_supabase_timestamp_equivalent_triggers.php');
            $migration->up();

            $this->assertSame(0, (int) DB::selectOne('select count(*) c from information_schema.triggers where trigger_schema = ?', [DB::connection()->getDatabaseName()])->c);
            $this->assertEquals($before, DB::table('external_weather_data')->orderBy('id')->get()->toArray());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS `iclimate_external_weather_data_timestamp`');
            DB::table('external_weather_data')->where('id', $id)->delete();
        }
    }
}
