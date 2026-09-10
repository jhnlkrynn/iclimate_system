"""
SMOKE TEST DATA GENERATOR — NOT REAL DATA.

Generates a purely synthetic, internally-consistent rice yield + climate
dataset so the Phase 1 pipeline (compare_yield_features.py, retraining,
joblib save) can be exercised end-to-end while the real MAO/PAGASA
historical dataset is still missing from this repo (see docs/capstone/
ICLIMATE_CHAPTERS_1_TO_3_MASTER.md and docs/phase2_gap_analysis.md).

Every row here is fabricated. Do NOT use any metric produced from this
file as a real accuracy claim, in a manuscript, or as a replacement for
the real MAO/PAGASA dataset. Output is written to a path prefixed with
SMOKETEST_ specifically so it can never be confused with a real export.
"""

import csv
import os
import random

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT_PATH = os.path.join(
    BASE_DIR, "storage", "app", "private", "ml", "SMOKETEST_rice_yield_training_data.csv"
)

BARANGAYS = [
    "Bagong Pook", "Balibago", "Barangay 1 (Pob.)", "Barangay 2 (Pob.)",
    "Binubusan", "Bungahan", "Cumba", "Humayingan", "Kapito", "Lumaniag",
    "Luyahan", "Malaruhatan", "Matabungkay", "Prenza", "Puting-Kahoy", "San Diego",
]
YEARS = range(2020, 2026)
SEASONS = ["Wet", "Dry"]
IRRIGATION = ["Irrigated", "Rainfed"]

random.seed(42)


def climate_for(season):
    if season == "Wet":
        rainfall = random.uniform(150, 350)
        temperature = random.uniform(26, 30)
    else:
        rainfall = random.uniform(20, 120)
        temperature = random.uniform(28, 34)

    humidity = random.uniform(60, 90)
    wind_speed = random.uniform(5, 25)

    return round(rainfall, 2), round(temperature, 2), round(humidity, 2), round(wind_speed, 2)


def yield_for(rainfall, temperature, irrigation_type, area_hectares):
    """Deliberately-learnable synthetic relationship (not real agronomy) so the
    smoke test can confirm the pipeline recovers a known signal, plus noise."""
    base = 3.6
    rainfall_effect = -0.004 * abs(rainfall - 220)
    temp_effect = -0.08 * abs(temperature - 27)
    irrigation_bonus = 0.5 if irrigation_type == "Irrigated" else 0.0
    noise = random.gauss(0, 0.35)

    value = base + rainfall_effect + temp_effect + irrigation_bonus + noise

    return round(max(0.8, value), 2), round(random.uniform(1.0, 9.0), 2)


def main():
    rows = []

    for year in YEARS:
        for season in SEASONS:
            rainfall, temperature, humidity, wind_speed = climate_for(season)

            for barangay in BARANGAYS:
                irrigation_type = random.choice(IRRIGATION)
                yield_per_hectare, area_hectares = yield_for(rainfall, temperature, irrigation_type, None)

                rows.append({
                    "barangay": barangay,
                    "year": year,
                    "season": season,
                    "irrigation_type": irrigation_type,
                    "rainfall": rainfall,
                    "temperature": temperature,
                    "humidity": humidity,
                    "wind_speed": wind_speed,
                    "area_hectares": area_hectares,
                    "yield_per_hectare": yield_per_hectare,
                })

    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)

    with open(OUTPUT_PATH, "w", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0].keys()))
        writer.writeheader()
        writer.writerows(rows)

    print(f"SMOKE TEST (fake) data written: {len(rows)} rows -> {OUTPUT_PATH}")
    print("This is synthetic data for pipeline verification only. Not real MAO/PAGASA records.")


if __name__ == "__main__":
    main()
