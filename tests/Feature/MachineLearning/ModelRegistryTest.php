<?php

namespace Tests\Feature\MachineLearning;

use App\Models\MlModel;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\MachineLearning\ModelRegistryService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\MachineLearning\RuntimeModelClient;
use App\Services\SystemHealthService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ModelRegistryTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/registry-'.uniqid());
        File::ensureDirectoryExists($this->dir);

        config([
            'services.farming_ai.url' => 'http://ml.test',
            'services.farming_ai.admin_token' => 'test-token',
        ]);
        $this->fakeRuntime();
    }

    /** What the fake Python service currently has loaded (null = nothing). */
    private ?array $loaded = null;

    /** 'ok' | 'fail' (500 on switch) | 'ignore' (200 but keeps old model) | 'down' (unreachable) */
    private string $runtimeMode = 'ok';

    /** @var list<string> */
    private array $switchRequests = [];

    private function fakeRuntime(): void
    {
        $info = fn () => $this->loaded === null
            ? Http::response(['loaded' => false], 503)
            : Http::response($this->loaded + ['loaded' => true, 'sklearn_version' => '1.6.1', 'loaded_at' => now()->toIso8601String()]);

        Http::fake([
            'ml.test/model-info' => function () use ($info) {
                if ($this->runtimeMode === 'down') {
                    throw new ConnectionException('refused');
                }

                return $info();
            },
            'ml.test/model/switch' => function (Request $request) {
                if ($this->runtimeMode === 'down') {
                    throw new ConnectionException('refused');
                }

                $this->switchRequests[] = basename($request['path']);

                if ($this->runtimeMode === 'fail') {
                    return Http::response(['detail' => 'boom'], 500);
                }

                if ($this->runtimeMode !== 'ignore') {
                    $this->loaded = [
                        'filename' => basename($request['path']),
                        'sha256' => $request['sha256'] ?? null,
                        'version' => $request['version'] ?? null,
                    ];
                }

                return Http::response(['loaded' => true]);
            },
        ]);
    }

    private function runtimeServes(MlModel $model): void
    {
        $this->loaded = [
            'filename' => $model->fileName(),
            'sha256' => $model->compatibility['sha256'],
            'version' => $model->version,
        ];
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function make(string $version, array $overrides = []): MlModel
    {
        $file = $this->dir.'/rf_v'.$version.'.pkl';
        file_put_contents($file, 'bytes-'.$version);

        return MlModel::create([
            'name' => 'Rice yield '.$version,
            'model_type' => 'Random Forest Regressor',
            'version' => $version,
            'target_variable' => 'AVERAGE_YIELD',
            'file_path' => $file,
            'training_years' => [2020, 2021, 2022, 2023, 2024],
            'test_year' => 2025,
            'feature_list' => ['RAINFALL', 'TMAX'],
            'hyperparameters' => ['n_estimators' => 500],
            'compatibility' => ['sha256' => hash('sha256', 'bytes-'.$version), 'estimator_class' => 'RandomForestRegressor'],
            'mae' => 0.3944,
            'rmse' => 0.469,
            'r2' => -1.1043,
            ...$overrides,
        ]);
    }

    private function activeOne(string $version = '1.0.0'): MlModel
    {
        $model = $this->make($version, ['status' => 'active', 'is_active' => true]);
        $this->runtimeServes($model);

        return $model;
    }

    public function test_only_one_model_can_be_active_at_the_database_level(): void
    {
        $this->activeOne('1.0.0');

        $this->expectException(QueryException::class);
        $this->activeOne('1.1.0');
    }

    public function test_candidate_cannot_activate_before_validation(): void
    {
        $this->activeOne();
        $candidate = $this->make('1.1.0');

        try {
            app(ModelRegistryService::class)->activate($candidate);
            $this->fail('Expected activation to be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('validated', $e->getMessage());
        }

        $this->assertSame('candidate', $candidate->refresh()->status);
        $this->assertSame('1.0.0', MlModel::where('is_active', true)->value('version'));
    }

    public function test_failed_model_cannot_activate(): void
    {
        $failed = $this->make('1.1.0', ['status' => 'failed']);

        $this->expectException(RuntimeException::class);
        app(ModelRegistryService::class)->activate($failed);
    }

    public function test_validation_requires_recorded_metrics_and_never_invents_them(): void
    {
        $noMetrics = $this->make('1.1.0', ['mae' => null, 'rmse' => null, 'r2' => null]);

        try {
            app(ModelRegistryService::class)->validate($noMetrics);
            $this->fail('Expected validation to be refused.');
        } catch (RuntimeException) {
        }

        $noMetrics->refresh();
        $this->assertSame('candidate', $noMetrics->status);
        $this->assertNull($noMetrics->mae);
    }

    public function test_activation_swaps_active_model_and_writes_audit_log(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0');
        $registry = app(ModelRegistryService::class);

        $registry->validate($new, $it);
        $registry->activate($new, $it);

        $this->assertSame('retired', $old->refresh()->status);
        $this->assertFalse($old->is_active);
        $this->assertSame('active', $new->refresh()->status);
        $this->assertTrue($new->is_active);
        $this->assertSame(1, MlModel::where('is_active', true)->count());

        $log = SystemLog::where('action', 'ml_model.activated')->firstOrFail();
        $details = json_decode($log->details, true);
        $this->assertSame($it->id, $log->user_id);
        $this->assertSame('1.1.0', $details['version']);
        $this->assertSame('validated', $details['from_status']);
        $this->assertSame('1.0.0', $details['previous_active_version']);
        $this->assertTrue($details['runtime_verified']);
        $this->assertSame('rf_v1.1.0.pkl', $details['runtime_loaded_file']);
        $this->assertSame('rf_v1.1.0.pkl', $this->loaded['filename']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/model/switch')
            && $r->header('X-Model-Admin-Token') === ['test-token']
            && $r['sha256'] === hash('sha256', 'bytes-1.1.0'));
        $this->assertSame(-1.1043, $details['metrics']['r2']);
        $this->assertStringNotContainsString($this->dir, $log->details);
    }

    public function test_activation_is_rolled_back_when_it_fails_midway(): void
    {
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);

        // The audit write is the last step; make it fail after both status changes.
        SystemLog::creating(fn () => throw new RuntimeException('audit failure'));

        try {
            app(ModelRegistryService::class)->activate($new);
            $this->fail('Expected failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit failure', $e->getMessage());
        }

        $this->assertTrue($old->refresh()->is_active);
        $this->assertSame('active', $old->status);
        $this->assertSame('validated', $new->refresh()->status);
        $this->assertFalse($new->is_active);

        // Python had already been switched, so it must have been put back.
        $this->assertSame(['rf_v1.1.0.pkl', 'rf_v1.0.0.pkl'], $this->switchRequests);
        $this->assertSame('rf_v1.0.0.pkl', $this->loaded['filename']);
    }

    /** Asserts the registry still names $old, $new is untouched, and the failure was logged. */
    private function assertActivationFailedCleanly(MlModel $old, MlModel $new, string $reasonFragment): void
    {
        $this->assertTrue($old->refresh()->is_active);
        $this->assertSame('active', $old->status);
        $this->assertSame('validated', $new->refresh()->status);
        $this->assertFalse($new->is_active);
        $this->assertSame(0, SystemLog::where('action', 'ml_model.activated')->count());

        $log = SystemLog::where('action', 'ml_model.activation_failed')->firstOrFail();
        $this->assertStringContainsString($reasonFragment, $log->details);
        $this->assertStringNotContainsString('ml.test', $log->details);
        $this->assertStringNotContainsString($this->dir, $log->details);
    }

    public function test_failed_reload_keeps_previous_model_active_and_logs(): void
    {
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);
        $this->runtimeMode = 'fail';

        try {
            app(ModelRegistryService::class)->activate($new);
            $this->fail('Expected activation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('failed to load', $e->getMessage());
        }

        $this->assertActivationFailedCleanly($old, $new, 'failed to load');
        $this->assertSame('rf_v1.0.0.pkl', $this->loaded['filename'], 'runtime untouched');
    }

    public function test_mismatched_runtime_model_blocks_activation_and_restores_previous(): void
    {
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);
        $this->runtimeMode = 'ignore'; // service says OK but keeps serving the old model

        try {
            app(ModelRegistryService::class)->activate($new);
            $this->fail('Expected activation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not running the expected model', $e->getMessage());
        }

        $this->assertActivationFailedCleanly($old, $new, 'not running the expected model');
        $details = json_decode(SystemLog::where('action', 'ml_model.activation_failed')->value('details'), true);
        $this->assertTrue($details['runtime_switch_attempted']);
        $this->assertTrue($details['runtime_restored_to_previous']);
    }

    public function test_unreachable_python_service_leaves_registry_unchanged(): void
    {
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);
        $this->runtimeMode = 'down';

        try {
            app(ModelRegistryService::class)->activate($new);
            $this->fail('Expected activation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('could not be reached', $e->getMessage());
        }

        $this->assertActivationFailedCleanly($old, $new, 'could not be reached');
    }

    public function test_activation_is_refused_without_a_configured_switch_token_and_sends_nothing(): void
    {
        config(['services.farming_ai.admin_token' => null]);
        $old = $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);

        try {
            app(ModelRegistryService::class)->activate($new);
            $this->fail('Expected activation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        $this->assertActivationFailedCleanly($old, $new, 'not configured');
        $this->assertSame([], $this->switchRequests);
    }

    public function test_activation_does_not_touch_python_when_sync_is_disabled(): void
    {
        config(['prediction.rice_yield.sync_runtime' => false]);
        $this->activeOne('1.0.0');
        $new = $this->make('1.1.0', ['status' => 'validated']);

        app(ModelRegistryService::class)->activate($new);

        $this->assertTrue($new->refresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_model_info_is_reported_by_the_client(): void
    {
        $model = $this->activeOne('1.0.0');

        $info = app(RuntimeModelClient::class)->info();

        $this->assertSame('rf_v1.0.0.pkl', $info['filename']);
        $this->assertSame('1.6.1', $info['sklearn_version']);
        $this->assertArrayHasKey('loaded_at', $info);
        $this->assertTrue(app(RuntimeModelClient::class)->matches($model, $info));
    }

    /** @return array<string, mixed> */
    private function syncCheck(): array
    {
        return collect(app(SystemHealthService::class)->checks())->keyBy('key')['model-sync'];
    }

    public function test_health_check_reports_sync_mismatch_and_unavailable(): void
    {
        $registryActive = $this->activeOne('1.0.0');
        $other = $this->make('1.1.0', ['status' => 'validated']);

        $check = $this->syncCheck();
        $this->assertSame('Healthy', $check['status']);
        $this->assertSame('Sync', $check['details']['Sync state']);
        $this->assertSame('v1.0.0 (rf_v1.0.0.pkl)', $check['details']['Registry active model']);
        $this->assertStringStartsWith('rf_v1.0.0.pkl', $check['details']['Python loaded model']);

        $this->runtimeServes($other);
        $check = $this->syncCheck();
        $this->assertSame('Needs Attention', $check['status']);
        $this->assertSame('Mismatch', $check['details']['Sync state']);
        $this->assertStringStartsWith('rf_v1.1.0.pkl', $check['details']['Python loaded model']);

        $this->runtimeMode = 'down';
        $check = $this->syncCheck();
        $this->assertSame('Unavailable', $check['status']);
        $this->assertSame('Unavailable', $check['details']['Sync state']);
        $this->assertSame('Unknown', $check['details']['Python loaded model']);

        $this->assertStringNotContainsString($this->dir, json_encode($check));
        $this->assertStringNotContainsString('ml.test', json_encode($check));
        $this->assertTrue($registryActive->refresh()->is_active);
    }

    public function test_health_check_is_unavailable_when_no_model_is_registered(): void
    {
        $check = $this->syncCheck();

        $this->assertSame('Unavailable', $check['status']);
        Http::assertNothingSent();
    }

    public function test_registry_metadata_persists_and_absolute_paths_are_hidden(): void
    {
        $model = $this->make('1.1.0')->fresh();

        $this->assertSame([2020, 2021, 2022, 2023, 2024], $model->training_years);
        $this->assertSame(2025, $model->test_year);
        $this->assertSame(['RAINFALL', 'TMAX'], $model->feature_list);
        $this->assertSame(500, $model->hyperparameters['n_estimators']);
        $this->assertSame(0.3944, $model->mae);
        $this->assertSame(0.469, $model->rmse);
        $this->assertSame(-1.1043, $model->r2);
        $this->assertArrayNotHasKey('file_path', $model->toArray());
        $this->assertSame('rf_v1.1.0.pkl', $model->fileName());
    }

    public function test_prediction_service_uses_active_model_and_falls_back_when_file_is_missing(): void
    {
        config(['prediction.rice_yield.model_path' => 'storage/models/rice_yield_model_final.pkl']);
        $service = app(RiceYieldPredictionService::class);

        $this->assertSame('storage/models/rice_yield_model_final.pkl', $service->modelSource(), 'empty registry');

        $active = $this->activeOne('2.0.0');
        $this->assertSame($active->file_path, $service->modelSource());
        $this->assertStringStartsWith('rice_yield_model_v2.0.0:', $service->modelVersion());

        unlink($active->file_path);
        $this->assertSame('storage/models/rice_yield_model_final.pkl', $service->modelSource(), 'missing file');
        $this->assertNull(app(ModelRegistryService::class)->resolveActive());
    }

    public function test_only_it_expert_can_view_or_change_the_registry(): void
    {
        $model = $this->make('1.1.0');
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('model-registry.index'))->assertForbidden();
            $this->actingAs($user)->post(route('model-registry.validate', $model))->assertForbidden();
            $this->actingAs($user)->post(route('model-registry.activate', $model))->assertForbidden();
        }

        $this->assertSame('candidate', $model->refresh()->status);

        $response = $this->actingAs($it)->get(route('model-registry.index'))->assertOk();
        $response->assertSee('ML Model Registry')->assertSee('rf_v1.1.0.pkl')->assertSee('0.3944');
        $response->assertDontSee($this->dir);

        $this->actingAs($it)->post(route('model-registry.validate', $model))->assertRedirect();
        $this->assertSame('validated', $model->refresh()->status);
        $this->actingAs($it)->post(route('model-registry.activate', $model))->assertRedirect();
        $this->assertTrue($model->refresh()->is_active);
    }
}
