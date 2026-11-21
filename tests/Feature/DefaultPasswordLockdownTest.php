<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use App\Services\Accounts\AccountRecovery;
use App\Services\Accounts\PasswordChange;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Audit 2026-11-18 P0-3, final form: a shared password (the published default, or a hash copied onto other accounts)
 * proves nothing about who is signing in. It never opens a session and never sets a new password — not on the web,
 * the mobile API, an existing session or Reverb. Ownership is proven by an email link to a working address, or by a
 * one-time code from MAO Staff after they check the person.
 */
class DefaultPasswordLockdownTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT = 'Password@123';

    private const STRONG = 'Rice-Field-2027!';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);
        $this->mock(\App\Services\Prediction\RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
    }

    /** No working email (placeholder domain), like 857 of the 867 affected accounts. */
    private function onDefault(string $role = User::ROLE_FARMER, bool $flagged = false, string $domain = 'iclimate.test'): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => User::STATUS_ACTIVE, 'barangay' => 'Prenza', 'email' => fake()->unique()->userName().'@'.$domain,
            'password' => Hash::make(self::DEFAULT), 'must_change_password' => $flagged,
        ]);
    }

    // ----------------------------------------------------------------- a shared password opens nothing

    public function test_web_sign_in_with_a_shared_password_opens_no_session_and_goes_to_recovery(): void
    {
        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = $this->onDefault($role);
            $this->post('/login', ['email' => $user->email, 'password' => self::DEFAULT])
                ->assertRedirect(route('account.recover'))->assertSessionHas('recovery', AccountRecovery::METHOD_MAO);
            $this->assertGuest();
            $this->assertTrue((bool) $user->fresh()->must_change_password, $role.' is flagged');
        }
        $this->get(route('account.recover'))->assertOk()->assertSee('Municipal Agriculture Office');
    }

    public function test_an_account_with_a_working_email_is_sent_a_reset_link_instead(): void
    {
        Notification::fake();
        $user = $this->onDefault(domain: 'gmail.com');
        $this->post('/login', ['email' => $user->email, 'password' => self::DEFAULT])->assertSessionHas('recovery', AccountRecovery::METHOD_EMAIL);
        $this->assertGuest();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_dot_local_and_placeholder_addresses_never_count_as_working_email(): void
    {
        $this->assertSame(AccountRecovery::METHOD_MAO, AccountRecovery::method($this->onDefault(domain: 'iclimate.local')));
        $this->assertSame(AccountRecovery::METHOD_MAO, AccountRecovery::method($this->onDefault(domain: 'iclimate.test')));
        $this->assertSame(AccountRecovery::METHOD_EMAIL, AccountRecovery::method($this->onDefault(domain: 'gmail.com')));
    }

    public function test_a_session_opened_before_the_lockdown_is_ended_on_every_route(): void
    {
        $user = $this->onDefault(User::ROLE_MAO, flagged: true);
        $this->actingAs($user)->get(route('password.first-change'))->assertRedirect(route('account.recover'));
        $this->assertGuest();
        $this->actingAs($user)->post(route('password.first-change.update'), ['password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertRedirect(route('account.recover'));
        $this->assertTrue(Hash::check(self::DEFAULT, $user->fresh()->password), 'the takeover attempt changed nothing');
        $this->actingAs($user)->getJson('/api/heat-map')->assertStatus(409)->assertJsonPath('recovery_required', true);
        $this->actingAs($user)->withSession([EnsurePasswordChanged::SKIP_KEY => true])->get('/mao/dashboard')->assertRedirect(route('account.recover'));
    }

    public function test_mobile_sign_in_and_mobile_password_change_refuse_a_shared_password(): void
    {
        $user = $this->onDefault();
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => self::DEFAULT])->assertStatus(409)
            ->assertJsonPath('recovery_required', true)->assertJsonPath('recovery_method', 'mao')->assertJsonMissingPath('user');
        $this->postJson('/api/mobile/password', ['email' => $user->email, 'current_password' => self::DEFAULT, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertStatus(409)->assertJsonPath('recovery_required', true);
        $this->assertTrue(Hash::check(self::DEFAULT, $user->fresh()->password), 'an attacker with the default cannot set a password');
    }

    public function test_two_accounts_with_matching_hashes_sign_in_while_flagged_accounts_require_change(): void
    {
        $hash = Hash::make('Some-Imported-Batch-1');
        $first = User::factory()->farmer()->create(['password' => $hash, 'email' => 'batch-a@iclimate.local', 'must_change_password' => false]);
        User::query()->whereKey(User::factory()->farmer()->create()->id)->update(['password' => $hash, 'must_change_password' => false]);
        $flagged = User::factory()->farmer()->create(['password' => Hash::make('Flagged-Mine-2027!'), 'must_change_password' => true]);

        $this->postJson('/api/mobile/login', ['email' => $first->email, 'password' => 'Some-Imported-Batch-1'])->assertOk()->assertJsonPath('user.id', $first->id);
        $this->assertFalse((bool) $first->fresh()->must_change_password);
        $this->postJson('/api/mobile/login', ['email' => $flagged->email, 'password' => 'Flagged-Mine-2027!'])->assertStatus(409)->assertJsonPath('password_change_required', true);
    }

    public function test_reverb_channels_refuse_flagged_accounts(): void
    {
        $user = $this->onDefault(User::ROLE_MAO, flagged: true);
        $channels = Broadcast::connection()->getChannels();
        $this->assertFalse(($channels->get('user.{userId}'))($user, $user->id));
        $this->assertFalse(($channels->get('mao'))($user));
        $user->forceFill(['must_change_password' => false, 'password' => Hash::make(self::STRONG)])->save();
        $this->assertTrue(($channels->get('user.{userId}'))($user->fresh(), $user->id));
    }

    // ----------------------------------------------------------------- MAO-assisted recovery

    public function test_any_mao_staff_member_issues_a_one_time_code_after_checking_the_person(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);
        $farmer = $this->onDefault(flagged: true);
        DB::table('sessions')->insert(['id' => str_repeat('s', 40), 'user_id' => $farmer->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($staff)->post(route('mao.farmer-approvals.recovery-code', $farmer), ['verification_method' => 'in_person'])
            ->assertSessionHasErrors('identity_confirmed');
        $this->actingAs($staff)->post(route('mao.farmer-approvals.recovery-code', $farmer), ['verification_method' => 'phone_callback', 'identity_confirmed' => '1', 'note' => 'Called the registered number']);
        $code = session('recovery_code');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', (string) $code);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $farmer->id)->where('id', str_repeat('s', 40))->count(), 'the farmer\'s open sessions ended');
        $log = DB::table('system_logs')->where('action', 'Issued account recovery code')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString(str_replace('-', '', $code), json_encode($log), 'the code is never logged');

        auth()->logout();
        $this->post('/reset-password', ['token' => strtolower($code), 'email' => $farmer->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::STRONG, $farmer->fresh()->password));
        $this->assertFalse((bool) $farmer->fresh()->must_change_password);
        $this->post('/login', ['email' => $farmer->email, 'password' => self::STRONG])->assertRedirect(route('farmer.dashboard', absolute: false));

        auth()->logout();
        $this->post('/reset-password', ['token' => $code, 'email' => $farmer->email, 'password' => 'Another-Pass-2027!', 'password_confirmation' => 'Another-Pass-2027!'])
            ->assertSessionHasErrors('email');   // used once
    }

    public function test_recovery_codes_expire_and_cannot_be_issued_for_staff_or_by_farmers(): void
    {
        $staff = User::factory()->maoPersonnel()->create();
        $farmer = $this->onDefault(flagged: true);
        $this->actingAs($staff)->post(route('mao.farmer-approvals.recovery-code', $farmer), ['verification_method' => 'in_person', 'identity_confirmed' => '1']);
        $code = session('recovery_code');
        auth()->logout();
        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();
        $this->post('/reset-password', ['token' => $code, 'email' => $farmer->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertSessionHasErrors('email');
        $this->travelBack();

        $otherStaff = User::factory()->maoPersonnel()->create();
        $this->actingAs($staff)->post(route('mao.farmer-approvals.recovery-code', $otherStaff), ['verification_method' => 'in_person', 'identity_confirmed' => '1'])->assertNotFound();
        $other = User::factory()->farmer()->create();
        $this->actingAs($other)->post(route('mao.farmer-approvals.recovery-code', $farmer), ['verification_method' => 'in_person', 'identity_confirmed' => '1'])->assertForbidden();
    }

    // ----------------------------------------------------------------- unique passwords keep working

    public function test_a_unique_one_time_password_from_mao_still_uses_the_change_page(): void
    {
        $user = User::factory()->farmer()->create(['password' => Hash::make('Temp-Unique-88!'), 'must_change_password' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Temp-Unique-88!']);
        $this->assertAuthenticatedAs($user);
        $this->get('/farmer/dashboard')->assertRedirect(route('password.first-change'));
        foreach ([self::DEFAULT, 'short1A'] as $bad) {
            $this->post(route('password.first-change.update'), ['password' => $bad, 'password_confirmation' => $bad])->assertSessionHasErrors('password');
        }
        $this->post(route('password.first-change.update'), ['password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertRedirect(route($user->dashboardRoute()));
        $this->assertFalse((bool) $user->fresh()->must_change_password);
        $this->assertSame([$user->role, $user->status, $user->email], [$user->fresh()->role, $user->fresh()->status, $user->fresh()->email]);

        $mobile = User::factory()->farmer()->create(['password' => Hash::make('Temp-Unique-99!'), 'must_change_password' => true]);
        $this->postJson('/api/mobile/login', ['email' => $mobile->email, 'password' => 'Temp-Unique-99!'])->assertStatus(409)->assertJsonPath('password_change_required', true);
        $this->postJson('/api/mobile/password', ['email' => $mobile->email, 'current_password' => 'Temp-Unique-99!', 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertOk();
        $this->postJson('/api/mobile/login', ['email' => $mobile->email, 'password' => self::STRONG])->assertOk();
    }

    public function test_a_password_change_ends_the_accounts_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->onDefault(flagged: true);
        $other = User::factory()->create();
        [$keep, $stolen, $elsewhere] = [str_repeat('k', 40), str_repeat('s', 40), str_repeat('e', 40)];
        foreach ([[$keep, $user->id], [$stolen, $user->id], [$elsewhere, $other->id]] as [$id, $owner]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner, 'payload' => '', 'last_activity' => time()]);
        }
        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession($session = app('session')->driver());
        $session->setId($keep);

        PasswordChange::apply($user, self::STRONG, $request);

        $this->assertEqualsCanonicalizing([$keep, $elsewhere], DB::table('sessions')->pluck('id')->all());
    }

    public function test_rate_limits_and_pending_accounts(): void
    {
        $pending = User::factory()->farmer()->create(['status' => User::STATUS_PENDING, 'password' => Hash::make('Unique-Pend-1!')]);
        $this->postJson('/api/mobile/password', ['email' => $pending->email, 'current_password' => 'Unique-Pend-1!', 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertUnprocessable();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/mobile/password', ['email' => 'nobody@lian-farmers.ph', 'current_password' => 'x', 'password' => self::STRONG, 'password_confirmation' => self::STRONG]);
        }
        $this->postJson('/api/mobile/password', ['email' => 'nobody@lian-farmers.ph', 'current_password' => 'x', 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertStatus(429);
    }

    public function test_mobile_forgot_password_sends_an_expiring_link_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = $this->onDefault(domain: 'gmail.com');
        $placeholder = $this->onDefault();
        $unknown = $this->postJson('/api/mobile/forgot-password', ['email' => 'not-registered@gmail.com'])->assertOk()->json('message');
        $this->assertSame($unknown, $this->postJson('/api/mobile/forgot-password', ['email' => $user->email])->assertOk()->json('message'));
        $this->postJson('/api/mobile/forgot-password', ['email' => $placeholder->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($placeholder, ResetPassword::class);
        $this->assertSame(60, (int) config('auth.passwords.users.expire'));
    }

    public function test_reset_link_refuses_the_default_and_clears_the_flag(): void
    {
        $user = $this->onDefault(flagged: true, domain: 'gmail.com');
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => self::DEFAULT, 'password_confirmation' => self::DEFAULT])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $user->fresh()->must_change_password);
    }

    public function test_new_accounts_cannot_choose_the_default(): void
    {
        $this->postJson('/api/mobile/register', [
            'name' => 'New farmer', 'email' => 'new-farmer@lian-farmers.ph', 'password' => self::DEFAULT, 'password_confirmation' => self::DEFAULT,
            'contact_number' => '09170000000', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_with_the_switch_off_a_shared_password_still_never_works_from_the_network(): void
    {
        config(['iclimate.password_lockdown' => false]);
        $user = $this->onDefault();
        $remote = ['REMOTE_ADDR' => '203.0.113.7'];

        $this->withServerVariables($remote)->post('/login', ['email' => $user->email, 'password' => self::DEFAULT])->assertRedirect(route('account.recover'));
        $this->assertGuest();
        $this->withServerVariables($remote)->postJson('/api/mobile/login', ['email' => $user->email, 'password' => self::DEFAULT])
            ->assertStatus(409)->assertJsonPath('recovery_required', true)->assertJsonMissingPath('user');
        $this->withServerVariables($remote)->postJson('/api/mobile/password', ['email' => $user->email, 'current_password' => self::DEFAULT, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertStatus(409);
        $this->withServerVariables($remote + ['HTTP_X_FORWARDED_FOR' => '127.0.0.1'])->post('/login', ['email' => $user->email, 'password' => self::DEFAULT])
            ->assertRedirect(route('account.recover'));   // a forged forwarding header does not count as this machine
        $this->assertFalse((bool) $user->fresh()->must_change_password, 'with the switch off no account is changed');
        $this->assertTrue(Hash::check(self::DEFAULT, $user->fresh()->password));
    }

    public function test_the_owner_is_told_when_a_recovery_code_is_issued(): void
    {
        $staff = User::factory()->maoPersonnel()->create(['name' => 'Staff Reyes']);
        $farmer = $this->onDefault(flagged: true);
        $this->actingAs($staff)->post(route('mao.farmer-approvals.recovery-code', $farmer), ['verification_method' => 'in_person', 'identity_confirmed' => '1']);
        $notice = \App\Models\Notification::query()->where('user_id', $farmer->id)->where('title', 'Account recovery code issued')->sole();
        $this->assertStringContainsString('Staff Reyes', $notice->message);
        $this->assertStringNotContainsString(str_replace('-', '', (string) session('recovery_code')), $notice->message);
    }

    public function test_with_the_rollout_switch_off_nothing_is_flagged_and_the_app_keeps_working(): void
    {
        config(['iclimate.password_lockdown' => false]);
        $user = $this->onDefault();
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => self::DEFAULT])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->post('/login', ['email' => $user->email, 'password' => self::DEFAULT]);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse((bool) $user->fresh()->must_change_password, 'no account is changed until the rollout is approved');
    }

    public function test_two_normal_accounts_can_independently_use_identical_passwords_without_recovery_redirect(): void
    {
        $sharedPlain = 'MyOwnHarvest#2027!';
        $identicalHash = Hash::make($sharedPlain);

        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Prenza',
            'email' => 'farmer-same@gmail.com',
            'password' => $identicalHash,
            'must_change_password' => false,
        ]);
        $mao = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Prenza',
            'email' => 'mao-same@gmail.com',
            'password' => $identicalHash,
            'must_change_password' => false,
        ]);

        $this->post('/login', ['email' => $farmer->email, 'password' => $sharedPlain])
            ->assertRedirect(route('farmer.dashboard', absolute: false));
        $this->assertAuthenticatedAs($farmer);
        $this->get(route('farmer.dashboard'))->assertOk();
        $this->post('/logout');

        $this->post('/login', ['email' => $mao->email, 'password' => $sharedPlain])
            ->assertRedirect(route('mao.dashboard', absolute: false));
        $this->assertAuthenticatedAs($mao);
        $this->get(route('mao.dashboard'))->assertOk();
    }
}
