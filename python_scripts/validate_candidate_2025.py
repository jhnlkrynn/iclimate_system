import os
import joblib
import pandas as pd
import numpy as np

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


DATASET_PATH = os.path.join(
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


TARGET = "AVERAGE_YIELD"


EXPECTED_METRICS = {
    "MAE": 0.3944,
    "RMSE": 0.4690,
    "R2": -1.1043,
}


def main():

    print("=" * 72)
    print("iCLIMATE CANDIDATE RANDOM FOREST - 2025 HOLDOUT VALIDATION")
    print("=" * 72)

    # ---------------------------------------------------------
    # 1. Check files
    # ---------------------------------------------------------

    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError(
            f"Candidate model not found:\n{MODEL_PATH}"
        )

    if not os.path.exists(DATASET_PATH):
        raise FileNotFoundError(
            f"Dataset not found:\n{DATASET_PATH}"
        )

    print("\n[OK] Candidate model found")
    print("[OK] Dataset found")


    # ---------------------------------------------------------
    # 2. Load dataset
    # ---------------------------------------------------------

    df = pd.read_csv(DATASET_PATH)

    print(
        f"\nDataset rows: {len(df)}"
    )


    # ---------------------------------------------------------
    # 3. Check required columns
    # ---------------------------------------------------------

    required_columns = (
        ["YEAR", "MONTH", "SEASON", "SPLIT"]
        + FEATURES
        + [TARGET]
    )

    missing_columns = [
        column
        for column in required_columns
        if column not in df.columns
    ]

    if missing_columns:
        raise ValueError(
            "Missing dataset columns: "
            + ", ".join(missing_columns)
        )


    # ---------------------------------------------------------
    # 4. Select untouched 2025 TEST rows
    # ---------------------------------------------------------

    test_df = df[
        (df["YEAR"] == 2025)
        & (
            df["SPLIT"]
            .astype(str)
            .str.upper()
            == "TEST"
        )
    ].copy()


    if test_df.empty:
        raise ValueError(
            "No 2025 TEST rows found."
        )


    print(
        f"2025 TEST rows: {len(test_df)}"
    )


    # ---------------------------------------------------------
    # 5. Prepare X and y
    # ---------------------------------------------------------

    X_test = test_df[FEATURES].copy()

    y_true = test_df[TARGET].astype(float)


    # ---------------------------------------------------------
    # 6. Load candidate model
    # ---------------------------------------------------------

    model = joblib.load(MODEL_PATH)

    print(
        f"\nModel type: {type(model).__name__}"
    )


    # ---------------------------------------------------------
    # 7. Verify model feature names
    # ---------------------------------------------------------

    if hasattr(model, "feature_names_in_"):

        model_features = list(
            model.feature_names_in_
        )

        if model_features != FEATURES:

            print("\nWARNING:")
            print(
                "Feature order in model does not "
                "match validation script."
            )

            print("\nModel expects:")
            print(model_features)

            print("\nScript uses:")
            print(FEATURES)

            raise ValueError(
                "Feature order mismatch."
            )

        print(
            "[OK] Model feature names/order match"
        )


    # ---------------------------------------------------------
    # 8. Predict
    # ---------------------------------------------------------

    y_pred = model.predict(X_test)


    # ---------------------------------------------------------
    # 9. Calculate row-by-row errors
    # ---------------------------------------------------------

    results = pd.DataFrame({
        "YEAR": test_df["YEAR"].values,
        "MONTH": test_df["MONTH"].values,
        "SEASON": test_df["SEASON"].values,
        "ACTUAL_YIELD": y_true.values,
        "PREDICTED_YIELD": y_pred,
    })


    results["ABS_ERROR"] = np.abs(
        results["ACTUAL_YIELD"]
        - results["PREDICTED_YIELD"]
    )


    results["SIGNED_ERROR"] = (
        results["PREDICTED_YIELD"]
        - results["ACTUAL_YIELD"]
    )


    # ---------------------------------------------------------
    # 10. Metrics
    # ---------------------------------------------------------

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


    # ---------------------------------------------------------
    # 11. Display predictions
    # ---------------------------------------------------------

    print("\n")
    print("=" * 72)
    print("2025 ACTUAL VS CANDIDATE PREDICTIONS")
    print("=" * 72)

    display_results = results.copy()

    display_results[
        "ACTUAL_YIELD"
    ] = display_results[
        "ACTUAL_YIELD"
    ].round(4)

    display_results[
        "PREDICTED_YIELD"
    ] = display_results[
        "PREDICTED_YIELD"
    ].round(4)

    display_results[
        "ABS_ERROR"
    ] = display_results[
        "ABS_ERROR"
    ].round(4)

    display_results[
        "SIGNED_ERROR"
    ] = display_results[
        "SIGNED_ERROR"
    ].round(4)


    print(
        display_results.to_string(
            index=False
        )
    )


    # ---------------------------------------------------------
    # 12. Display calculated metrics
    # ---------------------------------------------------------

    print("\n")
    print("=" * 72)
    print("CALCULATED 2025 METRICS")
    print("=" * 72)

    print(
        f"MAE  : {mae:.4f}"
    )

    print(
        f"RMSE : {rmse:.4f}"
    )

    print(
        f"R²   : {r2:.4f}"
    )


    # ---------------------------------------------------------
    # 13. Compare against metadata
    # ---------------------------------------------------------

    print("\n")
    print("=" * 72)
    print("METADATA VERIFICATION")
    print("=" * 72)

    tolerance = 0.001


    calculated = {
        "MAE": mae,
        "RMSE": rmse,
        "R2": r2,
    }


    all_match = True


    for metric in [
        "MAE",
        "RMSE",
        "R2"
    ]:

        expected = EXPECTED_METRICS[
            metric
        ]

        actual = calculated[
            metric
        ]

        difference = abs(
            actual - expected
        )

        match = (
            difference <= tolerance
        )

        if not match:
            all_match = False


        status = (
            "MATCH"
            if match
            else "DIFFERENT"
        )


        print(
            f"{metric:<5} "
            f"Calculated={actual:.4f} | "
            f"Metadata={expected:.4f} | "
            f"{status}"
        )


    # ---------------------------------------------------------
    # 14. Final verification result
    # ---------------------------------------------------------

    print("\n")
    print("=" * 72)

    if all_match:

        print(
            "VERIFICATION PASSED"
        )

        print(
            "The candidate model reproduces "
            "the recorded 2025 holdout metrics."
        )

    else:

        print(
            "VERIFICATION NEEDS REVIEW"
        )

        print(
            "The calculated metrics do not "
            "fully match the model metadata."
        )


    print("=" * 72)


if __name__ == "__main__":
    main()