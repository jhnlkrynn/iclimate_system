<?php

namespace App\Logging;

use App\Support\LogScrubber;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

final class ScrubCredentialsTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! method_exists($monolog, 'pushProcessor')) {
            return;
        }

        $monolog->pushProcessor(function (LogRecord $record): LogRecord {
            $message = LogScrubber::scrub($record->message);
            $context = $this->scrubArray($record->context);

            return $record->with(message: $message, context: $context);
        });
    }

    /** @param array<mixed> $data */
    private function scrubArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/^(password|passwd|pwd|secret|token|api_key|authorization)$/i', $key)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            if (is_string($value)) {
                $out[$key] = LogScrubber::scrub($value);
            } elseif (is_array($value)) {
                $out[$key] = $this->scrubArray($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
