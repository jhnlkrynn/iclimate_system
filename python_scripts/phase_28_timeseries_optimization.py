"""Leakage-safe Phase 28 optimization for the isolated PSA province experiment.

This module never touches the deployed 12-feature model. It uses authentic PSA
Batangas aggregate yield targets and only lagged/shifted target history plus
calendar fields known before the current quarterly forecast.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import ExtraTreesRegressor, GradientBoostingRegressor, HistGradientBoostingRegressor, RandomForestRegressor
from sklearn.inspection import permutation_importance
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import RandomizedSearchCV, TimeSeriesSplit
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from phase_27_provincial_experiment import load_dataset, model_spaces as phase27_model_spaces


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_PRODUCTION = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-production-batangas-quarterly-1987-2025.csv"
DEFAULT_AREA = ROOT / "storage/datasets/external/phase-27/psa-batangas/palay-area-harvested-batangas-quarterly-1987-2025.csv"
DEFAULT_OUTPUT = ROOT / "storage/models/experiments/phase-28"
TARGET = "actual_yield_t_ha"
CALENDAR_FEATURES = ["year", "quarter"]
LAGGED_FEATURES = CALENDAR_FEATURES + [
    "lag_1_yield_t_ha", "lag_4_yield_t_ha", "rolling_4_mean_prior",
    "rolling_8_mean_prior", "expanding_mean_prior", "trend_4_prior",
]


def metrics(actual: pd.Series, predicted: np.ndarray) -> dict[str, float | int]:
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


def build_features(frame: pd.DataFrame) -> pd.DataFrame:
    result = frame.sort_values("record_date").reset_index(drop=True).copy()
    series = result[TARGET]
    # Every derived predictor is shifted one row before calculation. The
    # current quarter's target and area are never used as predictors.
    result["lag_1_yield_t_ha"] = series.shift(1)
    result["lag_4_yield_t_ha"] = series.shift(4)
    result["rolling_4_mean_prior"] = series.shift(1).rolling(4, min_periods=4).mean()
    result["rolling_8_mean_prior"] = series.shift(1).rolling(8, min_periods=8).mean()
    result["expanding_mean_prior"] = series.shift(1).expanding(min_periods=4).mean()
    result["trend_4_prior"] = result["lag_1_yield_t_ha"] - result["lag_4_yield_t_ha"]
    return result


def preprocessor(features: list[str]) -> ColumnTransformer:
    numeric = [feature for feature in features if feature != "quarter"]
    return ColumnTransformer([
        ("numeric", "passthrough", numeric),
        ("quarter", OneHotEncoder(handle_unknown="ignore", sparse_output=False), ["quarter"]),
    ])


def spaces() -> dict[str, tuple[object, dict[str, list[object]]]]:
    # Keep the Phase 25/27 estimator families and search ranges reproducible.
    return phase27_model_spaces()


def seasonal_naive(train: pd.DataFrame, target: pd.DataFrame) -> dict[str, np.ndarray]:
    lag1 = []
    lag4 = []
    seasonal = train.groupby("quarter")[TARGET].mean().to_dict()
    overall = float(train[TARGET].mean())
    for _, row in target.iterrows():
        prior = train[train["record_date"] < row["record_date"]]
        lag1.append(float(prior.iloc[-1][TARGET]) if not prior.empty else overall)
        prior_same = prior[prior["quarter"] == row["quarter"]]
        lag4.append(float(prior_same.iloc[-1][TARGET]) if not prior_same.empty else overall)
    return {
        "Previous-quarter naive": np.asarray(lag1),
        "Previous-year same-quarter naive": np.asarray(lag4),
        "Seasonal historical mean": np.asarray([seasonal.get(q, overall) for q in target["quarter"]]),
    }


def write_svg(predictions: pd.DataFrame, path: Path, title: str) -> None:
    width, height = 1050, 540
    left, right, top, bottom = 72, 30, 45, 95
    plot_w, plot_h = width - left - right, height - top - bottom
    series = [predictions["actual_yield_t_ha"].to_numpy(float)]
    model_columns = [c for c in predictions.columns if c.startswith("pred_")]
    series.extend(predictions[c].to_numpy(float) for c in model_columns)
    low, high = min(float(x.min()) for x in series), max(float(x.max()) for x in series)
    padding = max((high - low) * 0.08, 0.1)
    low, high = low - padding, high + padding

    def point(i: int, value: float) -> tuple[float, float]:
        return left + i / max(len(predictions) - 1, 1) * plot_w, top + (high - value) / (high - low) * plot_h

    colors = ["#1f77b4", "#d62728", "#2ca02c", "#9467bd", "#ff7f0e"]
    lines = []
    labels = ["Actual PSA yield"] + [c.replace("pred_", "").replace("_", " ") for c in model_columns]
    for values, color in zip(series, colors):
        points = " ".join(f"{point(i, v)[0]:.1f},{point(i, v)[1]:.1f}" for i, v in enumerate(values))
        lines.append(f'<polyline fill="none" stroke="{color}" stroke-width="3" points="{points}" />')
    xlabels = "".join(
        f'<text x="{point(i, row.actual_yield_t_ha)[0]:.1f}" y="{height - 45}" font-size="10" text-anchor="end" transform="rotate(-45 {point(i, row.actual_yield_t_ha)[0]:.1f},{height - 45})">{row.record_date}</text>'
        for i, row in predictions.reset_index(drop=True).iterrows()
    )
    legend = "".join(
        f'<line x1="{width - 310}" y1="{30 + i * 22}" x2="{width - 285}" y2="{30 + i * 22}" stroke="{color}" stroke-width="3" /><text x="{width - 278}" y="{35 + i * 22}" font-family="sans-serif" font-size="12">{label}</text>'
        for i, (color, label) in enumerate(zip(colors, labels))
    )
    path.write_text(f'''<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}">
<rect width="100%" height="100%" fill="white"/><text x="{width/2}" y="25" text-anchor="middle" font-family="sans-serif" font-size="18">{title}</text>
<line x1="{left}" y1="{top}" x2="{left}" y2="{top+plot_h}" stroke="#333"/><line x1="{left}" y1="{top+plot_h}" x2="{width-right}" y2="{top+plot_h}" stroke="#333"/>
<text x="15" y="{top+plot_h/2}" transform="rotate(-90 15,{top+plot_h/2})" text-anchor="middle" font-family="sans-serif" font-size="13">Yield (t/ha)</text>
{''.join(lines)}{xlabels}{legend}<text x="{left}" y="{top-10}" font-family="sans-serif" font-size="11">range {low:.2f}–{high:.2f}</text>
</svg>''', encoding="utf-8")


def tune_and_walk_forward(data: pd.DataFrame, features: list[str], output_dir: Path) -> tuple[list[dict], dict, pd.DataFrame]:
    tuning = data[data.year <= 2016].copy()
    validation = data[(data.year >= 2017) & (data.year <= 2020)].copy()
    diagnostic = data[data.year >= 2021].copy()
    if tuning.empty or validation.empty or diagnostic.empty:
        raise ValueError("Required chronological partitions are empty.")
    X_tune, y_tune = tuning[features], tuning[TARGET]
    cv = TimeSeriesSplit(n_splits=5)
    results = []
    fitted = {}
    for name, (estimator, space) in spaces().items():
        pipeline = Pipeline([("preprocess", preprocessor(features)), ("model", estimator)])
        search = RandomizedSearchCV(pipeline, space, n_iter=6, scoring="neg_root_mean_squared_error", cv=cv, random_state=42, n_jobs=1, refit=True)
        search.fit(X_tune, y_tune)
        model = search.best_estimator_
        fold_rows = []
        fold_predictions = []
        for year in range(2017, 2021):
            train_fold = data[data.year < year]
            valid_fold = data[data.year == year]
            model.fit(train_fold[features], train_fold[TARGET])
            prediction = model.predict(valid_fold[features])
            fold_rows.append({"year": year, **metrics(valid_fold[TARGET], prediction)})
            fold_predictions.append(prediction)
        model.fit(pd.concat([tuning, validation])[features], pd.concat([tuning, validation])[TARGET])
        diagnostic_prediction = model.predict(diagnostic[features])
        results.append({
            "algorithm": name,
            "best_params": search.best_params_,
            "cv_rmse_mean": float(-search.best_score_),
            "walk_forward_validation": fold_rows,
            "validation_aggregate": metrics(validation[TARGET], np.concatenate(fold_predictions)),
            "historical_diagnostic_2021_2025": metrics(diagnostic[TARGET], diagnostic_prediction),
        })
        fitted[name] = (model, diagnostic_prediction)
    best = min(results, key=lambda row: row["cv_rmse_mean"])
    # A validation-only permutation importance snapshot: fit through 2019 and
    # score on 2020, never using the 2021–2025 diagnostic rows.
    best_model = fitted[best["algorithm"]][0]
    train_2020 = data[data.year < 2020]
    valid_2020 = data[data.year == 2020]
    best_model.fit(train_2020[features], train_2020[TARGET])
    importance = permutation_importance(best_model, valid_2020[features], valid_2020[TARGET], n_repeats=10, random_state=42, scoring="neg_root_mean_squared_error")
    importance_map = {feature: float(score) for feature, score in zip(features, importance.importances_mean)}
    best_model.fit(data[data.year <= 2020][features], data[data.year <= 2020][TARGET])
    import joblib
    model_name = "lagged" if features == LAGGED_FEATURES else "calendar"
    joblib.dump(best_model, output_dir / f"best_{model_name}_candidate.joblib")
    diagnostic_predictions = pd.DataFrame({"observation_id": diagnostic.observation_id, "record_date": diagnostic.record_date, "actual_yield_t_ha": diagnostic[TARGET]})
    for name, (_, prediction) in fitted.items():
        diagnostic_predictions[f"pred_{name}"] = prediction
    return results, {"algorithm": best["algorithm"], "cv_rmse_mean": best["cv_rmse_mean"], "permutation_importance_2020": importance_map}, diagnostic_predictions


def run(production_path: Path, area_path: Path, output_dir: Path) -> dict:
    base = load_dataset(production_path, area_path)
    data = build_features(base).dropna(subset=LAGGED_FEATURES).reset_index(drop=True)
    output_dir.mkdir(parents=True, exist_ok=True)
    data.to_csv(output_dir / "lagged_provincial_dataset.csv", index=False)
    lagged_results, selected, lagged_predictions = tune_and_walk_forward(data, LAGGED_FEATURES, output_dir)
    # Calendar-only benchmark rerun under the same pre-2021 selection protocol.
    calendar_results, calendar_selected, calendar_predictions = tune_and_walk_forward(data, CALENDAR_FEATURES, output_dir)
    validation = data[(data.year >= 2017) & (data.year <= 2020)]
    tuning = data[data.year <= 2016]
    diagnostic = data[data.year >= 2021]
    naive_validation = {name: metrics(validation[TARGET], prediction) for name, prediction in seasonal_naive(tuning, validation).items()}
    naive_diagnostic = {name: metrics(diagnostic[TARGET], prediction) for name, prediction in seasonal_naive(pd.concat([tuning, validation]), diagnostic).items()}
    prediction_chart = lagged_predictions[["observation_id", "record_date", "actual_yield_t_ha", f"pred_{selected['algorithm']}"]].rename(columns={f"pred_{selected['algorithm']}": "pred_selected_lagged_model"})
    write_svg(prediction_chart, output_dir / "walk-forward-diagnostic-comparison.svg", "Phase 28 2021–2025 historical diagnostic")
    phase27_metrics = json.loads((ROOT / "storage/models/experiments/phase-27/metrics.json").read_text(encoding="utf-8"))
    result = {
        "status": "EXPERIMENTAL RESULTS AVAILABLE",
        "dataset_scope": "Batangas province aggregate PSA yield; not Lian farm-level",
        "usable_observations": int(len(data)),
        "feature_availability_rule": "all lagged features shifted one quarterly observation; current/future target and area excluded",
        "tuning_period": "1987-2016 after lag warm-up",
        "walk_forward_validation_period": "2017-2020",
        "historical_diagnostic_period": "2021-2025; not used for selection",
        "lagged_features": LAGGED_FEATURES,
        "lagged_results": lagged_results,
        "lagged_selected": selected,
        "calendar_only_results": calendar_results,
        "calendar_only_selected": calendar_selected,
        "seasonal_naive": {
            "note": "Naive values use only prior observations and are evaluated on the same chronological partitions.",
            "validation": naive_validation,
            "historical_diagnostic_2021_2025": naive_diagnostic,
        },
        "phase27_reference": {
            "note": "Historical Phase 27 metrics retained for reference only; not used in Phase 28 selection.",
            "best_selected": phase27_metrics.get("best_selected_on_validation"),
        },
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
