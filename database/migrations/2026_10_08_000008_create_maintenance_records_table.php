<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manuscript – Naicel (2026-10-08), Phase 8: IT's maintenance log (updates, fixes, backups, other work). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 30);                    // update / fix / backup / security / other
            $table->text('description')->nullable();
            $table->string('status', 20)->default('done');     // planned / in_progress / done
            $table->timestamp('performed_at')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
