"""
Retrains the rice-yield Random Forest with the iClimate season calendar (Wet = May to October, so October = Wet)
and reports RMSE, MAE and R^2 before and after.

What is retrained: the model whose training data is in this repository — the 10-feature "candidate" Random Forest
(storage/models/rice_yield_model_candidate.pkl), built from storage/datasets/iClimate_Modeling_Ready_TimeAware.csv
with the method written in its metadata file: train on 2020-2024, test on the 2025 hold-out, hyperparameters as
recorded there.

What is NOT retrained: the deployed 12-feature model (storage/models/rice_yield_model_final.pkl). Its training
dataset (it has a farm "Area" column) and its training script are not in the repository, so it cannot be rebuilt
here. It is left untouched, and its inputs keep the season encoding it was trained with.

"Before" = the dataset's own SEASON_WET column (Wet = May to September; October is Dry).
"After"  = SEASON_WET worked out from the month with Wet = May to October. Nothing else changes: same rows,
           same other features, same hyperparameters, same seed, same train/test split.

Besides the 2025 hold-out (8 months), a leave-one-year-out check is reported, because one small hold-out year
moves a lot by chance.

Usage:
    python python_scripts/retrain_yield_season.py            # writes the report and the retrained model
    python python_scripts/retrain_yield_season.py --check    # only prints the report (writes nothing)
"""

import hashlib
import json
import os
import sys
import warnings
from datetime import datetime, timezone

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATASET_PATH = os.path.join(BASE_DIR, "storage", "datasets", "iClimate_Modeling_Ready_TimeAware.csv")
MODEL_DIR = os.path.join(BASE_DIR, "storage", "models")
CANDIDATE_METADATA_PATH = os.path.join(MODEL_DIR, "rice_yield_model_candidate_metadata.json")
OUTPUT_MODEL_PATH = os.path.join(MODEL_DIR, "rice_yield_model_candidate_oct_wet.pkl")
OUTPUT_METADATA_PATH = os.path.join(MODEL_DIR, "rice_yield_model_candidate_oct_wet_metadata.json")
REPORT_PATH = os.path.join(MODEL_DIR, "rice_yield_season_retrain_report.json")

FEATURES = ["MONTH_SIN", "MONTH_COS", "SEASON_WET", "RAINFALL", "TMAX", "TMIN", "RH", "WIND_SPEED", "WIND_DIR_SIN", "WIND_DIR_COS"]
TARGET = "AVERAGE_YIELD"
TRAINING_YEARS = [2020, 2021, 2022, 2023, 2024]
TEST_YEAR = 2025
HYPERPARAMETERS = {"n_estimators": 500, "max_depth": 2, "min_samples_leaf": 2, "max_features": "sqrt", "random_state": 42}

# The iClimate season calendar (App\Support\RiceSeason in PHP): Wet = May to October.
WET_MONTHS = {5, 6, 7, 8, 9, 10}


def _model() -> RandomForestRegressor:
    return RandomForestRegressor(**HYPERPARAMETERS)


def _metrics(actual, predicted) -> dict:
    return {
        "rmse": round(float(np.sqrt(mean_squared_error(actual, predicted))), 4),
        "mae": round(float(mean_absolute_error(actual, predicted)), 4),
        "r2": round(float(r2_score(actual, predicted)), 4),
    }


def _holdout(df: pd.DataFrame) -> tuple[dict, RandomForestRegressor]:
    train = df[df["YEAR"].isin(TRAINING_YEARS)]
    test = df[df["YEAR"] == TEST_YEAR]
    model = _model().fit(train[FEATURES], train[TARGET])

    return _metrics(test[TARGET], model.predict(test[FEATURES])) | {"train_rows": int(len(train)), "test_rows": int(len(test))}, model


