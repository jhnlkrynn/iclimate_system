"""Minimal smoke tests — not exhaustive coverage. Confirms the service boots
and the core contracts hold; does not validate model accuracy (see
docs/system_audit_fix_plan.md Phase 3 for that)."""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from fastapi.testclient import TestClient

import evaluation_service
import analytics_tasks
import main

client = TestClient(main.app)


def test_health_endpoint_responds():
    response = client.get("/health")
    assert response.status_code == 200
    assert "status" in response.json()


def test_predict_rice_yield_returns_expected_shape():
    payload = {
        "prediction_type": "rice_yield",
        "features": {
            "rainfall": 180, "temp_avg": 29, "temp_range": 8, "area": 1,
            "previous_rainfall": 180, "previous_temp": 29,
            "previous_humidity": 78, "previous_wind_speed": 8,
            "rainfall_6m": 900, "temp_3m": 29, "temp_6m": 29,
            "seasonal_rainfall": 900, "seasonal_temp": 29, "season": "Wet",
            "humidity": 78, "wind_speed": 8, "farm_type": "Rainfed", "month_num": 6,
        },
    }
    response = client.post("/predict", json=payload)
    assert response.status_code == 200
    body = response.json()
    assert "rice_yield_prediction" in body
    assert "predicted_yield" in body["rice_yield_prediction"]


def test_predict_rejects_unknown_prediction_type():
    response = client.post("/predict", json={"prediction_type": "nonsense", "features": {}})
    assert response.status_code == 422


def test_queued_rice_yield_analytics_matches_weather_page_contract():
    climate_records = [
        {
            "record_date": f"2026-{month:02d}-15",
            "rainfall": 80 + month * 8,
            "temperature": 27 + month * 0.2,
            "humidity": 70 + month,
            "wind_speed": 8 + month * 0.3,
            "season": "Wet" if month >= 5 else "Dry",
        }
        for month in range(1, 7)
    ]
    result = analytics_tasks.rice_yield_prediction({
        "target_date": "2026-11-15",
        "area": 2.5,
        "farm_type": "Rainfed",
        "climate_records": climate_records,
        "rice_productions": [],
    })

    assert result["weather_result"]["ready"] is True
    yield_result = result["ml_result"]
    for key in (
        "predicted_yield", "unit", "farm_area_hectares",
        "estimated_total_production_tons", "planting_advisory",
        "irrigation_recommendation", "weather_assessment", "risk_label",
        "condition_score", "features", "model_input",
    ):
        assert key in yield_result
        assert yield_result[key] not in (None, "")


def test_evaluate_reports_not_ready_when_csv_missing():
    result = evaluation_service.evaluate("storage/app/private/ml/__does_not_exist__.csv")
    assert result["ready"] is False
    assert "message" in result
