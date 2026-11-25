<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            'users' => [
                ['columns' => ['created_at'], 'name' => 'users_created_at_index'],
            ],
            'farms' => [
                ['columns' => ['created_at'], 'name' => 'farms_created_at_index'],
                ['columns' => ['barangay', 'verification_status', 'archived_at'], 'name' => 'farms_barangay_verified_archived_idx'],
            ],
            'farmer_profiles' => [
                ['columns' => ['created_at'], 'name' => 'farmer_profiles_created_at_index'],
            ],
            'farmer_planting_schedules' => [
                ['columns' => ['created_at'], 'name' => 'farmer_planting_schedules_created_at_index'],
            ],
            'rice_productions' => [
                ['columns' => ['created_at'], 'name' => 'rice_productions_created_at_index'],
            ],
            'climate_records' => [
                ['columns' => ['created_at'], 'name' => 'climate_records_created_at_index'],
            ],
        ];

        foreach ($targets as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $existing = $this->existingIndexNames($tableName);

            foreach ($indexes as $index) {
                if (in_array(strtolower($index['name']), $existing, true)) {
                    continue;
                }
                $missingCol = false;
                foreach ($index['columns'] as $col) {
                    if (! Schema::hasColumn($tableName, $col)) {
                        $missingCol = true;
                        break;
                    }
                }
                if ($missingCol) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->index($index['columns'], $index['name']);
                });
            }
        }
    }

    public function down(): void
    {
        $targets = [
            'users' => ['users_created_at_index'],
            'farms' => ['farms_created_at_index', 'farms_barangay_verified_archived_idx'],
            'farmer_profiles' => ['farmer_profiles_created_at_index'],
            'farmer_planting_schedules' => ['farmer_planting_schedules_created_at_index'],
            'rice_productions' => ['rice_productions_created_at_index'],
            'climate_records' => ['climate_records_created_at_index'],
        ];

        foreach ($targets as $tableName => $names) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $existing = $this->existingIndexNames($tableName);
            foreach ($names as $name) {
                if (in_array(strtolower($name), $existing, true)) {
                    Schema::table($tableName, function (Blueprint $table) use ($name): void {
                        $table->dropIndex($name);
                    });
                }
            }
        }
    }

    /** @return list<string> */
    private function existingIndexNames(string $tableName): array
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$tableName}')"))
                ->map(fn ($r) => strtolower((string) $r->name))
                ->all();
        }
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return collect(DB::select("SHOW INDEX FROM `{$tableName}`"))
                ->map(fn ($r) => strtolower((string) $r->Key_name))
                ->unique()
                ->values()
                ->all();
        }

        return collect(Schema::getIndexes($tableName))
            ->map(fn (array $idx) => strtolower((string) ($idx['name'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }
};
