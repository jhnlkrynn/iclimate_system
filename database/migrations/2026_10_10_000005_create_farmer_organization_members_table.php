<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 1.1: a farmer can belong to more than one farmer organization.
 *
 * farmer_organization_members is the membership list (farmer ↔ organization, with the date joined). The table is not
 * called farmer_organizations because that name already holds the organizations themselves.
 * farmer_profiles.farmer_organization_id stays as the farmer's PRIMARY organization (the one that sets their barangay and
 * farm type at sign-up); it is always one of their memberships.
 *
 * Existing data: every farmer keeps their current organization as their first membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_profile_id')->constrained('farmer_profiles')->cascadeOnDelete();
            $table->foreignId('farmer_organization_id')->constrained('farmer_organizations')->cascadeOnDelete();
            $table->date('date_joined')->nullable();
            $table->timestamps();

            $table->unique(['farmer_profile_id', 'farmer_organization_id'], 'farmer_org_member_unique');
            $table->index('farmer_organization_id');
        });

        $now = now();
        DB::table('farmer_profiles')
            ->whereNotNull('farmer_organization_id')
            ->orderBy('id')
            ->select(['id', 'farmer_organization_id', 'created_at'])
            ->chunk(500, function ($profiles) use ($now): void {
                DB::table('farmer_organization_members')->insertOrIgnore($profiles->map(fn ($p): array => [
                    'farmer_profile_id' => $p->id,
                    'farmer_organization_id' => $p->farmer_organization_id,
                    'date_joined' => $p->created_at ? substr((string) $p->created_at, 0, 10) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_organization_members');
    }
};
