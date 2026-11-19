<?php

namespace Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** Disposable, file-marker job used only by isolated worker verification. */
class IsolatedQueueProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 10;

    public function __construct(
        public readonly string $marker,
        public readonly bool $fail = false,
    ) {
    }

    public function handle(): void
    {
        file_put_contents($this->marker, "processed\n", FILE_APPEND);

        if ($this->fail) {
            throw new RuntimeException('isolated probe failure');
        }
    }
}