def _leave_one_year_out(df: pd.DataFrame) -> dict:
    """Each year is predicted by a model that never saw it; the metrics are over all of those predictions."""
    actual, predicted = [], []
    for year in sorted(df["YEAR"].unique()):
        train, test = df[df["YEAR"] != year], df[df["YEAR"] == year]
        model = _model().fit(train[FEATURES], train[TARGET])
        actual.extend(test[TARGET].tolist())
        predicted.extend(model.predict(test[FEATURES]).tolist())

    return _metrics(actual, predicted) | {"rows": len(actual), "years": int(df["YEAR"].nunique())}


def _sha256(path: str) -> str:
    with open(path, "rb") as handle:
        return hashlib.sha256(handle.read()).hexdigest()


def build_report() -> tuple[dict, RandomForestRegressor]:
    warnings.filterwarnings("ignore")
    before_df = pd.read_csv(DATASET_PATH)
    after_df = before_df.copy()
    after_df["SEASON_WET"] = after_df["MONTH"].isin(WET_MONTHS).astype(int)
    changed = before_df[before_df["SEASON_WET"] != after_df["SEASON_WET"]]

    before_holdout, _ = _holdout(before_df)
    after_holdout, after_model = _holdout(after_df)
    recorded = json.load(open(CANDIDATE_METADATA_PATH, encoding="utf-8")).get("final_metrics", {}) if os.path.exists(CANDIDATE_METADATA_PATH) else {}

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "model": "Random Forest Regressor (10-feature candidate)",
        "dataset": os.path.relpath(DATASET_PATH, BASE_DIR).replace("\\", "/"),
        "dataset_sha256": _sha256(DATASET_PATH),
        "rows": int(len(before_df)),
        "features": FEATURES,
        "target": TARGET,
        "hyperparameters": HYPERPARAMETERS,
        "training_years": TRAINING_YEARS,
        "test_year": TEST_YEAR,
        "change": {
            "before": "Wet = May to September (the dataset's SEASON_WET column; October is Dry)",
            "after": "Wet = May to October (October is Wet)",
            "rows_changed": int(len(changed)),
            "rows_changed_months": sorted({f"{int(r.YEAR)}-{int(r.MONTH):02d}" for r in changed.itertuples()}),
        },
        "holdout_2025": {"before": before_holdout, "after": after_holdout},
        "leave_one_year_out": {"before": _leave_one_year_out(before_df), "after": _leave_one_year_out(after_df)},
        # The "before" hold-out must equal what the existing candidate's metadata recorded, or this is not the same recipe.
        "reproduces_recorded_candidate": bool(recorded) and all(
            abs(before_holdout[key.lower()] - float(recorded[key])) < 0.0006 for key in ("MAE", "RMSE", "R2")
        ),
        "recorded_candidate_metrics": recorded,
        "deployed_model": {
            "path": "storage/models/rice_yield_model_final.pkl",
            "retrained": False,
            "reason": "Its training dataset (with a farm Area column) and training script are not in the repository.",
        },
    }

    return report, after_model


def main() -> None:
    report, model = build_report()
    if "--check" not in sys.argv:
        joblib.dump(model, OUTPUT_MODEL_PATH)       # trained on 2020-2024: the model the hold-out numbers describe
        report["retrained_model"] = {"path": os.path.relpath(OUTPUT_MODEL_PATH, BASE_DIR).replace("\\", "/"), "sha256": _sha256(OUTPUT_MODEL_PATH), "activated": False}
        json.dump({
            "model_name": "Random Forest Regressor",
            "target": TARGET,
            "features": FEATURES,
            "season_rule": "SEASON_WET = 1 for May to October",
            "training_years": TRAINING_YEARS,
            "test_year": TEST_YEAR,
            "hyperparameters": HYPERPARAMETERS,
            "final_metrics": {"MAE": report["holdout_2025"]["after"]["mae"], "RMSE": report["holdout_2025"]["after"]["rmse"], "R2": report["holdout_2025"]["after"]["r2"]},
        }, open(OUTPUT_METADATA_PATH, "w", encoding="utf-8"), indent=2)
        json.dump(report, open(REPORT_PATH, "w", encoding="utf-8"), indent=2)

    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
