"""Pure-Python analytics handlers called only by queued Laravel jobs."""

import calendar
from collections import defaultdict
from datetime import date, datetime, timedelta
import csv
import hashlib
import os
import tempfile

import evaluation_service
import monthly_weather_random_forest
import rice_yield_service


REPORT_COLUMNS = {
    "climate_records": {
        "record_type": "Record Type", "record_date": "Date", "rainfall": "Rainfall", "temperature": "Temperature",
        "humidity": "Humidity", "wind_speed": "Wind Speed", "season": "Season", "source": "Source",
    },
    "rice_production": {
        "barangay": "Barangay", "season": "Season", "irrigation_type": "Irrigation",
        "yield_per_hectare": "Yield/Ha", "area_hectares": "Area",
        "total_production": "Total", "year": "Year",
    },
    "farmer_registration": {
        "full_name": "Farmer", "contact_number": "Contact", "barangay": "Barangay",
        "farm_area": "Farm Area", "farm_type": "Farm Type", "created_at": "Registered",
    },
    "advisory": {
        "title": "Title", "type": "Type", "target_barangay": "Barangay",
        "status": "Status", "created_at": "Posted",
    },
    "community_feed": {
        "title": "Title", "category": "Category", "visibility": "Visibility",
        "author_name": "Posted By", "created_at": "Posted",
    },
}


def _number(value, default=0.0):
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def _optional_number(value):
    """float(value), or None when the value is missing or not numeric.

    A missing reading is NOT zero: 0 mm of rain is a real observation, a missing
    temperature or humidity is not 0 degrees / 0 %. Use this wherever the
    difference matters; _number() above is only for places where a missing value
    is genuinely harmless.
    """
    if value is None or isinstance(value, bool):
        return None
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None
    return None if number != number else number


def _mean(values):
    present = [v for v in values if v is not None]
    return sum(present) / len(present) if present else None


def _day(value):
    return str(value or "")[:10]


def _season(month: int) -> str:
    return "Wet" if month in {5, 6, 7, 8, 9, 10} else "Dry"


def weather_prediction(payload: dict) -> dict:
    target = date.fromisoformat(str(payload["target_date"])[:10])
    result = monthly_weather_random_forest.predict(target.replace(day=1), payload.get("climate_records", []))
    predictions = result.get("predictions", {})
    predicted_rainfall = _optional_number(predictions.get("rainfall"))
    if predicted_rainfall is None:
        risk_guidance = None
        recommendation = None
    elif predicted_rainfall > 300:
        risk_guidance = "High rainfall risk"
        recommendation = "Prioritize drainage, delay field work during heavy rain, and monitor PAGASA or LGU warnings."
    elif predicted_rainfall < 100:
        risk_guidance = "Low rainfall risk"
        recommendation = "Plan supplemental irrigation and confirm soil moisture before planting rainfed fields."
    else:
        risk_guidance = "Manageable rainfall outlook"
        recommendation = "Normal farm planning may continue, with routine field and official weather monitoring."

    result.update({
        "prediction_type": "weather",
        "target_date": target.isoformat(),
        "target_month": target.replace(day=1).isoformat(),
        "location": payload.get("location", "Lian, Batangas"),
        "generated_at": datetime.now().isoformat(),
        "model_runtime": "Python scikit-learn RandomForestRegressor",
        "processor": "python",
        "live_weather": payload.get("live_weather"),
        "predicted_rainfall": round(predicted_rainfall, 2) if predicted_rainfall is not None else None,
        "season": predictions.get("season", _season(target.month)),
        "reliability": result.get("confidence", {}),
        "risk_guidance": risk_guidance,
        "farming_recommendation": recommendation,
        "model_input": {
            "rainfall": predictions.get("rainfall"),
            "temp_avg": predictions.get("temperature"),
            "humidity": predictions.get("humidity"),
            "wind_speed": predictions.get("wind_speed"),
            "season": predictions.get("season", _season(target.month)),
            "month_num": target.month,
        },
    })
    return result


REQUIRED_RICE_INPUTS = (
    "rainfall", "temp_avg", "temp_range", "area", "previous_rainfall", "previous_temp",
    "previous_humidity", "previous_wind_speed", "rainfall_6m", "temp_3m", "temp_6m",
    "seasonal_rainfall", "seasonal_temp", "season", "month_num",
)


