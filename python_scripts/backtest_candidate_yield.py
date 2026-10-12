"""
Panel revision 4.2: predicted vs. actual rice yield for the whole municipality of Lian.

Runs the candidate rice-yield model (storage/models/rice_yield_model_candidate.pkl) on every month of the project's
modeling dataset (storage/datasets/iClimate_Modeling_Ready_TimeAware.csv) and writes, for each month, the model's
predicted yield next to the recorded (actual) municipal average yield, both in tons per hectare (t/ha):

    storage/datasets/generated/rice_yield_backtest.csv
    YEAR, MONTH, SEASON, SPLIT, ACTUAL_YIELD, PREDICTED_YIELD, ABS_ERROR, PCT_ERROR, UNIT, MODEL

SPLIT is copied from the dataset. TRAIN months were used to fit the model (in-sample: their error looks better than
the model really is); TEST months were held out (out-of-sample: the honest accuracy). The MAO dashboard shows both and
labels them, and computes MAPE on the TEST months separately.

Usage (from the project root, with the ML environment):
    python python_scripts/backtest_candidate_yield.py
Then load it into the database:
    php artisan iclimate:import-yield-backtest
"""

import csv
import os
import sys

import joblib
import pandas as pd

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_PATH = os.path.join(BASE_DIR, "storage", "models", "rice_yield_model_candidate.pkl")
DATASET_PATH = os.path.join(BASE_DIR, "storage", "datasets", "iClimate_Modeling_Ready_TimeAware.csv")
OUTPUT_PATH = os.path.join(BASE_DIR, "storage", "datasets", "generated", "rice_yield_backtest.csv")
MODEL_NAME = "iClimate Candidate Random Forest"

FEATURES = [
    "MONTH_SIN",
    "MONTH_COS",
    "SEASON_WET",
    "RAINFALL",
    "TMAX",
    "TMIN",
    "RH",
    "WIND_SPEED",
    "WIND_DIR_SIN",
    "WIND_DIR_COS",
]


def main() -> int:
    if not os.path.exists(MODEL_PATH):
        print(f"Model not found: {MODEL_PATH}", file=sys.stderr)
        return 1
    if not os.path.exists(DATASET_PATH):
        print(f"Dataset not found: {DATASET_PATH}", file=sys.stderr)
        return 1

    data = pd.read_csv(DATASET_PATH)
    missing = [c for c in FEATURES + ["YEAR", "MONTH", "AVERAGE_YIELD"] if c not in data.columns]
    if missing:
        print(f"Dataset is missing columns: {missing}", file=sys.stderr)
        return 1

    model = joblib.load(MODEL_PATH)
    features = list(getattr(model, "feature_names_in_", FEATURES))
    if sorted(features) != sorted(FEATURES):
        print(f"Model features {features} do not match {FEATURES}", file=sys.stderr)
        return 1

    rows = data.dropna(subset=features + ["AVERAGE_YIELD"]).copy()
    rows["PREDICTED_YIELD"] = model.predict(rows[features])

    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
    with open(OUTPUT_PATH, "w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["YEAR", "MONTH", "SEASON", "SPLIT", "ACTUAL_YIELD", "PREDICTED_YIELD", "ABS_ERROR", "PCT_ERROR", "UNIT", "MODEL"])
        for _, r in rows.sort_values(["YEAR", "MONTH"]).iterrows():
            actual = float(r["AVERAGE_YIELD"])
            predicted = float(r["PREDICTED_YIELD"])
            writer.writerow([
                int(r["YEAR"]),
                int(r["MONTH"]),
                r.get("SEASON", ""),
                r.get("SPLIT", ""),
                round(actual, 4),
                round(predicted, 4),
                round(abs(predicted - actual), 4),
                round(abs(predicted - actual) / actual * 100, 2) if actual else "",
                "t/ha",
                MODEL_NAME,
            ])

    print(f"Wrote {len(rows)} months to {OUTPUT_PATH}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
