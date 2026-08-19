<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\FeedPost;
use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsHeatmapNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mao_can_generate_and_export_climate_report(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        ClimateRecord::factory()->create(['season' => 'Wet']);

        $this->actingAs($mao)->post(route('reports.generate'), [
            'report_type' => 'climate_records',
            'season' => 'Wet',
        ])->assertOk();

        $this->assertDatabaseHas('reports', [
            'report_type' => 'Climate Records Report',
            'generated_by' => $mao->id,
        ]);

        $this->actingAs($mao)->get(route('reports.export', [
            'report_type' => 'climate_records',
            'season' => 'Wet',
        ]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_rice_production_rejects_invalid_barangay_season_and_irrigation(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('rice-productions.store'), [
            'barangay' => 'Not A Lian Barangay',
            'season' => 'Summer',
            'irrigation_type' => 'Unknown',
            'yield_per_hectare' => 4.2,
            'area_hectares' => 10,
            'total_production' => 42,
            'year' => 2026,
        ])->assertSessionHasErrors(['barangay', 'season', 'irrigation_type']);
    }

    public function test_heatmap_index_displays_risk_cards(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        HeatmapArea::factory()->create(['barangay' => 'Matabungkay', 'risk_level' => 'Severe', 'risk_type' => 'Flood']);
        PlantingAdvisory::query()->create([
            'title' => 'PAGASA Lian Heavy Rainfall Advisory',
            'content' => 'Official PAGASA advisory detected for Lian, Batangas.',
            'message' => 'Official PAGASA advisory detected for Lian, Batangas.',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Official PAGASA advisory detected for Lian, Batangas.',
            'severity' => 'high',
            'target_scope' => 'municipality',
            'source' => 'PAGASA',
            'source_url' => 'https://www.pagasa.dost.gov.ph/?vm=r',
            'status' => 'published',
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addHours(6),
            'posted_by' => $mao->id,
        ]);

        $this->actingAs($mao)
            ->get(route('heatmap-areas.index'))
            ->assertOk()
            ->assertSee('Matabungkay')
            ->assertSee('Severe')
            ->assertSee('Flood')
            ->assertSee('PAGASA Official Source')
            ->assertSee('View PAGASA Map')
            ->assertSee('PAGASA Lian Heavy Rainfall Advisory');
    }

    public function test_farmer_heatmap_displays_only_their_saved_farm_boundary(): void
    {
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id]);
        FarmBoundary::factory()->create(['farmer_profile_id' => $profile->id]);
        HeatmapArea::factory()->create(['barangay' => 'Matabungkay']);

        $this->actingAs($farmer)
            ->get(route('heatmap-areas.index'))
            ->assertOk()
            ->assertSee('My Farm Boundary')
            ->assertSee('myFarmBoundary', false);
    }

    public function test_mao_can_send_notifications_to_farmers_and_farmer_can_mark_read(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($mao)->post(route('notifications.store'), [
            'recipient_scope' => 'farmers',
            'title' => 'Weather Warning',
            'message' => 'Heavy rainfall expected.',
            'type' => 'Warning',
        ])->assertRedirect(route('notifications.index'));

        $notification = Notification::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertFalse($notification->is_read);

        $this->actingAs($farmer)
            ->patch(route('notifications.mark-read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_mao_can_publish_announcement_category_to_community_feed(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('community-feed.store'), [
            'title' => 'Seed Distribution Schedule',
            'body' => 'Seeds will be distributed this Friday.',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ])->assertRedirect(route('community-feed.index'));

        $this->assertDatabaseHas('feed_posts', [
            'user_id' => $mao->id,
            'title' => 'Seed Distribution Schedule',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ]);
    }

    public function test_farmer_sees_community_feed_announcements(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'Visible Update',
            'body' => 'Published as a community feed announcement.',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ]);
        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'General Update',
            'body' => 'A normal community feed update.',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $this->actingAs($farmer)
            ->get(route('community-feed.index'))
            ->assertOk()
            ->assertSee('Visible Update')
            ->assertSee('General Update');
    }

    public function test_notifications_index_is_current_users_inbox(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        Notification::factory()->create(['user_id' => $mao->id, 'title' => 'MAO Inbox']);
        Notification::factory()->create(['user_id' => $farmer->id, 'title' => 'Farmer Inbox']);

        $this->actingAs($mao)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('MAO Inbox')
            ->assertDontSee('Farmer Inbox');
    }
}
