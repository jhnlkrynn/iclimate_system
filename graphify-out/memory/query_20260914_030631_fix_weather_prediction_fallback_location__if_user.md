---
type: "query"
date: "2026-09-14T03:06:31.560724+00:00"
question: "Fix Weather Prediction fallback location. If user has no barangay, use Lian, Batangas default coordinates 14.0389, 120.6503 and show “Lian, Batangas municipal preview” instead of unavailable. Use real API/database values only, cache results, keep PAGASA context separate, and still allow ML prediction below."
contributor: "graphify"
outcome: "useful"
source_nodes: ["WeatherPredictionController", "WeatherApiService", "LianBarangays", "PlantingAdvisory"]
---

# Q: Fix Weather Prediction fallback location. If user has no barangay, use Lian, Batangas default coordinates 14.0389, 120.6503 and show “Lian, Batangas municipal preview” instead of unavailable. Use real API/database values only, cache results, keep PAGASA context separate, and still allow ML prediction below.

## Answer

Expanded from original query via graph vocab: [weather, prediction, fallback, location, barangay, municipal, coordinates, cache, pagasa, climate, forecast, weatherapi]. Implemented blank-barangay municipal preview at 14.0389, 120.6503, retained coordinate-specific cached Open-Meteo/database fallback data, kept municipal PAGASA advisory context separate, and allowed weather ML runs to queue with the municipal location.

## Outcome

- Signal: useful

## Source Nodes

- WeatherPredictionController
- WeatherApiService
- LianBarangays
- PlantingAdvisory