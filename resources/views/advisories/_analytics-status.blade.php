@if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
    <div class="alert alert-info border" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
        <strong>Preparing your advisories&hellip;</strong> Your saved advisories are still available below.
    </div>
    <script>
        (() => {
            const banner = document.querySelector('[data-analytics-run]');
            if (!banner) return;
            window.setInterval(async () => {
                try {
                    const response = await fetch(banner.dataset.analyticsRun, { headers: { Accept: 'application/json' } });
                    const run = await response.json();
                    if (run.status === 'completed' || run.status === 'failed') window.location.reload();
                } catch (_) {}
            }, 2500);
        })();
    </script>
@elseif (($analyticsRun ?? null)?->status === 'failed')
    <div class="alert alert-danger border"><strong>Advisories could not be prepared.</strong> Please try again later.</div>
@endif
