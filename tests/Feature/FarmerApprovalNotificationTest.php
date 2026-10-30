<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Mao\FarmerApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MAO – Naicel (2026-10-09): a new farmer sign-up that waits for approval is a notification in every MAO's bell, linking
 * to Farmer Approvals, and it leaves the bell once the sign-up is approved or rejected.
 */
class FarmerApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function mao(array $more = []): User
    {
        return User::factory()->create($more + ['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function pendingFarmer(string $name = 'Ana Reyes', ?string $barangay = 'Prenza'): User
    {
        return User::factory()->farmer()->create(['name' => $name, 'barangay' => $barangay, 'status' => User::STATUS_PENDING]);
    }

    /** @return array<int, Notification> this MAO's sign-up notifications */
    private function notes(User $mao): array
    {
        return Notification::query()->where('user_id', $mao->id)->where('category', 'like', FarmerApprovalNotifier::CATEGORY_PREFIX.'%')->orderBy('id')->get()->all();
    }

    public function test_every_mao_gets_one_notification_per_waiting_sign_up_in_their_language(): void
    {
        $english = $this->mao();
        $filipino = $this->mao(['preferred_language' => 'fil']);
        $farmer = $this->pendingFarmer();
        User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);     // an active farmer is not a sign-up to approve

        app(FarmerApprovalNotifier::class)->sync();
        app(FarmerApprovalNotifier::class)->sync();                                // running it again adds nothing

        $note = $this->notes($english);
        $this->assertCount(1, $note);
        $this->assertSame(['New farmer sign-up to approve: Ana Reyes', 'Ana Reyes of Prenza registered and is waiting for your approval.', 'farmer-approval:'.$farmer->id, false],
            [$note[0]->title, $note[0]->message, $note[0]->category, (bool) $note[0]->is_read]);
        $this->assertSame('Bagong sign-up ng magsasaka na aaprubahan: Ana Reyes', $this->notes($filipino)[0]->title);

        // Farmers and IT never get it.
        $this->assertSame(2, Notification::query()->where('category', 'like', 'farmer-approval:%')->count());
    }

    public function test_the_bell_links_the_notification_to_farmer_approvals(): void
    {
        $mao = $this->mao();
        $this->pendingFarmer('Ben Cruz', null);
        app(FarmerApprovalNotifier::class)->sync();

        $item = collect($this->actingAs($mao)->getJson(route('notifications.feed'))->assertOk()->json('items'))->firstWhere('title', 'New farmer sign-up to approve: Ben Cruz');
        $this->assertNotNull($item);
        $this->assertSame([route('mao.farmer-approvals.index'), 'Review'], [$item['link_url'] ?? null, $item['link_label'] ?? null]);
        $this->assertSame('Ben Cruz registered and is waiting for your approval.', $this->notes($mao)[0]->message);
        $this->actingAs($mao)->get(route('mao.farmer-approvals.index'))->assertOk()->assertSee('Ben Cruz');
    }

    public function test_it_leaves_the_bell_when_the_sign_up_is_approved_or_rejected(): void
    {
        $mao = $this->mao();
        $other = $this->mao();
        $approved = $this->pendingFarmer('Ana Reyes');
        $rejected = $this->pendingFarmer('Ben Cruz');
        app(FarmerApprovalNotifier::class)->sync();
        $this->assertCount(2, $this->notes($other));

        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $approved))->assertRedirect();
        $this->assertSame(['farmer-approval:'.$rejected->id], array_map(fn (Notification $n) => $n->category, $this->notes($other)));

        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $rejected), ['reason' => 'not_resident'])->assertRedirect();
        $this->assertSame([], $this->notes($mao));
        $this->assertSame([], $this->notes($other));
        // The approved farmer still gets their own "Account approved" notification.
        $this->assertDatabaseHas('notifications', ['user_id' => $approved->id, 'title' => 'Account approved']);
    }

    public function test_the_every_minute_warm_up_keeps_the_bell_in_step(): void
    {
        $mao = $this->mao();
        $farmer = $this->pendingFarmer();

        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);
        $this->assertCount(1, $this->notes($mao));

        // IT enables the account in User Management instead: the next check removes the notification.
        $farmer->forceFill(['status' => User::STATUS_ACTIVE])->save();
        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);
        $this->assertSame([], $this->notes($mao));
    }
}
