<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Break-glass: designate the Municipal Agriculturist (MAO Head) from the server console.
 *
 * Only an MAO Head can manage MAO accounts or pick a new Head in the app, and the IT role that used to do it is gone.
 * A database with no active Head therefore has no in-app way to get one. This command is that way. It needs shell
 * access to the server, shows what it would change, and only writes with --apply. It never creates an account and
 * never touches a password, role or status.
 */
class AssignMaoHead extends Command
{
    protected $signature = 'iclimate:assign-mao-head
        {email : Email of the existing, active MAO Staff account to make the MAO Head}
        {--apply : Make the change (without it, only shows what would change)}';

    protected $description = 'Designate the MAO Head (Municipal Agriculturist) from the console; dry-run unless --apply.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $target = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $target || ! $target->isMao() || ! $target->hasApplicationAccess()) {
            $this->error('No active MAO Staff account has that email. Nothing was changed.');

            return self::FAILURE;
        }

        $current = User::query()->where('role', User::ROLE_MAO)->where('is_mao_head', true)->get(['id', 'name', 'status']);
        $this->line('Current MAO Head: '.($current->isEmpty() ? 'none' : $current->map(fn (User $u) => '#'.$u->id.' '.$u->name.' ('.$u->status.')')->implode(', ')));
        $this->line('New MAO Head:     #'.$target->id.' '.$target->name);

        if ($current->count() === 1 && $current->first()->is($target)) {
            $this->info('This account is already the MAO Head. Nothing was changed.');

            return self::SUCCESS;
        }
        if (! $this->option('apply')) {
            $this->warn('Dry run. Run again with --apply to make this change.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($target): void {
            User::query()->where('role', User::ROLE_MAO)->whereKeyNot($target->id)->where('is_mao_head', true)->get()
                ->each(fn (User $previous) => $previous->forceFill(['is_mao_head' => false])->save());
            $target->forceFill(['is_mao_head' => true])->save();
        });
        SystemAuditLogger::record('Changed MAO Head assignment', null, ['new_head_user_id' => $target->id, 'source' => 'console'], $target);
        $this->info('#'.$target->id.' '.$target->name.' is now the MAO Head.');

        return self::SUCCESS;
    }
}