def _model_input(payload: dict, weather: dict) -> dict:
    """Model inputs from real data only. Anything that cannot be derived is None
    (never 0, never a made-up default such as 75 % humidity or a 1 ha farm), and
    rice_yield_prediction() refuses to run the model while any required one is None."""
    records = sorted(payload.get("climate_records", []), key=lambda row: _day(row.get("record_date")))
    recent = records[-6:]
    latest = recent[-1] if recent else {}
    predictions = weather.get("predictions", {})
    temperatures = [t for t in (_optional_number(row.get("temperature")) for row in recent) if t is not None]
    rainfalls = [r for r in (_optional_number(row.get("rainfall")) for row in recent) if r is not None]
    target_month = date.fromisoformat(payload["target_date"]).month
    season = predictions.get("season") or _season(target_month)
    seasonal = [row for row in recent if row.get("season") == season]
    seasonal_rain = [r for r in (_optional_number(row.get("rainfall")) for row in seasonal) if r is not None]
    seasonal_temp = [t for t in (_optional_number(row.get("temperature")) for row in seasonal) if t is not None]

    def rounded(value):
        return round(value, 2) if value is not None else None

    has6 = len(recent) >= 6
    has3 = len(recent) >= 3
    area = _optional_number(payload.get("area"))

    return {
        "rainfall": rounded(_optional_number(predictions.get("rainfall"))),
        "temp_avg": rounded(_optional_number(predictions.get("temperature"))),
        "temp_range": rounded(max(temperatures) - min(temperatures)) if temperatures else None,
        "area": rounded(area) if area is not None and area > 0 else None,
        "previous_rainfall": rounded(_optional_number(latest.get("rainfall"))),
        "previous_temp": rounded(_optional_number(latest.get("temperature"))),
        "previous_humidity": rounded(_optional_number(latest.get("humidity"))),
        "previous_wind_speed": rounded(_optional_number(latest.get("wind_speed"))),
        "rainfall_6m": rounded(sum(rainfalls)) if has6 and len(rainfalls) == len(recent) else None,
        "temp_3m": rounded(_mean(temperatures[-3:])) if has3 else None,
        "temp_6m": rounded(_mean(temperatures)) if has6 else None,
        "seasonal_rainfall": rounded(sum(seasonal_rain)) if seasonal_rain else None,
        "seasonal_temp": rounded(_mean(seasonal_temp)),
        "humidity": rounded(_optional_number(predictions.get("humidity"))),
        "wind_speed": rounded(_optional_number(predictions.get("wind_speed"))),
        "season": season,
        "farm_type": payload.get("farm_type", "Rainfed"),
        "month_num": target_month,
    }


def rice_yield_prediction(payload: dict) -> dict:
    weather = weather_prediction(payload)
    features = _model_input(payload, weather)
    missing = [key for key in REQUIRED_RICE_INPUTS if features.get(key) is None]
    if missing:
        # No model run, no number: the caller records this run as failed.
        raise ValueError("Not enough verified input data for a rice-yield prediction (missing: " + ", ".join(missing) + ").")
    prediction = rice_yield_service.predict(features)
    yield_data = prediction["rice_yield_prediction"]
    predicted = _number(yield_data.get("predicted_yield"))
    area = _number(features["area"])
    warnings = prediction.get("warnings", [])
    risk_level = "High" if len(warnings) >= 3 else ("Moderate" if warnings else "Low")
    condition_score = max(0, min(100, int(prediction.get("confidence_score", 50))))

    return {
        "weather_result": weather,
        "ml_result": {
            "success": True,
            "prediction_type": "rice_yield",
            "yield_tons_per_hectare": round(predicted, 2),
            "predicted_yield": round(predicted, 2),
            "unit": yield_data.get("unit", "tons/hectare"),
            "farm_area_hectares": round(area, 2),
            "estimated_total_production_tons": round(predicted * area, 2),
            "model_input": features,
            "features": features,
            "risk_level": risk_level,
            "risk_label": f"{risk_level} Risk",
            "risk_score": min(1, len(warnings) / 4),
            "condition_score": condition_score,
            "condition_score_label": "Python model confidence score",
            "api_confidence": condition_score,
            "planting_advisory": prediction["planting_recommendation"]["action"],
            "irrigation_recommendation": prediction["irrigation_recommendation"]["recommendation"],
            "weather_assessment": prediction.get("weather_prediction", {}).get(
                "predicted_weather", "Weather conditions were evaluated by the Python model."
            ),
            "warnings": warnings,
            "source": prediction.get("source_name"),
            "source_name": prediction.get("source_name"),
            "source_credit": prediction.get("source_credit"),
            "processor": "python",
        },
    }


