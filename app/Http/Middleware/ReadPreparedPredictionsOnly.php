<?php

namespace App\Http\Middleware;

use App\Services\Prediction\PredictionReadMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No page or API request may train or rebuild a machine-learning model.
 *
 * While a request is handled, the prediction path may only READ forecasts
 * that were prepared beforehand (the 02:00 refresh, the 02:15 warm-up, or a
 * queued job). If one is missing it queues the work and the page shows "not
 * available yet" instead of waiting 20+ seconds for a Random Forest to train.
 *
 * Commands and queue workers are not web requests and are unaffected, so the
 * scheduled jobs still do the heavy work.
 */
class ReadPreparedPredictionsOnly
{
    public function __construct(private readonly PredictionReadMode $readMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->readMode->enter();

        try {
            return $next($request);
        } finally {
            $this->readMode->leave();
        }
    }
}
