<?php

namespace App\Services\MachineLearning;

use App\Models\MlModel;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Tracks model files and decides which one is live. Never trains, loads or
 * evaluates a model, and never activates anything on its own: a model only
 * becomes active through activate(), called by an authorised person after it
 * has been validated.
 */
class ModelRegistryService
{
    public function __construct(private readonly RuntimeModelClient $runtime) {}

    /**
     * The active model whose file is actually present, or null. Callers must
     * treat null as "use the configured default", which keeps prediction
     * working when the registry is empty or the file has gone missing.
     */
    public function resolveActive(string $purpose = MlModel::PURPOSE_RICE_YIELD): ?MlModel
    {
        try {
            $model = MlModel::query()
                ->where('purpose', $purpose)
                ->where('is_active', true)
                ->first();
        } catch (Throwable) {
            // Table missing (not migrated yet) or database unavailable.
            return null;
        }

        if ($model === null) {
            return null;
        }

        if (! $model->fileExists()) {
            Log::warning('Active ML model file is unavailable; using configured default.', ['model_id' => $model->id]);

            return null;
        }

        return $model;
    }

    /**
     * candidate -> validated. Requires a present file and real recorded
     * metrics; nothing is computed here.
     */
    public function validate(MlModel $model, ?User $actor = null): MlModel
    {
        if ($model->status !== MlModel::STATUS_CANDIDATE) {
            throw new RuntimeException('Only a candidate model can be validated.');
        }

        $problems = [];

        if (! $model->fileExists() || filesize($model->absolutePath()) <= 0) {
            $problems[] = 'the model file is missing or empty';
        }

        foreach (['mae', 'rmse', 'r2'] as $metric) {
            if ($model->{$metric} === null) {
                $problems[] = strtoupper($metric).' has not been recorded';
            }
        }

        if ($problems !== []) {
            throw new RuntimeException('Cannot validate: '.implode('; ', $problems).'.');
        }

        $model->update(['status' => MlModel::STATUS_VALIDATED]);
        $this->audit('ml_model.validated', $actor, $model, MlModel::STATUS_CANDIDATE, MlModel::STATUS_VALIDATED);

        return $model->refresh();
    }

    public function markFailed(MlModel $model, ?User $actor = null, string $reason = ''): MlModel
    {
        if ($model->is_active) {
            throw new RuntimeException('The active model cannot be marked failed; activate another model first.');
        }

        $from = $model->status;
        $model->update(['status' => MlModel::STATUS_FAILED]);
        $this->audit('ml_model.failed', $actor, $model, $from, MlModel::STATUS_FAILED, ['reason' => $reason]);

        return $model->refresh();
    }

    /**
     * validated -> active, retiring the current active model, all in one
     * transaction. Any failure rolls both changes back. A retired model may
     * be re-activated (rollback) only if it has recorded metrics, meaning it
     * was validated at some point.
     */
    public function activate(MlModel $model, ?User $actor = null): MlModel
    {
        $previous = null;
        $switched = false;

        try {
            return $this->activateInTransaction($model, $actor, $previous, $switched);
        } catch (Throwable $e) {
            // The transaction has rolled back, so the registry still names the
            // previous model. If Python was already switched, put it back so
            // registry and runtime stay in step.
            $reverted = $switched ? $this->revertRuntime($previous) : null;

            $this->auditFailure($model, $actor, $e, $switched, $reverted);

            throw $e;
        }
    }

    /**
     * The registry change and the Python switch share one transaction: the
     * database is only committed if the service verifiably serves the new model.
     */
    private function activateInTransaction(MlModel $model, ?User $actor, ?MlModel &$previous, bool &$switched): MlModel
    {
        return DB::transaction(function () use ($model, $actor, &$previous, &$switched): MlModel {
            $target = MlModel::query()->lockForUpdate()->findOrFail($model->id);

            if ($target->is_active) {
                throw new RuntimeException('This model is already active.');
            }

            $activatable = $target->status === MlModel::STATUS_VALIDATED
                || ($target->status === MlModel::STATUS_RETIRED && $target->mae !== null);

            if (! $activatable) {
                throw new RuntimeException('Only a validated model can be activated.');
            }

            if (! $target->fileExists()) {
                throw new RuntimeException('The model file is not available, so it cannot be activated.');
            }

            $previous = MlModel::query()
                ->where('purpose', $target->purpose)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            // Retire first so the unique active slot is free.
            $previous?->update(['is_active' => false, 'status' => MlModel::STATUS_RETIRED]);

            $from = $target->status;
            $target->update(['is_active' => true, 'status' => MlModel::STATUS_ACTIVE]);

            $runtime = null;

            if (config('prediction.rice_yield.sync_runtime', true)) {
                // Mark first: even a failed request may have swapped the model.
                $switched = true;
                $runtime = $this->runtime->switchTo($target);
            }

            $this->audit('ml_model.activated', $actor, $target, $from, MlModel::STATUS_ACTIVE, [
                'previous_active_id' => $previous?->id,
                'previous_active_version' => $previous?->version,
                'runtime_verified' => $runtime !== null,
                'runtime_loaded_file' => $runtime['filename'] ?? null,
                'runtime_sklearn_version' => $runtime['sklearn_version'] ?? null,
            ]);

            return $target->refresh();
        });
    }

    /**
     * Best effort: switch Python back to the previous registry model.
     */
    private function revertRuntime(?MlModel $previous): bool
    {
        if ($previous === null) {
            return false;
        }

        try {
            $this->runtime->switchTo($previous);

            return true;
        } catch (Throwable $e) {
            Log::critical('ML runtime could not be restored to the previous model; registry and service may be out of sync.', [
                'previous_model_id' => $previous->id,
            ]);

            return false;
        }
    }

    private function auditFailure(MlModel $model, ?User $actor, Throwable $e, bool $switched, ?bool $reverted): void
    {
        Log::warning('ML model activation failed.', ['model_id' => $model->id, 'reason' => $e->getMessage()]);

        try {
            SystemLog::create([
                'user_id' => $actor?->id,
                'action' => 'ml_model.activation_failed',
                'details' => json_encode([
                    'model_id' => $model->id,
                    'purpose' => $model->purpose,
                    'version' => $model->version,
                    'file' => $model->fileName(),
                    'reason' => $e instanceof RuntimeException ? $e->getMessage() : 'Unexpected error.',
                    'runtime_switch_attempted' => $switched,
                    'runtime_restored_to_previous' => $reverted,
                    'actor' => $actor ? ['id' => $actor->id, 'role' => $actor->role] : null,
                ]),
            ]);
        } catch (Throwable) {
            // Never let audit trouble hide the original failure.
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(string $action, ?User $actor, MlModel $model, string $from, string $to, array $extra = []): void
    {
        SystemLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'details' => json_encode([
                'model_id' => $model->id,
                'purpose' => $model->purpose,
                'version' => $model->version,
                'file' => $model->fileName(),
                'from_status' => $from,
                'to_status' => $to,
                'metrics' => ['mae' => $model->mae, 'rmse' => $model->rmse, 'r2' => $model->r2],
                'actor' => $actor ? ['id' => $actor->id, 'role' => $actor->role] : null,
                ...$extra,
            ]),
        ]);
    }
}
