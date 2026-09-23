import json
import os
import subprocess
import sys


BASE_DIR = os.path.dirname(
    os.path.dirname(os.path.abspath(__file__))
)

CANDIDATE_SCRIPT = os.path.join(
    BASE_DIR,
    "python_scripts",
    "predict_rice_yield_candidate.py",
)


payload = {
    "month": 6,
    "rainfall": 250,
    "tmax": 32,
    "tmin": 24,
    "rh": 82,
    "wind_speed": 2.5,
    "wind_direction": 180
}


result = subprocess.run(
    [
        sys.executable,
        CANDIDATE_SCRIPT,
        json.dumps(payload)
    ],
    capture_output=True,
    text=True
)


print("=" * 50)
print("CANDIDATE MODEL TEST")
print("=" * 50)

print("\nINPUT:")
print(json.dumps(payload, indent=2))

print("\nRETURN CODE:")
print(result.returncode)

if result.stdout:
    print("\nOUTPUT:")
    print(result.stdout)

if result.stderr:
    print("\nERROR:")
    print(result.stderr)