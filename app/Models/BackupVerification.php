<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupVerification extends Model
{
    use \App\Models\Concerns\KeepsDatabaseClockColumns;

    /** MariaDB refreshes this column itself on UPDATE (Manila clock); see KeepsDatabaseClockColumns. */
    public const DATABASE_CLOCK_COLUMNS = ['verified_at'];

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    protected $fillable = [
        'backup_filename',
        'backup_size_bytes',
        'backup_sha256',
        'verified_at',
        'status',
        'archive_entry_count',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'backup_size_bytes' => 'integer',
            'archive_entry_count' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
