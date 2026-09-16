"""Pure-Python analytics handlers called only by queued Laravel jobs."""

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
        "record_date": "Date", "rainfall": "Rainfall", "temperature": "Temperature",
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

DEFAULT_AREAS = {
    "Bagong Pook": (14.0182, 120.6408), "Balibago": (13.9898, 120.6524),
    "Barangay 1 (Pob.)": (14.0361, 120.6507), "Barangay 2 (Pob.)": (14.0371, 120.6530),
    "Barangay 3 (Pob.)": (14.0349, 120.6489), "Barangay 4 (Pob.)": (14.0383, 120.6484),
    "Barangay 5 (Pob.)": (14.0339, 120.6525), "Binubusan": (14.0296, 120.6364),
    "Bungahan": (14.0480, 120.6285), "Cumba": (14.0205, 120.7045),
    "Humayingan": (14.0108, 120.6888), "Kapito": (14.0557, 120.6765),
    "Lumaniag": (14.0479, 120.6613), "Luyahan": (13.9926, 120.6355),
    "Malaruhatan": (14.0119, 120.6669), "Matabungkay": (13.9593, 120.6227),
    "Prenza": (14.0353, 120.6822), "Puting-Kahoy": (14.0290, 120.7015),
    "San Diego": (13.9793, 120.6118),
}


def _number(value, default=0.0):
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def _day(value):
    return str(value or "")[:10]


def _season(month: int) -> str:
    return "Wet" if month in {5, 6, 7, 8, 9, 10} else "Dry"


def weather_prediction(payload: dict) -> dict:
    target = date.fromisoformat(str(payload["target_date"])[:10])
    result = monthly_weather_random_forest.predict(target.replace(day=1), payload.get("climate_records", []))
    predictions = result.get("predictions", {})
    predicted_rainfall = _number(predictions.get("rainfall"))
    if predicted_rainfall > 300:
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
        "predicted_rainfall": round(predicted_rainfall, 2),
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


def _model_input(payload: dict, weather: dict) -> dict:
    records = sorted(payload.get("climate_records", []), key=lambda row: _day(row.get("record_date")))
    recent = records[-6:]
    latest = recent[-1] if recent else {}
    predictions = weather.get("predictions", {})
    temperatures = [_number(row.get("temperature")) for row in recent]
    rainfall = _number(predictions.get("rainfall"), sum(_number(row.get("rainfall")) for row in recent) / max(1, len(recent)))
    temperature = _number(predictions.get("temperature"), sum(temperatures) / max(1, len(temperatures)))
    season = str(predictions.get("season") or latest.get("season") or _season(date.fromisoformat(payload["target_date"]).month))
    seasonal = [row for row in recent if row.get("season") == season]

    return {
        "rainfall": round(rainfall, 2),
        "temp_avg": round(temperature, 2),
        "temp_range": round(max(temperatures) - min(temperatures), 2) if temperatures else 0,
        "area": round(_number(payload.get("area"), 1), 2),
        "previous_rainfall": round(_number(latest.get("rainfall"), rainfall), 2),
        "previous_temp": round(_number(latest.get("temperature"), temperature), 2),
        "previous_humidity": round(_number(latest.get("humidity"), 75), 2),
        "previous_wind_speed": round(_number(latest.get("wind_speed"), 8), 2),
        "rainfall_6m": round(sum(_number(row.get("rainfall")) for row in recent) or rainfall, 2),
        "temp_3m": round(sum(temperatures[-3:]) / max(1, len(temperatures[-3:])), 2),
        "temp_6m": round(sum(temperatures) / max(1, len(temperatures)), 2),
        "seasonal_rainfall": round(sum(_number(row.get("rainfall")) for row in seasonal) or rainfall, 2),
        "seasonal_temp": round(sum(_number(row.get("temperature")) for row in seasonal) / max(1, len(seasonal)) if seasonal else temperature, 2),
        "humidity": round(_number(predictions.get("humidity"), _number(latest.get("humidity"), 75)), 2),
        "wind_speed": round(_number(predictions.get("wind_speed"), _number(latest.get("wind_speed"), 8)), 2),
        "season": season,
        "farm_type": payload.get("farm_type", "Rainfed"),
        "month_num": date.fromisoformat(payload["target_date"]).month,
    }


def rice_yield_prediction(payload: dict) -> dict:
    weather = weather_prediction(payload)
    features = _model_input(payload, weather)
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

    areas = payload.get("areas", []) or [
        {"barangay": barangay, "latitude": coordinates[0], "longitude": coordinates[1]}
        for barangay, coordinates in DEFAULT_AREAS.items()
    ]
    results = []
    for area in areas:
        rainfall = _number(latest_weather.get("rainfall"))
        temperature = _number(latest_weather.get("temperature"))
        wind = _number(latest_weather.get("wind_speed"))
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
        if filters.get("date_from") and _day(row.get(date_field)) < filters["date_from"]:
            return False
        if filters.get("date_to") and _day(row.get(date_field)) > filters["date_to"]:
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
    for production in rice:
        weather = climate_groups.get((int(production.get("year") or 0), production.get("season")), [])
        if not weather:
            continue
        dataset.append({
            "rainfall": sum(_number(row.get("rainfall")) for row in weather) / len(weather),
            "temperature": sum(_number(row.get("temperature")) for row in weather) / len(weather),
            "area_hectares": _number(production.get("area_hectares")),
            "humidity": sum(_number(row.get("humidity")) for row in weather) / len(weather),
            "wind_speed": sum(_number(row.get("wind_speed")) for row in weather) / len(weather),
            "season": production.get("season"),
            "irrigation_type": production.get("irrigation_type"),
            "yield_per_hectare": _number(production.get("yield_per_hectare")),
        })

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
