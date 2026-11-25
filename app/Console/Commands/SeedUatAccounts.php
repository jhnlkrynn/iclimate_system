<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\ActiveFarm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Idempotently creates/updates UAT test accounts (farmer + non-head MAO staff) and prints their passwords once.
 */
class SeedUatAccounts extends Command
{
    protected $signature = 'iclimate:seed-uat-accounts';

    protected $description = 'Create or update UAT test accounts (uat-farmer@iclimate.local and uat-mao@iclimate.local) and print generated passwords.';

    public function handle(): int
    {
        $farmerPassword = Str::password(16, symbols: false);
        $maoPassword = Str::password(16, symbols: false);

        DB::transaction(function () use ($farmerPassword, $maoPassword): void {
            $now = now();

            $farmer = User::withDeleted()->firstOrNew(['email' => 'uat-farmer@iclimate.local']);
            $farmer->forceFill([
                'name' => 'UAT Farmer',
                'email' => 'uat-farmer@iclimate.local',
                'email_verified_at' => $farmer->email_verified_at ?? $now,
                'password' => Hash::make($farmerPassword),
                'role' => User::ROLE_FARMER,
                'status' => User::STATUS_ACTIVE,
                'barangay' => 'Prenza',
                'contact_number' => '09170001111',
                'privacy_agreed_at' => $farmer->privacy_agreed_at ?? $now,
                'must_change_password' => false,
                'deleted_at' => null,
                'deleted_reason' => null,
                'deleted_reason_other' => null,
                'deleted_note_internal' => null,
            ])->save();

            FarmerProfile::query()->updateOrCreate(
                ['user_id' => $farmer->id],
                [
                    'full_name' => $farmer->name,
                    'barangay' => 'Prenza',
                    'contact_number' => '09170001111',
                    'farm_type' => 'Irrigated',
                    'farm_area' => 1.50,
                ]
            );

            if (ActiveFarm::available()) {
                $farm = Farm::query()->where('user_id', $farmer->id)->active()->first() ?? new Farm(['user_id' => $farmer->id]);
                $farm->forceFill([
                    'farm_name' => 'UAT Prenza Farm',
                    'barangay' => 'Prenza',
                    'farm_type' => 'Irrigated',
                    'farm_size_hectares' => 1.50,
                    'is_primary' => true,
                    'verification_status' => Farm::VERIFICATION_APPROVED,
                    'verified_at' => $farm->verified_at ?? $now,
                    'archived_at' => null,
                ])->save();
            }

            $mao = User::withDeleted()->firstOrNew(['email' => 'uat-mao@iclimate.local']);
            $mao->forceFill([
                'name' => 'UAT MAO Staff',
                'email' => 'uat-mao@iclimate.local',
                'email_verified_at' => $mao->email_verified_at ?? $now,
                'password' => Hash::make($maoPassword),
                'role' => User::ROLE_MAO,
                'is_mao_head' => false,
                'position' => 'Agricultural Technologist',
                'station_type' => User::STATION_MUNICIPAL,
                'status' => User::STATUS_ACTIVE,
                'contact_number' => '09170002222',
                'privacy_agreed_at' => $mao->privacy_agreed_at ?? $now,
                'must_change_password' => false,
                'deleted_at' => null,
                'deleted_reason' => null,
                'deleted_reason_other' => null,
                'deleted_note_internal' => null,
            ])->save();
        });

        $this->info('UAT accounts ready:');
        $this->line("  Farmer (approved + 1 approved farm): uat-farmer@iclimate.local / {$farmerPassword}");
        $this->line("  MAO Staff (not head):                uat-mao@iclimate.local / {$maoPassword}");

        return self::SUCCESS;
    }
}
