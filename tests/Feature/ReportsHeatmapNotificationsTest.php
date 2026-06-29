<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\Report;
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

    public function test_heatmap_index_displays_risk_cards(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        HeatmapArea::factory()->create(['barangay' => 'Matabungkay', 'risk_level' => 'Severe', 'risk_type' => 'Flood']);

        $this->actingAs($mao)
            ->get(route('heatmap-areas.index'))
            ->assertOk()
            ->assertSee('Matabungkay')
            ->assertSee('Severe')
            ->assertSee('Flood');
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
}