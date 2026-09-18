# Python analytics runtime

iClimate runs weather prediction, rice-yield prediction, heat-map risk scoring,
advisory generation, report filtering, and dataset evaluation outside Laravel
HTTP requests.

Laravel validates the request, creates an `analytics_runs` row, queues
`RunPythonAnalytics`, and renders only saved JSON results. The queue job gathers
raw database rows, calls FastAPI, persists the response, and caches it. A failed
or timed-out job stores its error in the same run row.

## Required processes

Run the Python API:

```bash
cd python_scripts
uvicorn main:app --host 127.0.0.1 --port 8010
```

Run a dedicated Laravel worker:

```bash
php artisan queue:work database --queue=analytics --timeout=90 --tries=3
```

Run the Laravel scheduler once per minute (cron or Task Scheduler):

```bash
php artisan schedule:run
```

Install Python dependencies from `python_scripts/requirements.txt`, then set
`FARMING_AI_API_URL`, `FARMING_AI_ANALYTICS_TIMEOUT`,
`FARMING_AI_QUEUE_CONNECTION`, and `FARMING_AI_QUEUE` in `.env`. Production
should supervise both Uvicorn and the queue worker and restart them on failure.

## Request lifecycle

1. The controller validates and queues an analytics run.
2. The browser receives a redirect immediately and polls the run-status route.
3. The queue worker calls the matching `/analytics/{type}` Python handler.
4. Laravel stores the returned JSON and any report/advisory/heat-map records.
5. The page reloads and displays only the stored result.

The Python HTTP client uses separate connection and processing timeouts. Queue
jobs retry three times with backoff and are marked failed after the final error
or worker timeout.
