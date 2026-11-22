"""Audit 2026-11-22: edge cases of the ML service's preprocessing, model loading and prediction.

Run: .venv/Scripts/python -m pytest -q python_scripts/tests/test_audit_edge_cases.py   (from the project root)

Tests marked xfail(strict=True) describe the CORRECT behaviour for a defect found in the audit (AUDIT_REPORT.md);
they fail today on purpose and will report XPASS (and so fail the run) once the defect is fixed, so the marker must
then be removed.
"""
import math
import os
import sys
from datetime import date

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
os.chdir(os.path.join(os.path.dirname(__file__), "..", ".."))   # the service resolves storage/models from the project root

import monthly_weather_random_forest as monthly  # noqa: E402
import rice_yield_service as rys  # noqa: E402

rys.install_sklearn_compatibility_shims()

# A realistic wet-season input for one farm in Lian (the shape PredictionEngine sends).
VALID = {
    "rainfall": 210.0, "temp_avg": 27.8, "temp_range": 7.5, "area": 1.5,
    "previous_rainfall": 180.0, "previous_temp": 28.1, "previous_humidity": 82.0, "previous_wind_speed": 12.0,
    "rainfall_6m": 190.0, "temp_3m": 28.0, "temp_6m": 27.9, "seasonal_rainfall": 1150.0, "seasonal_temp": 27.9,
    "humidity": 83.0, "wind_speed": 11.0, "season": "Wet", "farm_type": "Irrigated", "month_num": 9,
}


def yield_for(**changes) -> float:
    return rys.predict({**VALID, **changes})["rice_yield_prediction"]["predicted_yield"]


# --------------------------------------------------------------------------- model loading


def test_both_deployed_models_load_and_are_fingerprinted():
    models = rys.load_models(force=True)
    assert set(models) >= {"rice", "weather"}
    info = rys.model_info()
    assert info["loaded"] is True
    assert len(info["sha256"]) == 64
    assert info["filename"].endswith("rice_yield_model_final.pkl")


def test_the_deployed_rice_model_expects_exactly_the_features_the_service_builds():
    model = rys.load_models()["rice"]
    built = rys.rice_features(VALID)
    assert list(built.columns) == list(model.feature_names_in_)


@pytest.mark.parametrize("path", ["../../.env", "storage/models/../../composer.json", "C:/Windows/notepad.exe", "storage/models/weather_model.joblib"])
def test_a_model_outside_storage_models_or_not_a_pkl_is_refused(path):
    with pytest.raises(ValueError):
        rys.resolve_model_path(path)


# --------------------------------------------------------------------------- input validation (edge cases)


@pytest.mark.parametrize("area", [0, 0.0, -1.5, -0.0001])
def test_zero_or_negative_farm_size_is_refused(area):
    with pytest.raises(ValueError, match="area"):
        rys.validate_supplied_features({**VALID, "area": area})


@pytest.mark.parametrize("key", ["rainfall", "temp_avg", "area", "rainfall_6m", "seasonal_temp"])
def test_a_missing_or_null_required_feature_is_refused_not_turned_into_zero(key):
    missing = {k: v for k, v in VALID.items() if k != key}
    with pytest.raises(ValueError, match=key):
        rys.rice_features(missing)
    with pytest.raises(ValueError, match=key):
        rys.rice_features({**VALID, key: None})


@pytest.mark.parametrize("value", [float("nan"), float("inf"), -999.0, "abc", True])
def test_nan_infinite_sentinel_text_and_boolean_values_are_refused(value):
    with pytest.raises(ValueError):
        rys.rice_features({**VALID, "rainfall": value})


@pytest.mark.parametrize("key,value", [("rainfall", 3500), ("temp_avg", 60), ("previous_humidity", 140), ("wind_speed", 400)])
def test_physically_impossible_weather_is_refused(key, value):
    with pytest.raises(ValueError, match="Out-of-range"):
        rys.validate_supplied_features({**VALID, key: value})


@pytest.mark.parametrize("month", [0, 13, 6.5, -1])
def test_a_month_outside_1_to_12_is_refused(month):
    with pytest.raises(ValueError):
        rys.weather_features({**VALID, "month_num": month})


@pytest.mark.parametrize("season", ["Summer", "", None, "rainy"])
def test_an_unknown_season_is_refused(season):
    with pytest.raises(ValueError):
        rys.rice_features({**VALID, "season": season})


def test_season_is_case_insensitive():
    assert yield_for(season="wet") == yield_for(season="Wet")


# --------------------------------------------------------------------------- prediction behaviour


def test_the_same_input_always_gives_the_same_estimate():
    assert yield_for() == yield_for()


def test_estimates_stay_in_a_plausible_rice_range_across_lian_weather():
    for rain in (20, 150, 400, 900):
        for temp in (24, 27.5, 31):
            for season in ("Wet", "Dry"):
                y = yield_for(rainfall=rain, temp_avg=temp, season=season)
                assert 1.0 <= y <= 7.0, (rain, temp, season, y)


def test_an_unknown_barangay_is_simply_not_an_input():
    # The deployed model has no barangay input at all, so a barangay (known or not) cannot change the estimate.
    assert yield_for(barangay="Nowhere") == yield_for(barangay="Prenza") == yield_for()


