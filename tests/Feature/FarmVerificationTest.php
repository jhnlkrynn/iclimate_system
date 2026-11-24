<?php

namespace Tests\Feature;

use App\Mail\FarmVerificationMail;
use App\Models\Farm;
use App\Models\MaoUserBarangay;
use App\Models\Notification;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FarmVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_account_first_farm_is_pending_and_shown_on_account_approval_screen(): void
    {
        $pendingFarmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_PENDING,
            'barangay' => 'Binubusan',
        ]);

        // Simulate first farm added via FarmService (same path as Complete Profile)
        $farm = app(\App\Services\Farms\FarmService::class)->create($pendingFarmer, [
            'farm_name' => 'First Farm',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_method' => Farm::SIZE_EXACT,
            'farm_size_value' => 1.5,
            'farm_size_unit' => Farm::UNIT_HECTARES,
        ]);

        $this->assertSame(Farm::VERIFICATION_PENDING, $farm->verification_status);

        $maoHead = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        $response = $this->actingAs($maoHead)->get(route('mao.farmer-approvals.show', $pendingFarmer));
        $response->assertOk();
        $response->assertSee('First Farm');
        $response->assertSee('Pending MAO review');
        $response->assertSee('Approve Farm');
    }

    public function test_existing_account_adding_farm_is_pending_and_shows_waiting_note_on_dashboard(): void
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Binubusan',
            'contact_number' => '09171234567',
        ]);

        $maoStaff = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Agricultural Technologist',
        ]);
        MaoUserBarangay::query()->create(['user_id' => $maoStaff->id, 'barangay' => 'Binubusan']);
        User::forgetMaoPersonnel();

        $this->actingAs($farmer)->post(route('farmer.farms.store'), [
            'farm_name' => 'Bukid Dos',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_method' => Farm::SIZE_EXACT,
            'farm_size_value' => 2.0,
            'farm_size_unit' => Farm::UNIT_HECTARES,
            'association_status' => Farm::ASSOCIATION_NOT_MEMBER,
        ])->assertRedirect(route('farmer.my-farm'));

        $farm = Farm::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertSame(Farm::VERIFICATION_PENDING, $farm->verification_status);

        // Farmer dashboard shows waiting note and no yield estimate
        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Your farm is waiting for MAO review.');

        // My Farm shows Pending MAO review badge
        $myFarm = $this->actingAs($farmer)->get(route('farmer.my-farm'));
        $myFarm->assertOk();
        $myFarm->assertSee('Pending MAO review');

        // Farmer and barangay's MAO staff both got in-app notifications
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'title' => 'Farm sent for MAO review',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $maoStaff->id,
            'title' => 'New farm pending verification',
        ]);
    }

    public function test_mao_staff_outside_barangay_cannot_act_on_farm(): void
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Binubusan',
        ]);

        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Binubusan Farm',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 1.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
            'verification_status' => Farm::VERIFICATION_PENDING,
        ]);

        $cumbaStaff = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Agricultural Technologist',
        ]);
        MaoUserBarangay::query()->create(['user_id' => $cumbaStaff->id, 'barangay' => 'Cumba']);
        User::forgetMaoPersonnel();

        $queue = $this->actingAs($cumbaStaff)->get(route('mao.farm-verification.index'));
        $queue->assertOk();
        $queue->assertDontSee('Binubusan Farm');

        $this->actingAs($cumbaStaff)
            ->post(route('mao.farm-verification.approve', $farm))
            ->assertForbidden();

        $this->actingAs($cumbaStaff)
            ->post(route('mao.farm-verification.reject', $farm), ['reason' => 'Wrong barangay'])
            ->assertForbidden();
    }

    public function test_approve_and_reject_notify_farmer_via_in_app_and_email_and_write_audit_log(): void
    {
        Mail::fake();

        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'email' => 'farmer.lian@gmail.com',
            'barangay' => 'Binubusan',
        ]);

        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'San Diego Plot',
            'barangay' => 'Binubusan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 1.2,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
            'verification_status' => Farm::VERIFICATION_PENDING,
        ]);

        $maoHead = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        // Reject without reason fails validation
        $this->actingAs($maoHead)
            ->post(route('mao.farm-verification.reject', $farm), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // Reject with reason
        $this->actingAs($maoHead)
            ->post(route('mao.farm-verification.reject', $farm), ['reason' => 'Area does not match map'])
            ->assertRedirect();

        $farm->refresh();
        $this->assertSame(Farm::VERIFICATION_REJECTED, $farm->verification_status);
        $this->assertSame('Area does not match map', $farm->rejection_reason);

        Mail::assertSent(FarmVerificationMail::class, fn (FarmVerificationMail $mail) => $mail->decision === Farm::VERIFICATION_REJECTED);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'title' => 'Farm needs updates',
        ]);
        $this->assertDatabaseHas('system_logs', ['action' => 'Rejected Farm']);

        // Approve
        $this->actingAs($maoHead)
            ->post(route('mao.farm-verification.approve', $farm))
            ->assertRedirect();

        $farm->refresh();
        $this->assertSame(Farm::VERIFICATION_APPROVED, $farm->verification_status);
        $this->assertNull($farm->rejection_reason);
        $this->assertSame($maoHead->id, $farm->verified_by);
        $this->assertNotNull($farm->verified_at);

        Mail::assertSent(FarmVerificationMail::class, fn (FarmVerificationMail $mail) => $mail->decision === Farm::VERIFICATION_APPROVED);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'title' => 'Farm approved',
        ]);
        $this->assertDatabaseHas('system_logs', ['action' => 'Approved Farm']);
    }

    public function test_editing_approved_farm_location_or_area_resets_to_pending(): void
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Binubusan',
        ]);

        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Approved Farm',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
            'verification_status' => Farm::VERIFICATION_APPROVED,
            'verified_at' => now()->subDay(),
        ]);

        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm), [
            'farm_name' => 'Approved Farm',
            'barangay' => 'Malaruhatan',
            'farm_type' => 'Irrigated',
            'farm_size_method' => Farm::SIZE_EXACT,
            'farm_size_value' => 3.5,
            'farm_size_unit' => Farm::UNIT_HECTARES,
            'association_status' => Farm::ASSOCIATION_NOT_MEMBER,
        ])->assertRedirect(route('farmer.my-farm'));

        $farm->refresh();
        $this->assertSame(Farm::VERIFICATION_PENDING, $farm->verification_status);
        $this->assertNull($farm->verified_at);
        $this->assertNull($farm->verified_by);
    }
}
