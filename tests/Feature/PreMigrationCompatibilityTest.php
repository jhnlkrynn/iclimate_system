<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireSchema;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\User;
use App\Services\Accounts\FarmerAccountReviewService;
use App\Services\Mao\FarmerApprovalNotifier;
use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The application code can be live before the 2026-11-16/17 migrations run (Phase 18 deployment). On that schema
 * nothing may answer with a server error or report success for a change that was not saved. The schema is put back
 * to its pre-migration state with the migrations' own down() methods; RefreshDatabase rolls it all back afterwards.
 */
class PreMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const PENDING = [
        // Rolled back first: its submissions table references association_yield_reports, and MySQL/MariaDB refuse to
        // drop a referenced table (SQLite does not check). A pre-migration database has neither table.
        '2026_11_18_000001_create_association_report_submissions',
        '2026_11_17_000002_add_farmer_account_review_history',
        '2026_11_16_000004_create_association_chairman_invitations_table',
        '2026_11_16_000002_create_association_reporting_tables',
        // 2026_11_16_000001 only adds nullable directory columns; its SQLite down() cannot drop indexed columns, and the
        // pre-migration MariaDB probe showed no page depends on them, so it stays applied here.
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);
        $this->mock(\App\Services\Prediction\RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
        foreach (self::PENDING as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->down();
        }
        SchemaCache::forget();
        $this->assertFalse(FarmerAccountReviewService::available());
    }

    private function pending(string $status = User::STATUS_PENDING): User
    {
        return User::factory()->farmer()->create(['status' => $status, 'barangay' => 'Prenza', 'email' => fake()->unique()->userName().'@lian-farmers.ph']);
    }

    public function test_farmer_pages_open_while_association_tables_are_missing(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Prenza', 'farm_area' => 1.5, 'farm_type' => 'Rainfed']);

        $this->assertFalse($farmer->isAssociationChairman());
        foreach (['/farmer/dashboard', '/profile', '/notifications'] as $url) {
            $this->actingAs($farmer)->get($url)->assertOk();
        }
    }

    public function test_account_requests_open_and_review_actions_change_nothing_and_say_so(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $first = $this->pending();
        $second = $this->pending();
        $rejected = $this->pending(User::STATUS_REJECTED);

        foreach (['/mao/farmer-approvals', '/mao/farmer-approvals?show=rejected', '/mao/farmer-approvals?show=approved', '/mao/farmer-approvals/'.$first->id] as $url) {
            $this->actingAs($mao)->get($url)->assertOk();
        }

        $this->actingAs($mao)->from('/mao/farmer-approvals')->post("/mao/farmer-approvals/{$first->id}/approve", ['revision' => 0])
            ->assertRedirect('/mao/farmer-approvals')->assertSessionHas('error', FarmerAccountReviewService::NOT_READY_MESSAGE)->assertSessionMissing('success');
        $this->actingAs($mao)->from('/mao/farmer-approvals')->post("/mao/farmer-approvals/{$second->id}/reject", ['revision' => 0, 'reason' => 'incomplete'])
            ->assertSessionHas('error', FarmerAccountReviewService::NOT_READY_MESSAGE)->assertSessionMissing('success');
        $this->actingAs($mao)->from('/mao/farmer-approvals')->post("/mao/farmer-approvals/{$rejected->id}/reconsider", ['revision' => 0, 'resubmission_note' => 'Returned for a second look.'])
            ->assertSessionHas('error', FarmerAccountReviewService::NOT_READY_MESSAGE)->assertSessionMissing('success');

        $this->assertSame(User::STATUS_PENDING, $first->fresh()->status);
        $this->assertSame(User::STATUS_PENDING, $second->fresh()->status);
        $this->assertSame(User::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame(0, Notification::query()->whereIn('user_id', [$first->id, $second->id, $rejected->id])->count());
        Mail::assertNothingOutgoing();
    }

    public function test_resubmission_explains_the_wait_without_counting_a_failed_sign_in(): void
    {
        $rejected = User::factory()->farmer()->create(['status' => User::STATUS_REJECTED, 'password' => 'CorrectPassword!23']);

        $this->from('/registration/resubmit')->post('/registration/resubmit', [
            'email' => $rejected->email, 'password' => 'CorrectPassword!23', 'resubmission_note' => 'Corrected my contact number.',
        ])->assertRedirect('/registration/resubmit')->assertSessionHasErrors(['resubmission_note' => FarmerAccountReviewService::NOT_READY_MESSAGE]);

        $this->assertSame(User::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts(strtolower($rejected->email).'|127.0.0.1'));
    }

    public function test_registration_still_saves_pending_and_alerts_every_active_mao_once(): void
    {
        $one = User::factory()->maoPersonnel()->create();
        $two = User::factory()->maoPersonnel()->create();

        $this->postJson('/api/mobile/register', [
            'name' => 'Early farmer', 'email' => 'early-farmer@lian-farmers.ph', 'password' => 'SecurePassword!23', 'password_confirmation' => 'SecurePassword!23',
            'contact_number' => '09170000000', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated', 'privacy_agreed' => true,
        ])->assertCreated()->assertJsonPath('user.status', User::STATUS_PENDING);

        $farmer = User::query()->where('email', 'early-farmer@lian-farmers.ph')->sole();
        app(FarmerApprovalNotifier::class)->sync();
        foreach ([$one, $two] as $mao) {
            $this->assertSame(1, Notification::query()->where('user_id', $mao->id)->where('category', FarmerApprovalNotifier::CATEGORY_PREFIX.$farmer->id)->count());
        }
    }

    public function test_association_reporting_answers_an_explained_503_before_saving_anything(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $association = FarmerOrganization::query()->create(['name' => 'Early association', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated']);
        $users = User::query()->count();

        $this->actingAs($mao)->get('/mao/association-reports')->assertStatus(503)->assertSee(RequireSchema::MESSAGE);
        $this->actingAs($mao)->post("/mao/association-reports/associations/{$association->id}/invite", ['name' => 'New chairman', 'email' => 'chairman@lian-farmers.ph'])->assertStatus(503);
        $this->actingAs($farmer)->get('/association-chairman')->assertStatus(503);
        auth()->logout();
        $this->get('/association-chairman/invitations/some-token')->assertStatus(503);

        $this->assertSame($users, User::query()->count(), 'the invite must not leave a half-created account behind');
        $this->artisan('associations:send-reminders')->assertSuccessful();
    }

    public function test_the_guards_switch_off_as_soon_as_the_migrations_run(): void
    {
        foreach (array_reverse(self::PENDING) as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        SchemaCache::forget();
        $this->assertTrue(FarmerAccountReviewService::available());

        $mao = User::factory()->maoPersonnel()->create();
        $farmer = $this->pending();
        $this->actingAs($mao)->from('/mao/farmer-approvals')->post("/mao/farmer-approvals/{$farmer->id}/approve", ['revision' => 0])
            ->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSame(User::STATUS_APPROVED, $farmer->fresh()->status);
        $this->actingAs($mao)->get('/mao/association-reports')->assertOk();
    }
}
