---
type: "query"
date: "2026-09-12T16:46:38.980766+00:00"
question: "Refactor iClimate so all heavy ML/AI/analytics processing runs in Python, not inside Laravel page requests. Laravel should only validate input, call Python, save JSON results to DB, cache results, and display stored results. Move weather prediction, rice yield prediction, heat map risk scoring, advisory AI, reports analytics, and dataset processing to Python with queue/background handling and error timeouts."
contributor: "graphify"
outcome: "useful"
source_nodes: ["AnalyticsRun", "RunPythonAnalytics", "PythonAnalyticsClient", "ReportController", "WeatherPredictionController", "HeatmapAreaController", "PlantingAdvisoryController", "analytics_tasks.py"]
---

# Q: Refactor iClimate so all heavy ML/AI/analytics processing runs in Python, not inside Laravel page requests. Laravel should only validate input, call Python, save JSON results to DB, cache results, and display stored results. Move weather prediction, rice yield prediction, heat map risk scoring, advisory AI, reports analytics, and dataset processing to Python with queue/background handling and error timeouts.

## Answer

Implemented AnalyticsRun persistence and RunPythonAnalytics queue orchestration. Laravel controllers now validate and dispatch weather_prediction, rice_yield_prediction, heatmap_risk, advisory_ai, report_analytics, and dataset_processing; pages poll and display stored JSON. Python analytics_tasks owns prediction, scoring, report filtering/sorting, advisory generation, and dataset evaluation. Results are cached and persisted; jobs have timeouts, retries, backoff, and failure status. Expanded query tokens: python, prediction, weather, yield, heatmap, advisory, reports, processing, queue, jobs, cache, dataset.

## Outcome

- Signal: useful

## Source Nodes

- AnalyticsRun
- RunPythonAnalytics
- PythonAnalyticsClient
- ReportController
- WeatherPredictionController
- HeatmapAreaController
- PlantingAdvisoryController
- analytics_tasks.py