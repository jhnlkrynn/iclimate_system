<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\CandidateModelReport;
use App\Support\RiceSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * IT (2026-10): the dashboard is System Status, Users by Role, Errors and Failed Jobs and Security; Usage Patterns,
 * Resources and the Data Pipeline are on System Health. IT views advisories, notifications to farmers, climate
 * records and rice production without changing them. The candidate model page is part of Model Evaluation, which
 * also shows the season-calendar retraining report.
 */
class ItPhaseEightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function advisory(string $status = 'published'): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(['title' => 'Planting Advisory', 'content' => 'Plant this week.', 'message' => 'Plant this week.', 'summary' => 'Plant this week.',
            'type' => 'Planting', 'advisory_type' => 'planting', 'severity' => 'low', 'target_scope' => 'municipality', 'status' => $status,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addWeek(), 'posted_by' => $this->mao()->id]);
    }

    // ------------------------------------------------------------ the dashboard and System Health

    public function test_the_it_dashboard_is_four_sections_and_the_rest_is_on_system_health(): void
    {
        $it = $this->it();
        $html = $this->actingAs($it)->get(route('admin.dashboard'))->assertOk()->getContent();

        preg_match_all('#data-it-section="([a-z]+)"#', $html, $sections);
        $this->assertSame(['status', 'users', 'errors', 'security'], $sections[1]);
        $this->assertStringContainsString('action="'.route('admin.dashboard.retry-failed').'"', view('dashboards.it.jobs', ['it' => ['extra' => ['failed_jobs' => ['state' => 'ok', 'rows' => [['class' => 'App\\Jobs\\X', 'count' => 2, 'latest' => now()]]]], 'stamps' => ['now' => now()]]])->render());
        $this->assertStringNotContainsString('role="tablist"', $html);
        // An old tab link still opens the dashboard.
        $this->actingAs($it)->get(route('admin.dashboard', ['tab' => 'resources']))->assertOk()->assertSee('data-it-section="status"', false);

        // System Health carries Usage Patterns, Resources and the Data Pipeline, from the overview already prepared.
        $health = $this->actingAs($it)->get(route('system-health.index'))->assertOk()->getContent();
        preg_match_all('#data-health-section="([a-z-]+)"#', $health, $moved);
        $this->assertSame(['usage', 'resources', 'pipeline'], $moved[1]);
        foreach (['Usage Patterns', 'Resources', 'Data Pipeline'] as $title) {
            $this->assertStringContainsString('>'.$title.'</h2>', $health, $title);
        }
    }

    public function test_system_health_never_builds_the_overview_itself(): void
    {
        // Nothing prepared yet: the page still opens, says so, and calls no service.
        $html = $this->actingAs($this->it())->get(route('system-health.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-health-section="not-prepared"', $html);
        $this->assertStringContainsString('Walang datos pa', $html);
        $this->assertStringContainsString('Service Status', $html);                       // the health checks themselves still show
        Http::assertNotSent(fn ($request) => ! str_ends_with($request->url(), '/model-info'));
        Queue::assertNothingPushed();
    }

    public function test_mao_and_farmers_cannot_open_it_pages_and_the_it_sidebar_lists_them(): void
    {
        $html = $this->actingAs($this->it())->get(route('admin.dashboard'))->getContent();
        preg_match_all('#<span class="sidebar-label">([^<]+)</span>#', $html, $labels);
        foreach (['System Health', 'System Logs', 'User Management'] as $label) {
            $this->assertContains($label, $labels[1], $label);
        }
        // Chatbot Knowledge is hidden from the interface (see ChatbotKnowledgeHiddenTest).
        $this->assertNotContains('Chatbot Knowledge', $labels[1]);

        foreach ([$this->mao(), User::factory()->farmer()->create()] as $other) {
            foreach (['admin.dashboard', 'system-health.index', 'model-evaluation.index', 'system-logs.index', 'users.index'] as $route) {
                $this->actingAs($other)->get(route($route))->assertForbidden();
            }
        }
    }

    // ------------------------------------------------------------ view-only

    public function test_it_reads_advisories_but_cannot_create_edit_approve_or_archive_them(): void
    {
        $it = $this->it();
        $published = $this->advisory();
        $pending = $this->advisory('pending_review');

        $list = $this->actingAs($it)->get(route('planting-advisories.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-adv-item="'.$published->id.'"', $list);
        foreach (['New Advisory', 'Review and publish', '>Edit<'] as $control) {
            $this->assertStringNotContainsString($control, $list, $control);
        }
        // IT still sees what waits for approval, read-only.
        $waiting = $this->actingAs($it)->get(route('planting-advisories.index', ['show' => 'pending']))->assertOk()->getContent();
        $this->assertStringContainsString('data-adv-item="'.$pending->id.'"', $waiting);
        $this->assertStringNotContainsString('Review and publish', $waiting);
        $this->actingAs($it)->get(route('planting-advisories.show', $published))->assertOk();

        $fields = ['message' => 'Changed by IT.', 'valid_until' => now()->addDays(3)->toDateString(), 'status' => 'published'];
        $this->actingAs($it)->get(route('planting-advisories.create'))->assertForbidden();
        $this->actingAs($it)->post(route('planting-advisories.store'), $fields)->assertForbidden();
        $this->actingAs($it)->get(route('planting-advisories.edit', $pending))->assertForbidden();
        $this->actingAs($it)->put(route('planting-advisories.update', $pending), $fields)->assertForbidden();
        $this->actingAs($it)->delete(route('planting-advisories.destroy', $published))->assertForbidden();
        foreach (['approve', 'publish', 'archive'] as $action) {
            $this->actingAs($it)->post(route('management.advisories.'.$action, $pending))->assertForbidden();
        }
        $this->actingAs($it)->post(route('management.advisories.reject', $pending), ['rejection_reason' => 'x'])->assertForbidden();

        $this->assertSame(['pending_review', 'Plant this week.'], [$pending->fresh()->status, $pending->fresh()->message]);
        $this->assertSame('published', $published->fresh()->status);
        $this->assertSame(2, PlantingAdvisory::query()->count());
        $this->assertSame(0, Notification::query()->count());

        // The MAO still does all of it.
        $this->actingAs($this->mao())->post(route('management.advisories.approve', $pending))->assertRedirect();
        $this->assertSame('published', $pending->fresh()->status);
    }

    public function test_it_cannot_send_edit_or_delete_notifications(): void
    {
        $it = $this->it();
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);
        $note = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Office closed', 'message' => 'Closed on Friday.', 'type' => 'Announcement', 'is_read' => false]);
        $send = ['recipient_scope' => 'farmers', 'title' => 'From IT', 'message' => 'Hello farmers.', 'type' => 'Announcement'];

        $this->actingAs($it)->post(route('notifications.store'), $send)->assertForbidden();
        $this->actingAs($it)->get(route('notifications.create'))->assertForbidden();
        $this->actingAs($it)->put(route('notifications.update', $note->id), ['user_id' => $farmer->id, 'title' => 'Changed', 'message' => 'x', 'type' => 'Warning'])->assertForbidden();
        $this->assertSame(1, Notification::query()->count());
        $this->assertSame('Office closed', $note->fresh()->title);

        // IT still reads its own notifications; the bell offers it no "Send notification".
        $this->actingAs($it)->get(route('notifications.index'))->assertOk()->assertDontSee('Create Notification');
        $feed = $this->actingAs($it)->getJson(route('notifications.feed', ['form' => 1]))->assertOk();
        $feed->assertJsonPath('can_manage', false)->assertJsonPath('store_url', null)->assertJsonPath('form', null);

        // The MAO still sends them.
        $this->actingAs($this->mao())->post(route('notifications.store'), $send)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'title' => 'From IT']);
    }

    public function test_it_views_climate_records_and_rice_production_without_changing_them(): void
    {
        $it = $this->it();
        $record = ClimateRecord::query()->create(['record_date' => '2026-09-05', 'rainfall' => 12, 'temperature' => 28, 'humidity' => 80, 'season' => 'Wet', 'source' => 'MAO field log']);

        foreach (['climate-records', 'rice-productions'] as $module) {
            $list = $this->actingAs($it)->get(route($module.'.index'))->assertOk()->getContent();
            $this->assertStringNotContainsString('href="'.route($module.'.create').'"', $list, $module);
            $this->assertStringNotContainsString('/edit"', $list, $module);
            $this->assertStringNotContainsString('data-ic-confirm="Delete this record?', $list, $module);
            $this->assertStringContainsString('data-print-list', $list, $module);                 // viewing and printing stay
            $this->actingAs($it)->get(route($module.'.create'))->assertForbidden();
            $this->actingAs($it)->post(route($module.'.store'), [])->assertForbidden();
        }
        $this->actingAs($it)->get(route('climate-records.show', $record->id))->assertOk();
        $this->actingAs($it)->get(route('climate-records.edit', $record->id))->assertForbidden();
        $this->actingAs($it)->put(route('climate-records.update', $record->id), ['rainfall' => 999])->assertForbidden();
        $this->actingAs($it)->delete(route('climate-records.destroy', $record->id))->assertForbidden();
        $this->assertSame(12.0, (float) $record->fresh()->rainfall);

        // The MAO still enters and corrects them.
        $mao = $this->mao();
        $this->actingAs($mao)->get(route('climate-records.index'))->assertSee('href="'.route('climate-records.create').'"', false);
        $this->actingAs($mao)->get(route('climate-records.edit', $record->id))->assertOk();
    }

    // ------------------------------------------------------------ Model Evaluation: candidate page merged, retraining report

    public function test_model_evaluation_carries_the_candidate_model_and_the_old_page_opens_it(): void
    {
        $it = $this->it();

        $this->actingAs($it)->get(route('testing.candidate-rice-yield'))->assertRedirect(route('model-evaluation.index'));
        $this->actingAs($it)->post(route('testing.candidate-rice-yield.predict'))->assertRedirect(route('model-evaluation.index'));

        $html = $this->actingAs($it)->get(route('model-evaluation.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="candidate-model"', $html);
        $this->assertStringContainsString('It is not the deployed model.', $html);
        $meta = CandidateModelReport::load()['metadata'];
        $this->assertNotNull($meta);
        foreach (['RMSE', 'MAE', 'R2'] as $key) {
            $this->assertStringContainsString(number_format((float) $meta['final_metrics'][$key], 4), $html, $key);
        }
        // The 2025 validation, month by month, read from the file the candidate script wrote.
        $this->assertSame(1, preg_match('#<table[^>]*data-candidate-validation>.*?<tbody>(.*?)</tbody>#s', $html, $m));
        $this->assertSame(count(CandidateModelReport::load()['validation']), substr_count($m[1], '<tr>'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'predict'));         // nothing is run from the page
    }

    public function test_the_season_retraining_report_is_shown_with_before_and_after_metrics(): void
    {
        $report = CandidateModelReport::load()['retrain'];
        $this->assertNotNull($report, 'run python python_scripts/retrain_yield_season.py');

        // The report describes a like-for-like comparison: same recipe as the recorded candidate, only October changes.
        $this->assertTrue($report['reproduces_recorded_candidate']);
        $this->assertSame(5, $report['change']['rows_changed']);
        $this->assertSame(['2020-10', '2021-10', '2022-10', '2023-10', '2024-10'], $report['change']['rows_changed_months']);
        $this->assertSame([52, 8], [$report['holdout_2025']['before']['train_rows'], $report['holdout_2025']['before']['test_rows']]);
        $this->assertFalse($report['deployed_model']['retrained']);
        $this->assertFalse($report['retrained_model']['activated']);

        $html = $this->actingAs($this->it())->get(route('model-evaluation.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Season Calendar Retraining (October = Wet)', $html);
        foreach (['holdout_2025', 'leave_one_year_out'] as $check) {
            foreach (['rmse', 'mae', 'r2'] as $metric) {
                $this->assertSame(1, preg_match('#<tr data-retrain-row="'.$check.'-'.$metric.'">(.*?)</tr>#s', $html, $row), "{$check} {$metric}");
                $this->assertStringContainsString(number_format((float) $report[$check]['before'][$metric], 4), $row[1]);
                $this->assertStringContainsString(number_format((float) $report[$check]['after'][$metric], 4), $row[1]);
            }
        }
        $this->assertStringContainsString('not retrained', $html);
    }

    public function test_the_deployed_model_keeps_its_trained_season_encoding_because_it_was_not_retrained(): void
    {
        // iClimate's calendar: October is Wet. The deployed model's inputs: October is Dry, as it was trained.
        $this->assertSame(RiceSeason::WET, RiceSeason::forMonth(10));
        $this->assertSame(RiceSeason::DRY, RiceSeason::forTrainedYieldModel(10));
        $this->assertSame(RiceSeason::WET, RiceSeason::forTrainedYieldModel(9));
        // The deployed file is untouched by the retraining: the retrained candidate is a separate, inactive file.
        $this->assertFileExists(base_path('storage/models/rice_yield_model_final.pkl'));
        $this->assertSame('storage/models/rice_yield_model_final.pkl', config('prediction.rice_yield.model_path', 'storage/models/rice_yield_model_final.pkl'));
        $this->assertStringNotContainsString('oct_wet', (string) config('prediction.rice_yield.model_path'));
    }
}
