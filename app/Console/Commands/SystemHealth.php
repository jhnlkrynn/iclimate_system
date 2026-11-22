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
 * queue, scheduler, backups (on and off this host), the yield model's weather inputs and disk. Prints one line per check with what to do; exits 1 when anything FAILs, so a monitor
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
        $this->results = [];   // one report per run, also when the same command object is run again in one process
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

        // Monitoring added 2026-11-20 (deployment audit): backups on this host and off it, and the weather inputs every
        // yield estimate needs, so a stopped job is reported before a farmer sees "Not enough weather records".
        $this->check('backup', function (): array {
            $status = app(\App\Services\DatabaseBackupService::class)->status();

            return match ($status['state']) {
                'ok' => ['OK', $status['count'].' backup(s); newest '.$status['last']->diffForHumans()],
                'stale' => ['FAIL', 'newest backup is '.$status['last']->diffForHumans().' — iclimate:backup-database is not running (scheduler) or failing; see storage/logs/automation-backup-database.log'],
                default => ['FAIL', 'no valid backup in the backup directory — run php artisan iclimate:backup-database and check its log'],
            };
        }, 'backup directory could not be read — check BACKUP_DIRECTORY and its permissions');

        $this->check('backup-offsite', function (): array {
            if (! \App\Services\OffsiteBackupService::configured()) {
                return ['WARN', 'off-host backups are not configured (BACKUP_OFFSITE_TARGET, BACKUP_ENCRYPTION_KEY_FILE) — a disk failure would lose every backup'];
            }
            $last = \App\Services\OffsiteBackupService::lastCopy();
            $maxAge = (int) config('backup.max_age_hours', 36);
            if ($last === null) {
                return ['FAIL', 'no encrypted off-host copy recorded yet — run php artisan iclimate:backup-offsite'];
            }

            return $last['at']->lt(now()->subHours($maxAge))
                ? ['FAIL', 'last off-host copy '.$last['at']->diffForHumans().' — iclimate:backup-offsite is not running or failing']
                : ['OK', 'encrypted copy '.$last['file'].' sent '.$last['at']->diffForHumans()];
        }, 'off-host backup status could not be read');

        $this->check('yield-inputs', function (): array {
            $now = \Carbon\CarbonImmutable::now('Asia/Manila')->startOfMonth();
            $issues = [];
            $archive = array_keys(\App\Services\Climate\OpenMeteoArchiveMonthly::months());
            $newest = $archive === [] ? null : max($archive);
            $expected = $now->subMonthNoOverflow()->format('Y-m');
            // The archive lags a few days after a month ends, so last month may still be missing early in a month.
            if ($newest === null) {
                $issues[] = 'no past-weather archive — run php artisan iclimate:fetch-climate-archive';
            } elseif ($newest < $now->subMonthsNoOverflow(2)->format('Y-m')) {
                $issues[] = 'past-weather archive ends '.$newest.' (expected '.$expected.') — iclimate:fetch-climate-archive is not running';
            }
            $ready = \App\Models\MonthlyWeatherPrediction::query()->where('status', 'ready')
                ->get(['prediction_year', 'prediction_month'])->map(fn ($r) => sprintf('%04d-%02d', $r->prediction_year, $r->prediction_month))->unique()->all();
            $missing = collect(range(0, 17))->map(fn (int $i): string => $now->addMonthsNoOverflow($i)->format('Y-m'))->diff($ready)->values();
            if ($missing->isNotEmpty()) {
                $issues[] = $missing->count().' of 18 forecast months missing (first '.$missing->first().') — run php artisan iclimate:refresh-predictions';
            }
            $harvest = \App\Support\RiceSeason::harvestMonth($now)->format('Y-m');
            $critical = $newest === null || $missing->contains($now->format('Y-m')) || $missing->contains($harvest);

            return $issues === []
                ? ['OK', 'archive to '.$newest.'; 18 forecast months ready (this season\'s harvest month '.$harvest.')']
                : [$critical ? 'FAIL' : 'WARN', implode('; ', $issues)];
        }, 'yield input check failed — check the monthly_weather_predictions table and storage/app/climate');

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
        } catch (Throwable $caught) {
            \App\Support\SilentFailure::log($caught, 'SystemHealth::check');
            [$status, $detail] = ['FAIL', $onError];   // the exception text can carry hosts or credentials: not shown
        }
        $this->results[] = ['check' => $name, 'status' => $status, 'detail' => $detail];
    }

    /**
     * E-mail the operator when a check FAILs (HEALTH_ALERT_EMAIL), at most once an hour for the same set of failures.
     * Without that setting the FAIL lines only reach the application log. A mail error never fails the health check.
     *
     * @param  list<array{check: string, status: string, detail: string}>  $failures
     */
    private function notifyOperator(array $failures): void
    {
        $to = (string) config('services.health.alert_email', '');
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $key = 'health-alert:'.md5(implode('|', array_column($failures, 'check')));
        try {
            if (! \Illuminate\Support\Facades\Cache::add($key, true, now()->addHour())) {
                return;
            }
            $body = "iClimate health check FAILED on ".config('app.url')." at ".now('Asia/Manila')->toDayDateTimeString().":\n\n"
                .collect($failures)->map(fn (array $r): string => '- '.$r['check'].': '.$r['detail'])->implode("\n")
                ."\n\nRun php artisan iclimate:health on the server for the full report.";
            \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->subject('[iClimate] Health check FAILED: '.implode(', ', array_column($failures, 'check'))));
            $this->line('alert sent to the operator address');
        } catch (Throwable $e) {
            \App\Support\SilentFailure::log($e, 'SystemHealth::notifyOperator');
            Log::warning('iClimate health alert e-mail could not be sent.', ['exception' => $e::class]);
        }
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
        if ($failed && $this->option('log')) {
            $this->notifyOperator(collect($this->results)->where('status', 'FAIL')->values()->all());
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
