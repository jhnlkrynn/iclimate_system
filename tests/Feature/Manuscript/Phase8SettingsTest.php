<?php

namespace Tests\Feature\Manuscript;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\AdvisoryPublishedNotifier;
use App\Services\Climate\WeatherAnalysisService;
use App\Support\ModuleAccess;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 8: System Settings, Security, Maintenance mode and Roles & Permissions. */
class Phase8SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(SystemSettings::path());
        SystemSettings::flush();
    }

    protected function tearDown(): void
    {
        @unlink(SystemSettings::path());
        SystemSettings::flush();
        parent::tearDown();
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    private function general(array $overrides = []): array
    {
        return $overrides + [
            'system_name' => 'iClimate', 'office_name' => 'MAO Lian', 'heavy_rain_month_mm' => 300, 'dry_month_mm' => 50,
            'hot_month_c' => 29, 'maintenance_message' => 'Back soon.', 'notify_farmers_on_publish' => '1', 'farmer_approval' => '1',
        ];
    }

    public function test_it_saves_settings_and_they_are_used(): void
    {
        $it = $this->it();
        $this->actingAs($it)->get(route('admin.settings.index'))->assertOk()->assertSee('data-manuscript-tabs="settings"', false);

        $this->actingAs($it)->post(route('admin.settings.save'), $this->general(['office_name' => 'Municipal Agriculture Office of Lian', 'heavy_rain_month_mm' => 250, 'risk_heavy_rain_mm' => 40]))
            ->assertRedirect(route('admin.settings.index'));

        SystemSettings::flush();
        $this->assertSame('Municipal Agriculture Office of Lian', SystemSettings::get('office_name'));
        $this->assertEquals(250, WeatherAnalysisService::heavyRainMm());
        $this->assertEquals(40, SystemSettings::get('risk_heavy_rain_mm'));
        $this->assertDatabaseHas('system_logs', ['action' => 'Updated System Settings']);
        $this->assertSame('Municipal Agriculture Office of Lian', \App\Http\Controllers\ReportExtrasController::office());
    }

    public function test_switching_off_farmer_alerts_and_approval(): void
    {
        $this->actingAs($this->it())->post(route('admin.settings.save'), $this->general(['notify_farmers_on_publish' => null, 'farmer_approval' => null]));
        SystemSettings::flush();

        $this->assertFalse(SystemSettings::farmerApproval());
        User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);
        $advisory = PlantingAdvisory::query()->create(['title' => 't', 'content' => 'c', 'message' => 'c', 'type' => 'planting', 'advisory_type' => 'planting', 'severity' => 'low', 'status' => 'published', 'posted_by' => $this->it()->id]);
        $this->assertSame(0, app(AdvisoryPublishedNotifier::class)->notify($advisory));
    }

    public function test_maintenance_mode_shows_the_message_to_everyone_but_it(): void
    {
        $it = $this->it();
        $this->actingAs($it)->post(route('admin.settings.save'), $this->general(['maintenance_mode' => '1', 'maintenance_message' => 'Updating the forecast model.']));
        SystemSettings::flush();

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->get(route('mao.dashboard'))->assertStatus(503)->assertSee('Updating the forecast model.');
        $this->actingAs($it)->get(route('admin.dashboard'))->assertOk();
        auth()->logout();
        $this->get(route('login'))->assertOk();
    }

    public function test_security_settings_change_the_password_rule_session_and_lockout(): void
    {
        $this->actingAs($this->it())->post(route('admin.settings.security.save'), [
            'session_timeout_minutes' => 30, 'password_min_length' => 12, 'lockout_attempts' => 4, 'lockout_minutes' => 10,
            'password_mixed_case' => '1', 'password_numbers' => '1',
        ])->assertRedirect(route('admin.settings.security'));
        SystemSettings::flush();

        $rule = Password::default();
        $this->assertTrue(Validator::make(['p' => 'Abcdefghijk1'], ['p' => $rule])->passes());       // no symbol needed now
        $this->assertTrue(Validator::make(['p' => 'Abcdefgh1!'], ['p' => $rule])->fails());          // shorter than 12
        $this->assertSame(4, SystemSettings::get('lockout_attempts'));
        $this->actingAs($this->it())->get(route('admin.settings.security'))->assertOk()->assertSee('data-https-status', false);
    }

    public function test_permissions_close_a_module_but_never_the_it_modules(): void
    {
        $it = $this->it();
        $this->actingAs($it)->get(route('admin.permissions.index'))->assertOk()->assertSee('data-permission-matrix', false);

        // Untick Heat Map for farmers; try to untick User Management for IT.
        $ticked = collect(ModuleAccess::MODULES)->map(fn ($m) => $m[2])->all();
        $ticked['heat_map'] = [User::ROLE_MAO, User::ROLE_IT_EXPERT];
        $ticked['user_management'] = [];
        $this->actingAs($it)->post(route('admin.permissions.save'), ['modules' => $ticked])->assertRedirect();
        SystemSettings::flush();

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($farmer)->get(route('heat-map.index'))->assertRedirect(route('farmer.dashboard'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('heat-map.index'))->assertOk();
        $this->assertContains(User::ROLE_IT_EXPERT, ModuleAccess::allowed('user_management'));
        $this->actingAs($it)->get(route('users.index'))->assertOk();
    }

    public function test_only_it_opens_settings_and_the_it_sidebar_has_settings(): void
    {
        $html = $this->actingAs($this->it())->get(route('admin.dashboard'))->getContent();
        $this->assertStringContainsString('<span class="sidebar-label">Settings</span>', $html);

        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.settings.index'))->assertStatus(403);
            $this->actingAs($user)->post(route('admin.permissions.save'), [])->assertStatus(403);
        }
    }
}
