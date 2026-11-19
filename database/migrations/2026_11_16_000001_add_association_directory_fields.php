<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('farmer_organizations')) {
            Schema::table('farmer_organizations', function (Blueprint $table): void {
                if (! Schema::hasColumn('farmer_organizations', 'directory_key')) $table->string('directory_key', 80)->nullable()->unique();
                if (! Schema::hasColumn('farmer_organizations', 'directory_status')) $table->string('directory_status', 24)->default('provisional')->index();
                if (! Schema::hasColumn('farmer_organizations', 'chairman_name')) $table->string('chairman_name')->nullable();
                if (! Schema::hasColumn('farmer_organizations', 'directory_source')) $table->string('directory_source')->nullable();
                if (! Schema::hasColumn('farmer_organizations', 'contact_verified_at')) $table->timestamp('contact_verified_at')->nullable();
                if (! Schema::hasColumn('farmer_organizations', 'confirmed_at')) $table->timestamp('confirmed_at')->nullable();
                if (! Schema::hasColumn('farmer_organizations', 'confirmed_by')) $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('users') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('Farmer','MAO Personnel','IT Expert','Association Chairman') NOT NULL DEFAULT 'Farmer'");
        } elseif (Schema::hasTable('users') && DB::getDriverName() === 'sqlite') {
            // SQLite represents the original enum as a CHECK constraint; changing it to a
            // plain string keeps the additive chairman role usable in isolated test databases.
            Schema::table('users', fn (Blueprint $table): \Illuminate\Support\Fluent => $table->string('role')->change());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && DB::getDriverName() === 'mysql') {
            // Never narrow the column over values it can no longer hold (canonical roles or chairman accounts
            // written after this migration): the rollback keeps the wider column instead of losing data.
            $unrepresentable = DB::table('users')->whereNotIn(DB::raw('BINARY role'), ['Farmer', 'MAO Personnel', 'IT Expert'])->exists();
            if (! $unrepresentable) {
                DB::statement("ALTER TABLE users MODIFY role ENUM('Farmer','MAO Personnel','IT Expert') NOT NULL DEFAULT 'Farmer'");
            }
        } elseif (Schema::hasTable('users') && DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table): \Illuminate\Support\Fluent => $table->string('role')->change());
        }
        if (Schema::hasTable('farmer_organizations') && Schema::hasColumn('farmer_organizations', 'confirmed_by')
            && collect(Schema::getForeignKeys('farmer_organizations'))->contains(fn (array $key): bool => $key['columns'] === ['confirmed_by'])) {
            Schema::table('farmer_organizations', fn (Blueprint $table) => $table->dropForeign(['confirmed_by']));
        }
        if (Schema::hasTable('farmer_organizations')) Schema::table('farmer_organizations', function (Blueprint $table): void {
            foreach (['confirmed_by', 'directory_key', 'directory_status', 'chairman_name', 'directory_source', 'contact_verified_at', 'confirmed_at'] as $column) {
                if (Schema::hasColumn('farmer_organizations', $column)) $table->dropColumn($column);
            }
        });
    }
};
