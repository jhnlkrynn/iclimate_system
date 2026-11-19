<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FarmerAccountReviewConcurrencyTest extends TestCase
{
    public function test_simultaneous_approve_and_reject_requests_commit_exactly_one_decision(): void
    {
        $path = sys_get_temp_dir().'/iclimate-account-review-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($path);
        config(['database.connections.approval_concurrency' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => true, 'busy_timeout' => 5000]]);
        $workers = [];
        try {
            Artisan::call('migrate', ['--database' => 'approval_concurrency', '--force' => true]);
            $mao = User::factory()->connection('approval_concurrency')->maoPersonnel()->create();
            $other = User::factory()->connection('approval_concurrency')->maoPersonnel()->create();
            $farmer = User::factory()->connection('approval_concurrency')->farmer()->create(['status' => User::STATUS_PENDING]);
            foreach ([[$mao, User::STATUS_APPROVED], [$other, User::STATUS_REJECTED]] as $slot => [$reviewer, $decision]) {
                $workers[$slot] = new Process([PHP_BINARY, base_path('tests/Support/farmer-account-review-worker.php'), $path, (string) $reviewer->id, (string) $farmer->id, $decision, (string) $slot], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null', 'MAIL_MAILER' => 'array',
                    'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null',
                ]);
                $workers[$slot]->setTimeout(20)->start();
            }
            $deadline = microtime(true) + 10;
            while (! is_file($path.'.ready.0') || ! is_file($path.'.ready.1')) {
                $this->assertLessThan($deadline, microtime(true), implode("\n", array_map(fn ($worker) => $worker->getErrorOutput(), $workers)));
                usleep(10000);
            }
            touch($path.'.go');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $results[] = trim($worker->getOutput());
            }
            sort($results);
            $this->assertSame(['200', '409'], $results);
            $db = DB::connection('approval_concurrency');
            $this->assertSame(1, $db->table('farmer_account_reviews')->where('user_id', $farmer->id)->count());
            $decision = $db->table('farmer_account_reviews')->where('user_id', $farmer->id)->first();
            $this->assertSame($decision->decision, $db->table('users')->where('id', $farmer->id)->value('status'));
            $this->assertSame(1, $db->table('notifications')->where('user_id', $farmer->id)->where('category', 'account')->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }
            DB::purge('approval_concurrency');
            foreach (glob($path.'*') as $file) {
                unlink($file);
            }
        }
    }
}
