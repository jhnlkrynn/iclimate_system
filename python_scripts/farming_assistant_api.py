import os
import time
import warnings

import joblib
import pandas as pd
from flask import Flask, jsonify, request

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_DIR = os.path.join(BASE_DIR, "storage", "models")
WEATHER_MODEL = os.path.join(MODEL_DIR, "weather_model.pkl")
RICE_MODEL = os.path.join(MODEL_DIR, "rice_yield_model_final.pkl")

app = Flask(__name__)
models = {}


def install_sklearn_compatibility_shims():
    try:
        import sklearn.compose._column_transformer as column_transformer
    except Exception:
        return

    if not hasattr(column_transformer, "_RemainderColsList"):
        class _RemainderColsList(list):
            pass

        column_transformer._RemainderColsList = _RemainderColsList


def load_models():
    warnings.filterwarnings("ignore")
    install_sklearn_compatibility_shims()
    models["weather"] = joblib.load(WEATHER_MODEL)
    models["rice"] = joblib.load(RICE_MODEL)


def feature(payload, key, default=0):
    return payload.get("features", {}).get(key, default)


def weather_features(payload):
    return pd.DataFrame([{
        "Previous_Rainfall": float(feature(payload, "previous_rainfall", feature(payload, "rainfall", 180))),
        "Previous_Temperature": float(feature(payload, "previous_temp", feature(payload, "temp_avg", 29))),
        "Previous_Humidity": float(feature(payload, "previous_humidity", feature(payload, "humidity", 78))),
        "Previous_Wind_Speed": float(feature(payload, "previous_wind_speed", feature(payload, "wind_speed", 8))),
        "Month_Num": int(feature(payload, "month_num", 1)),
        "Season": str(feature(payload, "season", "Wet")),
    }])


def rice_features(payload):
    return pd.DataFrame([{
        "RAINFALL": float(feature(payload, "rainfall", 180)),
        "TEMP_AVG": float(feature(payload, "temp_avg", 29)),
        "TEMP_RANGE": float(feature(payload, "temp_range", 8)),
        "Area": float(feature(payload, "area", 1)),
        "Previous_Rainfall": float(feature(payload, "previous_rainfall", 180)),
        "Previous_Temp": float(feature(payload, "previous_temp", 29)),
        "Rainfall_6M": float(feature(payload, "rainfall_6m", 180)),
        "Temp_3M": float(feature(payload, "temp_3m", 29)),
        "Temp_6M": float(feature(payload, "temp_6m", 29)),
        "Seasonal_Rainfall": float(feature(payload, "seasonal_rainfall", 900)),
        "Seasonal_Temp": float(feature(payload, "seasonal_temp", 29)),
        "Season": str(feature(payload, "season", "Wet")),
    }])


def weather_label(raw_value):
    try:
        rainfall = float(raw_value)
    except (TypeError, ValueError):
        return str(raw_value)

    if rainfall >= 300:
        return "Heavy Rain"
    if rainfall >= 120:
        return "Rain"
    if rainfall <= 70:
        return "Dry"
    return "Cloudy"


def confidence_for(weather_value, predicted_yield, yield_uncertainty=None):
    confidence = 78
    if predicted_yield is not None and predicted_yield >= 4:
        confidence += 7
    if weather_value in ["Heavy Rain", "Dry"]:
        confidence -= 8
    if yield_uncertainty is not None:
        if yield_uncertainty <= 0.35:
            confidence += 6
        elif yield_uncertainty >= 0.9:
            confidence -= 12
    return max(45, min(95, confidence))


def rice_prediction_with_uncertainty(features):
    model = models["rice"]
    predicted_yield = float(model.predict(features)[0])
    regressor = getattr(model, "named_steps", {}).get("model") if hasattr(model, "named_steps") else None

    if not hasattr(regressor, "estimators_"):
        return predicted_yield, None

    try:
        transformed = model.named_steps["preprocessor"].transform(features)
        tree_predictions = [float(tree.predict(transformed)[0]) for tree in regressor.estimators_]
    except Exception:
        return predicted_yield, None

    if len(tree_predictions) < 2:
        return predicted_yield, None

    series = pd.Series(tree_predictions)
    return predicted_yield, float(series.std())


def warnings_for(payload, predicted_weather, predicted_yield):
    warnings = []
    rainfall = float(feature(payload, "rainfall", 180))
    temperature = float(feature(payload, "temp_avg", 29))
    humidity = float(feature(payload, "humidity", 78))
    wind_speed = float(feature(payload, "wind_speed", 8))

    if rainfall >= 300 or predicted_weather == "Heavy Rain":
        warnings.append({"title": "Heavy rainfall", "reason": "Rainfall is high enough to increase flood and waterlogging risk."})
    if rainfall >= 360:
        warnings.append({"title": "Flood risk", "reason": "Very high rainfall may overwhelm field drainage."})
    if wind_speed >= 20:
        warnings.append({"title": "Strong winds", "reason": "Wind speed may increase lodging risk for rice."})
    if temperature >= 34:
        warnings.append({"title": "High temperature", "reason": "Heat can stress rice, especially during flowering."})
    if humidity <= 55:
        warnings.append({"title": "Low humidity", "reason": "Low humidity can dry soil and seedlings faster."})
    if rainfall <= 80:
        warnings.append({"title": "Drought", "reason": "Rainfall is below the safer range for rice establishment."})
    if rainfall <= 100 and str(feature(payload, "farm_type", "Rainfed")) == "Rainfed":
        warnings.append({"title": "Water shortage", "reason": "Rainfed fields may not have enough available water."})
    if predicted_yield is not None and predicted_yield < 3:
        warnings.append({"title": "Low yield outlook", "reason": "The rice yield model predicted below 3 tons per hectare."})

    return warnings


