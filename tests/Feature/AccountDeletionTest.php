<?php

namespace Tests\Feature;

use App\Mail\AccountStatusMail;
use App\Models\AccountEmail;
use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Accounts\AccountDeletion;
use App\Services\Accounts\AccountEmails;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * IT deletes (closes) a user account with a stated reason; the person is told why when they sign in with the right
 * password, and is emailed at their own registered address. Restore within 30 days. Emails on approval, rejection,
 * deletion and restore.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->it = User::factory()->itExpert()->create(['name' => 'Ivy IT', 'email' => 'ivy.it@lian.gov.ph']);
        User::factory()->itExpert()->create(['name' => 'Second IT', 'email' => 'second.it@lian.gov.ph']);   // so Ivy is not the last IT
    }

    private function farmer(array $attributes = []): User
    {
        return User::factory()->farmer()->create($attributes + ['name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'barangay' => 'Prenza']);
    }

    /** @param  array<string, string>  $more */
    private function deleteAccount(User $target, string $reason = 'moved_out', array $more = [], ?User $actor = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor ?? $this->it)->delete(route('users.destroy', $target->id), $more + [
            'reason' => $reason, 'confirm_name' => $target->name,
        ]);
    }

    private function fresh(User $user): User
    {
        return User::withDeleted()->findOrFail($user->id);
    }

    private function login(User $user, string $password = 'password'): \Illuminate\Testing\TestResponse
    {
        auth()->logout();

        return $this->from('/')->post(route('login'), ['email' => $user->email, 'password' => $password]);
    }

    // ------------------------------------------------------------------ deleting

    public function test_it_deletes_an_account_with_a_reason_and_it_is_closed_not_erased(): void
    {
        $farmer = $this->farmer();
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        $open = FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'planting_date' => '2026-07-01', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza']);
        $harvested = FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'planting_date' => '2026-01-01', 'season' => 'Dry', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'harvested_on' => '2026-04-20', 'progress_status' => 'Harvested', 'actual_yield' => 4.2]);

        $this->deleteAccount($farmer, 'moved_out', ['note_internal' => 'Called the office on Oct 30'])
            ->assertRedirect(route('users.index'))->assertSessionHas('success', 'Juan Dela Cruz\'s account was deleted.');

        $closed = $this->fresh($farmer);
        $this->assertSame([User::STATUS_DELETED, 'moved_out', 'Called the office on Oct 30', $this->it->id, User::STATUS_ACTIVE],
            [$closed->status, $closed->deleted_reason, $closed->deleted_note_internal, (int) $closed->deleted_by, $closed->status_before_delete]);
        $this->assertNotNull($closed->deleted_at);
        $this->assertNotNull($farm->fresh()->archived_at);
        $this->assertSame(AccountDeletion::PLAN_STATUS, FarmerPlantingSchedule::query()->withoutGlobalScopes()->find($open->id)->progress_status);
        $this->assertNull(FarmerPlantingSchedule::query()->find($open->id));   // the closed plan leaves every list of running plans
        $this->assertSame('Harvested', $harvested->fresh()->progress_status);   // history stays as it was
        $this->assertSame(4.2, (float) $harvested->fresh()->actual_yield);

        $log = SystemLog::query()->where('action', 'Deleted User Account')->sole();
        $this->assertSame($this->it->id, (int) $log->user_id);
        $context = json_decode((string) $log->details, true)['context'];
        $this->assertSame(['User', $farmer->id, 'Juan Dela Cruz', 'moved_out'], [$context['record_type'], $context['record_id'], $context['record_label'], $context['reason']]);
    }

    public function test_a_reason_is_required_other_needs_ten_characters_and_the_name_must_match(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($this->it)->delete(route('users.destroy', $farmer->id), ['confirm_name' => 'Juan Dela Cruz'])->assertSessionHasErrors('reason');
        $this->deleteAccount($farmer, 'not_a_reason')->assertSessionHasErrors('reason');
        $this->deleteAccount($farmer, 'other')->assertSessionHasErrors('reason_other');
        $this->deleteAccount($farmer, 'other', ['reason_other' => 'too short'])->assertSessionHasErrors('reason_other');
        $this->deleteAccount($farmer, 'moved_out', ['confirm_name' => 'juan'])->assertSessionHasErrors('confirm_name');
        $this->assertSame(User::STATUS_ACTIVE, $this->fresh($farmer)->status);

        // Text left in "Other" does not count against another reason.
        $this->deleteAccount($farmer, 'moved_out', ['reason_other' => 'short', 'confirm_name' => 'nobody'])->assertSessionHasErrors('confirm_name')->assertSessionDoesntHaveErrors('reason_other');
        $this->deleteAccount($farmer, 'other', ['reason_other' => 'Asked at the barangay assembly'])->assertSessionHasNoErrors();
        $this->assertSame('Asked at the barangay assembly', $this->fresh($farmer)->deleted_reason_other);
    }

    public function test_only_it_can_delete_and_never_themselves_or_the_last_it_account(): void
    {
        $farmer = $this->farmer();
        foreach ([User::factory()->farmer()->create(), User::factory()->maoPersonnel()->create()] as $user) {
            $this->deleteAccount($farmer, 'moved_out', [], $user)->assertForbidden();
        }

        $this->deleteAccount($this->it)->assertSessionHas('error', 'You cannot delete your own account. Ask another IT Personnel to do it.');
        $this->assertSame(User::STATUS_ACTIVE, $this->fresh($this->it)->status);

        // The last active IT account: only that account itself could try (an inactive IT is signed out first), and the
        // rule still holds on its own.
        User::query()->where('email', 'second.it@lian.gov.ph')->sole()->forceFill(['status' => User::STATUS_INACTIVE])->save();
        $this->assertNull(AccountDeletion::refusal($farmer, $this->it));
        $this->assertSame('You cannot delete the last active IT Personnel account. Create another IT Personnel account first.',
            AccountDeletion::refusal($this->it, User::factory()->itExpert()->make(['id' => 999999])));
    }

    public function test_the_users_list_has_the_delete_dialog_with_the_recipient_line_and_an_mao_head_warning(): void
    {
        $this->farmer();
        User::factory()->maoPersonnel()->create(['name' => 'Ofelia Head', 'email' => 'ofelia@lian.gov.ph', 'is_mao_head' => true]);
        User::factory()->farmer()->create(['name' => 'Test Farmer', 'email' => 'test@iclimate.test']);

        $html = $this->actingAs($this->it)->get(route('users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-user-delete-dialog', $html);
        $this->assertStringContainsString('Only IT can see this', $html);
        $this->assertStringContainsString('&quot;recipient&quot;:&quot;juan.delacruz@gmail.com&quot;', $html);
        $this->assertStringContainsString('&quot;recipient&quot;:null', $html);   // the @iclimate.test account
        $this->assertStringContainsString('&quot;maoHead&quot;:true', $html);
        // A name with an apostrophe stays inside the attribute.
        User::factory()->farmer()->create(['name' => "Rosa D'Souza", 'email' => 'rosa@gmail.com']);
        $this->actingAs($this->it)->get(route('users.index'))->assertSee('&quot;name&quot;:&quot;Rosa D&#039;Souza&quot;', false);
        $this->assertStringContainsString('Email will be sent to :email', $html);
        $this->assertStringContainsString('No email will be sent (no valid email on file)', $html);
        $this->assertStringNotContainsString('data-ic-confirm="Delete this record?', $html);   // no browser-style confirm for users
    }

    // ------------------------------------------------------------------ hidden everywhere, except the Deleted filter

    public function test_deleted_users_are_hidden_from_lists_and_counts_but_show_under_the_deleted_filter(): void
    {
        $farmer = $this->farmer();
        $profile = FarmerProfile::query()->where('user_id', $farmer->id)->first()
            ?? FarmerProfile::factory()->create(['user_id' => $farmer->id, 'full_name' => 'Juan Dela Cruz', 'barangay' => 'Prenza']);
        $gone = $this->farmer(['name' => 'Pedro Penduko', 'email' => 'pedro@gmail.com']);
        $this->deleteAccount($gone, 'deceased');
        $before = User::query()->where('role', User::ROLE_FARMER)->count();

        $this->assertSame(1, $before);
        $this->assertNull(User::query()->find($gone->id));
        $this->assertNotNull(User::withDeleted()->find($gone->id));
        $this->assertTrue(FarmerProfile::query()->whereKey($profile->id)->exists());

        $list = fn (array $q) => collect($this->actingAs($this->it)->get(route('users.index', $q))->viewData('records')->items())->pluck('name')->all();
        $this->assertNotContains('Pedro Penduko', $list([]));
        $this->assertContains('Juan Dela Cruz', $list([]));
        $this->assertSame(['Pedro Penduko'], $list(['status' => User::STATUS_DELETED]));
        // Deceased accounts are labelled so in the Deleted filter.
        $this->actingAs($this->it)->get(route('users.index', ['status' => User::STATUS_DELETED]))->assertSee('Deceased');

        // A deleted farmer's profile leaves the Farmers list too.
        FarmerProfile::factory()->create(['user_id' => $gone->id, 'full_name' => 'Pedro Penduko', 'barangay' => 'Prenza']);
        $this->assertSame(0, FarmerProfile::query()->where('user_id', $gone->id)->count());
        $mao = User::factory()->maoPersonnel()->create();
        $this->actingAs($mao)->get(route('farmer-profiles.index'))->assertOk()->assertDontSee('Pedro Penduko');

        // Their page is still open to IT, with the record, but no Edit.
        $this->actingAs($this->it)->get(route('users.show', $gone->id))->assertOk()->assertSee('Deleted')->assertDontSee(route('users.edit', $gone->id));
        $this->actingAs($this->it)->get(route('users.edit', $gone->id))->assertRedirect(route('users.show', $gone->id));
    }

    // ------------------------------------------------------------------ the deleted person

    public function test_a_deleted_user_cannot_log_in_use_the_app_or_reset_their_password_and_is_signed_out(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        $this->deleteAccount($farmer);
        // The open session ends on the next request: the signed-in user lookup no longer finds the account.
        auth()->forgetGuards();
        $this->app['auth']->guard('web')->setUser($this->fresh($farmer));
        $this->get(route('farmer.dashboard'))->assertRedirect();
        $this->assertNull(app(\App\Auth\CachedEloquentUserProvider::class, ['hasher' => app('hash'), 'model' => User::class])->retrieveById($farmer->id));

        $this->login($farmer)->assertRedirect(route('account.deleted'));
        $this->assertGuest();

        $this->postJson('/api/mobile/login', ['email' => $farmer->email, 'password' => 'password'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This account is no longer active. Please contact the Municipal Agriculture Office.');

        Notification::fake();
        $this->post('/forgot-password', ['email' => $farmer->email]);
        Notification::assertNotSentTo($this->fresh($farmer), ResetPassword::class);
    }

    public function test_the_right_password_shows_the_reason_panel_in_the_persons_language_and_a_wrong_one_does_not(): void
    {
        $farmer = $this->farmer();
        $this->deleteAccount($farmer, 'other', ['reason_other' => 'Your RSBSA record lists another farm owner.', 'note_internal' => 'SECRET-NOTE-123']);

        $this->login($farmer, 'wrong-password')->assertRedirect('/')->assertSessionHasErrors('email');
        $this->get(route('account.deleted'))->assertRedirect('/');   // nothing to show without the right password

        $this->login($farmer)->assertRedirect(route('account.deleted'));
        $this->get(route('account.deleted'))->assertOk()
            ->assertSee('Your iClimate account was deleted')
            ->assertSee('Your account was closed by the Municipal Agriculture Office.')
            ->assertSee('Your RSBSA record lists another farm owner.')
            ->assertSee('If you think this is a mistake, please visit or contact the Municipal Agriculture Office of Lian.')
            ->assertSee(\App\Support\MaoContact::EMAIL)
            ->assertSee('Back to home')
            ->assertDontSee('SECRET-NOTE-123')
            ->assertDontSee('Ivy IT');

        // In Filipino for a farmer who reads Filipino.
        $maria = $this->farmer(['name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'preferred_language' => 'fil']);
        $this->deleteAccount($maria, 'moved_out');
        $this->login($maria)->assertRedirect(route('account.deleted'));
        $this->get(route('account.deleted'))->assertOk()
            ->assertSee('Binura na ang inyong iClimate account')
            ->assertSee('Isinara ang inyong account dahil lumipat na kayo palabas ng Lian.')
            ->assertSee('Bumalik sa home');
    }

    public function test_deceased_shows_only_a_neutral_message_and_sends_no_email(): void
    {
        $farmer = $this->farmer();
        $this->deleteAccount($farmer, 'deceased');

        $this->login($farmer)->assertRedirect('/')
            ->assertSessionHasErrors(['email' => 'This account is no longer active. Please contact the Municipal Agriculture Office.']);
        $this->get(route('account.deleted'))->assertRedirect('/');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    // ------------------------------------------------------------------ restore

    public function test_restore_works_within_thirty_days_and_is_refused_after(): void
    {
        $farmer = $this->farmer(['status' => User::STATUS_INACTIVE]);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        $this->deleteAccount($farmer);

        $this->travel(29)->days();
        $this->actingAs($this->it)->get(route('users.index', ['status' => User::STATUS_DELETED]))->assertSee('Restore account');
        $this->actingAs($this->it)->post(route('users.restore', $farmer->id))->assertSessionHas('success', 'Juan Dela Cruz\'s account was restored. This farmer has 1 archived farm. They can restore it in My Farms.');
        $back = User::query()->findOrFail($farmer->id);
        $this->assertSame([User::STATUS_INACTIVE, null, null], [$back->status, $back->deleted_reason, $back->deleted_at]);   // its status before
        $this->assertNotNull($farm->fresh()->archived_at);   // farms stay archived
        $this->assertTrue(SystemLog::query()->where('action', 'Restored User Account')->exists());

        $this->deleteAccount($back->fresh());
        $this->travel(31)->days();
        $this->actingAs($this->it)->get(route('users.index', ['status' => User::STATUS_DELETED]))->assertSee('Restore period ended')->assertDontSee('data-user-restore=', false);
        $this->actingAs($this->it)->post(route('users.restore', $farmer->id))->assertSessionHas('error');
        $this->assertSame(User::STATUS_DELETED, $this->fresh($farmer)->status);

        foreach ([User::factory()->farmer()->create(), User::factory()->maoPersonnel()->create()] as $user) {
            $this->actingAs($user)->post(route('users.restore', $farmer->id))->assertForbidden();
        }
    }

    // ------------------------------------------------------------------ emails

    public function test_approving_and_rejecting_email_the_farmer_at_their_own_address(): void
    {
        $mao = User::factory()->maoPersonnel()->create(['email' => 'mao.staff@lian.gov.ph']);
        $waiting = $this->farmer(['status' => User::STATUS_PENDING]);
        $other = $this->farmer(['name' => 'Ana Reyes', 'email' => 'ana.reyes@yahoo.com', 'status' => User::STATUS_PENDING, 'preferred_language' => 'fil']);

        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $waiting))->assertRedirect();
        Mail::assertSent(AccountStatusMail::class, fn (AccountStatusMail $mail) => $mail->type === 'approved'
            && $mail->hasTo('juan.delacruz@gmail.com') && ! $mail->hasTo('mao.staff@lian.gov.ph')
            && $mail->envelope()->subject === 'Your iClimate account is approved');

        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $other), [])->assertSessionHasErrors('reason');
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $other), ['reason' => 'rsbsa_mismatch', 'note' => 'Hindi tugma sa listahan ng 2025.'])->assertRedirect();
        Mail::assertSent(AccountStatusMail::class, function (AccountStatusMail $mail) {
            if ($mail->type !== 'rejected' || ! $mail->hasTo('ana.reyes@yahoo.com')) {
                return false;
            }
            $html = $mail->render();

            return $mail->envelope()->subject === 'Hindi naaprubahan ang inyong iClimate account'
                && str_contains($html, 'Hindi tugma ang RSBSA number') && str_contains($html, 'Hindi tugma sa listahan ng 2025.')
                && str_contains($html, \App\Support\MaoContact::EMAIL);
        });
        $this->assertSame('sent', AccountEmails::last($other)->status);

        // IT activating a waiting account from the Users page sends it too.
        $third = $this->farmer(['name' => 'Rico Blanco', 'email' => 'rico@gmail.com', 'status' => User::STATUS_PENDING]);
        $third->forceFill(['status' => User::STATUS_ACTIVE])->save();
        Mail::assertSent(AccountStatusMail::class, fn (AccountStatusMail $mail) => $mail->type === 'approved' && $mail->hasTo('rico@gmail.com'));
    }

    public function test_delete_and_restore_emails_carry_the_reason_but_never_the_note_or_the_staff_name(): void
    {
        $farmer = $this->farmer(['preferred_language' => 'fil']);
        $this->deleteAccount($farmer, 'duplicate', ['note_internal' => 'SECRET-NOTE-123']);

        Mail::assertSent(AccountStatusMail::class, function (AccountStatusMail $mail) {
            $html = $mail->render();

            return $mail->type === 'deleted' && $mail->hasTo('juan.delacruz@gmail.com') && ! $mail->hasTo('ivy.it@lian.gov.ph')
                && $mail->envelope()->subject === 'Binura na ang inyong iClimate account'
                && str_contains($html, 'Isinara ang inyong account dahil may iba pa kayong iClimate account.')
                && str_contains($html, '30 araw')
                && ! str_contains($html, 'SECRET-NOTE-123') && ! str_contains($html, 'Ivy IT');
        });

        $this->actingAs($this->it)->post(route('users.restore', $farmer->id));
        Mail::assertSent(AccountStatusMail::class, fn (AccountStatusMail $mail) => $mail->type === 'restored' && $mail->hasTo('juan.delacruz@gmail.com')
            && $mail->envelope()->subject === 'Naibalik na ang inyong iClimate account');
        Mail::assertSent(AccountStatusMail::class, 2);
    }

    public function test_no_email_goes_to_an_empty_invalid_or_placeholder_address_and_the_page_says_so(): void
    {
        $placeholder = $this->farmer(['name' => 'Test Farmer', 'email' => 'test.farmer@iclimate.test']);
        $this->deleteAccount($placeholder, 'moved_out');
        Mail::assertNothingSent();

        $log = AccountEmails::last($this->fresh($placeholder));
        $this->assertSame([AccountEmail::STATUS_NO_VALID_EMAIL, 'deleted'], [$log->status, $log->type]);
        $this->actingAs($this->it)->get(route('users.show', $placeholder->id))->assertSee('No valid email');

        foreach (['', 'not-an-email', 'someone@example.com', 'x@mail.example.org', 'farmer@localhost'] as $email) {
            $this->assertNull(AccountEmails::recipient(User::factory()->make(['email' => $email])), $email);
        }
        $this->assertSame('juan@gmail.com', AccountEmails::recipient(User::factory()->make(['email' => ' Juan@Gmail.com '])));
    }

    public function test_mail_test_command_sends_one_email(): void
    {
        $this->artisan('mail:test', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('mail:test', ['email' => 'it.check@gmail.com'])->expectsOutputToContain('Test email sent to it.check@gmail.com.')->assertSuccessful();
        Mail::assertSent(\Illuminate\Mail\Mailable::class, fn ($mail) => $mail->hasTo('it.check@gmail.com'));
    }
}
