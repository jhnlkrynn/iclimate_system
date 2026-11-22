<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmer_organizations')) {
            return;
        }

        Schema::table('farmer_organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmer_organizations', 'registration_no')) {
                $table->string('registration_no', 100)->nullable()->after('farm_type');
            }
            if (! Schema::hasColumn('farmer_organizations', 'date_registered')) {
                $table->date('date_registered')->nullable()->after('registration_no');
            }
            if (! Schema::hasColumn('farmer_organizations', 'farm_type_source')) {
                $table->string('farm_type_source', 160)->nullable()->after('farm_type');
            }
            if (! Schema::hasColumn('farmer_organizations', 'farm_type_confirmed_at')) {
                $table->timestamp('farm_type_confirmed_at')->nullable()->after('farm_type_source');
            }
            if (! Schema::hasColumn('farmer_organizations', 'farm_type_confirmed_by')) {
                $table->foreignId('farm_type_confirmed_by')->nullable()->after('farm_type_confirmed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmer_organizations')) {
            return;
        }

        if (Schema::hasColumn('farmer_organizations', 'farm_type_confirmed_by')
            && collect(Schema::getForeignKeys('farmer_organizations'))->contains(fn (array $key): bool => $key['columns'] === ['farm_type_confirmed_by'])) {
            Schema::table('farmer_organizations', fn (Blueprint $table) => $table->dropForeign(['farm_type_confirmed_by']));
        }

        Schema::table('farmer_organizations', function (Blueprint $table): void {
            foreach (['farm_type_confirmed_by', 'farm_type_confirmed_at', 'farm_type_source', 'date_registered', 'registration_no'] as $column) {
                if (Schema::hasColumn('farmer_organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
