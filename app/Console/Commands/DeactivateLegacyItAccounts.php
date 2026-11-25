<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotently deactivates any legacy role='IT Expert' accounts while keeping the rows for audit.
 */
class DeactivateLegacyItAccounts extends Command
{
    protected $signature = 'iclimate:deactivate-it-accounts {--dry-run : Report matching accounts without modifying them}';

    protected $description = "Deactivate (soft-delete) any legacy role='IT Expert' user accounts while keeping rows for audit.";

    public function handle(): int
    {
        $targets = User::withDeleted()
            ->whereRaw('role = ?', ['IT Expert'])
            ->where('status', '!=', User::STATUS_DELETED)
            ->orderBy('id')
            ->get();

        if ($targets->isEmpty()) {
            $this->info('No active legacy IT Expert accounts found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('Found '.$targets->count().' legacy IT Expert account(s) to deactivate:');
        foreach ($targets as $user) {
            $this->line("  #{$user->id} {$user->name} <{$user->email}> (status={$user->status})");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no accounts were modified.');

            return self::SUCCESS;
        }

        $now = now();
        $sessionTable = (string) config('session.table', 'sessions');

        DB::transaction(function () use ($targets, $now, $sessionTable): void {
            foreach ($targets as $user) {
                $user->forceFill([
                    'status_before_delete' => $user->status,
                    'status' => User::STATUS_DELETED,
                    'deleted_reason' => 'other',
                    'deleted_reason_other' => 'Retired legacy IT Expert role; deactivated.',
                    'deleted_note_internal' => 'Deactivated by iclimate:deactivate-it-accounts (IT Expert is not a system role).',
                    'deleted_at' => $now,
                    'remember_token' => null,
                ])->save();

                if (\App\Support\SchemaCache::hasTable($sessionTable)) {
                    DB::table($sessionTable)->where('user_id', $user->id)->delete();
                }
            }
        });

        $this->info('Deactivated '.$targets->count().' legacy IT Expert account(s).');

        return self::SUCCESS;
    }
}
