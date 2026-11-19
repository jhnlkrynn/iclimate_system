<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve every historical value and foreign key. Authorization recognizes only farmer and mao.
        // Existing MySQL enums cannot store the canonical role keys used by new accounts.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 64)->default('farmer')->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded column on rollback so accounts created after this migration remain intact.
        // The preceding application version can be restored together with its database snapshot if required.
    }
};
