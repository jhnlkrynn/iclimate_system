import json
import os
import subprocess
import sys


BASE_DIR = os.path.dirname(
    os.path.dirname(os.path.abspath(__file__))
)

CURRENT_SCRIPT = os.path.join(
    BASE_DIR,
    "python_scripts",
    "predict.py"
)

CANDIDATE_SCRIPT = os.path.join(
    BASE_DIR,
    "python_scripts",
    "predict_rice_yield_candidate.py"
)


def run_model(script, payload):
    result = subprocess.run(
        [
            sys.executable,
            script,
            json.dumps(payload)
        ],
        capture_output=True,
        text=True
    )

    if result.returncode != 0:
        return {
            "success": False,
            "error": result.stderr.strip()
        }

    try:
        return {
            "success": True,
            "data": json.loads(result.stdout)
        }

    except json.JSONDecodeError:
        return {
            "success": False,
            "error": "Invalid JSON output",
            "raw_output": result.stdout
        }


# SAME CASE / SAME MONTH
# But each model receives the features it was trained on.

current_payload = {
    "rainfall": 250,
    "temp_avg": 28,
    "temp_range": 8,
    "area": 1.5,
    "previous_rainfall": 200,
    "previous_temp": 27.5,
    "rainfall_6m": 1200,
    "temp_3m": 28,
    "temp_6m": 27.8,
    "seasonal_rainfall": 1000,
    "seasonal_temp": 28,
    "season": "Wet",
    "farm_type": "Rainfed"
}


candidate_payload = {
    "month": 6,
    "rainfall": 250,
    "tmax": 32,
    "tmin": 24,
    "rh": 82,
    "wind_speed": 2.5,
    "wind_direction": 180
}


current_result = run_model(
    CURRENT_SCRIPT,
    current_payload
)

candidate_result = run_model(
    CANDIDATE_SCRIPT,
    candidate_payload
)


print("=" * 60)
print("RICE YIELD MODEL A/B COMPARISON")
print("=" * 60)


print("\nCURRENT PRODUCTION MODEL")
print("-" * 60)

if current_result["success"]:
    current_yield = current_result["data"]["predicted_yield"]

    print(
        f"Predicted Yield : {current_yield:.2f} t/ha"
    )

else:
    current_yield = None

    print(
        "ERROR:",
        current_result["error"]
    )


print("\nCANDIDATE RANDOM FOREST")
print("-" * 60)

if candidate_result["success"]:
    candidate_yield = candidate_result["data"]["predicted_yield"]

    print(
        f"Predicted Yield : {candidate_yield:.2f} t/ha"
    )

else:
    candidate_yield = None

    print(
        "ERROR:",
        candidate_result["error"]
    )


if (
    current_yield is not None
    and candidate_yield is not None
):

    difference = (
        candidate_yield
        - current_yield
    )

    print("\nCOMPARISON")
    print("-" * 60)

    print(
        f"Current    : {current_yield:.2f} t/ha"
    )

    print(
        f"Candidate  : {candidate_yield:.2f} t/ha"
    )

    print(
        f"Difference : {difference:+.2f} t/ha"
    )


print("\nSTATUS")
print("-" * 60)

print(
    "Current model   : PRODUCTION"
)

print(
    "Candidate model : TESTING ONLY"
)