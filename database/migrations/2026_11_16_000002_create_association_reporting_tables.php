<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('association_chairman_assignments', function (Blueprint $table): void {
            $table->id(); $table->foreignId('farmer_organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index(); $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reassigned_at')->nullable(); $table->timestamp('contact_verified_at')->nullable(); $table->timestamps();
            $table->index(['farmer_organization_id', 'status'], 'aca_org_status_idx'); $table->index(['user_id', 'status']);
        });
        Schema::create('association_reporting_periods', function (Blueprint $table): void {
            $table->id(); $table->string('period_key', 80)->unique(); $table->string('label'); $table->unsignedSmallInteger('reporting_year'); $table->string('season', 32)->nullable();
            $table->timestamp('opens_at')->nullable(); $table->timestamp('due_at')->useCurrent(); $table->boolean('required')->default(true); $table->json('field_requirements')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['reporting_year', 'due_at']);
        });
        Schema::create('association_yield_reports', function (Blueprint $table): void {
            $table->id(); $table->foreignId('farmer_organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('reporting_period_id')->constrained('association_reporting_periods')->cascadeOnDelete(); $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index(); $table->date('planting_date')->nullable(); $table->date('harvest_date')->nullable(); $table->string('rice_variety')->nullable(); $table->string('irrigation_type', 32)->nullable(); $table->decimal('area_planted_hectares', 10, 2)->nullable(); $table->decimal('area_harvested_hectares', 10, 2)->nullable(); $table->decimal('production_tons', 12, 2)->nullable(); $table->decimal('average_yield_t_per_hectare', 12, 4)->nullable(); $table->unsignedInteger('participating_farmers')->nullable(); $table->text('crop_losses')->nullable(); $table->text('remarks')->nullable(); $table->string('supporting_document_path')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable(); $table->string('research_provenance', 32)->default('unverified'); $table->timestamp('research_verified_at')->nullable(); $table->foreignId('research_verified_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['farmer_organization_id', 'reporting_period_id'], 'ayr_org_period_unique'); $table->index(['status', 'submitted_at']);
        });
        Schema::create('association_yield_report_reviews', function (Blueprint $table): void {
            $table->id(); $table->foreignId('association_yield_report_id')->constrained(null, null, 'ayrr_report_id_foreign')->cascadeOnDelete(); $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete(); $table->string('action', 32); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('association_reminder_deliveries', function (Blueprint $table): void {
            $table->id(); $table->string('delivery_key', 180)->unique(); $table->foreignId('farmer_organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('reporting_period_id')->constrained('association_reporting_periods')->cascadeOnDelete(); $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete(); $table->string('kind', 32); $table->timestamp('sent_at')->useCurrent(); $table->index(['reporting_period_id', 'kind']);
        });
        Schema::create('association_messages', function (Blueprint $table): void {
            $table->id(); $table->foreignId('farmer_organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('sender_id')->constrained('users')->restrictOnDelete(); $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('subject'); $table->text('body'); $table->string('message_type', 32)->default('announcement'); $table->timestamp('read_at')->nullable(); $table->timestamps(); $table->index(['farmer_organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('association_messages'); Schema::dropIfExists('association_reminder_deliveries'); Schema::dropIfExists('association_yield_report_reviews'); Schema::dropIfExists('association_yield_reports'); Schema::dropIfExists('association_reporting_periods'); Schema::dropIfExists('association_chairman_assignments');
    }
};
