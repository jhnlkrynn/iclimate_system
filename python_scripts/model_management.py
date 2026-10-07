"""
Manuscript - Naicel (2026-10-08), Phase 8: ML Model Management (IT).

Compares Multiple Linear Regression, Random Forest and Gradient Boosting on the iClimate modeling dataset (the same
data and the same 10 features as the candidate rice-yield model), and can retrain one of them on the newest dataset.

Method (same as python_scripts/retrain_yield_season.py): train on every year before the test year, test on the
newest year (hold-out), and also a leave-one-year-out check. Metrics: RMSE, MAE, R^2 (t/ha).

The deployed 12-feature model (rice_yield_model_final.pkl) is not touched: its training data and script are not in the
repository. A retrained model is saved as a new file and registered as a candidate; IT validates and activates it on
Model Registry like any other model.

Usage:
    python python_scripts/model_management.py compare [dataset.csv]
    python python_scripts/model_management.py retrain <mlr|rf|gb> [dataset.csv]
Prints one JSON object on stdout.
"""

import json
import os
import sys
import warnings
from datetime import datetime, timezone

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import StandardScaler

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_DATASET = os.path.join(BASE_DIR, "storage", "datasets", "iClimate_Modeling_Ready_TimeAware.csv")
MODEL_DIR = os.path.join(BASE_DIR, "storage", "models")
FEATURES = ["MONTH_SIN", "MONTH_COS", "SEASON_WET", "RAINFALL", "TMAX", "TMIN", "RH", "WIND_SPEED", "WIND_DIR_SIN", "WIND_DIR_COS"]
TARGET = "AVERAGE_YIELD"

ALGORITHMS = {
    "mlr": ("Multiple Linear Regression", lambda: Pipeline([("scale", StandardScaler()), ("model", LinearRegression())])),
    "rf": ("Random Forest", lambda: RandomForestRegressor(n_estimators=300, min_samples_leaf=2, random_state=42)),
    "gb": ("Gradient Boosting", lambda: GradientBoostingRegressor(n_estimators=200, learning_rate=0.05, max_depth=2, random_state=42)),
}


def metrics(y_true, y_pred):
    return {
        "rmse": round(float(np.sqrt(mean_squared_error(y_true, y_pred))), 4),
        "mae": round(float(mean_absolute_error(y_true, y_pred)), 4),
        "r2": round(float(r2_score(y_true, y_pred)), 4) if len(y_true) > 1 else None,
    }


def load(path):
    data = pd.read_csv(path)
    missing = [c for c in FEATURES + [TARGET, "YEAR"] if c not in data.columns]
    if missing:
        raise ValueError("The dataset is missing columns: " + ", ".join(missing))
    data = data.dropna(subset=FEATURES + [TARGET])
    if len(data) < 12:
        raise ValueError("The dataset has fewer than 12 complete rows.")
    return data


def evaluate(data, key):
    years = sorted(int(y) for y in data["YEAR"].unique())
    test_year = years[-1]
    train = data[data["YEAR"] < test_year]
    test = data[data["YEAR"] == test_year]
    model = ALGORITHMS[key][1]()
    model.fit(train[FEATURES], train[TARGET])
    holdout = metrics(test[TARGET], model.predict(test[FEATURES]))

    preds, truth = [], []
    for year in years:
        fold_train = data[data["YEAR"] != year]
        fold_test = data[data["YEAR"] == year]
        fold_model = ALGORITHMS[key][1]()
        fold_model.fit(fold_train[FEATURES], fold_train[TARGET])
        preds.extend(fold_model.predict(fold_test[FEATURES]))
        truth.extend(fold_test[TARGET])

    return {
        "key": key,
        "name": ALGORITHMS[key][0],
        "holdout_year": test_year,
        "training_years": [y for y in years if y < test_year],
        "holdout": holdout,
        "leave_one_year_out": metrics(np.array(truth), np.array(preds)),
    }


def compare(path):
    data = load(path)
    results = [evaluate(data, key) for key in ALGORITHMS]
    best = min(results, key=lambda r: r["leave_one_year_out"]["rmse"])
    return {
        "action": "compare",
        "dataset": os.path.basename(path),
        "rows": int(len(data)),
        "features": FEATURES,
        "results": results,
        "best": best["key"],
        "ran_at": datetime.now(timezone.utc).isoformat(),
    }


def retrain(key, path):
    data = load(path)
    result = evaluate(data, key)
    model = ALGORITHMS[key][1]()
    model.fit(data[FEATURES], data[TARGET])   # final model: every year, after the hold-out check above
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    name = "rice_yield_" + key + "_" + stamp
    model_path = os.path.join(MODEL_DIR, name + ".pkl")
    metadata_path = os.path.join(MODEL_DIR, name + "_metadata.json")
    os.makedirs(MODEL_DIR, exist_ok=True)
    joblib.dump(model, model_path)
    metadata = {
        "name": result["name"],
        "version": stamp,
        "features": FEATURES,
        "target": TARGET,
        "dataset": os.path.basename(path),
        "training_years": sorted(int(y) for y in data["YEAR"].unique()),
        "holdout": result["holdout"],
        "leave_one_year_out": result["leave_one_year_out"],
        "note": "Retrained from ML Model Management. Metrics are the hold-out year before the final fit on every year.",
    }
    with open(metadata_path, "w", encoding="utf-8") as handle:
        json.dump(metadata, handle, indent=2)
    return {
        "action": "retrain",
        "key": key,
        "name": result["name"],
        "version": stamp,
        "model_path": os.path.relpath(model_path, BASE_DIR).replace("\\", "/"),
        "metadata_path": os.path.relpath(metadata_path, BASE_DIR).replace("\\", "/"),
        "metrics": result["holdout"],
        "leave_one_year_out": result["leave_one_year_out"],
        "training_years": metadata["training_years"],
        "holdout_year": result["holdout_year"],
        "features": FEATURES,
    }


def main():
    warnings.filterwarnings("ignore")
    try:
        if len(sys.argv) < 2 or sys.argv[1] not in ("compare", "retrain"):
            raise ValueError("Use: compare [dataset.csv] | retrain <mlr|rf|gb> [dataset.csv]")
        if sys.argv[1] == "compare":
            print(json.dumps(compare(sys.argv[2] if len(sys.argv) > 2 else DEFAULT_DATASET)))
        else:
            key = sys.argv[2] if len(sys.argv) > 2 else ""
            if key not in ALGORITHMS:
                raise ValueError("Choose an algorithm: mlr, rf or gb.")
            print(json.dumps(retrain(key, sys.argv[3] if len(sys.argv) > 3 else DEFAULT_DATASET)))
    except Exception as error:  # one plain line for the page; details stay on the server
        print(json.dumps({"error": str(error)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
