<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
    }

    public function test_private_channel_callbacks_are_registered(): void
    {
        $channels = Broadcast::connection()->getChannels();

        $this->assertTrue($channels->has('user.{userId}'));
        $this->assertTrue($channels->has('farm.{farmId}'));
        $this->assertTrue($channels->has('conversation.{conversationId}'));
        $this->assertTrue($channels->has('mao'));
        $this->assertFalse($channels->has('it'));
        $this->assertTrue($channels->has('dashboard.{role}'));
        $this->assertTrue($channels->has('barangay.{barangay}'));
    }

    public function test_dashboard_role_channel_is_restricted_to_the_matching_role(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $channels = Broadcast::connection()->getChannels();
        $authorize = $channels->get('dashboard.{role}');

        $this->assertTrue($authorize($farmer, 'farmer'));
        $this->assertFalse($authorize($farmer, 'mao'));
        $this->assertTrue($authorize($mao, 'mao'));
    }

    public function test_user_and_role_channels_are_restricted_to_their_authorized_roles(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $it = User::factory()->state(['role' => 'IT Expert'])->create();
        $channels = Broadcast::connection()->getChannels();

        $this->assertTrue(($channels->get('user.{userId}'))($farmer, $farmer->id));
        $this->assertFalse(($channels->get('user.{userId}'))($farmer, $it->id));
        $this->assertTrue(($channels->get('mao'))($mao));
        $this->assertFalse(($channels->get('mao'))($farmer));
        $this->assertFalse($channels->has('it'));
        $this->assertFalse($channels->has('it'));
    }

    public function test_farm_and_conversation_channels_require_existing_relationships(): void
    {
        $owner = User::factory()->farmer()->create();
        $other = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $owner->id,
            'farm_name' => 'Channel test farm',
            'barangay' => 'Binubusan',
            'location_method' => Farm::LOCATION_BARANGAY,
            'farm_size_method' => Farm::SIZE_UNKNOWN,
        ]);
        $conversation = Conversation::query()->create([
            'participant_one_id' => $owner->id,
            'participant_two_id' => $other->id,
        ]);
        $channels = Broadcast::connection()->getChannels();

        $this->assertTrue(($channels->get('farm.{farmId}'))($owner, $farm->id));
        $this->assertFalse(($channels->get('farm.{farmId}'))($other, $farm->id));
        $this->assertTrue(($channels->get('conversation.{conversationId}'))($owner, $conversation->id));
        $this->assertTrue(($channels->get('conversation.{conversationId}'))($other, $conversation->id));
        $outsider = User::factory()->farmer()->create();
        $this->assertFalse(($channels->get('conversation.{conversationId}'))($outsider, $conversation->id));
    }

    public function test_barangay_channel_uses_canonical_scope_and_role_authorization(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Binubusan']);
        $mao = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $it = User::factory()->state(['role' => 'IT Expert'])->create();
        $channels = Broadcast::connection()->getChannels();
        $authorize = $channels->get('barangay.{barangay}');

        $this->assertTrue($authorize($farmer, 'Binubusan'));
        $this->assertFalse($authorize($farmer, 'Malaruhatan'));
        $this->assertTrue($authorize($mao, 'Malaruhatan'));
        $this->assertFalse($authorize($it, 'Malaruhatan'));
        $this->assertFalse($authorize($farmer, 'not-a-lian-barangay'));
    }
}
