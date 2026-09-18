---
type: "query"
date: "2026-09-14T17:09:42.736791+00:00"
question: "Planting Schedule Calendar shows Error 500 after clicking a date"
contributor: "graphify"
outcome: "useful"
source_nodes: ["PlantingAdvisoryController", "CalendarController", "PlantingAdvisory", "AdvisoryFeedback"]
---

# Q: Planting Schedule Calendar shows Error 500 after clicking a date

## Answer

Expanded from original query via vocab: [calendar, schedule, date, controller, exception, route, planting, advisory, index, event, events, mao]. The clicked calendar event routes to PlantingAdvisoryController::show. The live exception showed eager loading feedback queried the missing advisory_feedback table. Applying migration 2026_09_14_225652 fixed live data access. The show action now checks Schema readiness, loads non-feedback relations, supplies an empty loaded feedback collection, and keeps advisory details usable with a warning during deployment gaps. Feedback submission and MAO response actions also guard the missing table; integer route input prevents implicit feedback model binding from querying it before the guard. Live advisory 36 rendered successfully and 245 tests passed.

## Outcome

- Signal: useful

## Source Nodes

- PlantingAdvisoryController
- CalendarController
- PlantingAdvisory
- AdvisoryFeedback