def heatmap_risk(payload: dict) -> dict:
    climate = sorted(payload.get("climate_records", []), key=lambda row: _day(row.get("record_date")))
    latest_weather = climate[-1] if climate else {}
    production_by_barangay = defaultdict(list)
    for row in payload.get("rice_productions", []):
        production_by_barangay[str(row.get("barangay", ""))].append(row)

    # Barangay coordinates come from the caller (LianBarangays.php, centroids of the real
    # boundary polygons). There is no built-in coordinate table here any more.
    areas = payload.get("areas", [])
    rainfall = _optional_number(latest_weather.get("rainfall"))
    temperature = _optional_number(latest_weather.get("temperature"))
    wind = _optional_number(latest_weather.get("wind_speed"))
    if rainfall is None or temperature is None or wind is None:
        return {
            "areas": [],
            "row_count": 0,
            "processor": "python",
            "unavailable_reason": "No complete weather reading is available, so no barangay risk was scored.",
        }
    results = []
    for area in areas:
        scores = {"Flood": rainfall / 400, "Drought": (100 - rainfall) / 100, "Heat": (temperature - 28) / 10, "Typhoon": wind / 40}
        risk_type, raw_score = max(scores.items(), key=lambda item: item[1])
        score = round(max(0, min(1, raw_score)), 2)
        level = "Severe" if score >= .85 else ("High" if score >= .65 else ("Moderate" if score >= .4 else "Low"))
        productions = sorted(production_by_barangay.get(str(area.get("barangay")), []), key=lambda row: int(row.get("year") or 0))
        predicted_yield = _number(productions[-1].get("yield_per_hectare")) if productions else None
        results.append({
            **area,
            "risk_type": risk_type,
            "risk_score": score,
            "risk_level": level,
            "predicted_yield": predicted_yield,
            "rainfall_status": "Heavy" if rainfall >= 300 else ("Dry" if rainfall <= 80 else "Normal"),
            "planting_advisory": "Delay planting and monitor conditions." if level in {"High", "Severe"} else "Conditions are suitable with normal monitoring.",
            "irrigation_recommendation": "Increase irrigation monitoring." if risk_type in {"Drought", "Heat"} else "Check drainage and reduce excess irrigation.",
            "description": f"Python analytics risk score based on rainfall, temperature, wind, and stored production data ({score:.2f}).",
        })
    return {"areas": results, "row_count": len(results), "processor": "python"}


def advisory_ai(payload: dict) -> dict:
    advisories = []
    for row in payload.get("weather_records", [])[:8]:
        rainfall = _number(row.get("rainfall_probability") or row.get("rainfall"))
        wind = _number(row.get("wind_speed"))
        temperature = _number(row.get("temperature"))
        if rainfall < 70 and wind < 25 and temperature < 34:
            continue
        severity = "high" if rainfall >= 85 or wind >= 35 else "moderate"
        basis = f"rain-{rainfall:.0f}-wind-{wind:.0f}-temp-{temperature:.0f}-{_day(row.get('forecast_date'))}"
        advisories.append({
            "generation_key": "python-" + hashlib.sha256(basis.encode()).hexdigest()[:32],
            "title": "Weather-based agricultural advisory",
            "summary": "Python analytics detected weather conditions that may affect farm operations.",
            "message": f"Forecast indicators: rainfall {rainfall:.0f}, wind {wind:.1f}, temperature {temperature:.1f}.",
            "recommended_action": "Review field drainage, irrigation, and planting schedules before field work.",
            "advisory_type": "climate",
            "severity": severity,
            "priority": 80 if severity == "high" else 60,
            "valid_from": row.get("forecast_date") or datetime.now().isoformat(),
            "valid_until": (datetime.now() + timedelta(days=7)).isoformat(),
        })
    return {"advisories": advisories, "advisories_created": len(advisories), "processor": "python"}


