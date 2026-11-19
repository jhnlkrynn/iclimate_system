<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignMaoHeadCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_database_with_no_head_gets_one_only_with_apply(): void
    {
        $staff = User::factory()->maoPersonnel()->create(['email' => 'Staff.One@lian-mao.ph', 'is_mao_head' => false]);
        $this->assertSame(0, User::query()->where('is_mao_head', true)->count());

        $this->artisan('iclimate:assign-mao-head', ['email' => 'staff.one@lian-mao.ph'])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertFalse($staff->fresh()->isMaoHead(), 'a dry run changes nothing');

        $this->artisan('iclimate:assign-mao-head', ['email' => 'staff.one@lian-mao.ph', '--apply' => true])->assertSuccessful();
        $this->assertTrue($staff->fresh()->isMaoHead());
        $this->actingAs($staff->fresh())->get('/users')->assertOk();
    }

    public function test_the_previous_head_is_replaced_so_there_is_exactly_one(): void
    {
        $old = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $new = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);

        $this->artisan('iclimate:assign-mao-head', ['email' => $new->email, '--apply' => true])->assertSuccessful();

        $this->assertFalse($old->fresh()->isMaoHead());
        $this->assertTrue($new->fresh()->isMaoHead());
        $this->assertSame(1, User::query()->where('is_mao_head', true)->count());
        $this->assertSame([$old->role, $old->status], [$old->fresh()->role, $old->fresh()->status], 'only the head flag moves');
    }

    public function test_the_change_is_audit_logged_and_can_be_reversed_by_assigning_the_previous_head_again(): void
    {
        $old = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $new = User::factory()->maoPersonnel()->create();

        $this->artisan('iclimate:assign-mao-head', ['email' => $new->email, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('system_logs', ['action' => 'Changed MAO Head assignment']);

        $this->artisan('iclimate:assign-mao-head', ['email' => $old->email, '--apply' => true])->assertSuccessful();
        $this->assertTrue($old->fresh()->isMaoHead());
        $this->assertFalse($new->fresh()->isMaoHead());
    }

    public function test_ordinary_mao_staff_cannot_make_themselves_head_through_any_form(): void
    {
        $staff = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);

        $this->actingAs($staff)->patch('/profile', ['name' => $staff->name, 'email' => $staff->email, 'is_mao_head' => 1]);
        $this->actingAs($staff)->put('/mao/personnel/'.$staff->id, ['name' => $staff->name, 'email' => $staff->email, 'status' => 'Active', 'is_mao_head' => 1])->assertForbidden();
        $this->actingAs($staff)->put('/users/'.$staff->id, ['name' => $staff->name, 'email' => $staff->email, 'role' => 'mao', 'status' => 'Active', 'is_mao_head' => 1])->assertForbidden();

        $this->assertFalse($staff->fresh()->isMaoHead());
        $this->actingAs($staff->fresh())->get('/users')->assertForbidden();
        $this->actingAs($staff->fresh())->get('/mao/farmer-approvals')->assertOk();
    }

    public function test_farmers_inactive_staff_and_unknown_emails_are_refused(): void
    {
        $farmer = User::factory()->farmer()->create();
        $inactive = User::factory()->maoPersonnel()->create(['status' => User::STATUS_INACTIVE]);

        foreach ([$farmer->email, $inactive->email, 'nobody@lian-mao.ph'] as $email) {
            $this->artisan('iclimate:assign-mao-head', ['email' => $email, '--apply' => true])->assertFailed();
        }
        $this->assertSame(0, User::query()->where('is_mao_head', true)->count());
    }
}
