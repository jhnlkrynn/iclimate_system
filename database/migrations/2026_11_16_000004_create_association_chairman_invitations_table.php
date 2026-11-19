<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('association_chairman_invitations', function (Blueprint $table): void {
            $table->id(); $table->foreignId('association_chairman_assignment_id')->constrained(null, null, 'aci_assignment_id_foreign')->cascadeOnDelete(); $table->string('token_hash', 128)->unique(); $table->timestamp('expires_at')->useCurrent(); $table->timestamp('accepted_at')->nullable(); $table->foreignId('invited_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->index('expires_at');
        });
    }
    public function down(): void { Schema::dropIfExists('association_chairman_invitations'); }
};
