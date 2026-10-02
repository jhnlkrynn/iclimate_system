<?php

namespace Tests;

use App\Services\Weather\RecentRainService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The farmer dashboard asks Open-Meteo for the last days of rain. A test must never reach the network for it:
        // by default there is no history (the numbers that need it show "—"); a test that wants data binds its own.
        $this->app->bind(RecentRainService::class, fn () => new class extends RecentRainService
        {
            public function history(bool $force = false): array
            {
                return ['daily' => [], 'fetched_at' => null];
            }
        });
    }
}
