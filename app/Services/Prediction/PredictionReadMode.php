<?php

namespace App\Services\Prediction;

/**
 * "Read prepared results only". Switched on for the duration of a chat
 * request. While it is on, nothing in the prediction path may train a model or
 * process a dataset: it may only read what the scheduled refresh (or the
 * queue) already prepared. Off everywhere else (commands, queue workers,
 * pages that are allowed to build).
 */
class PredictionReadMode
{
    private int $depth = 0;

    public function enter(): void
    {
        $this->depth++;
    }

    public function leave(): void
    {
        $this->depth = max(0, $this->depth - 1);
    }

    public function active(): bool
    {
        return $this->depth > 0;
    }
}
