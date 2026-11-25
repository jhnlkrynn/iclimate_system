<?php
namespace Tests\Feature;

use App\Services\AI\AiTimings;
use App\Services\PythonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PythonChatDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_slow_local_inference_cannot_exceed_the_remaining_chat_budget(): void
    {
        if (PHP_OS_FAMILY==='Windows') $this->markTestSkipped('Disposable POSIX executable; target deployment is Ubuntu.');
        $path=tempnam(sys_get_temp_dir(),'iclimate-slow-python-');
        file_put_contents($path,"#!/bin/sh\nsleep 5\n");chmod($path,0700);
        config(['iclimate.python_binary'=>$path,'services.farming_ai.url'=>'http://python.test','ai.response_budget_seconds'=>2]);
        Http::fake(fn()=>throw new ConnectionException('isolated unavailable service'));
        $timings=app(AiTimings::class);$timings->begin();$start=microtime(true);
        try {
            try { app(PythonService::class)->farmingAssistant(['prediction_type'=>'rice_yield','features'=>[]]);$this->fail('Expected safe service failure'); }
            catch (\RuntimeException $exception) { $this->assertStringContainsString('local inference failed',$exception->getMessage()); }
            $this->assertLessThan(3,microtime(true)-$start,'the local fallback must respect the chat deadline');
        } finally { $timings->report();unlink($path); }
    }
}
