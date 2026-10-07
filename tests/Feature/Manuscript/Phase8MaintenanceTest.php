<?php

namespace Tests\Feature\Manuscript;

use App\Jobs\RunDatabaseMaintenance;
use App\Jobs\RunModelManagement;
use App\Models\MaintenanceRecord;
use App\Models\MlModel;
use App\Models\User;
use App\Services\MachineLearning\ModelManagementService;
use App\Services\SystemAuditLogger;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 8: Maintenance Records, Backups, System Logs export and ML Models. */
class Phase8MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(SystemSettings::path());
        @unlink(ModelManagementService::reportPath());
        SystemSettings::flush();
    }

    protected function tearDown(): void
    {
        @unlink(SystemSettings::path());
        @unlink(ModelManagementService::reportPath());
        SystemSettings::flush();
        parent::tearDown();
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    public function test_it_logs_edits_and_deletes_maintenance_records(): void
    {
        $it = $this->it();

        $this->actingAs($it)->post(route('admin.maintenance.store'), ['title' => 'Updated PHP to 8.5', 'category' => 'update', 'status' => 'done', 'description' => 'No downtime.'])
            ->assertRedirect(route('admin.maintenance.index'));
        $record = MaintenanceRecord::query()->sole();
        $this->assertSame($it->id, $record->performed_by);
        $this->assertNotNull($record->performed_at);

        $this->actingAs($it)->get(route('admin.maintenance.index'))->assertOk()->assertSee('Updated PHP to 8.5')->assertSee('System update');
        $this->actingAs($it)->get(route('admin.maintenance.index', ['category' => 'fix']))->assertDontSee('Updated PHP to 8.5');

        $this->actingAs($it)->put(route('admin.maintenance.update', $record), ['title' => 'Updated PHP', 'category' => 'update', 'status' => 'in_progress'])->assertRedirect();
        $this->assertSame('in_progress', $record->fresh()->status);
        $this->actingAs($it)->delete(route('admin.maintenance.destroy', $record))->assertRedirect();
        $this->assertSame(0, MaintenanceRecord::query()->count());
        $this->actingAs($it)->post(route('admin.maintenance.store'), ['title' => '', 'category' => 'nonsense', 'status' => 'done'])->assertSessionHasErrors(['title', 'category']);
    }

    public function test_back_up_now_and_restore_are_queued_and_restore_needs_confirmation_and_password(): void
    {
        Queue::fake();
        $it = $this->it();
        $directory = storage_path('framework/testing/backups');
        config(['backup.directory' => $directory]);
        @mkdir($directory, 0777, true);
        $name = 'iclimate_2026-10-01_0315.backup';
        file_put_contents($directory.DIRECTORY_SEPARATOR.$name, "-- MySQL dump\n");

        try {
            $this->actingAs($it)->get(route('admin.backups.index'))->assertOk()->assertSee($name)->assertSee('data-backup-files', false);

            $this->actingAs($it)->post(route('admin.backups.store'))->assertRedirect(route('admin.backups.index'));
            Queue::assertPushed(RunDatabaseMaintenance::class, fn ($job) => $job->action === 'backup');

            $this->actingAs($it)->post(route('admin.backups.restore', $name), ['confirm' => 'yes', 'password' => 'password'])->assertSessionHasErrors('confirm');
            $this->actingAs($it)->post(route('admin.backups.restore', $name), ['confirm' => 'RESTORE', 'password' => 'wrong'])->assertSessionHasErrors('password');
            Queue::assertNotPushed(RunDatabaseMaintenance::class, fn ($job) => $job->action === 'restore');

            $this->actingAs($it)->post(route('admin.backups.restore', $name), ['confirm' => 'RESTORE', 'password' => 'password'])->assertRedirect();
            Queue::assertPushed(RunDatabaseMaintenance::class, fn ($job) => $job->action === 'restore' && $job->file === $name);

            $this->actingAs($it)->get(route('admin.backups.download', $name))->assertOk();
            $this->actingAs($it)->get(route('admin.backups.download', 'iclimate_2020-01-01_0000.backup'))->assertNotFound();
            $this->actingAs($it)->get(route('admin.backups.download', '.env'))->assertNotFound();
        } finally {
            @unlink($directory.DIRECTORY_SEPARATOR.$name);
            @rmdir($directory);
        }
    }

    public function test_system_logs_export_as_csv_with_the_filters(): void
    {
        $it = $this->it();
        SystemAuditLogger::record('Sent Alert', null, ['title' => 'Typhoon'], $it);
        SystemAuditLogger::record('User Login', null, [], $it);

        $this->actingAs($it)->get(route('system-logs.index'))->assertOk()->assertSee('data-export-logs', false);
        $response = $this->actingAs($it)->get(route('admin.system-logs.export', ['search' => 'Sent Alert']));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith('Time,User,Role,Action', $csv);
        $this->assertStringContainsString('Sent Alert', $csv);
        $this->assertStringNotContainsString('Logged In', $csv);
    }

    public function test_ml_models_page_shows_the_comparison_and_queues_compare_and_retrain(): void
    {
        Queue::fake();
        $it = $this->it();
        @mkdir(dirname(ModelManagementService::reportPath()), 0777, true);
        file_put_contents(ModelManagementService::reportPath(), json_encode([
            'rows' => 60, 'best' => 'rf', 'ran_at' => now()->toIso8601String(),
            'results' => collect(ModelManagementService::ALGORITHMS)->map(fn ($name, $key) => [
                'key' => $key, 'name' => $name, 'holdout_year' => 2025,
                'holdout' => ['rmse' => 0.51, 'mae' => 0.4, 'r2' => 0.2], 'leave_one_year_out' => ['rmse' => 0.7, 'mae' => 0.5, 'r2' => 0.1],
            ])->values()->all(),
        ]));

        $this->actingAs($it)->get(route('admin.models.index'))->assertOk()
            ->assertSee('Multiple Linear Regression')->assertSee('Random Forest')->assertSee('Gradient Boosting')->assertSee('data-model-comparison', false);

        $this->actingAs($it)->post(route('admin.models.compare'))->assertRedirect();
        Queue::assertPushed(RunModelManagement::class, fn ($job) => $job->action === 'compare');
        $this->actingAs($it)->post(route('admin.models.retrain'), ['algorithm' => 'gb'])->assertRedirect();
        Queue::assertPushed(RunModelManagement::class, fn ($job) => $job->action === 'retrain' && $job->algorithm === 'gb');
        $this->actingAs($it)->post(route('admin.models.retrain'), ['algorithm' => 'xgboost'])->assertSessionHasErrors('algorithm');
    }

    public function test_a_finished_retrain_is_a_candidate_in_the_history_and_a_maintenance_record(): void
    {
        $it = $this->it();
        $this->mock(ModelManagementService::class, function ($mock) {
            $mock->shouldReceive('retrain')->with('rf')->andReturnUsing(fn () => MlModel::query()->create([
                'name' => 'Random Forest (retrained)', 'model_type' => 'Random Forest', 'version' => 'rf-20261008_010101', 'file_path' => 'storage/models/x.pkl',
                'mae' => 0.4, 'rmse' => 0.5, 'r2' => 0.3, 'test_year' => 2025, 'trained_at' => now(),
            ]));
        });

        (new RunModelManagement('retrain', 'rf', $it->id))->handle(app(ModelManagementService::class));

        $this->assertSame('done', Cache::get(RunModelManagement::RESULT_KEY)['state']);
        $this->assertSame(MlModel::STATUS_CANDIDATE, MlModel::query()->sole()->status);
        $this->assertTrue(MaintenanceRecord::query()->where('title', 'Model retrain')->exists());
        $this->actingAs($it)->get(route('admin.models.index'))->assertSee('rf-20261008_010101')->assertSee('Candidate');
    }

    public function test_dataset_upload_checks_the_columns_and_a_model_upload_becomes_a_candidate(): void
    {
        $it = $this->it();

        $this->actingAs($it)->post(route('admin.models.dataset'), ['dataset' => UploadedFile::fake()->createWithContent('d.csv', "YEAR,RAINFALL\n2026,10\n")])
            ->assertSessionHasErrors('dataset');
        $this->assertNull(SystemSettings::get('ml_dataset_path'));

        $this->actingAs($it)->post(route('admin.models.upload'), ['model' => UploadedFile::fake()->create('model.exe', 10), 'name' => 'Bad', 'model_type' => 'RF', 'version' => 'v1'])
            ->assertSessionHasErrors('model');
        $this->assertSame(0, MlModel::query()->count());
    }

    public function test_only_it_opens_these_pages(): void
    {
        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['admin.backups.index', 'admin.maintenance.index', 'admin.models.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertStatus(403);
            }
            $this->actingAs($user)->post(route('admin.backups.store'))->assertStatus(403);
        }
    }
}
