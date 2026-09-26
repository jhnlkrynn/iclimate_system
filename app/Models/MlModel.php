<?php

namespace App\Models;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    public const PURPOSE_RICE_YIELD = 'rice_yield';

    public const STATUS_CANDIDATE = 'candidate';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_CANDIDATE,
        self::STATUS_VALIDATED,
        self::STATUS_ACTIVE,
        self::STATUS_RETIRED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'purpose', 'name', 'model_type', 'version', 'target_variable',
        'file_path', 'metadata_path', 'training_years', 'test_year',
        'feature_list', 'hyperparameters', 'compatibility',
        'mae', 'rmse', 'r2', 'trained_at', 'status', 'is_active',
    ];

    /** Local filesystem locations never leave the server in serialized output. */
    protected $hidden = ['file_path', 'metadata_path', 'active_slot'];

    protected function casts(): array
    {
        return [
            'training_years' => 'array',
            'feature_list' => 'array',
            'hyperparameters' => 'array',
            'compatibility' => 'array',
            'mae' => 'float',
            'rmse' => 'float',
            'r2' => 'float',
            'trained_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keeps the unique active_slot in step with is_active, and refuses to
        // persist an impossible combination.
        static::saving(function (MlModel $model): void {
            $model->status ??= self::STATUS_CANDIDATE;
            $model->purpose ??= self::PURPOSE_RICE_YIELD;

            if (! in_array($model->status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Unknown model status.');
            }

            if ($model->is_active && $model->status !== self::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Only a model with active status can be marked active.');
            }

            $model->active_slot = $model->is_active ? $model->purpose : null;
        });
    }

    /** File name only, safe to show to users. */
    public function fileName(): string
    {
        return basename(str_replace('\\', '/', (string) $this->file_path));
    }

    public function metadataFileName(): ?string
    {
        return $this->metadata_path ? basename(str_replace('\\', '/', $this->metadata_path)) : null;
    }

    /** Absolute path on this server; internal use only. */
    public function absolutePath(): string
    {
        $path = (string) $this->file_path;

        return preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path) === 1 ? $path : base_path($path);
    }

    public function fileExists(): bool
    {
        return is_file($this->absolutePath());
    }
}
