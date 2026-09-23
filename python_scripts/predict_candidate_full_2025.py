import math
import os

import joblib
import numpy as np
import pandas as pd

from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score,
)


BASE_DIR = os.path.dirname(
    os.path.dirname(os.path.abspath(__file__))
)

MODEL_PATH = os.path.join(
    BASE_DIR,
    "storage",
    "models",
    "rice_yield_model_candidate.pkl",
)

WEATHER_PATH = os.path.join(
    BASE_DIR,
    "storage",
    "datasets",
    "Ambulong_Monthly_Data.csv",
)

MODELING_PATH = os.path.join(
    BASE_DIR,
    "storage",
    "datasets",
    "iClimate_Modeling_Ready_TimeAware.csv",
)


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


MONTH_NAMES = {
    1: "Jan",
    2: "Feb",
    3: "Mar",
    4: "Apr",
    5: "May",
    6: "Jun",
    7: "Jul",
    8: "Aug",
    9: "Sep",
    10: "Oct",
    11: "Nov",
    12: "Dec",
}


def add_candidate_features(df):
    df = df.copy()

    df["MONTH_SIN"] = np.sin(
        2 * np.pi * df["MONTH"] / 12
    )

    df["MONTH_COS"] = np.cos(
        2 * np.pi * df["MONTH"] / 12
    )

    # Must match the same rule used during training.
    df["SEASON_WET"] = df["MONTH"].isin(
        [5, 6, 7, 8, 9]
    ).astype(int)

    radians = np.radians(
        df["WIND_DIRECTION"].astype(float)
    )

    df["WIND_DIR_SIN"] = np.sin(radians)
    df["WIND_DIR_COS"] = np.cos(radians)

    return df


def main():
    print("=" * 86)
    print("iCLIMATE CANDIDATE RANDOM FOREST - FULL 2025 PREDICTIONS")
    print("=" * 86)

    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError(
            f"Candidate model not found: {MODEL_PATH}"
        )

    if not os.path.exists(WEATHER_PATH):
        raise FileNotFoundError(
            f"Weather dataset not found: {WEATHER_PATH}"
        )

    if not os.path.exists(MODELING_PATH):
        raise FileNotFoundError(
            f"Modeling dataset not found: {MODELING_PATH}"
        )

    # ----------------------------------------------------
    # Load complete 2025 weather
    # ----------------------------------------------------

    weather = pd.read_csv(WEATHER_PATH)

    weather_2025 = weather[
        weather["YEAR"] == 2025
    ].copy()

    weather_2025 = weather_2025.sort_values(
        "MONTH"
    )

    if len(weather_2025) != 12:
        print(
            f"WARNING: Expected 12 weather rows, found "
            f"{len(weather_2025)}."
        )

    # ----------------------------------------------------
    # Generate candidate features
    # ----------------------------------------------------

    weather_2025 = add_candidate_features(
        weather_2025
    )

    X = weather_2025[FEATURES]

    # ----------------------------------------------------
    # Load candidate model and predict
    # ----------------------------------------------------

    model = joblib.load(MODEL_PATH)

    if hasattr(model, "feature_names_in_"):
        model_features = list(
            model.feature_names_in_
        )

        if model_features != FEATURES:
            raise ValueError(
                "Candidate feature order does not match "
                "the trained model."
            )

    predictions = model.predict(X)

    # ----------------------------------------------------
    # Load known actual yields
    # ----------------------------------------------------

    modeling = pd.read_csv(MODELING_PATH)

    actual_2025 = modeling[
        modeling["YEAR"] == 2025
    ][
        ["MONTH", "AVERAGE_YIELD"]
    ].copy()

    actual_lookup = dict(
        zip(
            actual_2025["MONTH"],
            actual_2025["AVERAGE_YIELD"],
        )
    )

    # ----------------------------------------------------
    # Build Jan-Dec result table
    # ----------------------------------------------------

    rows = []

    for (_, weather_row), prediction in zip(
        weather_2025.iterrows(),
        predictions
    ):
        month = int(weather_row["MONTH"])

        actual = actual_lookup.get(
            month,
            np.nan
        )

        if pd.notna(actual):
            abs_error = abs(
                float(prediction)
                - float(actual)
            )

            status = "Validated"
        else:
            abs_error = np.nan
            status = "Prediction only"

        rows.append({
            "MONTH_NO": month,
            "MONTH": MONTH_NAMES[month],
            "RAINFALL": weather_row["RAINFALL"],
            "TMAX": weather_row["TMAX"],
            "TMIN": weather_row["TMIN"],
            "RH": weather_row["RH"],
            "WIND_SPEED": weather_row["WIND_SPEED"],
            "ACTUAL_YIELD": actual,
            "PREDICTED_YIELD": float(prediction),
            "ABS_ERROR": abs_error,
            "STATUS": status,
        })

    results = pd.DataFrame(rows)

    # ----------------------------------------------------
    # Display table
    # ----------------------------------------------------

    display = results.copy()

    for column in [
        "ACTUAL_YIELD",
        "PREDICTED_YIELD",
        "ABS_ERROR",
    ]:
        display[column] = display[
            column
        ].round(4)

    print("\n")
    print("=" * 86)
    print("JANUARY TO DECEMBER 2025")
    print("=" * 86)

    print(
        display[
            [
                "MONTH",
                "ACTUAL_YIELD",
                "PREDICTED_YIELD",
                "ABS_ERROR",
                "STATUS",
            ]
        ].to_string(index=False)
    )

    # ----------------------------------------------------
    # Accuracy metrics only for known actual yields
    # ----------------------------------------------------

    validated = results[
        results["ACTUAL_YIELD"].notna()
    ].copy()

    y_true = validated[
        "ACTUAL_YIELD"
    ].astype(float)

    y_pred = validated[
        "PREDICTED_YIELD"
    ].astype(float)

    mae = mean_absolute_error(
        y_true,
        y_pred
    )

    rmse = np.sqrt(
        mean_squared_error(
            y_true,
            y_pred
        )
    )

    r2 = r2_score(
        y_true,
        y_pred
    )

    print("\n")
    print("=" * 86)
    print("VALIDATION METRICS")
    print("=" * 86)

    print(
        f"Validated months : {len(validated)}/12"
    )

    print(
        "Prediction-only  : "
        f"{12 - len(validated)}/12"
    )

    print(
        f"MAE              : {mae:.4f}"
    )

    print(
        f"RMSE             : {rmse:.4f}"
    )

    print(
        f"R²               : {r2:.4f}"
    )

    # ----------------------------------------------------
    # Save CSV report
    # ----------------------------------------------------

    output_path = os.path.join(
        BASE_DIR,
        "storage",
        "datasets",
        "candidate_2025_full_predictions.csv",
    )

    results.to_csv(
        output_path,
        index=False
    )

    print("\n")
    print("=" * 86)
    print("REPORT SAVED")
    print("=" * 86)

    print(output_path)


if __name__ == "__main__":
    main()