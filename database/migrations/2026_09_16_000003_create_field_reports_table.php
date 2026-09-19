<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('barangay')->index();
            $table->enum('issue_type', ['Pest/Disease', 'Flooding', 'Drought/Water Shortage', 'Damaged Crops', 'Other'])->index();
            $table->text('description')->nullable();
            $table->enum('status', ['Pending', 'Verified', 'Resolved'])->default('Pending')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_reports');
    }
};
