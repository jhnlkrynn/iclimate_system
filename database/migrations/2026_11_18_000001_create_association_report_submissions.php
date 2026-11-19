<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('association_yield_report_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_yield_report_id')->constrained('association_yield_reports', 'id', 'ayrs_report_fk')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users', 'id', 'ayrs_submitter_fk')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->longText('snapshot');
            $table->timestamp('submitted_at');
            $table->unique(['association_yield_report_id', 'version'], 'ayrs_report_version_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('association_yield_report_submissions')->exists()) {
            throw new RuntimeException('Submission history exists; preserve it before an approved rollback.');
        }
        Schema::dropIfExists('association_yield_report_submissions');
    }
};
