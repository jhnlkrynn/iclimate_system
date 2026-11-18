"""Leakage-safe, calendar-only provincial PSA experiment for Phase 27.

This is deliberately separate from the deployed 12-feature model. It derives
province-level yield from official PSA production/area records and uses only
calendar fields known before a quarterly forecast (year and quarter). It does
not use harvested area as a predictor, climate normals, predictions, or any
application data.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import ExtraTreesRegressor, GradientBoostingRegressor, HistGradientBoostingRegressor, RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import RandomizedSearchCV, TimeSeriesSplit
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_PRODUCTION = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-production-batangas-quarterly-1987-2025.csv"
DEFAULT_AREA = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-area-harvested-batangas-quarterly-1987-2025.csv"
DEFAULT_OUTPUT = ROOT / "storage/models/experiments/phase-27"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def load_dataset(production_path: Path, area_path: Path) -> pd.DataFrame:
    production = pd.read_csv(production_path).iloc[0]
    area = pd.read_csv(area_path).iloc[0]
    production_periods = list(production.index[2:])
    area_periods = list(area.index[2:])
    if production_periods != area_periods:
        raise ValueError("Production and area period columns do not match.")
    rows = []
    for period in production_periods:
        year_text, _, quarter_text = period.split()
        year = int(year_text)
        quarter = int(quarter_text.replace("Quarter", ""))
        production_value = float(production[period])
        area_value = float(area[period])
        if not np.isfinite(production_value) or not np.isfinite(area_value):
            raise ValueError(f"Non-finite PSA value at {period}.")
        if production_value < 0 or area_value <= 0:
            raise ValueError(f"Invalid PSA production/area at {period}.")
        rows.append({
            "observation_id": f"batangas-{year}-q{quarter}",
            "record_date": f"{year}-{quarter * 3:02d}-01",
            "year": year,
            "quarter": quarter,
            "production_mt": production_value,
            "area_harvested_ha": area_value,
            "actual_yield_t_ha": production_value / area_value,
            "geographic_level": "province",
            "geography": "Batangas",
            "ecosystem": str(production["Ecosystem/Croptype"]),
        })
    frame = pd.DataFrame(rows).sort_values("record_date").reset_index(drop=True)
    if frame["observation_id"].duplicated().any():
        raise ValueError("Duplicate observation IDs detected.")
    if len(frame) != 156:
        raise ValueError(f"Expected 156 quarterly observations, received {len(frame)}.")
    return frame


def metrics(actual: pd.Series, predicted: np.ndarray) -> dict[str, float]:
    y = actual.to_numpy(dtype=float)
    p = np.asarray(predicted, dtype=float)
    return {
        "r2": float(r2_score(y, p)),
        "mae": float(mean_absolute_error(y, p)),
        "rmse": float(np.sqrt(mean_squared_error(y, p))),
        "mape": float(np.mean(np.abs((y - p) / y)) * 100),
        "bias": float(np.mean(p - y)),
        "n": int(len(y)),
    }


def preprocessor() -> ColumnTransformer:
    return ColumnTransformer([
        ("year", "passthrough", ["year"]),
        ("quarter", OneHotEncoder(handle_unknown="ignore", sparse_output=False), ["quarter"]),
    ])


def model_spaces() -> dict[str, tuple[object, dict[str, list[object]]]]:
    return {
        "Random Forest": (RandomForestRegressor(random_state=42, n_jobs=1), {
            "model__n_estimators": [200, 400], "model__max_depth": [None, 5, 10],
            "model__min_samples_leaf": [1, 2, 4], "model__max_features": [1.0, "sqrt"],
        }),
        "Gradient Boosting": (GradientBoostingRegressor(random_state=42), {
            "model__n_estimators": [100, 200], "model__learning_rate": [0.03, 0.08],
            "model__max_depth": [1, 2, 3], "model__min_samples_leaf": [1, 3],
        }),
        "Extra Trees": (ExtraTreesRegressor(random_state=42, n_jobs=1), {
            "model__n_estimators": [200, 400], "model__max_depth": [None, 5, 10],
            "model__min_samples_leaf": [1, 2, 4], "model__max_features": [1.0, "sqrt"],
        }),
        "HistGradientBoosting": (HistGradientBoostingRegressor(random_state=42), {
            "model__max_iter": [100, 200], "model__learning_rate": [0.03, 0.08],
            "model__max_leaf_nodes": [7, 15], "model__l2_regularization": [0.0, 0.5],
        }),
    }


def baseline_predictions(train: pd.DataFrame, target: pd.DataFrame) -> dict[str, np.ndarray]:
    overall = float(train["actual_yield_t_ha"].mean())
    seasonal = train.groupby("quarter")["actual_yield_t_ha"].mean().to_dict()
    return {
        "Historical mean": np.full(len(target), overall),
        "Seasonal mean": np.asarray([seasonal.get(q, overall) for q in target["quarter"]], dtype=float),
    }


def write_svg_chart(predictions: pd.DataFrame, algorithm: str, path: Path) -> None:
    """Write a small dependency-free actual-vs-predicted line chart."""
    width, height = 1000, 520
    left, right, top, bottom = 70, 30, 40, 90
    plot_w, plot_h = width - left - right, height - top - bottom
    actual = predictions["actual_yield_t_ha"].to_numpy(dtype=float)
    predicted = predictions["predicted_yield_t_ha"].to_numpy(dtype=float)
    values = np.concatenate([actual, predicted])
    low, high = float(values.min()), float(values.max())
    padding = max((high - low) * 0.08, 0.1)
    low -= padding
    high += padding

    def point(index: int, value: float) -> tuple[float, float]:
        x = left + (index / max(len(actual) - 1, 1)) * plot_w
        y = top + (high - value) / (high - low) * plot_h
        return x, y

    def polyline(values_: np.ndarray, color: str) -> str:
        points = " ".join(f"{point(i, value)[0]:.1f},{point(i, value)[1]:.1f}" for i, value in enumerate(values_))
        return f'<polyline fill="none" stroke="{color}" stroke-width="3" points="{points}" />'

    labels = "".join(
        f'<text x="{point(i, actual[i])[0]:.1f}" y="{height - 45}" font-size="11" text-anchor="end" transform="rotate(-45 {point(i, actual[i])[0]:.1f},{height - 45})">{row.record_date}</text>'
        for i, row in predictions.reset_index(drop=True).iterrows()
    )
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">
<rect width="100%" height="100%" fill="white" />
<text x="{width / 2}" y="24" text-anchor="middle" font-family="sans-serif" font-size="18">Phase 27 untouched Batangas quarterly test set</text>
<line x1="{left}" y1="{top}" x2="{left}" y2="{top + plot_h}" stroke="#333" />
<line x1="{left}" y1="{top + plot_h}" x2="{width - right}" y2="{top + plot_h}" stroke="#333" />
<text x="15" y="{top + plot_h / 2}" transform="rotate(-90 15,{top + plot_h / 2})" text-anchor="middle" font-family="sans-serif" font-size="13">Yield (t/ha)</text>
{polyline(actual, '#1f77b4')}
{polyline(predicted, '#d62728')}
{labels}
<line x1="{width - 250}" y1="35" x2="{width - 220}" y2="35" stroke="#1f77b4" stroke-width="3" /><text x="{width - 212}" y="40" font-family="sans-serif" font-size="13">Actual PSA yield</text>
<line x1="{width - 250}" y1="58" x2="{width - 220}" y2="58" stroke="#d62728" stroke-width="3" /><text x="{width - 212}" y="63" font-family="sans-serif" font-size="13">{algorithm} prediction</text>
<text x="{left}" y="{top - 10}" font-family="sans-serif" font-size="11">{low:.2f}</text><text x="{left}" y="{top + 5}" font-family="sans-serif" font-size="11">{high:.2f}</text>
</svg>'''
    path.write_text(svg, encoding="utf-8")


def run(production_path: Path, area_path: Path, output_dir: Path) -> dict:
    frame = load_dataset(production_path, area_path)
    train = frame[frame.year <= 2016].copy()
    validation = frame[(frame.year >= 2017) & (frame.year <= 2020)].copy()
    test = frame[frame.year >= 2021].copy()
    if len(train) != 120 or len(validation) != 16 or len(test) != 20:
        raise ValueError("Unexpected chronological split sizes.")
    X_train, y_train = train[["year", "quarter"]], train["actual_yield_t_ha"]
    X_validation, y_validation = validation[["year", "quarter"]], validation["actual_yield_t_ha"]
    X_fit = pd.concat([X_train, X_validation], ignore_index=True)
    y_fit = pd.concat([y_train, y_validation], ignore_index=True)
    X_test, y_test = test[["year", "quarter"]], test["actual_yield_t_ha"]

    output_dir.mkdir(parents=True, exist_ok=True)
    frame.to_csv(output_dir / "provincial_calendar_experiment_dataset.csv", index=False)
    results = []
    selected_models = {}
    cv = TimeSeriesSplit(n_splits=5)
    for name, (estimator, search_space) in model_spaces().items():
        pipeline = Pipeline([("preprocess", preprocessor()), ("model", estimator)])
        search = RandomizedSearchCV(
            pipeline, search_space, n_iter=6, scoring="neg_root_mean_squared_error",
            cv=cv, random_state=42, n_jobs=1, refit=True,
        )
        search.fit(X_train, y_train)
        validation_prediction = search.best_estimator_.predict(X_validation)
        validation_metrics = metrics(y_validation, validation_prediction)
        refit = search.best_estimator_
        refit.fit(X_fit, y_fit)
        test_prediction = refit.predict(X_test)
        results.append({
            "algorithm": name,
            "best_params": search.best_params_,
            "validation": validation_metrics,
            "test": metrics(y_test, test_prediction),
        })
        selected_models[name] = (refit, test_prediction)

    baseline_results = {}
    for name, prediction in baseline_predictions(train, validation).items():
        baseline_results[name] = {
            "validation": metrics(y_validation, prediction),
            "test": metrics(y_test, baseline_predictions(train, test)[name]),
        }
    best = min(results, key=lambda row: row["validation"]["rmse"])
    best_model, best_prediction = selected_models[best["algorithm"]]
    import joblib
    joblib.dump(best_model, output_dir / "best_calendar_only_candidate.joblib")
    predictions = test[["observation_id", "record_date", "year", "quarter", "actual_yield_t_ha"]].copy()
    predictions["predicted_yield_t_ha"] = best_prediction
    predictions["error_t_ha"] = predictions["predicted_yield_t_ha"] - predictions["actual_yield_t_ha"]
    predictions.to_csv(output_dir / "best_candidate_test_predictions.csv", index=False)
    write_svg_chart(predictions, best["algorithm"], output_dir / "actual-vs-predicted-test.svg")
    result = {
        "status": "EXPERIMENTAL RESULTS AVAILABLE",
        "experiment_scope": "Batangas province aggregate; calendar-only predictors",
        "train_rows": len(train), "validation_rows": len(validation), "test_rows": len(test),
        "usable_observations": len(frame), "predictors": ["year", "quarter"],
        "target": "official PSA palay production metric tons / area harvested hectares",
        "model_selection": "RandomizedSearchCV with TimeSeriesSplit on 1987-2016 only; validation 2017-2020; untouched test 2021-2025",
        "results": results, "baselines": baseline_results, "best_selected_on_validation": best,
        "artifacts": sorted({p.name for p in output_dir.iterdir()} | {"metrics.json"}),
        "deployed_model_unchanged": True,
        "accuracy_status": "NOT DEPLOYMENT EVIDENCE",
    }
    (output_dir / "metrics.json").write_text(json.dumps(result, indent=2, default=str), encoding="utf-8")
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--production", type=Path, default=DEFAULT_PRODUCTION)
    parser.add_argument("--area", type=Path, default=DEFAULT_AREA)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    print(json.dumps(run(args.production, args.area, args.output_dir), indent=2, default=str))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
