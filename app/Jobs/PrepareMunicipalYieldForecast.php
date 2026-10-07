<?php

namespace App\Jobs;

use App\Services\Prediction\MunicipalYieldForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareMunicipalYieldForecast implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;
    public int $uniqueFor = 1800;

    public function __construct(public readonly string $targetMonth) {}

    public function uniqueId(): string
    {
        return $this->targetMonth;
    }

    public function handle(MunicipalYieldForecastService $forecast): void
    {
        $forecast->prepare(CarbonImmutable::createFromFormat('Y-m-d', $this->targetMonth, 'Asia/Manila'));
    }
}
