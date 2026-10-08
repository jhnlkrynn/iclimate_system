<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementBarangayTest extends TestCase
{
    use RefreshDatabase;

    private function itExpert(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_IT_EXPERT,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function test_create_user_shows_the_lian_barangay_select_and_persists_the_selected_value(): void
    {
        $admin = $this->itExpert();

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertSee('<option value="">Select Barangay</option>', false)
            ->assertSee('value="Barangay 1 (Pob.)"', false)
            ->assertSee('>Barangay 1</option>', false)
            ->assertSee('<select class="form-select', false);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Created Lian User',
            'email' => 'created-lian-user@example.com',
            'password' => 'Password123!',
            'role' => User::ROLE_FARMER,
            'contact_number' => '',
            'address' => '',
            'barangay' => 'Matabungkay',
            'status' => User::STATUS_ACTIVE,
        ])->assertRedirect();

        $created = User::query()->where('email', 'created-lian-user@example.com')->firstOrFail();
        $this->assertSame('Matabungkay', $created->fresh()->barangay);
    }

    public function test_edit_user_preselects_and_persists_a_changed_barangay(): void
    {
        $admin = $this->itExpert();
        $user = User::factory()->create(['barangay' => 'Binubusan']);

        $this->actingAs($admin)->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('<option value="Binubusan" selected>Binubusan</option>', false);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'role' => $user->role,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'barangay' => 'San Diego',
            'status' => $user->status,
        ])->assertRedirect();

        $this->assertSame('San Diego', $user->fresh()->barangay);
    }

    public function test_invalid_barangay_is_rejected_and_old_value_is_retained(): void
    {
        $admin = $this->itExpert();

        $response = $this->actingAs($admin)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Invalid Lian User',
            'email' => 'invalid-lian-user@example.com',
            'password' => 'Password123!',
            'role' => User::ROLE_FARMER,
            'barangay' => 'Not A Lian Barangay',
            'status' => User::STATUS_ACTIVE,
        ]);

        $response->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('barangay')
            ->assertSessionHasInput('barangay', 'Not A Lian Barangay');
    }
}