def report_analytics(payload: dict) -> dict:
    report_type = payload["report_type"]
    filters = {key: value for key, value in payload.get("filters", {}).items() if value not in (None, "")}
    rows = payload.get("source_rows", [])

    def matches(row):
        date_field = "record_date" if report_type == "climate_records" else "created_at"
        start = end = _day(row.get(date_field))
        # A historical month ("2024-01") is in the range when any of its days is.
        if row.get("period") == "month" and len(start) == 7:
            year, month = int(start[:4]), int(start[5:7])
            start, end = f"{start}-01", f"{start}-{calendar.monthrange(year, month)[1]:02d}"
        if filters.get("date_from") and end < filters["date_from"]:
            return False
        if filters.get("date_to") and start > filters["date_to"]:
            return False
        if filters.get("year") and int(row.get("year") or 0) != int(filters["year"]):
            return False
        barangay = row.get("target_barangay") if report_type == "advisory" else row.get("barangay")
        if filters.get("barangay") and barangay != filters["barangay"]:
            return False
        return not filters.get("season") or row.get("season") == filters["season"]

    columns = REPORT_COLUMNS[report_type]
    records = []
    for row in filter(matches, rows):
        clean = {}
        for key in columns:
            if key == "author_name":
                clean[key] = (row.get("author") or {}).get("name")
            else:
                clean[key] = row.get(key)
        records.append(clean)

    sort_key = "record_date" if report_type == "climate_records" else ("year" if report_type == "rice_production" else "created_at")
    records.sort(key=lambda row: str(row.get(sort_key) or ""), reverse=True)
    return {"report_type": report_type, "filters": filters, "columns": columns, "records": records, "row_count": len(records), "processor": "python"}


def dataset_processing(payload: dict) -> dict:
    climate = payload.get("climate_records", [])
    rice = payload.get("rice_productions", [])
    climate_groups = defaultdict(list)
    for row in climate:
        year = int(_day(row.get("record_date"))[:4] or 0)
        climate_groups[(year, row.get("season"))].append(row)

    dataset = []
    skipped_rows = 0
    for production in rice:
        weather = climate_groups.get((int(production.get("year") or 0), production.get("season")), [])
        if not weather:
            continue
        row_values = {
            "rainfall": _mean([_optional_number(row.get("rainfall")) for row in weather]),
            "temperature": _mean([_optional_number(row.get("temperature")) for row in weather]),
            "area_hectares": _optional_number(production.get("area_hectares")),
            "humidity": _mean([_optional_number(row.get("humidity")) for row in weather]),
            "wind_speed": _mean([_optional_number(row.get("wind_speed")) for row in weather]),
            "season": production.get("season"),
            "irrigation_type": production.get("irrigation_type"),
            "yield_per_hectare": _optional_number(production.get("yield_per_hectare")),
        }
        # A row with a missing number is left out of the training set; it is not filled with 0.
        if any(value is None for value in row_values.values()):
            skipped_rows += 1
            continue
        dataset.append(row_values)

    evaluation = None
    temporary_path = None
    output_path = None
    try:
        requested_output = str(payload.get("requested_output_path") or "").strip()
        if requested_output:
            allowed_directory = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "storage", "app", "private", "ml"))
            os.makedirs(allowed_directory, exist_ok=True)
            output_path = os.path.join(allowed_directory, os.path.basename(requested_output))
            handle = open(output_path, mode="w", newline="", encoding="utf-8")
        else:
            handle = tempfile.NamedTemporaryFile(mode="w", suffix=".csv", newline="", delete=False, encoding="utf-8")
            temporary_path = handle.name

        with handle:
            writer = csv.DictWriter(handle, fieldnames=list(dataset[0].keys()) if dataset else [
                "rainfall", "temperature", "area_hectares", "humidity", "wind_speed",
                "season", "irrigation_type", "yield_per_hectare",
            ])
            writer.writeheader()
            writer.writerows(dataset)
        evaluation = evaluation_service.evaluate(output_path or temporary_path)
    finally:
        if temporary_path and os.path.exists(temporary_path):
            os.unlink(temporary_path)

    return {
        "climate_rows": len(climate),
        "rice_rows": len(rice),
        "dataset_rows": len(dataset),
        "rows_skipped_missing_values": skipped_rows,
        "climate_missing_values": sum(value is None for row in climate for value in row.values()),
        "rice_missing_values": sum(value is None for row in rice for value in row.values()),
        "evaluation": evaluation,
        "output_path": output_path,
        "processor": "python",
    }


HANDLERS = {
    "weather_prediction": weather_prediction,
    "rice_yield_prediction": rice_yield_prediction,
    "heatmap_risk": heatmap_risk,
    "advisory_ai": advisory_ai,
    "report_analytics": report_analytics,
    "dataset_processing": dataset_processing,
}


def run(task_type: str, payload: dict) -> dict:
    if task_type not in HANDLERS:
        raise ValueError(f"Unsupported analytics type: {task_type}")
    return HANDLERS[task_type](payload)
