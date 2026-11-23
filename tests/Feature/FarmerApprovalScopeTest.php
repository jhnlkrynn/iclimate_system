<?php

namespace Tests\Feature;

use App\Models\MaoUserBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerApprovalScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_mao_only_sees_assigned_barangay_farmers_and_gets_403_for_others(): void
    {
        $scopedMao = User::factory()->maoPersonnel()->create();
        MaoUserBarangay::query()->create(['user_id' => $scopedMao->id, 'barangay' => 'Prenza']);

        $prenzaPending = User::factory()->farmer()->create(['name' => 'Prenza Pending', 'barangay' => 'Prenza', 'status' => User::STATUS_PENDING]);
        $balibagoPending = User::factory()->farmer()->create(['name' => 'Balibago Pending', 'barangay' => 'Balibago', 'status' => User::STATUS_PENDING]);
        $balibagoActive = User::factory()->farmer()->create(['name' => 'Balibago Active', 'barangay' => 'Balibago', 'status' => User::STATUS_ACTIVE]);

        // Index only lists Prenza, not Balibago.
        $this->actingAs($scopedMao)
            ->get(route('mao.farmer-approvals.index'))
            ->assertOk()
            ->assertSee('Prenza Pending')
            ->assertDontSee('Balibago Pending');

        // Scoped MAO gets 403 for Balibago farmer across show, approve, reject, reconsider, recoveryCode.
        $this->actingAs($scopedMao)->get(route('mao.farmer-approvals.show', $balibagoPending))->assertForbidden();
        $this->actingAs($scopedMao)->post(route('mao.farmer-approvals.approve', $balibagoPending))->assertForbidden();
        $this->actingAs($scopedMao)->post(route('mao.farmer-approvals.reject', $balibagoPending), ['reason' => 'duplicate'])->assertForbidden();
        $this->actingAs($scopedMao)->post(route('mao.farmer-approvals.reconsider', $balibagoPending), ['resubmission_note' => 'Please review again.'])->assertForbidden();
        $this->actingAs($scopedMao)->post(route('mao.farmer-approvals.recovery-code', $balibagoActive), [
            'verification_method' => 'in_person',
            'identity_confirmed' => '1',
        ])->assertForbidden();

        // Scoped MAO can view their own barangay farmer.
        $this->actingAs($scopedMao)->get(route('mao.farmer-approvals.show', $prenzaPending))->assertOk();

        // MAO head and unscoped MAO keep full access.
        $maoHead = User::factory()->maoHead()->create();
        $unscopedMao = User::factory()->maoPersonnel()->create();

        $this->actingAs($maoHead)->get(route('mao.farmer-approvals.show', $balibagoPending))->assertOk();
        $this->actingAs($unscopedMao)->get(route('mao.farmer-approvals.show', $balibagoPending))->assertOk();
    }
}
