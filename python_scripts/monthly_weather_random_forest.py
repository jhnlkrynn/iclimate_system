"""
Python/scikit-learn port of app/Services/MachineLearning/MonthlyWeatherRandomForest.php.

Replaces the hand-rolled PHP RandomForestRegressor/DecisionTreeRegressor (45 trees,
depth 7, rebuilt from scratch on every cache-miss request) with scikit-learn's
RandomForestRegressor, run once per target (rainfall, temperature, humidity,
wind_speed) exactly as the PHP version does — same feature vector, same training-
sample construction, same confidence/insights logic — so behavior stays the same
while training/inference moves off the PHP request thread.

Input contract (stdin JSON, one line):
{
  "target_month": "2026-11-01",
  "climate_records": [
    {"record_date": "2026-01-15", "rainfall": 120.5, "temperature": 27.1, "humidity": 78.0, "wind_speed": 12.3},
    ...
  ]
}

Output contract (stdout JSON): same shape as the PHP service's buildPrediction()
return value (see MonthlyWeatherRandomForest.php), so PredictionEngine.php (or,
later, the FastAPI service) can consume it as a drop-in replacement.
"""

import json
import math
import sys
import warnings
import zlib
from collections import defaultdict
from datetime import date
from statistics import mean, pstdev

import numpy as np
from sklearn.ensemble import RandomForestRegressor

TARGETS = ["rainfall", "temperature", "humidity", "wind_speed"]
WET_MONTHS = {5, 6, 7, 8, 9, 10}

FEATURE_KEYS = [
    "month", "month_sin", "month_cos", "year", "time_index", "is_wet_season",
    "prev_rainfall", "prev_temperature", "prev_humidity", "prev_wind_speed",
    "avg3_rainfall", "avg3_temperature", "avg3_humidity", "avg3_wind_speed",
]


def season_for_month(month_number: int) -> str:
    return "Wet" if month_number in WET_MONTHS else "Dry"


def parse_date(value: str) -> date:
    return date.fromisoformat(value[:10])


def month_key(d: date) -> str:
    return f"{d.year:04d}-{d.month:02d}"


def month_start(key: str) -> date:
    year, month = key.split("-")
    return date(int(year), int(month), 1)


def monthly_climate_records(climate_records: list[dict]) -> list[dict]:
    """Mirrors MonthlyWeatherRandomForest::monthlyClimateRecords()."""
    buckets: dict[str, list[dict]] = defaultdict(list)

    for record in climate_records:
        buckets[month_key(parse_date(record["record_date"]))].append(record)

    monthly = []
    for key in sorted(buckets.keys()):
        records = buckets[key]
        month = month_start(key)
        monthly.append({
            "month": month,
            "rainfall": mean(float(r["rainfall"]) for r in records),
            "temperature": mean(float(r["temperature"]) for r in records),
            "humidity": mean(float(r["humidity"]) for r in records),
            "wind_speed": mean(float(r["wind_speed"]) for r in records),
            "season": season_for_month(month.month),
            "records": len(records),
        })

    return monthly


def feature_vector(month: date, previous: dict, history: list[dict], index: int) -> dict:
    """Mirrors MonthlyWeatherRandomForest::featureVector()."""
    month_number = month.month
    angle = 2 * math.pi * (month_number / 12)

    def avg3(field):
        return mean(h[field] for h in history) if history else 0.0

    return {
        "month": month_number,
        "month_sin": math.sin(angle),
        "month_cos": math.cos(angle),
        "year": month.year,
        "time_index": index,
        "is_wet_season": 1 if season_for_month(month_number) == "Wet" else 0,
        "prev_rainfall": float(previous["rainfall"]),
        "prev_temperature": float(previous["temperature"]),
        "prev_humidity": float(previous["humidity"]),
        "prev_wind_speed": float(previous["wind_speed"]),
        "avg3_rainfall": avg3("rainfall"),
        "avg3_temperature": avg3("temperature"),
        "avg3_humidity": avg3("humidity"),
        "avg3_wind_speed": avg3("wind_speed"),
    }


def build_training_samples(monthly: list[dict]) -> tuple[list[dict], dict]:
    """Mirrors MonthlyWeatherRandomForest::buildTrainingSamples()."""
    samples = []

    for i in range(1, len(monthly)):
        current = monthly[i]
        previous = monthly[i - 1]
        history = monthly[max(0, i - 3):i][-3:]
        features = feature_vector(current["month"], previous, history, i)

        for target in TARGETS:
            features[target] = current[target]

        samples.append(features)

    latest = monthly[-1]
    history = monthly[max(0, len(monthly) - 3):][-3:]

    return samples, {"previous": latest, "history": history}


def target_stability(samples: list[dict], target: str) -> dict:
    """Mirrors MonthlyWeatherRandomForest::targetStability()."""
    values = [float(s[target]) for s in samples]
    avg = mean(values) if values else 0.0
    deviation = pstdev(values) if len(values) > 1 else 0.0
    ratio = (deviation / avg) if avg > 0 else 1.0

    label = "Stable" if ratio <= 0.12 else ("Variable" if ratio <= 0.24 else "Highly variable")

    return {"mean": round(avg, 2), "deviation": round(deviation, 2), "label": label}


