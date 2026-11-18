@if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
    <div class="alert alert-info border" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}" data-prediction-realtime data-prediction-labels='{"requested":"Advisories requested","processing":"Preparing your advisories…","completed":"Advisories ready","failed":"Advisories failed"}'>
        <strong data-prediction-status-text>Preparing your advisories&hellip;</strong> <span data-prediction-status-detail>Your saved advisories are still available below.</span>
    </div>
    <script>
        (() => {
            const banner = document.querySelector('[data-analytics-run]');
            if (!banner) return;
            window.setInterval(async () => {
                try {
                    const response = await fetch(banner.dataset.analyticsRun, { headers: { Accept: 'application/json' }, loader: false });
                    const run = await response.json();
                    window.iClimatePredictionApply?.(banner, run);
                    banner.dispatchEvent(new CustomEvent('iclimate:prediction-poll', { detail: { element: banner, run } }));
                } catch (_) {}
            }, 2500);
        })();
    </script>
@elseif (($analyticsRun ?? null)?->status === 'failed')
    <div class="alert alert-danger border"><strong>Advisories could not be prepared.</strong> Please try again later.</div>
@endif
