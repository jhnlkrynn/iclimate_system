---
type: "query"
date: "2026-09-14T17:04:13.225299+00:00"
question: "Error 500 on login to the MAO dashboard after adding the Farmer Planting and Harvest Calendar"
contributor: "graphify"
outcome: "useful"
source_nodes: ["DashboardController", "CalendarController", "FarmerPlantingScheduleController", "Migration"]
---

# Q: Error 500 on login to the MAO dashboard after adding the Farmer Planting and Harvest Calendar

## Answer

Expanded from original query via vocab: [login, authentication, dashboard, mao, error, exception, migration, schema, route, calendar]. The DashboardController schedule query depended on farmer_planting_schedules, but the live database had not run its new migration. Applying the update-safe migration restored the MAO dashboard. Schema readiness guards in DashboardController, CalendarController, and FarmerPlantingScheduleController now prevent the optional schedule feature from causing a dashboard-wide 500 during deployment gaps; views display a clear warning. Full Laravel suite: 244 tests passed with 1259 assertions.

## Outcome

- Signal: useful

## Source Nodes

- DashboardController
- CalendarController
- FarmerPlantingScheduleController
- Migration