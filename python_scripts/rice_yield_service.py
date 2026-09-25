"""
Consolidated rice-yield + weather-label prediction logic, in-process. This
is the contract PredictionEngine.php's primary path —
PythonService::farmingAssistant() — sends requests to, importable directly
by the FastAPI service instead of requiring a separate process and an HTTP
round-trip on the same machine.

Feature schema matches PredictionEngine::buildModelInput() (PHP) exactly —
see FEATURE_MAP / WEATHER_FEATURE_MAP below. This is the DEPLOYED schema
(12 lag/rolling features) described in app/Services/Prediction/
FeatureCatalog.php — that file is the canonical source for what each
variable means, why it's included, and its expected effect on yield. Do not
change the column names in rice_features() below without retraining
storage/models/rice_yield_model_final.pkl to match; see the schema
reconciliation note at the top of FeatureCatalog.php for the migration plan
away from this schema.
"""

import hashlib
import os
import threading
import warnings
from datetime import datetime, timezone

import joblib
import pandas as pd

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_DIR = os.path.join(BASE_DIR, "storage", "models")
WEATHER_MODEL_PATH = os.path.join(MODEL_DIR, "weather_model.pkl")
# Same override as Laravel's RICE_YIELD_MODEL_PATH; read once at process start.
RICE_MODEL_PATH = os.path.join(
    BASE_DIR,
    os.environ.get("RICE_YIELD_MODEL_PATH") or os.path.join("storage", "models", "rice_yield_model_final.pkl"),
)

_models: dict = {}
_swap_lock = threading.Lock()

# What the rice model currently in memory is. Filled by load_models() and
# switch_rice_model(); reported by model_info().
_rice_state: dict = {}


def install_sklearn_compatibility_shims() -> None:
    """Allow models saved with older sklearn internals to load on this machine."""
    try:
        import sklearn.compose._column_transformer as column_transformer
    except Exception:
        return

    if not hasattr(column_transformer, "_RemainderColsList"):
        class _RemainderColsList(list):
            pass

        column_transformer._RemainderColsList = _RemainderColsList


def _sha256(path: str) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _record_rice_state(path: str, sha256: str, version) -> None:
    import sklearn

    _rice_state.clear()
    _rice_state.update({
        "filename": os.path.basename(path),
        "path": path,
        "sha256": sha256,
        "version": version,
        "sklearn_version": sklearn.__version__,
        "loaded_at": datetime.now(timezone.utc).isoformat(),
    })


def load_models(force: bool = False) -> dict:
    if _models and not force:
        return _models

    warnings.filterwarnings("ignore")
    install_sklearn_compatibility_shims()
    _models["weather"] = joblib.load(WEATHER_MODEL_PATH)
    _models["rice"] = joblib.load(RICE_MODEL_PATH)
    _record_rice_state(RICE_MODEL_PATH, _sha256(RICE_MODEL_PATH), os.environ.get("RICE_YIELD_MODEL_VERSION") or None)

    return _models


def model_info() -> dict:
    """Public, path-free description of the rice model in memory."""
    if not _rice_state:
        return {"loaded": False}

    return {
        "loaded": True,
        **{k: _rice_state[k] for k in ("filename", "sha256", "version", "sklearn_version", "loaded_at")},
    }


def resolve_model_path(requested: str) -> str:
    """Map a requested model file to an absolute path, only inside storage/models.

    Loading a pickle executes code, so this service never loads a file from
    anywhere else, and only .pkl files.
    """
    candidate = requested if os.path.isabs(requested) else os.path.join(BASE_DIR, requested)
    resolved = os.path.realpath(candidate)
    allowed = os.path.realpath(MODEL_DIR)

    if os.path.commonpath([resolved, allowed]) != allowed or not resolved.lower().endswith(".pkl"):
        raise ValueError("Model file must be a .pkl inside storage/models.")

    if not os.path.isfile(resolved):
        raise FileNotFoundError("Model file was not found.")

    return resolved


