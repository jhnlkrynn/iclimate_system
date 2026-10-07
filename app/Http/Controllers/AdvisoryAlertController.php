<?php

namespace App\Http\Controllers;

use App\Services\Advisories\AdvisoryAlertService;
use App\Services\SystemAuditLogger;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 6: Send Alert (MAO, opened from Advisories). Compose a typhoon, flood,
 * drought, heat or general alert for all farmers or chosen barangays, send it now or schedule it, and see every sent
 * alert with how many farmers got it and read it.
 */
class AdvisoryAlertController extends Controller
{
    public function __construct(private readonly AdvisoryAlertService $alerts) {}

    public function index(): View
    {
        return view('mao.alerts', [
            'types' => AdvisoryAlertService::TYPES,
            'severities' => AdvisoryAlertService::SEVERITIES,
            'barangays' => LianBarangays::all(),
            'history' => $this->alerts->history(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(AdvisoryAlertService::TYPES))],
            'severity' => ['required', Rule::in(array_keys(AdvisoryAlertService::SEVERITIES))],
            'message' => ['required', 'string', 'max:5000'],
            'recommended_action' => ['nullable', 'string', 'max:2000'],
            'recipients' => ['required', Rule::in(['all', 'barangays'])],
            'barangays' => ['array', 'required_if:recipients,barangays'],
            'barangays.*' => [Rule::in(LianBarangays::all())],
            'send' => ['required', Rule::in(['now', 'later'])],
            'scheduled_for' => ['nullable', 'required_if:send,later', 'date'],
        ]);

        // The time is typed in Philippine time; stored in UTC like every timestamp.
        $scheduledFor = $data['send'] === 'later' && ! empty($data['scheduled_for'])
            ? Carbon::parse($data['scheduled_for'], BusinessClock::timezone())->utc()
            : null;
        if ($scheduledFor !== null && ! $scheduledFor->isFuture()) {
            return back()->withInput()->withErrors(['scheduled_for' => 'Choose a time in the future, or send it now.']);
        }

        $result = $this->alerts->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'severity' => $data['severity'],
            'message' => $data['message'],
            'recommended_action' => $data['recommended_action'] ?? null,
            'barangays' => $data['recipients'] === 'all' ? [] : array_values(array_unique($data['barangays'] ?? [])),
            'scheduled_for' => $scheduledFor,
        ], $request->user());
        SystemAuditLogger::record($result['scheduled'] ? 'Scheduled Alert' : 'Sent Alert', $request, [
            'title' => $data['title'], 'type' => $data['type'], 'advisories' => $result['advisories']->pluck('id')->all(),
        ]);

        return redirect()->route('mao.alerts.index')->with('success', $result['scheduled']
            ? 'Alert scheduled for '.$scheduledFor->copy()->timezone(BusinessClock::timezone())->format('M j, Y g:i A').'.'
            : 'Alert sent. '.($result['notified'] === 1 ? '1 farmer was' : number_format($result['notified']).' farmers were').' notified.');
    }

    public function cancel(Request $request, string $batch): RedirectResponse
    {
        $count = $this->alerts->cancel($batch);
        SystemAuditLogger::record('Cancelled Scheduled Alert', $request, ['batch' => $batch, 'advisories' => $count]);

        return redirect()->route('mao.alerts.index')->with('success', $count > 0 ? 'Scheduled alert cancelled.' : 'That alert was already sent.');
    }
}
