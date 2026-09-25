<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Internal to the restore test: carries only a fixed reason key, never a
 * message taken from a process, a path or another exception.
 */
class RestoreTestFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