def switch_rice_model(requested_path: str, version=None, expected_sha256=None) -> dict:
    """Load the requested rice model and, only if that succeeds, swap it in.

    The new model is fully loaded and checked before the swap, so any failure
    leaves the previous model serving untouched.
    """
    global RICE_MODEL_PATH

    resolved = resolve_model_path(requested_path)
    actual_sha = _sha256(resolved)

    if expected_sha256 and actual_sha.lower() != expected_sha256.lower():
        raise ValueError("Model file does not match the expected checksum.")

    warnings.filterwarnings("ignore")
    install_sklearn_compatibility_shims()
    new_model = joblib.load(resolved)

    if not hasattr(new_model, "predict"):
        raise ValueError("Loaded object is not a usable model.")

    with _swap_lock:
        if not _models:
            _models["weather"] = joblib.load(WEATHER_MODEL_PATH)
        _models["rice"] = new_model
        RICE_MODEL_PATH = resolved
        _record_rice_state(resolved, actual_sha, version)

    return model_info()


_REQUIRED_WEATHER_FEATURES = {"previous_rainfall", "previous_temp", "previous_humidity", "previous_wind_speed", "month_num", "season"}
_REQUIRED_RICE_FEATURES = {
    "rainfall", "temp_avg", "temp_range", "area", "previous_rainfall", "previous_temp",
    "rainfall_6m", "temp_3m", "temp_6m", "seasonal_rainfall", "seasonal_temp", "season",
}


def _feature(features: dict, key: str, default=0, *, required: bool = False):
    if required and key not in features:
        raise ValueError(f"Missing required feature: {key}")
    return features.get(key, default)


def weather_features(features: dict) -> pd.DataFrame:
    return pd.DataFrame([{
        "Previous_Rainfall": float(_feature(features, "previous_rainfall", required=True)),
        "Previous_Temperature": float(_feature(features, "previous_temp", required=True)),
        "Previous_Humidity": float(_feature(features, "previous_humidity", required=True)),
        "Previous_Wind_Speed": float(_feature(features, "previous_wind_speed", required=True)),
        "Month_Num": int(_feature(features, "month_num", required=True)),
        "Season": str(_feature(features, "season", required=True)),
    }])


def rice_features(features: dict) -> pd.DataFrame:
    return pd.DataFrame([{
        "RAINFALL": float(_feature(features, "rainfall", required=True)),
        "TEMP_AVG": float(_feature(features, "temp_avg", required=True)),
        "TEMP_RANGE": float(_feature(features, "temp_range", required=True)),
        "Area": float(_feature(features, "area", required=True)),
        "Previous_Rainfall": float(_feature(features, "previous_rainfall", required=True)),
        "Previous_Temp": float(_feature(features, "previous_temp", required=True)),
        "Rainfall_6M": float(_feature(features, "rainfall_6m", required=True)),
        "Temp_3M": float(_feature(features, "temp_3m", required=True)),
        "Temp_6M": float(_feature(features, "temp_6m", required=True)),
        "Seasonal_Rainfall": float(_feature(features, "seasonal_rainfall", required=True)),
        "Seasonal_Temp": float(_feature(features, "seasonal_temp", required=True)),
        "Season": str(_feature(features, "season", required=True)),
    }])


def weather_label(raw_value) -> str:
    """Keep these thresholds identical to WeatherLabeler::fromRainfall() in
    app/Services/Prediction/WeatherLabeler.php — see tests/Unit/WeatherLabelerTest.php."""
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


def confidence_for(weather_value, predicted_yield, yield_uncertainty=None) -> int:
    confidence = 78
    if predicted_yield is not None and predicted_yield >= 4:
        confidence += 7
    if weather_value in ("Heavy Rain", "Dry"):
        confidence -= 8
    if yield_uncertainty is not None:
        if yield_uncertainty <= 0.35:
            confidence += 6
        elif yield_uncertainty >= 0.9:
            confidence -= 12

    return max(45, min(95, confidence))