def recommendations(payload, predicted_weather, predicted_yield):
    rainfall = float(feature(payload, "rainfall", 180))
    temperature = float(feature(payload, "temp_avg", 29))
    humidity = float(feature(payload, "humidity", 78))
    farm_type = str(feature(payload, "farm_type", "Rainfed"))

    if rainfall < 80:
        planting = "Delay planting"
        planting_reason = "Rainfall is too low for reliable rice establishment."
    elif rainfall > 330 or predicted_weather == "Heavy Rain":
        planting = "Wait for better weather"
        planting_reason = "Heavy rainfall may cause flooding or seedling damage."
    elif predicted_yield is not None and predicted_yield >= 4:
        planting = "Plant now"
        planting_reason = "Yield outlook and weather inputs are favorable."
    elif predicted_yield is not None and predicted_yield < 3:
        planting = "Prepare seedlings"
        planting_reason = "Yield outlook is low, so improve field preparation and inputs before transplanting."
    else:
        planting = "Prepare seedlings"
        planting_reason = "Conditions are workable but should still be monitored."

    if farm_type == "Rainfed" and rainfall < 120:
        irrigation = "Additional irrigation is recommended for rainfed fields because rainfall is insufficient."
    elif farm_type == "Irrigated" and rainfall > 280:
        irrigation = "Reduce irrigation and check drainage because rainfall is already high."
    elif temperature >= 34 or humidity <= 55:
        irrigation = "Irrigate early in the morning to reduce heat and moisture stress."
    else:
        irrigation = "Maintain normal irrigation monitoring."

    return {
        "planting_recommendation": {"action": planting, "explanation": planting_reason},
        "irrigation_recommendation": {"recommendation": irrigation, "farm_type": farm_type},
    }


@app.route("/health", methods=["GET"])
def health():
    if not models:
        load_models()

    return jsonify({"status": "ok", "models_loaded": sorted(models.keys())})


@app.route("/predict", methods=["POST"])
def predict():
    if not models:
        load_models()

    started_at = time.perf_counter()
    payload = request.get_json(force=True) or {}

    raw_weather = models["weather"].predict(weather_features(payload))[0]
    predicted_weather = weather_label(raw_weather)
    rice_input = rice_features(payload)
    predicted_yield, yield_uncertainty = rice_prediction_with_uncertainty(rice_input)
    recs = recommendations(payload, predicted_weather, predicted_yield)
    generated_warnings = warnings_for(payload, predicted_weather, predicted_yield)
    confidence = confidence_for(predicted_weather, predicted_yield, yield_uncertainty)

    return jsonify({
        "weather_prediction": {
            "predicted_weather": predicted_weather,
            "raw_prediction": float(raw_weather) if str(raw_weather).replace(".", "", 1).isdigit() else str(raw_weather),
            "confidence": confidence,
            "explanation": "The weather model used previous rainfall, temperature, humidity, wind speed, month, and season.",
            "source_type": "Trained Model API",
            "source_name": "Farming AI API weather model",
            "source_credit": "Weather output generated by the Farming AI API trained weather model.",
        },
        "rice_yield_prediction": {
            "predicted_yield": round(predicted_yield, 2),
            "unit": "tons/hectare",
            "uncertainty": round(yield_uncertainty, 3) if yield_uncertainty is not None else None,
            "explanation": "The rice yield model used rainfall, temperature, farm area, recent weather memory, seasonal weather, and season.",
            "source_type": "Trained Model API",
            "source_name": "Farming AI API trained rice yield model",
            "source_credit": "Rice yield output generated by the Farming AI API using the trained iClimate Random Forest model.",
        },
        **recs,
        "warnings": generated_warnings,
        "explanation": "Machine learning outputs were combined with decision-support rules for planting, irrigation, and climate warnings.",
        "source_type": "Trained Model API",
        "source_name": "Farming AI API trained iClimate models",
        "source_credit": "Prediction values came from trained iClimate models; recommendations came from iClimate decision-support rules.",
        "confidence_score": confidence,
        "response_time_ms": round((time.perf_counter() - started_at) * 1000),
    })


if __name__ == "__main__":
    load_models()
    app.run(
        host=os.environ.get("FARMING_AI_HOST", "192.168.50.116"),
        port=int(os.environ.get("FARMING_AI_PORT", "5001")),
    )
