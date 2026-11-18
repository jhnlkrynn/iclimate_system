<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class FlagDefaultPasswords extends Command
{
    protected $signature = 'iclimate:flag-default-passwords {--apply : Mark suspected accounts as requiring a password change}';
    protected $description = 'Audit accounts for shared or configured default passwords (dry-run by default)';

    public function handle(): int
    {
        $users = User::withDeleted()->get(['id', 'role', 'password', 'must_change_password']);
        $groups = $users->groupBy('password')->filter(fn ($group) => $group->count() > 1);
        $known = array_values(array_filter((array) config('iclimate.known_default_passwords', [])));
        $suspects = collect();
        foreach ($groups as $group) $suspects = $suspects->merge($group);
        foreach ($users as $user) foreach ($known as $plain) if (Hash::check($plain, $user->password)) { $suspects->push($user); break; }
        $suspects = $suspects->unique('id');
        $this->info(($this->option('apply') ? 'Applying' : 'Dry-run: found').' '.$suspects->count().' account(s) requiring review.');
        foreach ($suspects->groupBy('role') as $role => $group) $this->line($role.': '.$group->count());
        if ($this->option('apply') && $suspects->isNotEmpty()) {
            User::query()->whereIn('id', $suspects->pluck('id'))->update(['must_change_password' => true]);
            // Their open sessions were opened with a shared password: end them (sign-in now sends them to recovery).
            $suspects->each(fn (User $user) => \App\Services\Accounts\PasswordChange::endOtherSessions($user));
            SystemAuditLogger::record('Flagged accounts with shared or known default passwords', null, ['account_count' => $suspects->count(), 'roles' => $suspects->groupBy('role')->map->count()->all()]);
            $this->info('Accounts flagged; no passwords or hashes were displayed.');
        }
        return self::SUCCESS;
    }
}
