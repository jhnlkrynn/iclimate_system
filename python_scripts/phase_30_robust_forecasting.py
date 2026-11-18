"""Nested chronological robustness testing for the isolated PSA forecast study."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import Ridge
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

from phase_27_provincial_experiment import load_dataset
from phase_28_timeseries_optimization import LAGGED_FEATURES, TARGET, build_features, write_svg


ROOT = Path(__file__).resolve().parents[1]
PRODUCTION = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-production-batangas-quarterly-1987-2025.csv"
AREA = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-area-harvested-batangas-quarterly-1987-2025.csv"
NEW_PRODUCTION = ROOT / "storage/datasets/external/phase-29/psa-batangas/palay-production-batangas-2026-q1-q3.csv"
NEW_AREA = ROOT / "storage/datasets/external/phase-29/psa-batangas/palay-area-harvested-batangas-2026-q1-q3.csv"
OUTPUT = ROOT / "storage/models/experiments/phase-30"
FEATURES = LAGGED_FEATURES


def score(actual: pd.Series, predicted: np.ndarray) -> dict[str, float | int]:
    y = actual.to_numpy(float)
    p = np.asarray(predicted, float)
    return {
        "r2": float(r2_score(y, p)) if len(y) > 1 else None,
        "mae": float(mean_absolute_error(y, p)),
        "rmse": float(np.sqrt(mean_squared_error(y, p))),
        "mape": float(np.mean(np.abs((y - p) / y)) * 100),
        "bias": float(np.mean(p - y)),
        "n": int(len(y)),
    }


def preprocess() -> ColumnTransformer:
    numeric = [x for x in FEATURES if x != "quarter"]
    return ColumnTransformer([
        ("numeric", StandardScaler(), numeric),
        ("quarter", OneHotEncoder(handle_unknown="ignore", sparse_output=False), ["quarter"]),
    ])


def model(method: str, params: dict[str, Any]) -> Pipeline:
    if method == "Regularized Ridge":
        estimator = Ridge(alpha=float(params["alpha"]))
    elif method == "Random Forest":
        estimator = RandomForestRegressor(random_state=42, n_jobs=1, **params)
    elif method == "Gradient Boosting":
        estimator = GradientBoostingRegressor(random_state=42, **params)
    else:
        raise ValueError(f"Unknown ML method: {method}")
    return Pipeline([("preprocess", preprocess()), ("model", estimator)])


PARAMS: dict[str, list[dict[str, Any]]] = {
    "Seasonal Naive": [{}],
    "Weighted Seasonal Average": [{"decay": x} for x in (0.10, 0.20, 0.35, 0.50)],
    "Exponential Seasonal Smoothing": [{"alpha": x} for x in (0.20, 0.35, 0.50, 0.70)],
    "Regularized Ridge": [{"alpha": x} for x in (0.1, 1.0, 10.0, 100.0)],
    "Random Forest": [
        {"n_estimators": 200, "max_depth": d, "min_samples_leaf": leaf, "max_features": 1.0}
        for d, leaf in ((3, 2), (5, 2), (8, 2), (5, 4), (10, 4), (None, 4))
    ],
    "Gradient Boosting": [
        {"n_estimators": n, "learning_rate": rate, "max_depth": depth, "min_samples_leaf": leaf}
        for n, rate, depth, leaf in ((100, 0.03, 1, 2), (100, 0.05, 2, 2), (150, 0.03, 2, 3), (100, 0.08, 2, 3), (150, 0.03, 3, 3))
    ],
    "ML + Seasonal Naive Ensemble": [
        {"blend": blend, "n_estimators": 200, "max_depth": 5, "min_samples_leaf": 4, "max_features": 1.0}
        for blend in (0.25, 0.50, 0.75)
    ],
}


def seasonal_values(history: pd.DataFrame, target: pd.DataFrame, mode: str, params: dict[str, Any]) -> np.ndarray:
    overall = float(history[TARGET].mean())
    values = []
    for _, row in target.iterrows():
        prior = history[history.record_date < row.record_date]
        same = prior[prior.quarter == row.quarter].sort_values("record_date")
        if same.empty:
            values.append(overall)
            continue
        y = same[TARGET].to_numpy(float)
        if mode == "Seasonal Naive":
            values.append(float(y[-1]))
        elif mode == "Weighted Seasonal Average":
            ages = np.arange(len(y) - 1, -1, -1, dtype=float)
            weights = np.exp(-float(params["decay"]) * ages)
            values.append(float(np.average(y, weights=weights)))
        elif mode == "Exponential Seasonal Smoothing":
            level = float(y[0])
            alpha = float(params["alpha"])
            for item in y[1:]:
                level = alpha * float(item) + (1 - alpha) * level
            values.append(level)
        else:
            raise ValueError(mode)
    return np.asarray(values)


def predict_ml(history: pd.DataFrame, target: pd.DataFrame, method: str, params: dict[str, Any]) -> np.ndarray:
    train = history.dropna(subset=FEATURES)
    if train.empty:
        return np.full(len(target), float(history[TARGET].mean()))
    fitted = model(method, params)
    fitted.fit(train[FEATURES], train[TARGET])
    return fitted.predict(target[FEATURES])


def predict_method(history: pd.DataFrame, target: pd.DataFrame, method: str, params: dict[str, Any]) -> np.ndarray:
    if method in {"Seasonal Naive", "Weighted Seasonal Average", "Exponential Seasonal Smoothing"}:
        return seasonal_values(history, target, method, params)
    if method == "ML + Seasonal Naive Ensemble":
        rf_params = {k: v for k, v in params.items() if k != "blend"}
        ml_pred = predict_ml(history, target, "Random Forest", rf_params)
        naive_pred = seasonal_values(history, target, "Seasonal Naive", {})
        blend = float(params["blend"])
        return blend * ml_pred + (1 - blend) * naive_pred
    return predict_ml(history, target, method, params)


def inner_select(history: pd.DataFrame, method: str) -> tuple[dict[str, Any], float]:
    candidates = PARAMS[method]
    # Inner folds are chronological blocks inside the outer training history.
    n_splits = min(4, max(2, len(history) // 25))
    splitter = __import__("sklearn.model_selection", fromlist=["TimeSeriesSplit"]).TimeSeriesSplit(n_splits=n_splits)
    scored = []
    for params in candidates:
        fold_scores = []
        for train_idx, valid_idx in splitter.split(history):
            train = history.iloc[train_idx]
            valid = history.iloc[valid_idx]
            prediction = predict_method(train, valid, method, params)
            fold_scores.append(score(valid[TARGET], prediction)["rmse"])
        scored.append((float(np.mean(fold_scores)), params))
    return min(scored, key=lambda item: item[0])[1], min(scored, key=lambda item: item[0])[0]


def walk_year(data: pd.DataFrame, year: int, method: str, params: dict[str, Any]) -> pd.DataFrame:
    targets = data[data.year == year].sort_values("record_date")
    rows = []
    for _, target in targets.iterrows():
        history = data[data.record_date < target.record_date]
        prediction = predict_method(history, pd.DataFrame([target]), method, params)[0]
        rows.append({"observation_id": target.observation_id, "record_date": target.record_date, "year": year, "quarter": int(target.quarter), "actual": float(target[TARGET]), "prediction": float(prediction), "error": float(prediction - target[TARGET])})
    return pd.DataFrame(rows)


def parse_2026(path_prod: Path, path_area: Path) -> pd.DataFrame:
    prod = pd.read_csv(path_prod).iloc[0]
    area = pd.read_csv(path_area).iloc[0]
    rows = []
    for quarter in (1, 2, 3):
        key = f"2026 Quarter {quarter}"
        rows.append({"observation_id": f"batangas-2026-q{quarter}", "record_date": f"2026-{quarter*3:02d}-01", "year": 2026, "quarter": quarter, "production_mt": float(prod[key]), "area_harvested_ha": float(area[key]), TARGET: float(prod[key]) / float(area[key])})
    return pd.DataFrame(rows)


def write_chart(frame: pd.DataFrame, path: Path, title: str) -> None:
    actual = frame.rename(columns={"actual": TARGET})
    pred = actual[["record_date", TARGET, "prediction"]].rename(columns={"prediction": "pred_selected"})
    write_svg(pred, path, title)


def run() -> dict[str, Any]:
    base = load_dataset(PRODUCTION, AREA)
    data = build_features(base).dropna(subset=FEATURES).reset_index(drop=True)
    methods = ["Seasonal Naive", "Weighted Seasonal Average", "Exponential Seasonal Smoothing", "Regularized Ridge", "Random Forest", "Gradient Boosting", "ML + Seasonal Naive Ensemble"]
    outer_rows = []
    outer_params = {method: [] for method in methods}
    for year in range(2015, 2021):
        history = data[data.year < year]
        targets = data[data.year == year]
        for method in methods:
            params, inner_rmse = inner_select(history, method)
            predictions = walk_year(data, year, method, params)
            outer_rows.append({"year": year, "method": method, "inner_rmse": inner_rmse, **score(targets[TARGET], predictions.prediction.to_numpy())})
            outer_params[method].append(params)
    outer = pd.DataFrame(outer_rows)
    summary = outer.groupby("method").agg(mean_rmse=("rmse", "mean"), mean_mae=("mae", "mean"), mean_mape=("mape", "mean"), mean_bias=("bias", "mean"), years=("year", "count")).reset_index()
    winners = outer.loc[outer.groupby("year")["rmse"].idxmin(), "method"].value_counts()
    summary["years_won"] = summary["method"].map(winners).fillna(0).astype(int)
    # The lowest mean RMSE on the pre-2021 outer folds is the only selection rule.
    selected_method = str(summary.sort_values(["mean_rmse", "mean_mae"]).iloc[0]["method"])
    frozen_params, frozen_inner_rmse = inner_select(data[data.year <= 2020], selected_method)
    diagnostic = pd.concat([data, parse_2026(NEW_PRODUCTION, NEW_AREA)], ignore_index=True).sort_values("record_date").reset_index(drop=True)
    diagnostic = build_features(diagnostic)
    selected_rows = []
    for year in list(range(2021, 2026)) + [2026]:
        years = diagnostic[diagnostic.year == year]
        for _, target in years.iterrows():
            history = diagnostic[diagnostic.record_date < target.record_date]
            prediction = predict_method(history, pd.DataFrame([target]), selected_method, frozen_params)[0]
            selected_rows.append({"observation_id": target.observation_id, "record_date": target.record_date, "year": int(year), "quarter": int(target.quarter), "actual": float(target[TARGET]), "prediction": float(prediction), "error": float(prediction - target[TARGET])})
    selected_frame = pd.DataFrame(selected_rows)
    OUTPUT.mkdir(parents=True, exist_ok=True)
    outer.to_csv(OUTPUT / "nested_walk_forward_predictions.csv", index=False)
    selected_frame.to_csv(OUTPUT / "selected_forecaster_diagnostic_predictions.csv", index=False)
    write_chart(selected_frame[selected_frame.year >= 2021], OUTPUT / "selected_forecaster_2021_2026_diagnostics.svg", "Phase 30 selected forecaster diagnostic")
    import joblib
    if selected_method in {"Regularized Ridge", "Random Forest", "Gradient Boosting"}:
        final_model = model(selected_method, frozen_params)
        final_train = data[data.year <= 2020].dropna(subset=FEATURES)
        final_model.fit(final_train[FEATURES], final_train[TARGET])
        joblib.dump(final_model, OUTPUT / "selected_pre2021_forecaster.joblib")
    (OUTPUT / "selected_forecaster.json").write_text(json.dumps({
        "method": selected_method,
        "params": frozen_params,
        "selection_rule": "lowest mean RMSE across 2015-2020 outer folds; no 2021-2026 outcomes used",
        "operational_note": "The simple seasonal-naive baseline remains the conservative fallback because no complex method won consistently across years."
    }, indent=2, default=str), encoding="utf-8")
    result = {
        "status": "ROBUSTNESS RESULTS AVAILABLE",
        "dataset_scope": "Batangas province aggregate PSA yield; not Lian farm-level",
        "selection_period": "2015-2020 outer walk-forward folds; inner folds contained within each training history",
        "diagnostic_period": "2021-2025 and 2026 Q1-Q3; not used for selection",
        "methods": methods,
        "outer_summary": summary.to_dict(orient="records"),
        "outer_fold_results": outer.to_dict(orient="records"),
        "selected_method": selected_method,
        "selected_params": frozen_params,
        "selected_inner_rmse": frozen_inner_rmse,
        "selected_diagnostic": selected_frame.to_dict(orient="records"),
        "selection_rule": "lowest mean RMSE across pre-2021 outer folds, tie-broken by mean MAE",
        "future_test_ready": True,
        "deployed_model_unchanged": True,
        "accuracy_status": "NOT DEPLOYMENT EVIDENCE",
        "artifacts": sorted({p.name for p in OUTPUT.iterdir()} | {"metrics.json"}),
    }
    (OUTPUT / "metrics.json").write_text(json.dumps(result, indent=2, default=str), encoding="utf-8")
    return result


if __name__ == "__main__":
    print(json.dumps(run(), indent=2, default=str))
