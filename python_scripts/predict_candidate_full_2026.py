import os
import sys
import warnings

import joblib
import numpy as np
import pandas as pd


# ============================================================
# CONFIGURATION
# ============================================================

YEAR = 2026


BASE_DIR = os.path.dirname(
    os.path.dirname(os.path.abspath(__file__))
)


DATASET_DIR = os.path.join(
    BASE_DIR,
    "storage",
    "datasets",
)


MODEL_PATH = os.path.join(
    BASE_DIR,
    "storage",
    "models",
    "rice_yield_model_candidate.pkl",
)


WEATHER_PATH = os.path.join(
    DATASET_DIR,
    "weather_2026.csv",
)


OUTPUT_PATH = os.path.join(
    DATASET_DIR,
    "candidate_2026_full_predictions.csv",
)


# ============================================================
# EXACT MODEL FEATURES
# ============================================================

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


REQUIRED_WEATHER_COLUMNS = [
    "RAINFALL",
    "TMAX",
    "TMIN",
    "RH",
    "WIND_SPEED",
    "WIND_DIRECTION",
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


# ============================================================
# AUTOMATIC WEATHER TEMPLATE CREATION
# ============================================================

def create_weather_template():
    """
    Create weather_2026.csv automatically if it does not exist.

    Weather values are intentionally blank because this script
    must not invent Ambulong meteorological observations.
    """

    os.makedirs(
        DATASET_DIR,
        exist_ok=True
    )

    if os.path.exists(WEATHER_PATH):
        return False

    rows = []

    for month in range(1, 13):
        rows.append({
            "YEAR": YEAR,
            "MONTH": month,
            "RAINFALL": np.nan,
            "TMAX": np.nan,
            "TMIN": np.nan,
            "RH": np.nan,
            "WIND_SPEED": np.nan,
            "WIND_DIRECTION": np.nan,
        })

    pd.DataFrame(rows).to_csv(
        WEATHER_PATH,
        index=False
    )

    return True


# ============================================================
# FEATURE ENGINEERING
# ============================================================

def build_features(df):
    """
    Build the exact engineered features used by the
    candidate Random Forest model.
    """

    df = df.copy()


    # --------------------------------------------------------
    # Month cyclical encoding
    # --------------------------------------------------------

    df["MONTH_SIN"] = np.sin(
        2 * np.pi
        * df["MONTH"]
        / 12
    )

    df["MONTH_COS"] = np.cos(
        2 * np.pi
        * df["MONTH"]
        / 12
    )


    # --------------------------------------------------------
    # Season encoding
    # --------------------------------------------------------
    #
    # Wet-season rule must remain consistent with
    # the candidate model training dataset.
    #
    # Wet  = May–September
    # Dry  = October–April
    #
    # --------------------------------------------------------

    df["SEASON_WET"] = (
        df["MONTH"]
        .isin([
            5,
            6,
            7,
            8,
            9,
        ])
        .astype(int)
    )


    # --------------------------------------------------------
    # Wind-direction cyclical encoding
    # --------------------------------------------------------

    radians = np.radians(
        df["WIND_DIRECTION"].astype(float)
    )

    df["WIND_DIR_SIN"] = np.sin(
        radians
    )

    df["WIND_DIR_COS"] = np.cos(
        radians
    )


    return df


# ============================================================
# WEATHER DATA VALIDATION
# ============================================================

def validate_weather_data(weather):
    """
    Validate Ambulong 2026 monthly weather before prediction.
    """

    # Convert expected fields to numbers.
    # Blank/invalid cells become NaN.

    for column in REQUIRED_WEATHER_COLUMNS:

        weather[column] = pd.to_numeric(
            weather[column],
            errors="coerce"
        )


    # --------------------------------------------------------
    # Missing values
    # --------------------------------------------------------

    missing = weather[
        REQUIRED_WEATHER_COLUMNS
    ].isna()


    if missing.any().any():

        bad_rows = weather.loc[
            missing.any(axis=1),
            [
                "MONTH",
                "RAINFALL",
                "TMAX",
                "TMIN",
                "RH",
                "WIND_SPEED",
                "WIND_DIRECTION",
            ]
        ]

        raise ValueError(
            "\nAmbulong 2026 weather data is incomplete.\n\n"
            "Fill all required monthly weather values before "
            "generating rice-yield predictions.\n\n"
            "Required fields:\n"
            "- RAINFALL\n"
            "- TMAX\n"
            "- TMIN\n"
            "- RH\n"
            "- WIND_SPEED\n"
            "- WIND_DIRECTION\n\n"
            "Incomplete rows:\n"
            + bad_rows.to_string(index=False)
        )


    # --------------------------------------------------------
    # Rainfall
    # --------------------------------------------------------

    if weather["RAINFALL"].lt(0).any():
        raise ValueError(
            "RAINFALL cannot be negative."
        )


    # --------------------------------------------------------
    # Relative humidity
    # --------------------------------------------------------

    if (
        weather["RH"].lt(0).any()
        or weather["RH"].gt(100).any()
    ):
        raise ValueError(
            "RH must be between 0 and 100."
        )


    # --------------------------------------------------------
    # Wind speed
    # --------------------------------------------------------

    if weather["WIND_SPEED"].lt(0).any():
        raise ValueError(
            "WIND_SPEED cannot be negative."
        )


    # --------------------------------------------------------
    # Wind direction
    # --------------------------------------------------------

    if (
        weather["WIND_DIRECTION"].lt(0).any()
        or weather["WIND_DIRECTION"].gt(360).any()
    ):
        raise ValueError(
            "WIND_DIRECTION must be between "
            "0 and 360 degrees."
        )


    # --------------------------------------------------------
    # Temperature
    # --------------------------------------------------------

    if (
        weather["TMAX"]
        < weather["TMIN"]
    ).any():
        raise ValueError(
            "TMAX cannot be lower than TMIN."
        )


# ============================================================
# MAIN
# ============================================================

def main():

    warnings.filterwarnings(
        "ignore",
        category=UserWarning
    )


    print("=" * 78)

    print(
        "iCLIMATE - AMBULONG 2026 "
        "CANDIDATE RICE YIELD PREDICTION"
    )

    print("=" * 78)


    # --------------------------------------------------------
    # 1. Create CSV template if missing
    # --------------------------------------------------------

    created = create_weather_template()


    if created:

        print("\n[CREATED]")

        print(
            "weather_2026.csv was created automatically."
        )

        print("\nLocation:")

        print(
            WEATHER_PATH
        )

        print(
            "\nThe file contains January to December 2026."
        )

        print(
            "\nWeather values are intentionally blank."
        )

        print(
            "\nFill the Ambulong 2026 weather values "
            "and run this command again:"
        )

        print(
            "\npython "
            "python_scripts\\predict_candidate_full_2026.py"
        )

        return


    print(
        "\n[OK] Ambulong weather file found"
    )


    # --------------------------------------------------------
    # 2. Candidate model check
    # --------------------------------------------------------

    if not os.path.exists(MODEL_PATH):

        raise FileNotFoundError(
            "Candidate model not found:\n"
            + MODEL_PATH
        )


    print(
        "[OK] Candidate Random Forest model found"
    )


    # --------------------------------------------------------
    # 3. Read weather CSV
    # --------------------------------------------------------

    weather = pd.read_csv(
        WEATHER_PATH
    )


    required_columns = [
        "YEAR",
        "MONTH",
        "RAINFALL",
        "TMAX",
        "TMIN",
        "RH",
        "WIND_SPEED",
        "WIND_DIRECTION",
    ]


    missing_columns = [
        column
        for column in required_columns
        if column not in weather.columns
    ]


    if missing_columns:

        raise ValueError(
            "weather_2026.csv is missing columns: "
            + ", ".join(missing_columns)
        )


    # --------------------------------------------------------
    # 4. Select 2026
    # --------------------------------------------------------

    weather_2026 = weather[
        weather["YEAR"] == YEAR
    ].copy()


    if weather_2026.empty:

        raise ValueError(
            "No YEAR=2026 rows found."
        )


    # --------------------------------------------------------
    # 5. Validate month numbers
    # --------------------------------------------------------

    weather_2026["MONTH"] = pd.to_numeric(
        weather_2026["MONTH"],
        errors="coerce"
    )


    if weather_2026["MONTH"].isna().any():

        raise ValueError(
            "Invalid MONTH value detected."
        )


    weather_2026["MONTH"] = (
        weather_2026["MONTH"]
        .astype(int)
    )


    weather_2026 = (
        weather_2026
        .sort_values("MONTH")
        .reset_index(drop=True)
    )


    if len(weather_2026) != 12:

        raise ValueError(
            "Expected exactly 12 rows for 2026, "
            f"found {len(weather_2026)}."
        )


    expected_months = list(
        range(1, 13)
    )


    actual_months = (
        weather_2026[
            "MONTH"
        ].tolist()
    )


    if actual_months != expected_months:

        raise ValueError(
            "MONTH must contain exactly "
            "January (1) through December (12)."
        )


    print(
        "[OK] January–December 2026 rows found"
    )


    # --------------------------------------------------------
    # 6. Validate Ambulong weather values
    # --------------------------------------------------------

    validate_weather_data(
        weather_2026
    )


    print(
        "[OK] Ambulong weather inputs are complete"
    )


    # --------------------------------------------------------
    # 7. Feature engineering
    # --------------------------------------------------------

    weather_2026 = build_features(
        weather_2026
    )


    X = weather_2026[
        FEATURES
    ].copy()


    if X.isna().any().any():

        raise ValueError(
            "Generated Random Forest features "
            "contain missing values."
        )


    # --------------------------------------------------------
    # 8. Load candidate model
    # --------------------------------------------------------

    model = joblib.load(
        MODEL_PATH
    )


    print(
        f"[OK] Model loaded: "
        f"{type(model).__name__}"
    )


    # --------------------------------------------------------
    # 9. Feature compatibility
    # --------------------------------------------------------

    if hasattr(
        model,
        "feature_names_in_"
    ):

        model_features = list(
            model.feature_names_in_
        )


        if model_features != FEATURES:

            print("\nModel expects:")

            print(
                model_features
            )


            print("\nPrediction script provides:")

            print(
                FEATURES
            )


            raise ValueError(
                "Candidate model feature mismatch."
            )


    print(
        "[OK] Feature names/order match"
    )


    # --------------------------------------------------------
    # 10. Generate rice-yield predictions
    # --------------------------------------------------------

    predictions = model.predict(
        X
    )


    # --------------------------------------------------------
    # 11. Build report
    # --------------------------------------------------------

    rows = []


    for (_, row), prediction in zip(
        weather_2026.iterrows(),
        predictions
    ):

        month = int(
            row["MONTH"]
        )


        rows.append({

            "MONTH_NO":
                month,

            "MONTH":
                MONTH_NAMES[month],

            "RAINFALL":
                float(row["RAINFALL"]),

            "TMAX":
                float(row["TMAX"]),

            "TMIN":
                float(row["TMIN"]),

            "RH":
                float(row["RH"]),

            "WIND_SPEED":
                float(row["WIND_SPEED"]),

            "WIND_DIRECTION":
                float(row["WIND_DIRECTION"]),

            "WEATHER_SOURCE":
                "Ambulong",

            "ACTUAL_YIELD":
                np.nan,

            "PREDICTED_YIELD":
                float(prediction),

            "ABS_ERROR":
                np.nan,

            "STATUS":
                "Prediction only",
        })


    results = pd.DataFrame(
        rows
    )


    # --------------------------------------------------------
    # 12. Console display
    # --------------------------------------------------------

    print("\n")

    print("=" * 78)

    print(
        "JANUARY TO DECEMBER 2026 "
        "- AMBULONG WEATHER BASIS"
    )

    print("=" * 78)


    display = results[
        [
            "MONTH",
            "RAINFALL",
            "TMAX",
            "TMIN",
            "RH",
            "WIND_SPEED",
            "PREDICTED_YIELD",
            "STATUS",
        ]
    ].copy()


    display[
        "PREDICTED_YIELD"
    ] = display[
        "PREDICTED_YIELD"
    ].round(4)


    print(
        display.to_string(
            index=False
        )
    )


    # --------------------------------------------------------
    # 13. Save report
    # --------------------------------------------------------

    results.to_csv(
        OUTPUT_PATH,
        index=False
    )


    print("\n")

    print("=" * 78)

    print(
        "REPORT SAVED"
    )

    print("=" * 78)


    print(
        OUTPUT_PATH
    )


    print("\nWeather basis:")

    print(
        "Ambulong meteorological reference data"
    )


    print("\nValidation status:")

    print(
        "Prediction only. Actual 2026 yield "
        "records are not yet available."
    )


# ============================================================
# ENTRY POINT
# ============================================================

if __name__ == "__main__":

    try:

        main()

    except Exception as exc:

        print("\n")

        print("=" * 78)

        print(
            "PREDICTION STOPPED"
        )

        print("=" * 78)


        print(
            str(exc)
        )


        print("=" * 78)


        sys.exit(1)