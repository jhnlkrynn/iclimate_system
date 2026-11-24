<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table): void {
            if (! Schema::hasColumn('farms', 'verification_status')) {
                $table->string('verification_status', 20)->default('pending')->after('is_primary')->index();
            }
            if (! Schema::hasColumn('farms', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('farms', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('farms', 'rejection_reason')) {
                $table->string('rejection_reason', 500)->nullable()->after('verified_at');
            }
        });

        // Farms that already exist before this migration are grandfathered as approved.
        DB::table('farms')->whereNull('verified_at')->update([
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('farms')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_at', 'rejection_reason']);
        });
    }
};
