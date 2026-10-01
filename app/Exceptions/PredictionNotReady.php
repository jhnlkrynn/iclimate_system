<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A prepared prediction is not available yet and this request is not allowed
 * to build one (chat requests only read; building is done by the scheduled
 * refresh and the queue). Callers answer "being refreshed" instead.
 */
class PredictionNotReady extends RuntimeException
{
    public function __construct(string $message = 'The prediction is being prepared.')
    {
        parent::__construct($message);
    }
}
