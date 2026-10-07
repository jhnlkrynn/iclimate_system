<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Chatbot Knowledge pages are hidden from the interface unless CHATBOT_KNOWLEDGE_UI=true (config
 * iclimate.chatbot_knowledge_ui, off by default). While hidden, every role that opens one of them is sent to their
 * own dashboard. Only the pages are switched off: the knowledge tables, models, services and seeders are untouched,
 * and the Climora chatbot keeps using all of it.
 */
class ChatbotKnowledgeUi
{
    public static function enabled(): bool
    {
        return (bool) config('iclimate.chatbot_knowledge_ui', false);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::enabled()) {
            return $next($request);
        }

        // 303 so a form post opens the dashboard with GET.
        return redirect()->route('dashboard', [], 303);
    }
}