def confidence(months_available: int, training: dict) -> dict:
    """Mirrors MonthlyWeatherRandomForest::confidence()."""
    score = min(92, 48 + (months_available * 4))

    for metric in training.values():
        label = metric.get("stability", {}).get("label", "Variable")
        if label == "Highly variable":
            score -= 6
        elif label == "Variable":
            score -= 3

    score = max(45, min(95, score))

    if score >= 80:
        label = "High confidence"
    elif score >= 65:
        label = "Moderate confidence"
    else:
        label = "Low confidence"

    note = (
        "The model has at least one full year of monthly climate history."
        if months_available >= 12
        else "Add more monthly climate records to improve forecast confidence."
    )

    return {"value": score, "label": label, "note": note}


def insights(predictions: dict, confidence_result: dict) -> list[str]:
    """Mirrors MonthlyWeatherRandomForest::insights()."""
    result = []

    rainfall = predictions.get("rainfall", 0)
    if rainfall > 300:
        result.append("Rainfall is elevated; prioritize drainage and flood monitoring.")
    elif rainfall < 100:
        result.append("Rainfall is low; rainfed farms may need delayed planting or water support.")
    else:
        result.append("Rainfall is within a workable planning range.")

    if predictions.get("temperature", 0) >= 32:
        result.append("Temperature may create heat stress during sensitive rice growth stages.")

    if predictions.get("humidity", 0) >= 88:
        result.append("Humidity is high; monitor for pest and disease pressure.")

    if predictions.get("wind_speed", 0) >= 18:
        result.append("Wind speed is elevated; watch for storm or lodging risk.")

    result.append(confidence_result["note"])

    seen = set()
    unique = []
    for item in result:
        if item not in seen:
            seen.add(item)
            unique.append(item)

    return unique


def not_ready(message: str, months_available: int, target_month: date) -> dict:
    return {
        "ready": False,
        "message": message,
        "source_type": "Trained Model",
        "source_name": "iClimate monthly Random Forest model",
        "source_note": "Model trains from saved iClimate climate records.",
        "months_available": months_available,
        "target_month": target_month.isoformat(),
        "predictions": {},
        "training": {},
    }


def _leave_one_out_mae(x_train: np.ndarray, y_train: np.ndarray, max_features: int, target_seed_key: str) -> float:
    """Cheap real validation signal for small samples — not a replacement for
    proper k-fold CV, but strictly better than reporting no accuracy measure
    at all. Only called when there are >=5 samples (see caller)."""
    errors = []

    for i in range(len(y_train)):
        train_idx = [j for j in range(len(y_train)) if j != i]
        forest = RandomForestRegressor(
            n_estimators=45,
            max_depth=7,
            min_samples_split=2,
            max_features=max_features,
            random_state=zlib.crc32(f"{target_seed_key}-loo-{i}".encode()),
        )
        forest.fit(x_train[train_idx], y_train[train_idx])
        prediction = forest.predict(x_train[[i]])[0]
        errors.append(abs(prediction - y_train[i]))

    return float(mean(errors))


def predict(target_month: date, climate_records: list[dict]) -> dict:
    monthly = monthly_climate_records(climate_records)

    if len(monthly) < 4:
        return not_ready(
            "At least 4 months of climate records are required to train the monthly Random Forest model.",
            len(monthly), target_month,
        )

    samples, latest_context = build_training_samples(monthly)

    if len(samples) < 3:
        return not_ready(
            "The available records must span at least 4 usable monthly observations.",
            len(monthly), target_month,
        )

    features = feature_vector(target_month.replace(day=1), latest_context["previous"], latest_context["history"], len(monthly))

    feature_order = [k for k in FEATURE_KEYS]
    x_train = np.array([[s[k] for k in feature_order] for s in samples])
    x_target = np.array([[features[k] for k in feature_order]])

    predictions = {}
    training = {}

    max_features = max(2, math.floor(math.sqrt(len(feature_order))))

    for target in TARGETS:
        y_train = np.array([s[target] for s in samples])

        forest = RandomForestRegressor(
            n_estimators=45,
            max_depth=7,
            min_samples_split=2,
            max_features=max_features,
            random_state=zlib.crc32(target.encode()),
        )
        forest.fit(x_train, y_train)
        predicted_value = float(forest.predict(x_target)[0])

        loo_mae = _leave_one_out_mae(x_train, y_train, max_features, target) if len(y_train) >= 5 else None

        predictions[target] = round(predicted_value, 2)
        training[target] = {
            "trees": forest.n_estimators,
            "samples": len(samples),
            "features": len(feature_order),
            "stability": target_stability(samples, target),
            "loo_mae": round(loo_mae, 2) if loo_mae is not None else None,
        }

    predictions["season"] = season_for_month(target_month.month)
    confidence_result = confidence(len(monthly), training)

    return {
        "ready": True,
        "message": "Prediction generated using monthly climate history and Random Forest regression.",
        "source_type": "Trained Model",
        "source_name": "iClimate monthly Random Forest model",
        "source_note": "Model output trained from saved monthly climate records.",
        "months_available": len(monthly),
        "target_month": target_month.isoformat(),
        "predictions": predictions,
        "training": training,
        "confidence": confidence_result,
        "insights": insights(predictions, confidence_result),
    }


def main():
    warnings.filterwarnings("ignore")
    payload = json.loads(sys.stdin.read() if len(sys.argv) < 2 else sys.argv[1])

    target_month = parse_date(payload["target_month"])
    climate_records = payload["climate_records"]

    print(json.dumps(predict(target_month, climate_records)))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)
