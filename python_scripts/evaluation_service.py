"""
Real, computed model-evaluation metrics — the replacement for
ModelEvaluationService.php's hardcoded TRAINED_MODEL_RESULTS constant.

Reads the REAL export produced by `php artisan iclimate:export-yield-data`
(storage/app/private/ml/rice_yield_training_data.csv). Deliberately does NOT
default to the SMOKETEST csv — this endpoint is meant to answer "what is our
actual model accuracy right now", and a fabricated answer to that question is
worse than an honest "not enough data yet".

Evaluates the three algorithms the app has always claimed to compare
(Multiple Linear Regression, Random Forest, Gradient Boosting) with identical
cross-validated methodology, on the "Extended" feature set locked in by
compare_yield_features.py (rainfall, temperature, area_hectares, humidity,
wind_speed, season, irrigation_type) — this is the TARGET schema described in
app/Services/Prediction/FeatureCatalog.php, which is the canonical source for
what each of these variables means and why it's included. Keep that file and
this feature list in sync if either changes.
"""

import os
import warnings
from datetime import datetime, timezone

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import KFold, cross_val_predict
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_CSV = os.path.join(BASE_DIR, "storage", "app", "private", "ml", "rice_yield_training_data.csv")

NUMERIC_FEATURES = ["rainfall", "temperature", "area_hectares", "humidity", "wind_speed"]
CATEGORICAL_FEATURES = ["season", "irrigation_type"]
TARGET = "yield_per_hectare"

ALGORITHMS = {
    "Multiple Linear Regression": lambda: LinearRegression(),
    "Random Forest": lambda: RandomForestRegressor(n_estimators=300, random_state=42, min_samples_leaf=2),
    "Gradient Boosting": lambda: GradientBoostingRegressor(n_estimators=300, random_state=42, max_depth=3),
}

RELIABILITY_FLOOR = 30


def _pipeline(estimator) -> Pipeline:
    preprocessor = ColumnTransformer(
        transformers=[
            ("num", "passthrough", NUMERIC_FEATURES),
            ("cat", OneHotEncoder(handle_unknown="ignore"), CATEGORICAL_FEATURES),
        ]
    )

    return Pipeline(steps=[("preprocess", preprocessor), ("model", estimator)])


def _not_ready(sample_count: int, message: str) -> dict:
    return {
        "ready": False,
        "message": message,
        "sample_count": sample_count,
        "rows": [],
        "best_algorithm": None,
        "generated_at": datetime.now(timezone.utc).isoformat(),
    }


def evaluate(csv_path: str | None = None) -> dict:
    warnings.filterwarnings("ignore")
    path = csv_path or DEFAULT_CSV

    if not os.path.isabs(path):
        path = os.path.join(BASE_DIR, path)

    if not os.path.exists(path):
        return _not_ready(
            0,
            "No exported training data found yet. Run `php artisan iclimate:export-yield-data` "
            "after real MAO/PAGASA records are imported, then call this endpoint again.",
        )

    df = pd.read_csv(path)

    if len(df) < 2:
        return _not_ready(
            len(df),
            f"Only {len(df)} row(s) in the export — not enough to cross-validate.",
        )

    x = df[NUMERIC_FEATURES + CATEGORICAL_FEATURES]
    y = df[TARGET].to_numpy()

    folds = max(2, min(5, len(df)))
    cv = KFold(n_splits=folds, shuffle=True, random_state=42)

    rows = []
    for name, make_estimator in ALGORITHMS.items():
        predictions = cross_val_predict(_pipeline(make_estimator()), x, y, cv=cv)

        rows.append({
            "algorithm": name,
            "rmse": round(float(np.sqrt(mean_squared_error(y, predictions))), 4),
            "mae": round(float(mean_absolute_error(y, predictions)), 4),
            "r2": round(float(r2_score(y, predictions)), 4),
        })

    best = max(rows, key=lambda row: row["r2"])

    result = {
        "ready": True,
        "message": "Evaluation metrics computed live from the current exported training data.",
        "sample_count": len(df),
        "cv_folds": folds,
        "features": NUMERIC_FEATURES + CATEGORICAL_FEATURES,
        "rows": rows,
        "best_algorithm": best["algorithm"],
        "source": {
            "dataset_path": path,
            "computed_live": True,
        },
        "generated_at": datetime.now(timezone.utc).isoformat(),
    }

    if len(df) < RELIABILITY_FLOOR:
        result["warning"] = (
            f"Only {len(df)} rows available (below the {RELIABILITY_FLOOR}-row reliability floor). "
            "Treat these metrics as a pipeline smoke test, not a statistically reliable accuracy claim, "
            "until more historical MAO/PAGASA records are imported."
        )

    return result


if __name__ == "__main__":
    import json
    import sys

    print(json.dumps(evaluate(sys.argv[1] if len(sys.argv) > 1 else None), indent=2))
