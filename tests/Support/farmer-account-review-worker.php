<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.sqlite.busy_timeout' => 5000]);

[$script, $database, $maoId, $farmerId, $decision, $slot] = $argv;
$request = Illuminate\Http\Request::create('/mao/farmer-approvals/'.$farmerId, 'POST');
$request->setUserResolver(fn () => App\Models\User::query()->findOrFail($maoId));
file_put_contents($database.'.ready.'.$slot, 'ready');
$deadline = microtime(true) + 15;
while (! is_file($database.'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent review barrier timed out.');
    }
    usleep(10000);
}

try {
    $app->make(App\Services\Accounts\FarmerAccountReviewService::class)->decide(
        $request, (int) $farmerId, $decision, 0, $decision === App\Models\User::STATUS_REJECTED ? 'incomplete' : null,
    );
    echo '200';
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    echo $exception->getStatusCode();
}
