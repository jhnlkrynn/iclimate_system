"""
Trains and saves (via joblib) the rice-yield Random Forest pipeline, using the
feature set locked in by compare_yield_features.py's comparison (rainfall,
temperature, area_hectares, humidity, wind_speed, season, irrigation_type —
the "Extended" set, which beat the baseline in that comparison).

Input: a CSV in the same shape produced by
`php artisan iclimate:export-yield-data` (see ExportRiceYieldTrainingData.php):
barangay, year, season, irrigation_type, rainfall, temperature, humidity,
wind_speed, area_hectares, yield_per_hectare.

IMPORTANT: while the real MAO/PAGASA historical dataset is still missing from
this repo, running this against the SMOKETEST CSV produces a model trained on
fabricated data. Such a model must never be treated as the deployed model —
it exists only to prove the train -> joblib.dump -> joblib.load -> .predict()
mechanism works end-to-end. Re-run this against the real export once
`iclimate:export-yield-data` produces >=30 real rows, and only then point
`storage/models/rice_yield_model_final.pkl` at the result.

Usage:
    python python_scripts/train_rice_yield_model.py [input.csv] [output.pkl]
"""

import os
import sys
import warnings

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import KFold, cross_val_predict
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

DEFAULT_INPUT = os.path.join(BASE_DIR, "storage", "app", "private", "ml", "SMOKETEST_rice_yield_training_data.csv")
DEFAULT_OUTPUT = os.path.join(BASE_DIR, "storage", "models", "SMOKETEST_rice_yield_model.pkl")

NUMERIC_FEATURES = ["rainfall", "temperature", "area_hectares", "humidity", "wind_speed"]
CATEGORICAL_FEATURES = ["season", "irrigation_type"]
TARGET = "yield_per_hectare"


def build_pipeline() -> Pipeline:
    preprocessor = ColumnTransformer(
        transformers=[
            ("num", "passthrough", NUMERIC_FEATURES),
            ("cat", OneHotEncoder(handle_unknown="ignore"), CATEGORICAL_FEATURES),
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


def main():
    warnings.filterwarnings("ignore")

    input_path = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_INPUT
    output_path = sys.argv[2] if len(sys.argv) > 2 else DEFAULT_OUTPUT

    df = pd.read_csv(input_path)

    if len(df) < 2:
        raise SystemExit(f"Not enough rows in {input_path} to train (found {len(df)}).")

    is_smoketest = "SMOKETEST" in os.path.basename(input_path).upper()

    if is_smoketest or "SMOKETEST" in os.path.basename(output_path).upper():
        print(
            "NOTE: training on/saving to a SMOKETEST path. This model is for pipeline "
            "verification only and must not replace storage/models/rice_yield_model_final.pkl "
            "until it is retrained on the real MAO/PAGASA export.",
            file=sys.stderr,
        )

    if len(df) < 30:
        print(
            f"WARNING: only {len(df)} rows in {input_path}. Reported metrics are a pipeline "
            "smoke test, not a statistically reliable accuracy claim.",
            file=sys.stderr,
        )

    x = df[NUMERIC_FEATURES + CATEGORICAL_FEATURES]
    y = df[TARGET].to_numpy()

    # Report cross-validated metrics before the final fit, same methodology as
    # compare_yield_features.py, so this number is directly comparable to it.
    folds = max(2, min(5, len(df)))
    cv = KFold(n_splits=folds, shuffle=True, random_state=42)
    cv_predictions = cross_val_predict(build_pipeline(), x, y, cv=cv)

    rmse = float(np.sqrt(mean_squared_error(y, cv_predictions)))
    mae = float(mean_absolute_error(y, cv_predictions))
    r2 = float(r2_score(y, cv_predictions))

    print(f"Cross-validated (n={len(df)}, folds={folds}): RMSE={rmse:.4f} MAE={mae:.4f} R^2={r2:.4f}")

    # Final model: fit on all available rows (standard practice once CV has
    # already reported the expected out-of-sample accuracy above).
    final_pipeline = build_pipeline()
    final_pipeline.fit(x, y)

    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    joblib.dump(final_pipeline, output_path)

    print(f"Saved trained pipeline to {output_path}")

    # Round-trip sanity check: load it back and confirm it predicts.
    reloaded = joblib.load(output_path)
    sample_prediction = float(reloaded.predict(x.iloc[[0]])[0])
    print(f"Round-trip check OK — reloaded model predicted {sample_prediction:.2f} for row 0.")


if __name__ == "__main__":
    main()
