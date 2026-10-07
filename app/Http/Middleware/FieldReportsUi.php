<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MAO/IT – Naicel (2026-10-07): the Field Reports pages are hidden from the interface unless FIELD_REPORTS_UI=true
 * (config iclimate.field_reports_ui, off by default). While hidden, anyone who opens a Field Reports address is sent to
 * their own dashboard (MAO to the MAO dashboard, IT to the IT dashboard). Only the pages are switched off: the
 * field_reports table, its data, the model, the controller and the migrations are untouched. Same idea as ChatbotKnowledgeUi.
 */
class FieldReportsUi
{
    public static function enabled(): bool
    {
        return (bool) config('iclimate.field_reports_ui', false);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::enabled()) {
            return $next($request);
        }

        // 303 so a form post opens the dashboard with GET.
        return redirect()->route($request->user()?->dashboardRoute() ?? 'dashboard', [], 303);
    }
}
