<?php

namespace Tests\Feature;

use App\Events\DashboardChanged;
use App\Events\NotificationChanged;
use App\Mail\AccountStatusMail;
use App\Models\AssociationChairmanAssignment;
use App\Models\AssociationChairmanInvitation;
use App\Models\FarmerAccountReview;
use App\Models\FarmerOrganization;
use App\Models\MaoUserBarangay;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mao\FarmerApprovalNotifier;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FarmerAccountRequestsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $settingsBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsBefore = is_file(SystemSettings::path()) ? file_get_contents(SystemSettings::path()) : null;
        file_put_contents(SystemSettings::path(), '{}');
        SystemSettings::flush();
        Mail::fake();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);
        $this->mock(\App\Services\Prediction\RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
    }

    protected function tearDown(): void
    {
        if ($this->settingsBefore === null) {
            @unlink(SystemSettings::path());
        } else {
            file_put_contents(SystemSettings::path(), $this->settingsBefore);
        }
        SystemSettings::flush();
        parent::tearDown();
    }

    private function pending(): User
    {
        return User::factory()->farmer()->create(['status' => User::STATUS_PENDING, 'barangay' => 'Prenza', 'email' => fake()->unique()->userName().'@lian-farmers.ph']);
    }

    public function test_mobile_registration_is_pending_and_notifies_all_active_staff_even_with_legacy_bypass_setting(): void
    {
        config(['iclimate.farmer_approval' => false]);
        $one = User::factory()->maoPersonnel()->create();
        $two = User::factory()->maoPersonnel()->create();
        $this->postJson('/api/mobile/register', [
            'name' => 'New association farmer', 'email' => 'new-chairman@lian-farmers.ph',
            'password' => 'SecurePassword!23', 'password_confirmation' => 'SecurePassword!23',
            'contact_number' => '09170000000', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated',
            'role' => 'association_chairman', 'is_chairman' => true,
        ])->assertCreated()->assertJsonPath('user.status', User::STATUS_PENDING)->assertJsonPath('user.role', User::ROLE_FARMER);
        $farmer = User::where('email', 'new-chairman@lian-farmers.ph')->sole();
        $this->assertGuest();
        $this->assertFalse($farmer->isAssociationChairman());
        $this->assertSame(0, AssociationChairmanAssignment::count());
        foreach ([$one, $two] as $mao) {
            $this->assertDatabaseHas('notifications', ['user_id' => $mao->id, 'category' => FarmerApprovalNotifier::CATEGORY_PREFIX.$farmer->id]);
        }
        app(FarmerApprovalNotifier::class)->sync();
        $this->assertSame(2, Notification::where('category', FarmerApprovalNotifier::CATEGORY_PREFIX.$farmer->id)->count());
    }

    public function test_every_mao_can_search_review_and_view_details_across_barangays_without_head_permission(): void
    {
        $mao = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);
        MaoUserBarangay::query()->create(['user_id' => $mao->id, 'barangay' => 'Kapito']);
        $farmer = $this->pending();
        SystemSettings::set(['permissions' => ['farmer_management' => []]], null);
        $this->actingAs($mao)->get(route('mao.farmer-approvals.index', ['search' => $farmer->name, 'barangay' => 'Prenza']))
            ->assertOk()->assertSee('Account Requests')->assertSee($farmer->email)->assertSee('View Details');
        $this->get(route('mao.farmer-approvals.show', $farmer))->assertOk()->assertSee($farmer->email);
        $this->post(route('mao.farmer-approvals.approve', $farmer))->assertRedirect();
        $this->assertSame(User::STATUS_APPROVED, $farmer->fresh()->status);
        $this->assertSame($mao->id, $farmer->fresh()->reviewed_by);
        $this->assertNotNull($farmer->fresh()->reviewed_at);
        $this->assertDatabaseHas('farmer_account_reviews', ['user_id' => $farmer->id, 'reviewer_id' => $mao->id, 'decision' => 'Approved']);
        $this->get('/users')->assertForbidden();
        $this->post('/mao/personnel', [])->assertForbidden();
    }

    public function test_approval_sends_one_decision_notification_and_email_and_enables_web_mobile_and_reverb_access(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        Event::fake([DashboardChanged::class, NotificationChanged::class]);
        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $farmer))->assertRedirect();
        $this->post(route('mao.farmer-approvals.approve', $farmer))->assertConflict();
        $this->assertSame(1, FarmerAccountReview::where('user_id', $farmer->id)->count());
        $this->assertSame(1, Notification::where('user_id', $farmer->id)->where('category', 'account')->count());
        Mail::assertSent(AccountStatusMail::class, 1);
        Event::assertDispatched(DashboardChanged::class, fn ($event) => in_array(User::ROLE_MAO, $event->roles, true));
        Event::assertDispatched(NotificationChanged::class);
        auth()->logout();
        $this->post('/login', ['email' => $farmer->email, 'password' => 'password'])->assertRedirect(route('farmer.dashboard', absolute: false));
        $this->get(route('farmer.dashboard'))->assertOk();
        $this->postJson('/api/mobile/login', ['email' => $farmer->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.status', 'Approved');
        $this->assertTrue((Broadcast::connection()->getChannels()->get('dashboard.{role}'))($farmer->fresh(), 'farmer'));
    }

    public function test_rejection_requires_a_reason_records_reviewer_and_notifies_farmer_once(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer))->assertSessionHasErrors('reason');
        $this->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'other'])->assertSessionHasErrors('note');
        $this->assertSame(User::STATUS_PENDING, $farmer->fresh()->status);
        $this->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'incomplete', 'note' => 'Provide the missing contact information.'])->assertRedirect();
        $this->assertSame(User::STATUS_REJECTED, $farmer->fresh()->status);
        $this->assertSame($mao->id, $farmer->fresh()->reviewed_by);
        $this->assertNotNull($farmer->fresh()->reviewed_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'title' => 'Account rejected']);
        Mail::assertSent(AccountStatusMail::class, fn ($mail) => $mail->type === 'rejected' && str_contains($mail->words['note'], 'contact'));
        $this->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'duplicate'])->assertConflict();
        $this->assertSame(1, FarmerAccountReview::where('user_id', $farmer->id)->count());
    }

    public function test_pending_and_rejected_users_are_denied_existing_sessions_apis_logins_and_channels(): void
    {
        foreach ([User::STATUS_PENDING, User::STATUS_REJECTED] as $status) {
            $farmer = User::factory()->farmer()->create(['status' => $status]);
            $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertRedirect(route('login'));
            $this->actingAs($farmer)->getJson('/api/heat-map')->assertUnauthorized();
            $this->post('/login', ['email' => $farmer->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->postJson('/api/mobile/login', ['email' => $farmer->email, 'password' => 'password'])->assertUnprocessable();
            $channels = Broadcast::connection()->getChannels();
            $this->assertFalse(($channels->get('user.{userId}'))($farmer, $farmer->id));
            $this->assertFalse(($channels->get('dashboard.{role}'))($farmer, 'farmer'));
            $this->assertFalse(($channels->get('barangay.{barangay}'))($farmer, $farmer->barangay));
        }
    }

    public function test_farmers_cannot_review_themselves_or_other_accounts(): void
    {
        $farmer = User::factory()->farmer()->create();
        $pending = $this->pending();
        foreach ([$farmer, $pending] as $target) {
            $this->actingAs($farmer)->post(route('mao.farmer-approvals.approve', $target))->assertForbidden();
            $this->post(route('mao.farmer-approvals.reject', $target), ['reason' => 'duplicate'])->assertForbidden();
            $this->get(route('mao.farmer-approvals.show', $target))->assertForbidden();
        }
        $this->assertSame(0, FarmerAccountReview::count());
    }

    public function test_conflicting_review_requests_and_stale_revisions_cannot_change_the_first_decision(): void
    {
        $one = User::factory()->maoPersonnel()->create();
        $two = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $this->actingAs($one)->post(route('mao.farmer-approvals.approve', $farmer), ['revision' => 0])->assertRedirect();
        $this->actingAs($two)->post(route('mao.farmer-approvals.reject', $farmer), ['revision' => 0, 'reason' => 'duplicate'])->assertConflict();
        $this->assertSame(User::STATUS_APPROVED, $farmer->fresh()->status);
        $this->assertSame($one->id, $farmer->fresh()->reviewed_by);
        $this->assertSame(1, FarmerAccountReview::where('user_id', $farmer->id)->count());
        $this->assertSame(1, Notification::where('user_id', $farmer->id)->where('category', 'account')->count());
    }

    public function test_farmer_resubmission_reuses_the_account_preserves_reviews_and_requires_a_new_review(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'incomplete'])->assertRedirect();
        $originalReview = FarmerAccountReview::where('user_id', $farmer->id)->sole()->getAttributes();
        auth()->logout();
        $data = ['email' => $farmer->email, 'password' => 'password', 'resubmission_note' => 'My corrected contact information is available at the office.'];
        $this->post('/registration/resubmit', array_replace($data, ['password' => 'incorrect']))->assertSessionHasErrors('email');
        $this->post('/registration/resubmit', $data)->assertRedirect(route('login'));
        $this->assertSame(1, User::where('email', $farmer->email)->count());
        $this->assertSame(User::STATUS_PENDING, $farmer->fresh()->status);
        $this->assertSame(1, $farmer->fresh()->registration_revision);
        $this->assertSame($originalReview, FarmerAccountReview::where('user_id', $farmer->id)->sole()->getAttributes());
        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $farmer), ['revision' => 0])->assertConflict();
        $this->post(route('mao.farmer-approvals.approve', $farmer), ['revision' => 1])->assertRedirect();
        $this->assertSame(2, FarmerAccountReview::where('user_id', $farmer->id)->count());
        $this->assertSame(User::STATUS_APPROVED, $farmer->fresh()->status);
    }

    public function test_pending_notifications_are_idempotent_for_each_active_mao_and_application_revision(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        User::factory()->maoPersonnel()->create(['status' => User::STATUS_INACTIVE]);
        $farmer = $this->pending();
        app(FarmerApprovalNotifier::class)->sync();
        app(FarmerApprovalNotifier::class)->sync();
        $this->assertSame(1, Notification::where('category', FarmerApprovalNotifier::CATEGORY_PREFIX.$farmer->id)->count());
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'unverified']);
        $this->post(route('mao.farmer-approvals.reconsider', $farmer), ['resubmission_note' => 'Please reconsider the verified contact details.'])->assertRedirect();
        app(FarmerApprovalNotifier::class)->sync();
        $this->assertSame(1, Notification::where('category', FarmerApprovalNotifier::CATEGORY_PREFIX.$farmer->id)->count());
        $this->assertDatabaseHas('notifications', ['deduplication_key' => 'farmer-approval:'.$farmer->id.':1:mao:'.$mao->id]);
    }

    public function test_chairman_invitation_does_not_approve_account_or_activate_assignment(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $association = FarmerOrganization::create(['name' => 'Approval association', 'barangay' => 'Prenza']);
        $assignment = AssociationChairmanAssignment::create(['farmer_organization_id' => $association->id, 'user_id' => $farmer->id, 'status' => 'pending', 'assigned_by' => $mao->id]);
        AssociationChairmanInvitation::create(['association_chairman_assignment_id' => $assignment->id, 'token_hash' => hash('sha256', 'approval-token'), 'expires_at' => now()->addDay(), 'invited_by' => $mao->id]);
        $this->post('/association-chairman/invitations/approval-token', ['password' => 'NewSecurePassword!23', 'password_confirmation' => 'NewSecurePassword!23'])->assertRedirect();
        $this->assertSame(User::STATUS_PENDING, $farmer->fresh()->status);
        $this->assertSame('pending', $assignment->fresh()->status);
        $this->assertFalse($farmer->fresh()->isAssociationChairman());
        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $farmer))->assertRedirect();
        $this->assertFalse($farmer->fresh()->isAssociationChairman());
        $this->post('/mao/association-reports/associations/'.$association->id.'/assign', ['user_id' => $farmer->id])->assertRedirect();
        $this->assertTrue($farmer->fresh()->isAssociationChairman());
    }

    public function test_legacy_approved_accounts_and_historical_approval_fields_are_preserved(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $legacy = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE, 'approved_by' => $mao->id, 'approved_at' => now()->subYear()]);
        $before = $legacy->getAttributes();
        $this->actingAs($legacy)->get(route('farmer.dashboard'))->assertOk();
        $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk();
        $this->get(route('mao.farmer-approvals.index', ['show' => 'approved']))->assertOk()->assertSee($legacy->email);
        $this->post(route('mao.farmer-approvals.reject', $legacy), ['reason' => 'duplicate'])->assertConflict();
        $this->assertSame($before['approved_at'], $legacy->fresh()->getRawOriginal('approved_at'));
        $this->assertSame($before['approved_by'], $legacy->fresh()->approved_by);
    }
    public function test_self_deletion_preserves_the_account_and_its_review_history(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $farmer))->assertRedirect();
        $this->actingAs($farmer->fresh())->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertDatabaseHas('users', ['id' => $farmer->id, 'status' => User::STATUS_APPROVED]);
        $this->assertSame(1, FarmerAccountReview::where('user_id', $farmer->id)->count());
        $mao->forceFill(['status' => User::STATUS_DELETED])->save();
        $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('mao.farmer-approvals.show', $farmer))->assertOk()->assertSee($mao->name);
    }

    public function test_resubmission_reuses_login_captcha_and_failed_attempt_safeguards(): void
    {
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_REJECTED]);
        $data = ['email' => $farmer->email, 'password' => 'incorrect', 'resubmission_note' => 'Corrected information is ready for review.'];
        foreach (range(1, 3) as $attempt) {
            $this->post('/registration/resubmit', $data)->assertSessionHasErrors('email');
        }
        $this->get('/registration/resubmit')->assertOk()->assertSee('Security code');
        $data['password'] = 'password';
        $this->post('/registration/resubmit', $data)->assertSessionHasErrors('captcha_answer');
        $this->assertSame(User::STATUS_REJECTED, $farmer->fresh()->status);
        $this->withSession([\App\Services\Security\CaptchaService::SESSION_KEY => [
            'context' => 'login', 'answer_hash' => \Illuminate\Support\Facades\Hash::make('7'), 'created_at' => now()->timestamp,
        ]])->post('/registration/resubmit', $data + ['captcha_answer' => '7'])->assertRedirect(route('login'));
        $this->assertSame(User::STATUS_PENDING, $farmer->fresh()->status);
        $this->assertGuest();
    }

}
