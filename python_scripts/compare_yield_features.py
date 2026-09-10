"""
Compares Random Forest rice yield accuracy with vs. without humidity and
wind_speed as model features.

Input: a CSV exported by `php artisan iclimate:export-yield-data`, with
columns: barangay, year, season, irrigation_type, rainfall, temperature,
humidity, wind_speed, area_hectares, yield_per_hectare.

Usage:
    python python_scripts/compare_yield_features.py [path/to/export.csv]

The "Extended" feature set here (rainfall, temperature, area_hectares,
humidity, wind_speed, season, irrigation_type) is the TARGET schema in
app/Services/Prediction/FeatureCatalog.php (PHP) — that file explains what
each variable means and why it's included, including why this schema drops
the lag/rolling features the currently-deployed model still uses.
"""

import sys
import warnings

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import KFold, cross_val_predict
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

DEFAULT_PATH = "storage/app/private/ml/rice_yield_training_data.csv"

BASELINE_NUMERIC = ["rainfall", "temperature", "area_hectares"]
EXTENDED_NUMERIC = BASELINE_NUMERIC + ["humidity", "wind_speed"]
CATEGORICAL = ["season", "irrigation_type"]

TARGET = "yield_per_hectare"


def build_pipeline(numeric_features):
    preprocessor = ColumnTransformer(
        transformers=[
            ("num", "passthrough", numeric_features),
            ("cat", OneHotEncoder(handle_unknown="ignore"), CATEGORICAL),
        ]
    )

    return Pipeline(
        steps=[
            ("preprocess", preprocessor),
            (
                "model",
                RandomForestRegressor(
                    n_estimators=300,
                    random_state=42,
                    min_samples_leaf=2,
                ),
            ),
        ]
    )


def evaluate(df, numeric_features, label):
    n = len(df)
    folds = max(2, min(5, n))

    X = df[numeric_features + CATEGORICAL]
    y = df[TARGET].to_numpy()

    pipeline = build_pipeline(numeric_features)
    cv = KFold(n_splits=folds, shuffle=True, random_state=42)

    predictions = cross_val_predict(pipeline, X, y, cv=cv)

    rmse = float(np.sqrt(mean_squared_error(y, predictions)))
    mae = float(mean_absolute_error(y, predictions))
    r2 = float(r2_score(y, predictions))

    print(f"\n[{label}]")
    print(f"  features   : {', '.join(numeric_features + CATEGORICAL)}")
    print(f"  cv folds   : {folds} (n={n})")
    print(f"  RMSE       : {rmse:.4f}")
    print(f"  MAE        : {mae:.4f}")
    print(f"  R^2        : {r2:.4f}")

    return {"label": label, "rmse": rmse, "mae": mae, "r2": r2, "n": n}


def main():
    warnings.filterwarnings("ignore")

    path = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_PATH
    df = pd.read_csv(path)

    if len(df) < 2:
        raise SystemExit(f"Not enough rows in {path} to evaluate (found {len(df)}).")

    if len(df) < 30:
        print(
            f"WARNING: only {len(df)} rows found. Cross-validated metrics below are a "
            "pipeline smoke test, not a statistically reliable comparison. Re-run this "
            "script after importing the full MAO/PAGASA historical dataset.",
            file=sys.stderr,
        )

    baseline = evaluate(df, BASELINE_NUMERIC, "Baseline (rainfall, temperature, area, season, irrigation)")
    extended = evaluate(df, EXTENDED_NUMERIC, "Extended (+ humidity, wind_speed)")

    print("\n[Comparison: extended vs. baseline]")
    print(f"  RMSE change: {extended['rmse'] - baseline['rmse']:+.4f} (lower is better)")
    print(f"  MAE change : {extended['mae'] - baseline['mae']:+.4f} (lower is better)")
    print(f"  R^2 change : {extended['r2'] - baseline['r2']:+.4f} (higher is better)")

    if extended["rmse"] < baseline["rmse"] and extended["r2"] > baseline["r2"]:
        verdict = "Extended feature set improved accuracy on this sample."
    elif extended["rmse"] > baseline["rmse"] and extended["r2"] < baseline["r2"]:
        verdict = "Extended feature set did not improve accuracy on this sample."
    else:
        verdict = "Mixed result on this sample: metrics disagree, treat as inconclusive."

    print(f"\n[Verdict] {verdict}")


if __name__ == "__main__":
    main()