def test_the_uncertainty_is_reported_and_non_negative():
    result = rys.predict(VALID)["rice_yield_prediction"]
    assert result["uncertainty"] is not None and result["uncertainty"] >= 0


@pytest.mark.xfail(strict=True, reason="AUDIT: the deployed rice model has no farm-type input; Irrigated and Rainfed farms "
                                       "get the same t/ha although the app says the estimate uses the farm type")
def test_farm_type_changes_the_estimate():
    assert yield_for(farm_type="Irrigated") != yield_for(farm_type="Rainfed")


def test_the_estimate_barely_moves_across_the_whole_range_of_farm_sizes():
    # Area IS a model input. Records how little it moves the estimate (documented in the audit), so a retrained model
    # that changes this is noticed.
    sizes = [0.1, 0.5, 1, 2, 5, 20, 100]
    spread = max(yield_for(area=a) for a in sizes) - min(yield_for(area=a) for a in sizes)
    assert spread < 1.5


# --------------------------------------------------------------------------- monthly weather forecast (preprocessing)


def _months(n: int, start=(2023, 1), rainfall=150.0):
    out, y, m = [], *start
    for i in range(n):
        out.append({"record_date": f"{y}-{m:02d}-01", "rainfall": rainfall + 10 * (i % 6), "temperature": 27 + (i % 4) * 0.5,
                    "humidity": 80.0, "wind_speed": 10.0})
        m += 1
        if m == 13:
            y, m = y + 1, 1
    return out


def test_monthly_records_are_grouped_by_month_in_date_order():
    shuffled = list(reversed(_months(6)))
    grouped = monthly.monthly_climate_records(shuffled)
    assert [g["month"] for g in grouped] == sorted(g["month"] for g in grouped)
    assert all(g["records"] == 1 for g in grouped)


def test_wet_and_dry_season_follow_the_rice_calendar():
    assert [monthly.season_for_month(m) for m in (5, 10, 11, 4)] == ["Wet", "Wet", "Dry", "Dry"]


def test_fewer_than_four_months_is_not_ready_instead_of_a_guess():
    result = monthly.predict(date(2023, 5, 1), _months(3))
    assert result["ready"] is False


def test_a_forecast_far_beyond_the_records_is_still_finite():
    result = monthly.predict(date(2030, 1, 1), _months(24))
    if result.get("ready"):
        assert all(math.isfinite(float(result["predictions"][k])) for k in ("rainfall", "temperature", "humidity", "wind_speed"))


@pytest.mark.xfail(strict=True, raises=TypeError, reason="AUDIT: a climate record with a missing (null) value raises TypeError, "
                                                          "which the API does not catch, so the endpoint answers 500 instead of 422")
def test_a_climate_record_with_a_missing_value_is_refused_cleanly():
    records = _months(12)
    records[5]["rainfall"] = None
    with pytest.raises((ValueError, KeyError)):
        monthly.predict(date(2024, 1, 1), records)


def test_a_climate_record_without_a_field_is_refused_with_a_key_error():
    records = _months(12)
    del records[3]["humidity"]
    with pytest.raises(KeyError):
        monthly.monthly_climate_records(records)


def test_several_records_in_one_month_are_averaged_not_summed():
    # Documents the contract: the service AVERAGES rainfall within a month, so callers must send one monthly total per
    # month. Sending daily rows would turn a 300 mm month into its 10 mm daily mean.
    daily = [{"record_date": f"2024-07-{d:02d}", "rainfall": 10.0, "temperature": 27.0, "humidity": 80.0, "wind_speed": 10.0}
             for d in range(1, 31)]
    assert monthly.monthly_climate_records(daily)[0]["rainfall"] == pytest.approx(10.0)


# --------------------------------------------------------------------------- the HTTP API


@pytest.fixture(scope="module")
def client():
    from fastapi.testclient import TestClient
    import main
    return TestClient(main.app)


def test_api_rice_yield_good_input(client):
    res = client.post("/predict", json={"prediction_type": "rice_yield", "features": VALID})
    assert res.status_code == 200
    assert 1.0 <= res.json()["rice_yield_prediction"]["predicted_yield"] <= 7.0


@pytest.mark.parametrize("payload", [
    {"prediction_type": "rice_yield", "features": {**VALID, "area": -2}},
    {"prediction_type": "rice_yield", "features": {k: v for k, v in VALID.items() if k != "rainfall"}},
    {"prediction_type": "rice_yield", "features": {**VALID, "season": "Summer"}},
    {"prediction_type": "stocks", "features": VALID},
    {"features": VALID},
])
def test_api_bad_input_is_a_422_not_a_500(client, payload):
    assert client.post("/predict", json=payload).status_code == 422


@pytest.mark.xfail(strict=True, reason="AUDIT: a null value in climate_records raises TypeError inside the endpoint -> HTTP 500")
def test_api_monthly_forecast_with_a_null_value_is_a_422(client):
    records = _months(12)
    records[2]["temperature"] = None
    res = client.post("/forecast/monthly-weather", json={"target_month": "2024-01-01", "climate_records": records})
    assert res.status_code == 422
