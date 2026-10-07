# Performance Phase 6 provider baseline

Baseline captured from the current source before the Phase 6 changes. This is
a code/configuration inventory, not a live-provider benchmark. No live HTTP
requests were made while collecting it.

## Provider inventory

| Provider / purpose | Access layer and callers | Request shape | Current control path |
| --- | --- | --- | --- |
| Open-Meteo municipal forecast | `OpenMeteoService`; local guidance, advisory generation, `FetchOnlineWeather` | One `/v1/forecast` request for configured Lian coordinates, current/hourly/daily, 16 days | 10s request timeout, 2 retries/300ms; stored `external_weather_data` fallback |
| Open-Meteo current/forecast dashboard weather | `FarmerDashboardWeatherService`; Farmer/MAO dashboards, chatbot context | One configured-coordinate `/forecast` request with current/hourly/daily dashboard fields | Configured provider timeout, bounded retries; page requests read cache/store only; scheduler warms it |
| Open-Meteo planning forecast | `WeatherApiService`; MAO/forecast/calendar flows and `RefreshWeatherCache` | One configured-coordinate `/forecast` request with daily planning fields | 8s default timeout, 2 retries/250ms; page requests read cache/store only |
| Open-Meteo 19-barangay current weather | `LianBarangayWeatherService`; Heat Map, risk, advisories, Climora | One multi-coordinate request for all 19 centroids; concurrent per-location fallback only after batch failure | Batch lock 60s, one-minute attempt throttle, 4s connect timeout, max 8s request timeout; scheduler every 10 minutes |
| Open-Meteo advisory-window forecast | `AdvisoryWeatherService`; advisory detail weather context | Per-advisory coordinate request with `past_days=2`, hourly/daily, 16 days | Five-minute fresh cache plus last-good fallback; 10s timeout, 2 retries/300ms |
| Open-Meteo recent rain | `RecentRainService`; recent-rain dashboard data | Configured-coordinate daily request with `past_days` history | Three-hour cache; 10s timeout; empty result on failure |
| PAGASA | `PagasaAdvisoryService`; explicit advisory refresh and `iclimate:fetch-pagasa-advisories` | Configured regional pages plus optional weekly outlook (currently up to 4 sources) | Per-source timeout up to 12s, connect timeout max 5s, bounded retries inside request budget; fetch lock 120s; 10-minute result cache; scheduler every 15 minutes |
| Groq / Climora | `GroqChatService`; chat-only paths and health check | OpenAI-compatible chat completion | Configured timeout/connect timeout and cooldown; kept separate from weather |
| Python ML service | `PythonAnalyticsClient`, runtime/evaluation clients; queued analytics and explicit health/evaluation paths | Local configured ML HTTP endpoints | Configured connect/request timeouts; queued work is not part of normal page navigation |
| Boundary refresh | `RefreshLianBoundaries` | External boundary endpoint | 20s timeout, 2 retries/300ms; daily job |

## Navigation and refresh findings

- Normal dashboard, Heat Map, advisory index, prediction, calendar, and rice
  production navigation paths read stored/cache data. Provider calls remain
  attached to explicit refresh actions, scheduled warmers, queued work, or
  advisory-detail weather context where a distinct historical/time-window
  payload is required.
- The 19-barangay path already has one authoritative batch and preserves the
  last verified reading for failed points. It was not replaced with sequential
  requests.
- The municipal weather classes intentionally expose different payloads for
  current dashboard data, planning forecasts, advisory windows, and historical
  rain. These are not blindly merged because their requested variables and
  horizons differ.
- Equivalent coordinate spellings now use one six-decimal identity for
  municipal weather cache keys and normalized provider URLs. The existing
  polygon centroids remain distinct.

## Phase 6 changes

- Added a short-lived shared Open-Meteo forecast result cache and refresh lock
  for non-forced reads, including realtime mode.
- Local guidance now reuses that shared result instead of forcing another
  municipal forecast request.
- PAGASA duplicate bulletin rows no longer receive a database write merely to
  update retrieval time. Structured bulletin details are written only when
  changed; unchanged provider content does not retire advisory readers.
- Added regression coverage for normalized coordinate identity and repeated
  non-forced Open-Meteo reads.

## Measurement status

Live provider benchmarking and browser/network capture were not available in
this environment. Therefore no before/after external latency or live request
counts are claimed here. PHPUnit HTTP fakes are used for regression behavior.
