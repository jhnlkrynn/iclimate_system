<?php

namespace Tests\Feature;

use App\Mail\AccountStatusMail;
use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\MaoUserBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Rejection reasons (config/user_rejection.php), the notice for signing up again with a closed account's email, and
 * bringing back archived farms (farmer in My Farms, MAO / IT on the farmer page, the hint after an account restore).
 */
class RejectionAndFarmRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function farmer(array $attributes = []): User
    {
        return User::factory()->farmer()->create($attributes + ['name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'barangay' => 'Prenza']);
    }

    // ------------------------------------------------------------------ a. rejection reasons

    public function test_rejecting_needs_a_listed_reason_and_the_note_goes_under_it_in_the_farmers_language(): void
    {
        $mao = User::factory()->maoPersonnel()->create(['email' => 'mao.staff@lian.gov.ph']);
        $farmer = $this->farmer(['status' => User::STATUS_PENDING, 'preferred_language' => 'fil']);
        $reject = fn (array $data) => $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer), $data);

        $this->actingAs($mao)->get(route('mao.farmer-approvals.index'))->assertOk()
            ->assertSee('<option value="rsbsa_mismatch">RSBSA number does not match</option>', false)
            ->assertSee('Other (please specify)');
        $reject([])->assertSessionHasErrors('reason');
        $reject(['reason' => 'Not a real reason'])->assertSessionHasErrors('reason');
        $reject(['reason' => 'other'])->assertSessionHasErrors('note');
        $reject(['reason' => 'other', 'note' => 'short'])->assertSessionHasErrors('note');
        $this->assertSame(User::STATUS_PENDING, $farmer->fresh()->status);

        // The note is optional for a listed reason.
        $reject(['reason' => 'unverified'])->assertSessionHasNoErrors();
        $this->assertSame([User::STATUS_REJECTED, 'Information could not be verified'], [$farmer->fresh()->status, $farmer->fresh()->rejection_reason]);
        Mail::assertSent(AccountStatusMail::class, fn (AccountStatusMail $mail) => $mail->type === 'rejected' && $mail->hasTo('juan.delacruz@gmail.com')
            && str_contains($mail->render(), 'Hindi ma-verify ang impormasyon') && ! str_contains($mail->render(), 'Paalala mula sa opisina'));

        // With a note: the translated reason, then the note under it; never to the MAO.
        $other = $this->farmer(['name' => 'Ana Reyes', 'email' => 'ana.reyes@yahoo.com', 'status' => User::STATUS_PENDING]);
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $other), ['reason' => 'rsbsa_mismatch', 'note' => 'Your RSBSA number is for another barangay.'])->assertSessionHasNoErrors();
        $this->assertSame('RSBSA number does not match: Your RSBSA number is for another barangay.', $other->fresh()->rejection_reason);
        Mail::assertSent(AccountStatusMail::class, function (AccountStatusMail $mail) {
            $html = $mail->render();

            return $mail->type === 'rejected' && $mail->hasTo('ana.reyes@yahoo.com') && ! $mail->hasTo('mao.staff@lian.gov.ph')
                && str_contains($html, 'RSBSA number does not match') && str_contains($html, 'Note from the office:')
                && str_contains($html, 'Your RSBSA number is for another barangay.')
                && strpos($html, 'RSBSA number does not match') < strpos($html, 'Your RSBSA number is for another barangay.');
        });
    }

    // ------------------------------------------------------------------ b. signing up with a closed account's email

    public function test_signing_up_with_a_closed_accounts_email_says_so_without_the_reason(): void
    {
        $closed = $this->farmer(['email' => 'closed.farmer@gmail.com']);
        $closed->forceFill(['status' => User::STATUS_DELETED, 'deleted_reason' => 'misuse', 'deleted_at' => now()])->save();
        $this->farmer(['name' => 'Active One', 'email' => 'active.farmer@gmail.com']);
        $notice = 'This email belongs to an iClimate account that was closed. Please contact the Municipal Agriculture Office of Lian to restore it.';
        $signUp = fn (string $email) => $this->withSession([\App\Services\Security\CaptchaService::SESSION_KEY => [
                'context' => 'register', 'question' => 'What is 3 + 4?', 'answer_hash' => \Illuminate\Support\Facades\Hash::make('7'), 'created_at' => now()->timestamp,
            ]])
            ->from('/')->post('/register', [
                'name' => 'New Person', 'email' => $email, 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
                'barangay' => 'Balibago', 'captcha_answer' => '7',
            ]);

        $signUp('Closed.Farmer@gmail.com')->assertSessionHasErrors(['email' => $notice]);
        $this->assertStringNotContainsString('terms of use', implode(' ', session('errors')->get('email')));
        $signUp('active.farmer@gmail.com')->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertSame(2, User::withDeleted()->where('role', User::ROLE_FARMER)->count());

        $this->postJson('/api/mobile/register', [
            'name' => 'App Person', 'email' => 'closed.farmer@gmail.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'contact_number' => '09170000000', 'barangay' => 'Balibago', 'farm_type' => 'Rainfed',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', $notice);
    }

    // ------------------------------------------------------------------ c. bringing back archived farms

    public function test_a_farmer_restores_their_own_archived_farm_from_my_farms(): void
    {
        $farmer = $this->farmer();
        $kept = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        $old = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'South', 'barangay' => 'Prenza', 'archived_at' => now()->subDay()]);
        $someoneElses = Farm::query()->create(['user_id' => $this->farmer(['name' => 'Other', 'email' => 'other@gmail.com'])->id, 'farm_name' => 'Theirs', 'barangay' => 'Prenza', 'archived_at' => now()]);

        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()
            ->assertSee('Archived farms')->assertSee('data-archived-farm="'.$old->id.'"', false)->assertDontSee('data-archived-farm="'.$kept->id.'"', false);

        $this->actingAs($farmer)->post(route('farmer.farms.restore', $someoneElses->id))->assertNotFound();
        $this->actingAs($farmer)->post(route('farmer.farms.restore', $kept->id))->assertNotFound();   // not archived
        $this->actingAs($farmer)->post(route('farmer.farms.restore', $old->id))
            ->assertRedirect(route('farmer.my-farm'))->assertSessionHas('success', 'South is back in My Farms.');
        $this->assertNull($old->fresh()->archived_at);
        $this->assertTrue($kept->fresh()->is_primary);   // the primary farm stays primary
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertDontSee('Archived farms');
    }

    public function test_mao_and_it_restore_a_farmers_archived_farm_on_the_farmer_page(): void
    {
        $farmer = $this->farmer();
        $profile = FarmerProfile::query()->where('user_id', $farmer->id)->first()
            ?? FarmerProfile::factory()->create(['user_id' => $farmer->id, 'full_name' => 'Juan Dela Cruz', 'barangay' => 'Prenza']);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'South', 'barangay' => 'Prenza', 'archived_at' => now()]);
        $route = route('farmer-profiles.farms.restore', ['farmerProfile' => $profile->id, 'farm' => $farm->id]);

        $outside = User::factory()->maoPersonnel()->create();
        MaoUserBarangay::query()->create(['user_id' => $outside->id, 'barangay' => 'Kapito']);
        $this->actingAs($farmer)->post($route)->assertForbidden();
        $this->actingAs($outside)->post($route)->assertForbidden();
        $this->assertNotNull($farm->fresh()->archived_at);

        $mao = User::factory()->maoPersonnel()->create();
        $this->actingAs($mao)->get(route('farmer-profiles.show', $profile->id))->assertOk()
            ->assertSee('Archived farms (1)')->assertSee('data-restore-farm="'.$farm->id.'"', false);
        $this->actingAs($mao)->from(route('farmer-profiles.show', $profile->id))->post($route)
            ->assertRedirect(route('farmer-profiles.show', $profile->id))->assertSessionHas('success', 'South is back in Juan Dela Cruz\'s farms.');
        $this->assertNull($farm->fresh()->archived_at);

        $farm->fresh()->forceFill(['archived_at' => now()->addMinute()])->save();
        $this->actingAs(User::factory()->itExpert()->create())->post($route)->assertSessionHas('success');
        $this->assertNull($farm->fresh()->archived_at);
    }

    public function test_restoring_an_account_tells_it_how_many_farms_are_still_archived(): void
    {
        $it = User::factory()->itExpert()->create(['email' => 'ivy.it@lian.gov.ph']);
        User::factory()->itExpert()->create(['email' => 'second.it@lian.gov.ph']);
        $farmer = $this->farmer();
        Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'South', 'barangay' => 'Prenza']);

        $this->actingAs($it)->delete(route('users.destroy', $farmer->id), ['reason' => 'moved_out', 'confirm_name' => 'Juan Dela Cruz'])->assertSessionHasNoErrors();
        $this->actingAs($it)->post(route('users.restore', $farmer->id))
            ->assertSessionHas('success', 'Juan Dela Cruz\'s account was restored. This farmer has 2 archived farms. They can restore them in My Farms.');

        $staff = User::factory()->maoPersonnel()->create(['name' => 'Mila MAO', 'email' => 'mila@lian.gov.ph']);
        $this->actingAs($it)->delete(route('users.destroy', $staff->id), ['reason' => 'moved_out', 'confirm_name' => 'Mila MAO']);
        $this->actingAs($it)->post(route('users.restore', $staff->id))->assertSessionHas('success', 'Mila MAO\'s account was restored.');
    }
}
