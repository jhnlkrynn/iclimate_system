"""Objective 2: the three algorithms are also scored per condition (wet/dry season, irrigated/rainfed)."""

import os
import sys
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import evaluation_service  # noqa: E402


def _write_dataset(rows: int) -> str:
    rng = np.random.default_rng(7)
    folder = os.path.dirname(evaluation_service.DEFAULT_CSV)
    os.makedirs(folder, exist_ok=True)
    path = os.path.join(folder, "__test_breakdown__.csv")
    season = np.where(np.arange(rows) % 2 == 0, "Wet", "Dry")
    irrigation = np.where(np.arange(rows) % 3 == 0, "Rainfed", "Irrigated")
    rainfall = rng.uniform(50, 300, rows)
    pd.DataFrame({
        "rainfall": rainfall,
        "temperature": rng.uniform(25, 31, rows),
        "area_hectares": rng.uniform(10, 250, rows),
        "humidity": rng.uniform(70, 90, rows),
        "wind_speed": rng.uniform(1, 4, rows),
        "season": season,
        "irrigation_type": irrigation,
        "yield_per_hectare": 3.5 + rainfall / 400 + np.where(irrigation == "Irrigated", 0.4, 0.0),
    }).to_csv(path, index=False)
    return path


def test_breakdown_scores_every_algorithm_per_season_and_farming_system():
    path = _write_dataset(36)
    try:
        result = evaluation_service.evaluate(path)
    finally:
        os.unlink(path)

    assert result["ready"] is True
    assert result["accuracy_status"] == "ACCURACY NOT VERIFIED"
    algorithms = {row["algorithm"] for row in result["rows"]}
    assert algorithms == {"Multiple Linear Regression", "Random Forest", "Gradient Boosting"}

    breakdown = result["breakdown"]
    assert set(breakdown["season"]) == {"Wet", "Dry"}
    assert set(breakdown["irrigation_type"]) == {"Irrigated", "Rainfed"}
    for column in ("season", "irrigation_type"):
        counts = 0
        for value, rows in breakdown[column].items():
            assert {row["algorithm"] for row in rows} == algorithms, (column, value)
            for row in rows:
                assert row["rmse"] >= 0 and row["mae"] >= 0
                assert row["n"] > 0
            counts += rows[0]["n"]
        # Every out-of-fold prediction lands in exactly one group.
        assert counts == result["sample_count"]


def test_group_metrics_leave_r2_empty_when_it_cannot_be_computed():
    groups = pd.Series(["Wet", "Dry", "Dry"])
    y = np.array([4.0, 3.0, 3.0])            # the Dry group has no spread in actual yield
    predictions = np.array([4.1, 3.2, 2.9])

    metrics = evaluation_service._group_metrics(groups, y, predictions)

    assert metrics["Wet"]["r2"] is None       # one row
    assert metrics["Dry"]["r2"] is None       # constant actual yield
    assert metrics["Dry"]["n"] == 2
