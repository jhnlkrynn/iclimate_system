<?php

namespace Tests\Feature;

use App\Models\AssociationChairmanAssignment;
use App\Models\AssociationReportingPeriod;
use App\Models\AssociationYieldReport;
use App\Models\FarmerOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TwoRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_two_roles_are_supported_and_historical_accounts_still_resolve(): void
    {
        $this->assertSame(['farmer', 'mao'], User::ROLES);
        foreach (['Farmer' => 'farmer', 'MAO Personnel' => 'mao', 'Association Chairman' => 'farmer'] as $stored => $role) {
            $user = User::factory()->create(['role' => $stored]);
            $this->assertSame($role, $user->fresh()->role);
            $this->assertTrue(User::query()->where('role', $role)->whereKey($user->id)->exists());
            $this->assertSame($stored, DB::table('users')->where('id', $user->id)->value('role'));
        }
    }

    public function test_former_it_accounts_cannot_log_in_on_web_or_mobile(): void
    {
        foreach (['IT Expert', 'IT Personnel', 'it', 'it_expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->withSession([\App\Services\Security\CaptchaService::attemptsSessionKey('login') => 0]);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
            $this->assertSame($role, $user->fresh()->getRawOriginal('role'));
        }
    }

    public function test_existing_it_sessions_are_rejected_on_web_api_and_private_channels(): void
    {
        $user = User::factory()->create(['role' => 'IT Expert']);
        $this->actingAs($user)->getJson('/api/heat-map')->assertUnauthorized();
        $this->actingAs($user)->get('/profile')->assertRedirect(route('login'));
        $channels = Broadcast::connection()->getChannels();
        $this->assertFalse($channels->has('it'));
        $this->assertFalse(($channels->get('user.{userId}'))($user, $user->id));
        $this->assertFalse(($channels->get('dashboard.{role}'))($user, 'it'));
        $this->assertFalse(($channels->get('barangay.{barangay}'))($user, 'Kapito'));
    }

    public function test_sensitive_routes_are_removed_for_every_supported_role(): void
    {
        foreach (['admin.dashboard', 'admin.backups.restore', 'admin.settings.security', 'model-registry.activate', 'system-health.index', 'system-logs.index', 'ai-admin.index'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
        foreach (User::ROLES as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_mao_head' => true]));
            foreach (['/admin/dashboard', '/admin/backups', '/system-health', '/model-evaluation', '/ai-admin/topics'] as $url) {
                $this->get($url)->assertNotFound();
            }
            $this->postJson('/admin/backups/snapshot.sql/restore')->assertNotFound();
            $this->postJson('/system-health/model-registry/1/activate')->assertNotFound();
        }
    }

    public function test_account_management_requires_mao_head_and_rejects_removed_roles(): void
    {
        $staff = User::factory()->maoPersonnel()->create();
        $this->actingAs($staff)->get('/users')->assertForbidden();
        $farmer = User::factory()->farmer()->create();
        $this->actingAs($farmer)->get('/users')->assertForbidden();
        $head = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $this->actingAs($head)->get('/users')->assertOk()->assertDontSee('IT Personnel');
        $this->post('/users', ['name' => 'Rejected role', 'email' => 'rejected@example.test', 'password' => 'Password@123', 'role' => 'IT Expert', 'status' => User::STATUS_ACTIVE])->assertSessionHasErrors('role');
        $this->post('/users', ['name' => 'Rejected chairman role', 'email' => 'rejected-chair@example.test', 'password' => 'Password@123', 'role' => 'association_chairman', 'status' => User::STATUS_ACTIVE])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'rejected@example.test']);
    }

    public function test_chairman_is_a_farmer_and_can_only_see_and_submit_for_assigned_association(): void
    {
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);
        $this->mock(\App\Services\Prediction\RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
        $farmer = User::factory()->farmer()->create();
        $other = FarmerOrganization::query()->create(['name' => 'Private other association', 'barangay' => 'Kapito']);
        $own = FarmerOrganization::query()->create(['name' => 'Assigned association', 'barangay' => 'Prenza']);
        $period = AssociationReportingPeriod::query()->create(['period_key' => '2026-wet', 'label' => 'Wet season', 'reporting_year' => 2026, 'due_at' => now()->addWeek()]);
        AssociationYieldReport::query()->create(['farmer_organization_id' => $other->id, 'reporting_period_id' => $period->id, 'remarks' => 'Private report']);
        $this->actingAs($farmer)->get('/association-chairman')->assertForbidden();
        AssociationChairmanAssignment::query()->create(['farmer_organization_id' => $own->id, 'user_id' => $farmer->id, 'status' => 'active']);
        $this->assertTrue($farmer->isAssociationChairman());
        $this->assertSame('farmer.dashboard', $farmer->dashboardRoute());
        $this->withoutExceptionHandling();
        $this->get('/association-chairman')->assertOk()->assertSee('Assigned association')->assertDontSee('Private other association')->assertDontSee('Private report');
        $this->post('/association-chairman/reports/'.$period->id, ['action' => 'submit', 'farmer_organization_id' => $other->id, 'remarks' => 'My report'])->assertRedirect(route('association-chairman.dashboard'));
        $this->assertDatabaseHas('association_yield_reports', ['farmer_organization_id' => $own->id, 'submitted_by' => $farmer->id]);
        $this->assertDatabaseHas('association_yield_reports', ['farmer_organization_id' => $other->id, 'remarks' => 'Private report']);
        $this->withExceptionHandling();
        $ownReport = AssociationYieldReport::query()->where('farmer_organization_id', $own->id)->sole();
        $otherReport = AssociationYieldReport::query()->where('farmer_organization_id', $other->id)->sole();
        $this->get('/association-chairman/reports/'.$ownReport->id)->assertOk()->assertSee('My report');
        $this->get('/association-chairman/reports/'.$otherReport->id)->assertNotFound();
    }
    public function test_mao_assigns_a_farmer_and_manages_reporting_periods_without_changing_the_role(): void
    {
        $mao = User::factory()->maoHead()->create();
        $farmer = User::factory()->farmer()->create();
        $association = FarmerOrganization::query()->create(['name' => 'Assigned by MAO', 'barangay' => 'Prenza']);
        $this->actingAs($mao)->get('/mao/association-reports')->assertOk()->assertSee('Assigned by MAO');
        $this->post('/mao/association-reports/associations/'.$association->id.'/assign', ['user_id' => $farmer->id])->assertRedirect();
        $this->assertTrue($farmer->fresh()->isAssociationChairman());
        $this->assertSame(User::ROLE_FARMER, $farmer->fresh()->role);
        $this->post('/mao/association-reports/periods', ['period_key' => '2026-dry', 'label' => 'Dry season', 'reporting_year' => 2026, 'due_at' => now()->addWeek()->toDateTimeString()])->assertRedirect();
        $this->assertDatabaseHas('association_reporting_periods', ['period_key' => '2026-dry']);
        $this->artisan('associations:send-reminders')->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'category' => 'association_reporting']);
        $legacy = User::factory()->create(['role' => 'IT Expert']);
        $this->put('/users/'.$legacy->id, ['role' => User::ROLE_MAO])->assertNotFound();
        $this->assertSame('IT Expert', $legacy->fresh()->getRawOriginal('role'));
        $this->post('/mao/association-reports/associations/'.$association->id.'/assign', ['user_id' => $mao->id])->assertUnprocessable();
    }

}
