<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\MaoUserBarangay;
use App\Models\Notification;
use App\Models\User;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_farmer_without_barangay_appears_for_mao_head_and_scoped_staff(): void
    {
        $pendingFarmer = User::factory()->farmer()->create([
            'name' => 'No Barangay Pending',
            'status' => User::STATUS_PENDING,
            'barangay' => null,
        ]);
        FarmerProfile::create([
            'user_id' => $pendingFarmer->id,
            'full_name' => $pendingFarmer->name,
            'barangay' => null,
            'farm_type' => null,
        ]);

        $maoHead = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'is_mao_head' => true,
        ]);
        $scopedStaff = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'is_mao_head' => false,
        ]);
        MaoUserBarangay::create([
            'user_id' => $scopedStaff->id,
            'barangay' => 'Balibago',
        ]);

        foreach ([$maoHead, $scopedStaff] as $staff) {
            $this->actingAs($staff)
                ->get(route('mao.farmer-approvals.index'))
                ->assertOk()
                ->assertSee('No Barangay Pending');

            $this->actingAs($staff)
                ->get(route('mao.farmer-approvals.show', $pendingFarmer))
                ->assertOk()
                ->assertSee('Barangay: not set yet');
        }
    }

    public function test_incomplete_farmer_sees_banner_prompts_single_notification_and_can_dismiss_for_session(): void
    {
        $farmer = User::factory()->farmer()->create([
            'status' => User::STATUS_APPROVED,
            'barangay' => null,
            'contact_number' => null,
        ]);
        FarmerProfile::create([
            'user_id' => $farmer->id,
            'full_name' => $farmer->name,
            'barangay' => null,
            'farm_type' => null,
        ]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('Complete your profile to get weather advice and yield estimates for your farm')
            ->assertSee(route('farmer.complete-profile.show'));

        // Notification is created once and not repeated.
        app(FarmerMissingInfoNotifier::class)->sync($farmer->fresh());
        $this->assertSame(1, Notification::query()->where('user_id', $farmer->id)->where('category', 'setup:complete_profile')->count());

        // Also appears on my-farm; weather-predictions redirects farmers to dashboard.
        $this->actingAs($farmer)
            ->get(route('weather-predictions.index'))
            ->assertRedirect(route('farmer.dashboard'));

        $this->actingAs($farmer)
            ->get(route('farmer.my-farm'))
            ->assertOk()
            ->assertSee('Complete your profile to get weather advice and yield estimates for your farm');

        // Dismiss for this session.
        $this->actingAs($farmer)
            ->from(route('farmer.dashboard'))
            ->post(route('farmer.complete-profile.dismiss'))
            ->assertRedirect(route('farmer.dashboard'));

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertDontSee('data-complete-profile-banner', false);

        // Next login (fresh session) brings the banner back.
        $this->flushSession();
        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('data-complete-profile-banner', false);
    }

    public function test_completing_profile_saves_details_creates_farm_and_clears_banner_and_notification(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        $org = FarmerOrganization::query()->where('barangay', 'Balibago')->firstOrFail();

        $farmer = User::factory()->farmer()->create([
            'status' => User::STATUS_APPROVED,
            'barangay' => null,
            'contact_number' => null,
        ]);
        FarmerProfile::create([
            'user_id' => $farmer->id,
            'full_name' => $farmer->name,
            'barangay' => null,
            'farm_type' => null,
        ]);

        app(FarmerMissingInfoNotifier::class)->sync($farmer);
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'category' => 'setup:complete_profile']);

        $this->actingAs($farmer)
            ->post(route('farmer.complete-profile.store'), [
                'barangay' => 'Balibago',
                'association_choice' => (string) $org->id,
                'contact_number' => '09171234567',
                'farm_name' => 'Bukid Uno',
                'farm_type' => 'Irrigated',
                'farm_size_hectares' => '1.50',
            ])
            ->assertRedirect(route('farmer.dashboard'))
            ->assertSessionHasNoErrors();

        $farmer->refresh();
        $this->assertSame('Balibago', $farmer->barangay);
        $this->assertSame('09171234567', $farmer->contact_number);
        $this->assertDatabaseHas('farms', [
            'user_id' => $farmer->id,
            'farm_name' => 'Bukid Uno',
            'barangay' => 'Balibago',
            'farmer_organization_id' => $org->id,
            'association_status' => 'member',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $farmer->id, 'category' => 'setup:complete_profile']);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertDontSee('data-complete-profile-banner', false);
    }
}