def rice_prediction_with_uncertainty(models: dict, features: pd.DataFrame):
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


def warnings_for(features: dict, predicted_weather: str, predicted_yield) -> list[dict]:
    result = []
    rainfall = float(_feature(features, "rainfall", 180))
    temperature = float(_feature(features, "temp_avg", 29))
    humidity = float(_feature(features, "humidity", 78))
    wind_speed = float(_feature(features, "wind_speed", 8))

    if rainfall >= 300 or predicted_weather == "Heavy Rain":
        result.append({"title": "Heavy rainfall", "reason": "Rainfall is high enough to increase flood and waterlogging risk."})
    if rainfall >= 360:
        result.append({"title": "Flood risk", "reason": "Very high rainfall may overwhelm field drainage."})
    if wind_speed >= 20:
        result.append({"title": "Strong winds", "reason": "Wind speed may increase lodging risk for rice."})
    if temperature >= 34:
        result.append({"title": "High temperature", "reason": "Heat can stress rice, especially during flowering."})
    if humidity <= 55:
        result.append({"title": "Low humidity", "reason": "Low humidity can dry soil and seedlings faster."})
    if rainfall <= 80:
        result.append({"title": "Drought", "reason": "Rainfall is below the safer range for rice establishment."})
    if rainfall <= 100 and str(_feature(features, "farm_type", "Rainfed")) == "Rainfed":
        result.append({"title": "Water shortage", "reason": "Rainfed fields may not have enough available water."})
    if predicted_yield is not None and predicted_yield < 3:
        result.append({"title": "Low yield outlook", "reason": "The rice yield model predicted below 3 tons per hectare."})

    return result


def recommendations(features: dict, predicted_weather: str, predicted_yield) -> dict:
    rainfall = float(_feature(features, "rainfall", 180))
    temperature = float(_feature(features, "temp_avg", 29))
    humidity = float(_feature(features, "humidity", 78))
    farm_type = str(_feature(features, "farm_type", "Rainfed"))

    if rainfall < 80:
        planting, planting_reason = "Delay planting", "Rainfall is too low for reliable rice establishment."
    elif rainfall > 330 or predicted_weather == "Heavy Rain":
        planting, planting_reason = "Wait for better weather", "Heavy rainfall may cause flooding or seedling damage."
    elif predicted_yield is not None and predicted_yield >= 4:
        planting, planting_reason = "Plant now", "Yield outlook and weather inputs are favorable."
    elif predicted_yield is not None and predicted_yield < 3:
        planting, planting_reason = "Prepare seedlings", "Yield outlook is low, so improve field preparation and inputs before transplanting."
    else:
        planting, planting_reason = "Prepare seedlings", "Conditions are workable but should still be monitored."

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


def predict(features: dict) -> dict:
    """
    features: same shape as PredictionEngine::buildModelInput()'s $modelInput —
    rainfall, temp_avg, temp_range, area, previous_rainfall, previous_temp,
    previous_humidity, previous_wind_speed, rainfall_6m, temp_3m, temp_6m,
    seasonal_rainfall, seasonal_temp, humidity, wind_speed, season, farm_type,
    month_num.

    Returns the response shape for prediction_type="rice_yield" that
    PredictionEngine.php's parsing expects (data_get($apiResult,
    'rice_yield_prediction', $apiResult) etc.).
    """
    models = load_models()

    raw_weather = models["weather"].predict(weather_features(features))[0]
    predicted_weather = weather_label(raw_weather)

    rice_input = rice_features(features)
    predicted_yield, yield_uncertainty = rice_prediction_with_uncertainty(models, rice_input)
    recs = recommendations(features, predicted_weather, predicted_yield)
    generated_warnings = warnings_for(features, predicted_weather, predicted_yield)
    confidence = confidence_for(predicted_weather, predicted_yield, yield_uncertainty)

    return {
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
    }
