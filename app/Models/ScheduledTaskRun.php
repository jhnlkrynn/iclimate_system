<?php

namespace App\Models;

use App\Support\SchemaCache;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Throwable;

class ScheduledTaskRun extends Model
{
    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $fillable = ['task_name', 'started_at', 'finished_at', 'status', 'output_summary'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** Row ids of runs in progress in this process, keyed by the task object. @var array<int, int> */
    private static array $open = [];

    /** Records every scheduled run (start, finish, failure) in the database. Called once from the service provider. */
    public static function listen(): void
    {
        Event::listen(ScheduledTaskStarting::class, fn ($e) => self::safely(function () use ($e) {
            self::$open[spl_object_id($e->task)] = self::query()->create([
                'task_name' => $e->task->getSummaryForDisplay(), 'started_at' => now(), 'status' => self::RUNNING,
            ])->id;
        }));
        Event::listen(ScheduledTaskFinished::class, fn ($e) => self::close($e->task, self::SUCCEEDED, 'Completed in '.round($e->runtime, 1).' s'));
        Event::listen(ScheduledTaskFailed::class, fn ($e) => self::close($e->task, self::FAILED, $e->exception->getMessage()));
    }

    private static function close(object $task, string $status, string $summary): void
    {
        self::safely(function () use ($task, $status, $summary) {
            $id = self::$open[spl_object_id($task)] ?? null;
            unset(self::$open[spl_object_id($task)]);
            $row = $id ? self::query()->find($id) : null;
            $row ??= self::query()->create(['task_name' => $task->getSummaryForDisplay(), 'started_at' => now()]);
            $row->update(['finished_at' => now(), 'status' => $status, 'output_summary' => mb_substr($summary, 0, 500)]);
        });
    }

    private static function safely(callable $fn): void
    {
        try {
            if (SchemaCache::hasTable('scheduled_task_runs')) {
                $fn();
            }
        } catch (Throwable) {
            // A bookkeeping failure must never stop a scheduled task.
        }
    }
}
