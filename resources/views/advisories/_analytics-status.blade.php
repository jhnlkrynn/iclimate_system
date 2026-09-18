@if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
    <div class="alert alert-info border" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
        <strong>Python is generating agricultural advisories.</strong> Stored advisories remain available while processing finishes.
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
    <div class="alert alert-danger border"><strong>Advisory processing failed.</strong> {{ $analyticsRun->error }}</div>
@endif
