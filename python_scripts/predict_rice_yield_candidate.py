import json
import math
import os
import sys
import warnings

import joblib
import pandas as pd


# ============================================================
# PATHS
# ============================================================

BASE_DIR = os.path.dirname(
    os.path.dirname(
        os.path.abspath(__file__)
    )
)

MODEL_PATH = os.path.join(
    BASE_DIR,
    "storage",
    "models",
    "rice_yield_model_candidate.pkl",
)


# ============================================================
# EXACT CANDIDATE MODEL FEATURES
# ============================================================

FEATURE_COLUMNS = [
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


# ============================================================
# PAYLOAD
# ============================================================

def read_payload():

    if len(sys.argv) < 2:
        raise ValueError(
            "Missing JSON prediction payload."
        )

    raw = sys.argv[1]

    try:
        payload = json.loads(raw)

    except json.JSONDecodeError as exc:
        raise ValueError(
            f"Invalid JSON prediction payload: {exc}"
        ) from exc

    if not isinstance(payload, dict):
        raise ValueError(
            "Prediction payload must be a JSON object."
        )

    return payload


# ============================================================
# VALIDATION
# ============================================================

def validate_payload(payload):

    required = [
        "month",
        "rainfall",
        "tmax",
        "tmin",
        "rh",
        "wind_speed",
        "wind_direction",
    ]

    missing = [
        name
        for name in required
        if name not in payload
    ]

    if missing:
        raise ValueError(
            "Missing required field(s): "
            + ", ".join(missing)
        )

    month = int(
        payload["month"]
    )

    rainfall = float(
        payload["rainfall"]
    )

    tmax = float(
        payload["tmax"]
    )

    tmin = float(
        payload["tmin"]
    )

    rh = float(
        payload["rh"]
    )

    wind_speed = float(
        payload["wind_speed"]
    )

    wind_direction = float(
        payload["wind_direction"]
    )

    if month < 1 or month > 12:
        raise ValueError(
            "month must be between 1 and 12."
        )

    if rainfall < 0:
        raise ValueError(
            "rainfall cannot be negative."
        )

    if tmax < tmin:
        raise ValueError(
            "tmax cannot be lower than tmin."
        )

    if rh < 0 or rh > 100:
        raise ValueError(
            "rh must be between 0 and 100."
        )

    if wind_speed < 0:
        raise ValueError(
            "wind_speed cannot be negative."
        )

    if (
        wind_direction < 0
        or wind_direction > 360
    ):
        raise ValueError(
            "wind_direction must be between 0 and 360."
        )

    return {
        "month": month,
        "rainfall": rainfall,
        "tmax": tmax,
        "tmin": tmin,
        "rh": rh,
        "wind_speed": wind_speed,
        "wind_direction": wind_direction,
    }


# ============================================================
# FEATURE ENGINEERING
# ============================================================

def build_features(values):

    month = values["month"]

    month_angle = (
        2
        * math.pi
        * month
        / 12
    )

    month_sin = math.sin(
        month_angle
    )

    month_cos = math.cos(
        month_angle
    )

    # --------------------------------------------------------
    # Must match the candidate model training rule.
    #
    # Wet:
    # May–September
    #
    # Dry:
    # October–April
    # --------------------------------------------------------

    season_wet = (
        1
        if month in [
            5,
            6,
            7,
            8,
            9,
        ]
        else 0
    )

    wind_radians = math.radians(
        values["wind_direction"]
    )

    wind_dir_sin = math.sin(
        wind_radians
    )

    wind_dir_cos = math.cos(
        wind_radians
    )

    row = {
        "MONTH_SIN":
            month_sin,

        "MONTH_COS":
            month_cos,

        "SEASON_WET":
            season_wet,

        "RAINFALL":
            values["rainfall"],

        "TMAX":
            values["tmax"],

        "TMIN":
            values["tmin"],

        "RH":
            values["rh"],

        "WIND_SPEED":
            values["wind_speed"],

        "WIND_DIR_SIN":
            wind_dir_sin,

        "WIND_DIR_COS":
            wind_dir_cos,
    }

    return pd.DataFrame(
        [row],
        columns=FEATURE_COLUMNS
    )


# ============================================================
# MODEL
# ============================================================

def load_model():

    if not os.path.exists(
        MODEL_PATH
    ):
        raise FileNotFoundError(
            "Candidate model not found: "
            + MODEL_PATH
        )

    return joblib.load(
        MODEL_PATH
    )


# ============================================================
# MAIN
# ============================================================

def main():

    warnings.filterwarnings(
        "ignore"
    )

    payload = read_payload()

    values = validate_payload(
        payload
    )

    features = build_features(
        values
    )

    model = load_model()

    # --------------------------------------------------------
    # Verify exact feature compatibility
    # --------------------------------------------------------

    if hasattr(
        model,
        "feature_names_in_"
    ):

        expected = list(
            model.feature_names_in_
        )

        if expected != FEATURE_COLUMNS:

            raise ValueError(
                "Candidate model feature mismatch. "
                f"Model expects {expected}, "
                f"but predictor provides {FEATURE_COLUMNS}."
            )

    predicted_yield = float(
        model.predict(
            features
        )[0]
    )

    result = {
        "success":
            True,

        "predicted_yield":
            round(
                predicted_yield,
                4
            ),

        "unit":
            "t/ha",

        "source_type":
            "Candidate Model",

        "source_name":
            "iClimate Candidate Random Forest",

        "weather_basis":
            "Ambulong",

        "status":
            "Testing Only",
    }

    print(
        json.dumps(
            result
        )
    )


# ============================================================
# ENTRY
# ============================================================

if __name__ == "__main__":

    try:
        main()

    except Exception as exc:

        error = {
            "success":
                False,

            "message":
                str(exc),
        }

        print(
            json.dumps(
                error
            )
        )

        sys.exit(1)