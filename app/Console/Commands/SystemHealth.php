<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One health check for monitoring (systemd timer, cron, uptime monitor): database, migrations, ML service, Reverb,
 * queue, scheduler and disk. Prints one line per check with what to do; exits 1 when anything FAILs, so a monitor
 * can alert. With --log every FAIL is also written to the application log (the scheduler runs it that way).
 * Never prints credentials, hosts' passwords or personal data.
 */
class SystemHealth extends Command
{
    protected $signature = 'iclimate:health {--json : Machine-readable output} {--log : Write FAIL lines to the application log}';

    protected $description = 'Check database, migrations, ML service, Reverb, queue, scheduler and disk; exit 1 on any failure.';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->check('database', function (): array {
            $started = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $started) * 1000);

            return [$ms > 1000 ? 'WARN' : 'OK', "connected in {$ms} ms".($ms > 1000 ? ' — slow; check MariaDB load' : '')];
        }, 'MariaDB is not answering. Start it (XAMPP/systemctl start mariadb) and check its error log.');

        $this->check('migrations', function (): array {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return ['FAIL', 'no migrations table — run the deployment migration'];
            }
            $ran = $migrator->getRepository()->getRan();
            $pending = collect($migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')]))->keys()->diff($ran);

            return $pending->isEmpty() ? ['OK', 'schema is up to date'] : ['WARN', $pending->count().' pending: run the approved migration runbook (deploy/hostinger/phase-18-deployment-runbook.md)'];
        });

        $this->check('ml-service', function (): array {
            $url = rtrim((string) config('services.farming_ai.url'), '/').'/health';
            $body = Http::timeout(3)->connectTimeout(2)->acceptJson()->get($url)->throw()->json();
            $models = (array) ($body['models_loaded'] ?? []);

            return ($body['status'] ?? null) === 'ok' && in_array('rice', $models, true)
                ? ['OK', 'models loaded: '.implode(', ', $models)]
                : ['FAIL', 'ML service is degraded (models not loaded) — check python_scripts and the model files'];
        }, 'ML service is not reachable — start it (iclimate-python.service / local supervisor "ml").');

        $this->check('reverb', function (): array {
            $host = (string) config('reverb.servers.reverb.host', '127.0.0.1');
            $host = in_array($host, ['0.0.0.0', ''], true) ? '127.0.0.1' : $host;
            Http::timeout(3)->connectTimeout(2)->get('http://'.$host.':'.(int) config('reverb.servers.reverb.port', 8080).'/up')->throw();

            return ['OK', 'WebSocket server answering'];
        }, 'Reverb is not answering — live updates are off. Start it (iclimate-reverb.service / supervisor "reverb").');

        $this->check('queue', function (): array {
            if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
                return ['OK', 'queue driver '.config('queue.default')];
            }
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            $waiting = $oldest ? time() - (int) $oldest : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count() : 0;
            if ($waiting > 600) {
                return ['FAIL', 'oldest job waiting '.intdiv($waiting, 60).' min — the queue worker is not running or stuck; restart it'];
            }

            return [$failed > 0 ? 'WARN' : 'OK', DB::table('jobs')->count().' queued, '.$failed.' failed in the last hour'.($failed ? ' — see php artisan queue:failed' : '')];
        });

        $this->check('scheduler', function (): array {
            if (! Schema::hasTable('weather_fetch_logs')) {
                return ['WARN', 'no weather_fetch_logs table to judge the scheduler by'];
            }
            $last = DB::table('weather_fetch_logs')->max('created_at');
            $age = $last ? now()->diffInMinutes(\Illuminate\Support\Carbon::parse($last), true) : null;

            return $age !== null && $age <= 90
                ? ['OK', 'last scheduled weather fetch '.(int) $age.' min ago']
                : ['FAIL', ($age === null ? 'no scheduled weather fetch recorded' : 'last scheduled weather fetch '.(int) $age.' min ago').' — the scheduler is not running (one schedule:work, or the systemd timer)'];
        });

        $this->check('accounts', function (): array {
            if (\App\Services\Accounts\PasswordChange::lockdown()) {
                return ['OK', 'shared-password lockdown on'];
            }
            $copied = (int) DB::table('users')->select('password')->groupBy('password')->havingRaw('count(*) > 1')->get()->count();

            return ['WARN', 'shared-password lockdown OFF (local sign-ins with a shared password still work; network ones are refused)'
                .($copied > 0 ? '; '.$copied.' password hash(es) are copied across accounts' : '').' — see the release handoff, step 6'];
        });

        $this->check('disk', function (): array {
            $free = @disk_free_space(storage_path());
            if ($free === false) {
                return ['WARN', 'free space unknown'];
            }
            $gb = round($free / 1024 ** 3, 1);

            return [$gb < 1 ? 'FAIL' : ($gb < 5 ? 'WARN' : 'OK'), $gb.' GB free for storage/'.($gb < 5 ? ' — free space or rotate logs and old backups' : '')];
        });

        return $this->report();
    }

    private function check(string $name, \Closure $probe, string $onError = 'check failed'): void
    {
        try {
            [$status, $detail] = $probe();
        } catch (Throwable) {
            [$status, $detail] = ['FAIL', $onError];   // the exception text can carry hosts or credentials: not shown
        }
        $this->results[] = ['check' => $name, 'status' => $status, 'detail' => $detail];
    }

    private function report(): int
    {
        $failed = collect($this->results)->contains('status', 'FAIL');
        if ($this->option('log')) {
            // Only failures go to the application log (WARNs stay in this command's own output / automation log).
            foreach ($this->results as $r) {
                if ($r['status'] === 'FAIL') {
                    Log::log('error', 'iClimate health FAIL '.$r['check'].': '.$r['detail']);
                }
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => ! $failed, 'checks' => $this->results], JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($this->results as $r) {
                $this->line(str_pad($r['status'], 5).' '.str_pad($r['check'], 11).' '.$r['detail']);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
