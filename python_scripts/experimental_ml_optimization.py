"""Evidence-gated experimental rice-yield model optimization.

This module is deliberately separate from the production prediction service.
It will not read candidate/demo data as research evidence, will not choose an
evaluation split, and will not write an experimental model until an approved
dataset plus an approved metadata sidecar are supplied.

Expected approved CSV columns use the production input names in lower case:
the eleven numeric inputs from ``ml_validation.DEPLOYED_INPUTS`` plus
``season``, ``actual_yield_t_ha``, ``observation_id``, ``group_id``,
``record_date``, ``split``, ``provenance_status``, and ``approval_ref``.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Mapping, Sequence

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.dummy import DummyRegressor
from sklearn.ensemble import ExtraTreesRegressor, GradientBoostingRegressor, HistGradientBoostingRegressor, RandomForestRegressor
from sklearn.inspection import permutation_importance
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import GroupKFold, RandomizedSearchCV, TimeSeriesSplit
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from ml_validation import DEPLOYED_INPUTS, DEPLOYED_RICE_FEATURES, validate_evaluation_split


NUMERIC_FEATURES: tuple[str, ...] = tuple(item for item in DEPLOYED_INPUTS if item != "season")
CATEGORICAL_FEATURES: tuple[str, ...] = ("season",)
TARGET = "actual_yield_t_ha"
REQUIRED_META_FIELDS = ("approval_status", "model_feature_names", "target", "feature_definitions")
APPROVED_PROVENANCE = frozenset({"verified", "approved"})
EXPERIMENT_DIR = Path(__file__).resolve().parents[1] / "storage" / "models" / "experiments"


@dataclass(frozen=True)
class ExperimentDiagnostic:
    code: str
    message: str
    field: str | None = None


class SeasonalMeanRegressor:
    """Leakage-safe seasonal baseline fitted only on the training partition."""

    def fit(self, X: pd.DataFrame, y: Sequence[float]):
        self.global_mean_ = float(np.mean(y))
        self.season_means_ = pd.Series(y, index=X.index).groupby(X["season"]).mean().to_dict()
        return self

    def predict(self, X: pd.DataFrame) -> np.ndarray:
        return np.asarray([self.season_means_.get(season, self.global_mean_) for season in X["season"]], dtype=float)


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _base_preprocessor() -> ColumnTransformer:
    return ColumnTransformer(
        transformers=[
            ("numeric", "passthrough", list(NUMERIC_FEATURES)),
            ("season", OneHotEncoder(handle_unknown="ignore", sparse_output=False), list(CATEGORICAL_FEATURES)),
        ],
        remainder="drop",
    )


def build_pipeline(estimator) -> Pipeline:
    return Pipeline([("preprocess", _base_preprocessor()), ("model", estimator)])


def model_search_spaces(random_state: int = 42) -> dict[str, tuple[Any, dict[str, Sequence[Any]]]]:
    """Return reproducible estimator/search-space definitions.

    Search spaces are intentionally modest; the final search must be rerun on
    the approved data package, never on a candidate/demo dataset.
    """
    return {
        "Random Forest": (
            RandomForestRegressor(random_state=random_state, n_jobs=1),
            {
                "model__n_estimators": [200, 300, 500],
                "model__max_depth": [None, 8, 12, 20],
                "model__min_samples_leaf": [1, 2, 4],
                "model__max_features": ["sqrt", 0.7, 1.0],
            },
        ),
        "Gradient Boosting": (
            GradientBoostingRegressor(random_state=random_state),
            {
                "model__n_estimators": [100, 200, 300],
                "model__learning_rate": [0.02, 0.05, 0.1],
                "model__max_depth": [1, 2, 3],
                "model__min_samples_leaf": [1, 2, 4],
            },
        ),
        "Extra Trees": (
            ExtraTreesRegressor(random_state=random_state, n_jobs=1),
            {
                "model__n_estimators": [200, 300, 500],
                "model__max_depth": [None, 8, 12, 20],
                "model__min_samples_leaf": [1, 2, 4],
                "model__max_features": ["sqrt", 0.7, 1.0],
            },
        ),
        "HistGradientBoosting": (
            HistGradientBoostingRegressor(random_state=random_state),
            {
                "model__max_iter": [100, 200, 300],
                "model__learning_rate": [0.03, 0.05, 0.1],
                "model__max_leaf_nodes": [7, 15, 31],
                "model__l2_regularization": [0.0, 0.1, 1.0],
            },
        ),
    }


def _diagnostics_for_metadata(metadata: Mapping[str, Any]) -> list[ExperimentDiagnostic]:
    diagnostics: list[ExperimentDiagnostic] = []
    for field in REQUIRED_META_FIELDS:
        if metadata.get(field) in (None, "", []):
            diagnostics.append(ExperimentDiagnostic("MISSING_METADATA", f"Approved metadata is missing {field}.", field))
    if metadata.get("approval_status") != "approved":
        diagnostics.append(ExperimentDiagnostic("DATA_NOT_APPROVED", "Dataset metadata approval_status must be approved.", "approval_status"))
    if metadata.get("target") != TARGET:
        diagnostics.append(ExperimentDiagnostic("TARGET_MISMATCH", f"Approved target must be {TARGET}.", "target"))
    if tuple(metadata.get("model_feature_names", ())) != DEPLOYED_RICE_FEATURES:
        diagnostics.append(ExperimentDiagnostic("FEATURE_SCHEMA_MISMATCH", "Metadata feature names/order do not match the deployed 12-feature schema.", "model_feature_names"))
    definitions = metadata.get("feature_definitions")
    if isinstance(definitions, Mapping):
        for feature in DEPLOYED_INPUTS:
            definition = definitions.get(feature)
            if not isinstance(definition, Mapping):
                diagnostics.append(ExperimentDiagnostic("MISSING_FEATURE_DEFINITION", f"No approved definition for {feature}.", feature))
                continue
            for field in ("unit", "transformation", "window", "spatial_unit"):
                if definition.get(field) in (None, ""):
                    diagnostics.append(ExperimentDiagnostic("INCOMPLETE_FEATURE_DEFINITION", f"Feature {feature} lacks {field}.", feature))
    return diagnostics


def validate_approved_dataset(csv_path: str | Path, metadata_path: str | Path) -> tuple[pd.DataFrame | None, list[ExperimentDiagnostic]]:
    """Load only an approved compatible dataset; never mutates the input file."""
    path = Path(csv_path)
    metadata_file = Path(metadata_path)
    diagnostics: list[ExperimentDiagnostic] = []
    if not path.is_file() or not metadata_file.is_file():
        return None, [ExperimentDiagnostic("EVIDENCE_UNAVAILABLE", "Approved dataset and metadata sidecar are required.")]
    try:
        metadata = json.loads(metadata_file.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return None, [ExperimentDiagnostic("INVALID_METADATA", f"Could not read metadata sidecar: {exc}")]
    diagnostics.extend(_diagnostics_for_metadata(metadata))
    declared_sha = metadata.get("dataset_sha256")
    if declared_sha and declared_sha.lower() != _sha256(path).lower():
        diagnostics.append(ExperimentDiagnostic("DATASET_CHECKSUM_MISMATCH", "Dataset checksum does not match approved metadata."))
    try:
        frame = pd.read_csv(path)
    except Exception as exc:  # pandas may raise multiple parser/IO errors
        return None, diagnostics + [ExperimentDiagnostic("INVALID_DATASET", f"Could not read CSV: {exc}")]
    required = set(DEPLOYED_INPUTS) | {TARGET, "observation_id", "group_id", "record_date", "split", "provenance_status", "approval_ref"}
    missing = sorted(required - set(frame.columns))
    diagnostics.extend(ExperimentDiagnostic("MISSING_DATASET_FIELD", f"Dataset is missing {field}.", field) for field in missing)
    if not missing:
        if frame.empty:
            diagnostics.append(ExperimentDiagnostic("EMPTY_DATASET", "Approved dataset contains no observations."))
        if frame["observation_id"].duplicated().any():
            diagnostics.append(ExperimentDiagnostic("DUPLICATE_OBSERVATION_ID", "Observation IDs must be unique."))
        if frame["provenance_status"].astype(str).str.lower().isin(APPROVED_PROVENANCE).eq(False).any():
            diagnostics.append(ExperimentDiagnostic("UNAPPROVED_PROVENANCE", "Every evaluation row must have verified or approved provenance."))
        if frame["approval_ref"].isna().any() or frame["approval_ref"].astype(str).str.strip().eq("").any():
            diagnostics.append(ExperimentDiagnostic("MISSING_APPROVAL_REFERENCE", "Every evaluation row requires an approval reference."))
        for feature in NUMERIC_FEATURES + (TARGET,):
            values = pd.to_numeric(frame[feature], errors="coerce")
            if values.isna().any() or not np.isfinite(values.to_numpy()).all():
                diagnostics.append(ExperimentDiagnostic("INVALID_NUMERIC_VALUE", f"Feature/target contains non-finite values: {feature}.", feature))
        if not frame["season"].astype(str).isin({"Wet", "Dry"}).all():
            diagnostics.append(ExperimentDiagnostic("INVALID_SEASON", "Season values must be Wet or Dry.", "season"))
        split_rows = frame.to_dict("records")
        split_diagnostics = validate_evaluation_split(split_rows, strategy=str(metadata.get("evaluation_strategy", "")))
        diagnostics.extend(ExperimentDiagnostic(item.code, item.message, item.field) for item in split_diagnostics)
    return (frame if not diagnostics else None), diagnostics


def _metrics(actual: Sequence[float], predicted: Sequence[float]) -> dict[str, float | None]:
    actual_array = np.asarray(actual, dtype=float)
    predicted_array = np.asarray(predicted, dtype=float)
    nonzero = actual_array != 0
    return {
        "r2": float(r2_score(actual_array, predicted_array)) if len(actual_array) > 1 else None,
        "mae": float(mean_absolute_error(actual_array, predicted_array)),
        "rmse": float(np.sqrt(mean_squared_error(actual_array, predicted_array))),
        "mape": float(np.mean(np.abs((actual_array[nonzero] - predicted_array[nonzero]) / actual_array[nonzero])) * 100) if nonzero.any() else None,
        "bias": float(np.mean(predicted_array - actual_array)),
    }


def evaluate_approved_dataset(
    frame: pd.DataFrame,
    *,
    n_iter: int = 10,
    random_state: int = 42,
    output_dir: str | Path = EXPERIMENT_DIR,
) -> dict[str, Any]:
    """Tune on train rows only, score once on the supplied approved test rows."""
    train = frame[frame["split"].astype(str).str.lower() == "train"].copy()
    test = frame[frame["split"].astype(str).str.lower() == "test"].copy()
    if train.empty or test.empty:
        raise ValueError("Approved dataset must contain both train and test rows.")
    train = train.sort_values("record_date")
    test = test.sort_values("record_date")
    X_train, y_train = train[list(DEPLOYED_INPUTS)], train[TARGET].astype(float)
    X_test, y_test = test[list(DEPLOYED_INPUTS)], test[TARGET].astype(float)
    groups = train["group_id"].astype(str)
    unique_groups = groups.nunique()
    if unique_groups < 2:
        raise ValueError("At least two independent training groups are required for tuning.")
    cv = GroupKFold(n_splits=min(5, unique_groups))
    results: list[dict[str, Any]] = []
    fitted_candidates: dict[str, Pipeline] = {}
    for name, (estimator, space) in model_search_spaces(random_state).items():
        search = RandomizedSearchCV(
            build_pipeline(estimator),
            space,
            n_iter=min(n_iter, max(1, int(np.prod([len(values) for values in space.values()])))),
            scoring="neg_root_mean_squared_error",
            cv=cv,
            n_jobs=1,
            random_state=random_state,
            refit=True,
        )
        search.fit(X_train, y_train, groups=groups)
        predictions = search.best_estimator_.predict(X_test)
        metrics = _metrics(y_test, predictions)
        results.append({"algorithm": name, "best_params": search.best_params_, **metrics})
        fitted_candidates[name] = search.best_estimator_
    mean_baseline = np.full(len(y_test), float(y_train.mean()))
    seasonal = SeasonalMeanRegressor().fit(X_train, y_train).predict(X_test)
    baseline_results = {"overall_mean": _metrics(y_test, mean_baseline), "seasonal_mean": _metrics(y_test, seasonal)}
    best = min(results, key=lambda row: row["rmse"])
    best_model = fitted_candidates[best["algorithm"]]
    output = Path(output_dir)
    output.mkdir(parents=True, exist_ok=True)
    model_path = output / f"{best['algorithm'].lower().replace(' ', '_')}_candidate.joblib"
    import joblib
    joblib.dump(best_model, model_path)
    feature_importance = permutation_importance(best_model, X_test, y_test, n_repeats=10, random_state=random_state, scoring="neg_root_mean_squared_error")
    return {
        "status": "EXPERIMENTAL RESULTS AVAILABLE",
        "train_rows": len(train), "test_rows": len(test), "train_groups": int(unique_groups),
        "results": results, "baselines": baseline_results, "best_candidate": best,
        "model_path": str(model_path),
        "permutation_importance": {feature: float(score) for feature, score in zip(DEPLOYED_INPUTS, feature_importance.importances_mean)},
        "production_model_unchanged": True,
    }


def blocked_report(reason: str = "No approved dataset and metadata sidecar supplied.") -> dict[str, Any]:
    return {"status": "BLOCKED", "accuracy_status": "ACCURACY NOT VERIFIED", "reason": reason, "production_model_unchanged": True, "artifacts_written": False}


def main() -> int:
    parser = argparse.ArgumentParser(description="Evidence-gated iClimate experimental ML optimization")
    parser.add_argument("--dataset")
    parser.add_argument("--metadata")
    parser.add_argument("--output-dir", default=str(EXPERIMENT_DIR))
    parser.add_argument("--n-iter", type=int, default=10)
    args = parser.parse_args()
    if not args.dataset or not args.metadata:
        print(json.dumps(blocked_report(), indent=2))
        return 2
    frame, diagnostics = validate_approved_dataset(args.dataset, args.metadata)
    if diagnostics:
        print(json.dumps({"status": "BLOCKED", "accuracy_status": "ACCURACY NOT VERIFIED", "diagnostics": [asdict(item) for item in diagnostics], "artifacts_written": False}, indent=2))
        return 2
    print(json.dumps(evaluate_approved_dataset(frame, n_iter=args.n_iter, output_dir=args.output_dir), indent=2, default=str))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
