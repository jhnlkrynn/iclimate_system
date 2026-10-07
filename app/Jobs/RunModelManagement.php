<?php

namespace App\Jobs;

use App\Models\MaintenanceRecord;
use App\Services\MachineLearning\ModelManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: Compare models / Retrain from ML Model Management, run by the queue worker
 * (training never runs inside a page). The outcome shows on the page and in Maintenance Records.
 */
class RunModelManagement implements ShouldQueue
{
    use Queueable;

    public const RESULT_KEY = 'admin:models:last-result';

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $action, public ?string $algorithm = null, public ?int $userId = null) {}

    public function handle(ModelManagementService $models): void
    {
        Cache::put(self::RESULT_KEY, ['state' => 'running', 'action' => $this->action, 'at' => now()->toIso8601String()], now()->addDay());

        try {
            if ($this->action === 'retrain') {
                $model = $models->retrain((string) $this->algorithm);
                $message = $model->model_type.' retrained: RMSE '.$model->rmse.', MAE '.$model->mae.', R² '.$model->r2.' on '.$model->test_year.'. Saved as a candidate (version '.$model->version.'); validate and activate it on Model Registry.';
            } else {
                $report = $models->compare();
                $best = collect($report['results'])->firstWhere('key', $report['best']);
                $message = 'Compared '.count($report['results']).' algorithms on '.$report['rows'].' months. Lowest leave-one-year-out RMSE: '.$best['name'].'.';
            }
            $state = 'done';
        } catch (Throwable $e) {
            $state = 'failed';
            $message = $e->getMessage();
        }

        Cache::put(self::RESULT_KEY, ['state' => $state, 'action' => $this->action, 'message' => $message, 'at' => now()->toIso8601String()], now()->addDays(7));
        rescue(fn () => MaintenanceRecord::query()->create([
            'title' => ($this->action === 'retrain' ? 'Model retrain' : 'Model comparison').($state === 'done' ? '' : ' (failed)'),
            'category' => 'update',
            'description' => $message,
            'status' => 'done',
            'performed_at' => now(),
            'performed_by' => $this->userId,
        ]), report: false);
    }
}
