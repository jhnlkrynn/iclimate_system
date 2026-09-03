<?php

namespace App\Console\Commands;

use App\Services\AI\GroqChatService;
use Illuminate\Console\Command;

class CheckGroqApi extends Command
{
    protected $signature = 'iclimate:check-groq';

    protected $description = 'Check whether the configured Groq API key and model are reachable.';

    public function handle(GroqChatService $groq): int
    {
        $result = $groq->healthCheck();

        $this->line($result['message']);
        $this->line('Model: '.($result['model'] ?? config('services.groq.model')));

        if (! empty($result['reply'])) {
            $this->line('Reply: '.$result['reply']);
        }

        if (! empty($result['usage'])) {
            $this->line('Usage: '.json_encode($result['usage']));
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
