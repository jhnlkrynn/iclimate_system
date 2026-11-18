"""Frozen pre-2026 evaluation of the isolated provincial experiments."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from phase_27_provincial_experiment import load_dataset
from phase_28_timeseries_optimization import LAGGED_FEATURES, TARGET, build_features, metrics, write_svg


ROOT = Path(__file__).resolve().parents[1]
HISTORY_PRODUCTION = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-production-batangas-quarterly-1987-2025.csv"
HISTORY_AREA = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-area-harvested-batangas-quarterly-1987-2025.csv"
NEW_PRODUCTION = ROOT / "storage/datasets/external/phase-29/psa-batangas/palay-production-batangas-2026-q1-q3.csv"
NEW_AREA = ROOT / "storage/datasets/external/phase-29/psa-batangas/palay-area-harvested-batangas-2026-q1-q3.csv"
PROTOCOL = ROOT / "storage/models/experiments/phase-29/protocol-freeze.json"
OUTPUT = ROOT / "storage/models/experiments/phase-29"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def read_new_targets(production_path: Path, area_path: Path) -> pd.DataFrame:
    production = pd.read_csv(production_path).iloc[0]
    area = pd.read_csv(area_path).iloc[0]
    periods = list(production.index[2:])
    if periods != list(area.index[2:]) or periods != ["2026 Quarter 1", "2026 Quarter 2", "2026 Quarter 3"]:
        raise ValueError("The 2026 files do not contain exactly Q1-Q3 matching periods.")
    rows = []
    for period in periods:
        _, _, quarter_text = period.split()
        quarter = int(quarter_text)
        production_value = float(production[period])
        area_value = float(area[period])
        if not np.isfinite(production_value) or not np.isfinite(area_value):
            raise ValueError(f"Non-finite 2026 value at {period}.")
        if production_value < 0 or area_value <= 0:
            raise ValueError(f"Invalid 2026 production/area at {period}.")
        rows.append({
            "observation_id": f"batangas-2026-q{quarter}",
            "record_date": f"2026-{quarter * 3:02d}-01",
            "year": 2026,
            "quarter": quarter,
            "production_mt": production_value,
            "area_harvested_ha": area_value,
            TARGET: production_value / area_value,
            "geographic_level": "province",
            "geography": "Batangas",
            "ecosystem": str(production["Ecosystem/Croptype"]),
        })
    return pd.DataFrame(rows)


def preprocessor(features: list[str]) -> ColumnTransformer:
    numeric = [feature for feature in features if feature != "quarter"]
    return ColumnTransformer([
        ("numeric", "passthrough", numeric),
        ("quarter", OneHotEncoder(handle_unknown="ignore", sparse_output=False), ["quarter"]),
    ])


def fixed_pipeline(features: list[str], params: dict) -> Pipeline:
    estimator = RandomForestRegressor(random_state=42, n_jobs=1, **params)
    return Pipeline([("preprocess", preprocessor(features)), ("model", estimator)])


def future_lagged_features(history: pd.DataFrame, future: pd.DataFrame) -> pd.DataFrame:
    history = history.sort_values("record_date").reset_index(drop=True)
    last_yield = float(history.iloc[-1][TARGET])
    rolling4 = float(history.tail(4)[TARGET].mean())
    rolling8 = float(history.tail(8)[TARGET].mean())
    expanding = float(history[TARGET].mean())
    rows = []
    for _, row in future.iterrows():
        same_quarter = history[(history.year == 2025) & (history.quarter == row.quarter)]
        lag4 = float(same_quarter.iloc[-1][TARGET])
        rows.append({
            "year": int(row.year),
            "quarter": int(row.quarter),
            "lag_1_yield_t_ha": last_yield,
            "lag_4_yield_t_ha": lag4,
            "rolling_4_mean_prior": rolling4,
            "rolling_8_mean_prior": rolling8,
            "expanding_mean_prior": expanding,
            "trend_4_prior": last_yield - lag4,
        })
    return pd.DataFrame(rows)


def naive_predictions(history: pd.DataFrame, future: pd.DataFrame) -> dict[str, np.ndarray]:
    overall = float(history[TARGET].mean())
    seasonal = history.groupby("quarter")[TARGET].mean().to_dict()
    last = float(history.iloc[-1][TARGET])
    same_year = []
    for _, row in future.iterrows():
        match = history[(history.year == 2025) & (history.quarter == row.quarter)]
        same_year.append(float(match.iloc[-1][TARGET]) if not match.empty else overall)
    return {
        "previous_quarter_naive": np.full(len(future), last),
        "previous_year_same_quarter_naive": np.asarray(same_year),
        "seasonal_historical_mean": np.asarray([seasonal.get(q, overall) for q in future.quarter]),
    }


def run() -> dict:
    protocol = json.loads(PROTOCOL.read_text(encoding="utf-8"))
    if protocol.get("status") != "FROZEN_BEFORE_2026_TARGET_INSPECTION":
        raise RuntimeError("Phase 29 protocol is not frozen before target inspection.")
    history = load_dataset(HISTORY_PRODUCTION, HISTORY_AREA)
    future = read_new_targets(NEW_PRODUCTION, NEW_AREA)
    history_features = build_features(history).dropna(subset=LAGGED_FEATURES)
    lagged_params = protocol["models"]["lagged_random_forest"]["hyperparameters"].copy()
    calendar_params = protocol["models"]["calendar_random_forest"]["hyperparameters"].copy()
    lagged_params.pop("random_state", None)
    lagged_params.pop("n_jobs", None)
    calendar_params.pop("random_state", None)
    calendar_params.pop("n_jobs", None)

    lagged_model = fixed_pipeline(LAGGED_FEATURES, lagged_params)
    lagged_model.fit(history_features[LAGGED_FEATURES], history_features[TARGET])
    calendar_model = fixed_pipeline(["year", "quarter"], calendar_params)
    calendar_model.fit(history[["year", "quarter"]], history[TARGET])
    future_lagged = future_lagged_features(history, future)
    predictions = future[["observation_id", "record_date", "quarter", TARGET, "production_mt", "area_harvested_ha"]].copy()
    predictions["pred_lagged_random_forest"] = lagged_model.predict(future_lagged[LAGGED_FEATURES])
    predictions["pred_calendar_random_forest"] = calendar_model.predict(future[["year", "quarter"]])
    for name, values in naive_predictions(history, future).items():
        predictions[f"pred_{name}"] = values
    model_columns = [c for c in predictions.columns if c.startswith("pred_")]
    model_metrics = {}
    for column in model_columns:
        model_metrics[column.removeprefix("pred_")] = metrics(predictions[TARGET], predictions[column].to_numpy())
        predictions[f"error_{column.removeprefix('pred_')}"] = predictions[column] - predictions[TARGET]
    OUTPUT.mkdir(parents=True, exist_ok=True)
    predictions.to_csv(OUTPUT / "2026_q1_q3_predictions_and_errors.csv", index=False)
    chart = predictions[["observation_id", "record_date", TARGET] + model_columns].rename(columns={TARGET: "actual_yield_t_ha"})
    write_svg(chart, OUTPUT / "2026_q1_q3_actual_vs_predicted.svg", "Phase 29 frozen 2026 Q1-Q3 evaluation")
    import joblib
    joblib.dump(lagged_model, OUTPUT / "frozen_lagged_random_forest.joblib")
    joblib.dump(calendar_model, OUTPUT / "frozen_calendar_random_forest.joblib")
    source_files = [NEW_PRODUCTION, NEW_AREA, PROTOCOL]
    result = {
        "status": "EVALUATED_FROZEN_MODELS",
        "protocol_status": protocol["status"],
        "observations": int(len(future)),
        "periods": [f"2026 Q{q}" for q in future.quarter],
        "provenance": "PSA OpenSTAT Batangas Palay production and area harvested; province aggregate",
        "prediction_origin": protocol["forecast_origin"],
        "models": model_metrics,
        "individual_predictions": predictions.to_dict(orient="records"),
        "small_sample_warning": "Only three quarterly observations; MAE/RMSE/bias/MAPE are descriptive and R² is not a reliable standalone conclusion.",
        "model_selection_uses_2026": False,
        "production_model_unchanged": True,
        "accuracy_status": "NOT DEPLOYMENT EVIDENCE",
        "source_checksums": {str(path.relative_to(ROOT)): sha256(path) for path in source_files},
        "artifacts": sorted({p.name for p in OUTPUT.iterdir()} | {"metrics.json"}),
    }
    (OUTPUT / "metrics.json").write_text(json.dumps(result, indent=2, default=str), encoding="utf-8")
    return result


if __name__ == "__main__":
    print(json.dumps(run(), indent=2, default=str))
