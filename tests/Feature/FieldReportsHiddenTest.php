<?php

namespace Tests\Feature;

use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MAO/IT – Naicel (2026-10-07): Field Reports is hidden from the interface (FIELD_REPORTS_UI=false by default), like
 * Chatbot Knowledge. Its addresses send MAO and IT to their own dashboard; the table, data, model and controller stay.
 */
class FieldReportsHiddenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function report(User $by): FieldReport
    {
        return FieldReport::query()->create([
            'title' => 'Clogged canal', 'barangay' => 'Prenza', 'issue_type' => 'Flooding',
            'description' => 'Water in the field.', 'status' => FieldReport::STATUS_PENDING, 'reported_by' => $by->id,
        ]);
    }

    public function test_it_is_off_by_default(): void
    {
        $this->assertFalse(config('iclimate.field_reports_ui'));
        $this->assertFalse(\App\Http\Middleware\FieldReportsUi::enabled());
    }

    public function test_every_field_reports_address_sends_mao_to_the_mao_dashboard(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $report = $this->report($mao);

        foreach ([
            route('field-reports.index'), route('field-reports.index', ['unresolved' => 1]), route('field-reports.index', ['print' => 1]),
            route('field-reports.create'), route('field-reports.show', $report), route('field-reports.edit', $report),
        ] as $url) {
            $this->actingAs($mao)->get($url)->assertRedirect(route('mao.dashboard'));
        }
        $this->actingAs($mao)->post(route('field-reports.store'), ['title' => 'x'])->assertStatus(303)->assertRedirect(route('mao.dashboard'));
        $this->actingAs($mao)->delete(route('field-reports.destroy', $report))->assertRedirect(route('mao.dashboard'));

        // Only the pages are hidden: nothing was written or removed.
        $this->assertDatabaseHas('field_reports', ['id' => $report->id]);
        $this->assertSame(1, FieldReport::query()->count());
        $this->assertTrue(Schema::hasTable('field_reports'));
    }

    public function test_it_is_sent_to_its_own_dashboard(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($it)->get(route('field-reports.index'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_no_menu_item_dashboard_item_or_link_points_to_it(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->report($mao);

        foreach ([route('mao.dashboard'), route('climate-records.index'), route('reports.index')] as $page) {
            $html = $this->actingAs($mao)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString(route('field-reports.index'), $html, $page);
            $this->assertStringNotContainsString('Field Reports', $html, $page);
        }
        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->getContent();
        $this->assertStringNotContainsString('data-mao-action="field-reports"', $html);
        $this->assertStringNotContainsString('Unresolved field reports', $html);
    }

    public function test_switching_it_on_brings_the_pages_and_menu_item_back(): void
    {
        config(['iclimate.field_reports_ui' => true]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->report($mao);

        $this->actingAs($mao)->get(route('field-reports.index'))->assertOk()->assertSee('Clogged canal');
        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('<span class="sidebar-label">Field Reports</span>', $html);
        $this->assertStringContainsString('data-mao-action="field-reports"', $html);
    }
}
