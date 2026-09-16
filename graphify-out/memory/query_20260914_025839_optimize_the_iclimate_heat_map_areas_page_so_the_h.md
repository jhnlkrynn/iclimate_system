---
type: "query"
date: "2026-09-14T02:58:39.195050+00:00"
question: "Optimize the iClimate Heat Map Areas page so the heat map loads fast while keeping the risk results accurate."
contributor: "graphify"
outcome: "useful"
source_nodes: ["LianHeatmapController", "HeatmapAreaController", "HeatmapRiskService", "HeatmapArea"]
---

# Q: Optimize the iClimate Heat Map Areas page so the heat map loads fast while keeping the risk results accurate.

## Answer

Expanded from original query via graph vocab: [heatmap, areas, cache, cached, risk, climate, rice, farmer, profiles, layer, performance, response]. Implemented a versioned Laravel cache per heatmap layer and filter, lightweight API responses, model-event invalidation, precomputed risk display fields, an accurate queued refresh through HeatmapRiskService, single-render SVG geometry with in-place layer updates, last-known/localStorage fallback, development timing logs, indexes, and regression tests.

## Outcome

- Signal: useful

## Source Nodes

- LianHeatmapController
- HeatmapAreaController
- HeatmapRiskService
- HeatmapArea