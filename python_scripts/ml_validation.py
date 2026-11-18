"""Read-only validation primitives for iClimate ML research preparation.

This module deliberately does not load, retrain, replace, or activate a model.
It validates caller-supplied fixtures and reports missing evidence explicitly.
Production prediction calculations must remain in the existing services until
the research approval gates in docs/data-audit/phase-7-ml-implementation-readiness.md
are complete.
"""

from __future__ import annotations

import hashlib
import math
from dataclasses import asdict, dataclass, field
from datetime import date, datetime
from pathlib import Path
from typing import Any, Iterable, Mapping, Sequence


DEPLOYED_RICE_FEATURES: tuple[str, ...] = (
    "RAINFALL",
    "TEMP_AVG",
    "TEMP_RANGE",
    "Area",
    "Previous_Rainfall",
    "Previous_Temp",
    "Rainfall_6M",
    "Temp_3M",
    "Temp_6M",
    "Seasonal_Rainfall",
    "Seasonal_Temp",
    "Season",
)

DEPLOYED_INPUTS: tuple[str, ...] = (
    "rainfall",
    "temp_avg",
    "temp_range",
    "area",
    "previous_rainfall",
    "previous_temp",
    "rainfall_6m",
    "temp_3m",
    "temp_6m",
    "seasonal_rainfall",
    "seasonal_temp",
    "season",
)

INPUT_TO_MODEL_FEATURE: dict[str, str] = dict(zip(DEPLOYED_INPUTS, DEPLOYED_RICE_FEATURES))
READINESS_STATUSES: tuple[str, ...] = ("PASS", "FAIL", "BLOCKED", "NOT VERIFIED")
EVIDENCE_ARTIFACT_ROLES: frozenset[str] = frozenset({
    "deployed_model", "training_dataset", "training_code", "preprocessing_code",
    "feature_dictionary", "evaluation_protocol", "source_document", "climate_dataset",
    "agricultural_dataset", "approval_record",
})


@dataclass(frozen=True)
class Diagnostic:
    code: str
    message: str
    field: str | None = None
    severity: str = "error"


@dataclass(frozen=True)
class EvaluationResult:
    """Serializable metrics container; ``ready`` stays false when evidence is incomplete."""

    ready: bool
    status: str
    n_rows: int = 0
    n_groups: int = 0
    mae: float | None = None
    rmse: float | None = None
    r2: float | None = None
    bias: float | None = None
    baseline_mae: float | None = None
    baseline_rmse: float | None = None
    baseline_r2: float | None = None
    diagnostics: tuple[Diagnostic, ...] = field(default_factory=tuple)

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["diagnostics"] = [asdict(item) for item in self.diagnostics]
        return result


def validate_evidence_manifest(manifest: Mapping[str, Any]) -> dict[str, Any]:
    """Validate a metadata-only evidence package manifest.

    This checks package completeness and checksum declarations, but never opens,
    copies, imports, or trusts the referenced artifacts. Approval remains an
    external researcher/MAO/adviser decision.
    """
    diagnostics: list[Diagnostic] = []
    for field_name in ("package_id", "provider_role", "approval_status", "artifacts"):
        if manifest.get(field_name) in (None, "", []):
            diagnostics.append(Diagnostic("MISSING_MANIFEST_FIELD", f"Evidence manifest field is missing: {field_name}.", field_name))

    approval = str(manifest.get("approval_status", ""))
    if approval and approval not in {"pending", "approved", "rejected"}:
        diagnostics.append(Diagnostic("UNSUPPORTED_APPROVAL_STATUS", f"Unsupported evidence approval status: {approval}.", "approval_status"))

    artifacts = manifest.get("artifacts")
    if not isinstance(artifacts, list):
        artifacts = []
    artifact_ids: set[str] = set()
    for index, artifact in enumerate(artifacts):
        if not isinstance(artifact, Mapping):
            diagnostics.append(Diagnostic("INVALID_MANIFEST_ARTIFACT", f"Artifact {index} must be an object.", "artifacts"))
            continue
        for field_name in ("artifact_id", "role", "filename", "sha256", "format"):
            if artifact.get(field_name) in (None, ""):
                diagnostics.append(Diagnostic("MISSING_ARTIFACT_FIELD", f"Artifact {index} is missing {field_name}.", "artifacts"))
        artifact_id = str(artifact.get("artifact_id", ""))
        if artifact_id and artifact_id in artifact_ids:
            diagnostics.append(Diagnostic("DUPLICATE_ARTIFACT_ID", f"Artifact ID is repeated: {artifact_id}.", "artifacts"))
        if artifact_id:
            artifact_ids.add(artifact_id)
        role = str(artifact.get("role", ""))
        if role and role not in EVIDENCE_ARTIFACT_ROLES:
            diagnostics.append(Diagnostic("UNSUPPORTED_ARTIFACT_ROLE", f"Unsupported evidence artifact role: {role}.", "role"))
        digest = str(artifact.get("sha256", ""))
        if digest and (len(digest) != 64 or any(char not in "0123456789abcdefABCDEF" for char in digest)):
            diagnostics.append(Diagnostic("INVALID_ARTIFACT_CHECKSUM", f"Artifact {index} does not declare a SHA-256 checksum.", "sha256"))

    return {
        "package_id": manifest.get("package_id"),
        "provider_role": manifest.get("provider_role"),
        "approval_status": approval or None,
        "artifact_count": len(artifacts),
        "artifact_roles": sorted({str(item.get("role")) for item in artifacts if isinstance(item, Mapping) and item.get("role")}),
        "diagnostics": [asdict(item) for item in diagnostics],
        "valid": bool(artifacts) and not diagnostics,
    }


def compare_feature_definitions(
    training_definitions: Mapping[str, Mapping[str, Any]],
    production_definitions: Mapping[str, Mapping[str, Any]],
    *,
    required_fields: Sequence[str] = ("unit", "transformation", "window", "spatial_unit"),
) -> list[Diagnostic]:
    """Compare supplied training and production dictionaries without inferring either."""
    diagnostics: list[Diagnostic] = []
    if tuple(training_definitions) != tuple(production_definitions):
        diagnostics.append(Diagnostic("FEATURE_DEFINITION_ORDER_MISMATCH", "Training and production feature definitions have different names or order."))

    for feature in dict.fromkeys([*training_definitions, *production_definitions]):
        training = training_definitions.get(feature)
        production = production_definitions.get(feature)
        if training is None or production is None:
            diagnostics.append(Diagnostic("FEATURE_DEFINITION_MISSING", f"Feature definition is missing on one side: {feature}.", feature))
            continue
        for field_name in required_fields:
            if training.get(field_name) in (None, "") or production.get(field_name) in (None, ""):
                diagnostics.append(Diagnostic("MISSING_FEATURE_DEFINITION", f"Feature {feature} lacks a {field_name} definition.", feature))
            elif training.get(field_name) != production.get(field_name):
                diagnostics.append(Diagnostic(
                    "FEATURE_DEFINITION_MISMATCH",
                    f"Feature {feature} differs for {field_name}: training={training.get(field_name)!r}, production={production.get(field_name)!r}.",
                    feature,
                ))
    return diagnostics


def validate_feature_mapping(
    model_features: Sequence[str],
    input_mapping: Mapping[str, str] = INPUT_TO_MODEL_FEATURE,
) -> list[Diagnostic]:
    """Check exact feature names/order without inspecting or changing a model."""
    diagnostics: list[Diagnostic] = []
    mapped = tuple(input_mapping.values())
    if tuple(model_features) != mapped:
        diagnostics.append(Diagnostic(
            "FEATURE_ORDER_MISMATCH",
            f"Expected model feature order {mapped}; received {tuple(model_features)}.",
            severity="error",
        ))
    if len(set(model_features)) != len(tuple(model_features)):
        diagnostics.append(Diagnostic("DUPLICATE_MODEL_FEATURE", "Model feature names must be unique."))
    if tuple(input_mapping.keys()) != DEPLOYED_INPUTS or mapped != DEPLOYED_RICE_FEATURES:
        diagnostics.append(Diagnostic(
            "INPUT_SCHEMA_INVALID",
            "The deployed rice-yield input mapping must contain exactly 12 ordered inputs.",
        ))

    return diagnostics


def validate_feature_values(
    features: Mapping[str, Any],
    *,
    required_inputs: Sequence[str] = DEPLOYED_INPUTS,
    approved_ranges: Mapping[str, tuple[float, float]] | None = None,
) -> list[Diagnostic]:
    """Validate presence and basic numeric/category safety without inventing values.

    ``approved_ranges`` is intentionally optional: no training ranges are assumed
    by this module. If omitted, the result reports missing/non-finite values only.
    """
    diagnostics: list[Diagnostic] = []
    for key in required_inputs:
        if key not in features or features[key] is None or features[key] == "":
            diagnostics.append(Diagnostic("MISSING_FEATURE", f"Required feature is unavailable: {key}.", key))
            continue
        if key == "season":
            if str(features[key]) not in {"Wet", "Dry"}:
                diagnostics.append(Diagnostic("UNSUPPORTED_SEASON", "Season must be Wet or Dry.", key))
            continue
        try:
            value = float(features[key])
        except (TypeError, ValueError):
            diagnostics.append(Diagnostic("NON_NUMERIC_FEATURE", f"Feature is not numeric: {key}.", key))
            continue
        if not math.isfinite(value):
            diagnostics.append(Diagnostic("NON_FINITE_FEATURE", f"Feature is not finite: {key}.", key))
        if approved_ranges and key in approved_ranges:
            low, high = approved_ranges[key]
            if value < low or value > high:
                diagnostics.append(Diagnostic(
                    "OUT_OF_APPROVED_RANGE",
                    f"{key}={value} is outside the approved range [{low}, {high}].",
                    key,
                    severity="warning",
                ))

    return diagnostics


def validate_feature_units(
    actual_units: Mapping[str, str],
    approved_units: Mapping[str, str],
) -> list[Diagnostic]:
    """Compare declared units only; this function never converts values."""
    diagnostics: list[Diagnostic] = []
    for key, expected in approved_units.items():
        actual = actual_units.get(key)
        if actual in (None, ""):
            diagnostics.append(Diagnostic("MISSING_UNIT", f"No unit declaration was supplied for {key}.", key))
        elif actual != expected:
            diagnostics.append(Diagnostic("UNIT_MISMATCH", f"{key} declares {actual}; approved unit is {expected}.", key))
    return diagnostics


def diagnose_freshness(
    records: Iterable[Mapping[str, Any]],
    target_date: str | date | datetime,
    *,
    date_key: str = "record_date",
    required_months: int = 6,
) -> list[Diagnostic]:
    """Report missing/old monthly climate history; it never substitutes a value."""
    records = list(records)
    target = _as_date(target_date)
    months: set[tuple[int, int]] = set()
    newest: date | None = None

    for row in records:
        invalid_fields = [
            key for key, value in row.items()
            if key not in {date_key, "record_date", "YEAR", "MONTH"}
            and str(value).strip() in {"-999", "-999.0", "-999.00"}
        ]
        if invalid_fields:
            # Sentinel rows are coverage holes, not observations. Keep them out
            # of the month set and report the issue once per row below.
            continue
        raw = row.get(date_key)
        if raw in (None, ""):
            continue
        try:
            observed = _as_date(raw)
        except (TypeError, ValueError):
            continue
        months.add((observed.year, observed.month))
        if newest is None or observed > newest:
            newest = observed

    diagnostics: list[Diagnostic] = []
    invalid_rows = [
        index for index, row in enumerate(records)
        if any(
            key not in {date_key, "record_date", "YEAR", "MONTH"}
            and str(value).strip() in {"-999", "-999.0", "-999.00"}
            for key, value in row.items()
        )
    ]
    if invalid_rows:
        diagnostics.append(Diagnostic(
            "INVALID_CLIMATE_SENTINEL",
            f"Climate rows contain missing-value sentinels at row indexes: {', '.join(map(str, invalid_rows))}.",
            date_key,
        ))
    expected = [_shift_month(target.replace(day=1), -offset) for offset in range(1, required_months + 1)]
    missing = [f"{item.year:04d}-{item.month:02d}" for item in expected if (item.year, item.month) not in months]
    if missing:
        diagnostics.append(Diagnostic(
            "MISSING_HISTORY_MONTHS",
            "Required historical months are unavailable: " + ", ".join(missing),
            "record_date",
        ))
    if newest is None:
        diagnostics.append(Diagnostic("NO_HISTORY", "No parseable climate history was supplied.", "record_date"))
    elif newest < expected[0]:
        diagnostics.append(Diagnostic(
            "STALE_HISTORY",
            f"Newest climate record is {newest.isoformat()}, older than the required previous month.",
            "record_date",
        ))
    return diagnostics


def classify_records(rows: Sequence[Mapping[str, Any]]) -> dict[str, Any]:
    """Classify provenance markers and duplicate identities without mutating rows."""
    counts = {key: 0 for key in ("verified_observation", "unverified_source", "derived_allocation", "demo_seed", "model_output")}
    source_ids: dict[str, list[int]] = {}
    diagnostics: list[Diagnostic] = []

    for index, row in enumerate(rows):
        text = " ".join(str(row.get(key, "")) for key in ("source", "source_type", "provenance", "record_type", "notes")).lower()
        if "demo" in text or "seed" in text:
            category = "demo_seed"
        elif "derived" in text or "allocation" in text:
            category = "derived_allocation"
        elif "model" in text or "prediction" in text:
            category = "model_output"
        elif (
            row.get("validated") is True
            or row.get("validation_status") == "verified"
            or row.get("provenance") == "approved"
            or row.get("approval_status") == "approved"
        ):
            category = "verified_observation"
        else:
            category = "unverified_source"
        counts[category] += 1

        identity = row.get("observation_id", row.get("source_id"))
        if identity not in (None, ""):
            source_ids.setdefault(str(identity), []).append(index)

    duplicates = {key: indexes for key, indexes in source_ids.items() if len(indexes) > 1}
    if duplicates:
        diagnostics.append(Diagnostic(
            "DUPLICATE_OBSERVATION_ID",
            f"Duplicate observation identities detected: {', '.join(sorted(duplicates))}.",
            severity="error",
        ))

    return {"counts": counts, "duplicates": duplicates, "diagnostics": [asdict(item) for item in diagnostics]}


def validate_research_dataset(
    rows: Sequence[Mapping[str, Any]],
    *,
    required_fields: Sequence[str] = (
        "observation_id", "record_date", "group_id", "season", "source_type", "provenance", "unit",
    ),
    date_key: str = "record_date",
    season_key: str = "season",
    unit_key: str = "unit",
    expected_unit: str = "t/ha",
) -> dict[str, Any]:
    """Validate a future researcher-approved snapshot without mutating it.

    This is an intake check, not an approval mechanism. It preserves the
    distinction between genuine observations, derived/demo/model rows, and
    climate proxies. A clean result means the file is structurally suitable for
    review; it does not authorize evaluation or establish accuracy.
    """
    diagnostics: list[Diagnostic] = []
    missing_fields = sorted({field for row in rows for field in required_fields if row.get(field) in (None, "")})
    for field in missing_fields:
        diagnostics.append(Diagnostic("MISSING_INTAKE_FIELD", f"Required intake field is missing: {field}.", field))

    classification = classify_records(rows)
    for item in classification["diagnostics"]:
        diagnostics.append(Diagnostic(item["code"], item["message"], item.get("field"), item.get("severity", "error")))

    for index, row in enumerate(rows):
        raw_date = row.get(date_key)
        if raw_date not in (None, ""):
            try:
                _as_date(raw_date)
            except (TypeError, ValueError):
                diagnostics.append(Diagnostic("INVALID_INTAKE_DATE", f"Row {index} has an invalid {date_key}.", date_key))
        season = str(row.get(season_key, ""))
        if season and season not in {"Wet", "Dry"}:
            diagnostics.append(Diagnostic("UNSUPPORTED_INTAKE_SEASON", f"Row {index} has unsupported season {season!r}.", season_key))
        unit = str(row.get(unit_key, ""))
        if unit and unit != expected_unit:
            diagnostics.append(Diagnostic("UNIT_MISMATCH", f"Row {index} declares {unit}; expected {expected_unit}.", unit_key))

        source = " ".join(str(row.get(key, "")) for key in ("source", "source_type", "station", "location_scope")).lower()
        if "ambulong" in source and "lian" in source and "proxy" not in source:
            diagnostics.append(Diagnostic("UNLABELLED_CLIMATE_PROXY", "Ambulong evidence must be labelled as a Tanauan proxy when used for Lian.", "source"))

    return {
        "rows": len(rows),
        "independent_groups": len({str(row.get("group_id")) for row in rows if row.get("group_id") not in (None, "")}),
        "classification": classification,
        "diagnostics": [asdict(item) for item in diagnostics],
        "valid": bool(rows) and not diagnostics,
    }


def detect_split_leakage(rows: Sequence[Mapping[str, Any]], *, group_key: str = "group_id", split_key: str = "split") -> list[Diagnostic]:
    """Detect duplicate identities or season/groups split between train and test."""
    diagnostics: list[Diagnostic] = []
    group_splits: dict[str, set[str]] = {}
    observation_splits: dict[str, set[str]] = {}
    for row in rows:
        split = str(row.get(split_key, ""))
        group = row.get(group_key)
        observation = row.get("observation_id", row.get("source_id"))
        if group not in (None, ""):
            group_splits.setdefault(str(group), set()).add(split)
        if observation not in (None, ""):
            observation_splits.setdefault(str(observation), set()).add(split)

    leaked_groups = sorted(key for key, values in group_splits.items() if "train" in values and "test" in values)
    leaked_observations = sorted(key for key, values in observation_splits.items() if "train" in values and "test" in values)
    if leaked_groups:
        diagnostics.append(Diagnostic("GROUP_SPLIT_LEAKAGE", f"Groups appear in both train and test: {', '.join(leaked_groups)}."))
    if leaked_observations:
        diagnostics.append(Diagnostic("OBSERVATION_SPLIT_LEAKAGE", f"Observations appear in both train and test: {', '.join(leaked_observations)}."))
    return diagnostics


def validate_evaluation_split(
    rows: Sequence[Mapping[str, Any]],
    *,
    strategy: str,
    date_key: str = "record_date",
    group_key: str = "group_id",
    split_key: str = "split",
) -> list[Diagnostic]:
    """Validate an already-approved evaluation split without creating one.

    The validator deliberately does not choose a cutoff, shuffle rows, or assign
    groups. Those are methodology decisions that require research approval.
    It only checks evidence supplied by the caller for chronological ordering or
    group separation, then applies the common duplicate/leakage checks.
    """
    if strategy not in {"chronological", "season_grouped"}:
        return [Diagnostic("UNSUPPORTED_EVALUATION_STRATEGY", f"Unsupported evaluation strategy: {strategy}.")]

    diagnostics = detect_split_leakage(rows, group_key=group_key, split_key=split_key)
    train = [row for row in rows if str(row.get(split_key, "")) == "train"]
    test = [row for row in rows if str(row.get(split_key, "")) == "test"]
    if not train or not test:
        diagnostics.append(Diagnostic("INCOMPLETE_EVALUATION_SPLIT", "Both train and test observations are required."))
        return diagnostics

    if strategy == "season_grouped":
        if any(row.get(group_key) in (None, "") for row in [*train, *test]):
            diagnostics.append(Diagnostic("MISSING_EVALUATION_GROUP", f"{group_key} is required for season-grouped validation."))
    else:
        try:
            train_dates = [_as_date(row[date_key]) for row in train]
            test_dates = [_as_date(row[date_key]) for row in test]
            if max(train_dates) >= min(test_dates):
                diagnostics.append(Diagnostic("CHRONOLOGICAL_SPLIT_OVERLAP", "Chronological test observations must occur after all training observations."))
        except (KeyError, TypeError, ValueError):
            diagnostics.append(Diagnostic("INVALID_EVALUATION_DATE", f"Parseable {date_key} values are required for chronological validation."))

    return diagnostics


def validate_input_provenance(
    provenance: Mapping[str, Mapping[str, Any]],
    *,
    required_inputs: Sequence[str] = DEPLOYED_INPUTS,
) -> list[Diagnostic]:
    """Require a source declaration for each input without approving the source."""
    diagnostics: list[Diagnostic] = []
    for key in required_inputs:
        source = provenance.get(key)
        if not source or not source.get("source"):
            diagnostics.append(Diagnostic("MISSING_INPUT_PROVENANCE", f"No source declaration was supplied for {key}.", key))
        if source and source.get("status") in {"demo", "derived", "unverified"}:
            diagnostics.append(Diagnostic(
                "UNSUPPORTED_INPUT_PROVENANCE",
                f"{key} uses {source['status']} provenance and is not approved for scientific evaluation.",
                key,
            ))
    return diagnostics


def compare_sensitivity(baseline: float | None, perturbed: float | None, *, tolerance: float = 1e-9) -> list[Diagnostic]:
    """Report whether two externally supplied outputs differ; never runs a model."""
    if baseline is None or perturbed is None:
        return [Diagnostic("SENSITIVITY_NOT_VERIFIED", "Baseline and perturbed outputs are required.", severity="warning")]
    if not math.isfinite(float(baseline)) or not math.isfinite(float(perturbed)):
        return [Diagnostic("SENSITIVITY_NOT_VERIFIED", "Sensitivity outputs must be finite.", severity="warning")]
    if abs(float(perturbed) - float(baseline)) <= tolerance:
        return [Diagnostic("NO_OBSERVED_SENSITIVITY", "Perturbed output did not change beyond the supplied tolerance.", severity="warning")]
    return []


def evaluate_predictions(
    actual: Sequence[float],
    predicted: Sequence[float],
    baseline: Sequence[float] | None = None,
    *,
    n_groups: int = 0,
    diagnostics: Sequence[Diagnostic] = (),
) -> EvaluationResult:
    """Calculate metrics only for supplied independent observations."""
    if len(actual) != len(predicted) or not actual:
        return EvaluationResult(False, "ACCURACY NOT VERIFIED", diagnostics=tuple(diagnostics) + (Diagnostic("INSUFFICIENT_PAIRS", "Actual and predicted observations are not a non-empty equal-length pair."),))
    if any(not math.isfinite(float(value)) for value in [*actual, *predicted]):
        return EvaluationResult(False, "ACCURACY NOT VERIFIED", diagnostics=tuple(diagnostics) + (Diagnostic("NON_FINITE_METRIC_INPUT", "Metrics require finite actual and predicted values."),))

    errors = [float(p) - float(a) for a, p in zip(actual, predicted)]
    mae = sum(abs(error) for error in errors) / len(errors)
    rmse = math.sqrt(sum(error * error for error in errors) / len(errors))
    mean_actual = sum(float(value) for value in actual) / len(actual)
    total = sum((float(value) - mean_actual) ** 2 for value in actual)
    r2 = None if total == 0 else 1 - (sum(error * error for error in errors) / total)
    result = dict(ready=True, status="METRICS AVAILABLE", n_rows=len(actual), n_groups=n_groups or len(actual), mae=mae, rmse=rmse, r2=r2, bias=sum(errors) / len(errors), diagnostics=tuple(diagnostics))

    if baseline is not None:
        if len(baseline) != len(actual) or any(not math.isfinite(float(value)) for value in baseline):
            return EvaluationResult(False, "ACCURACY NOT VERIFIED", n_rows=len(actual), n_groups=n_groups or len(actual), diagnostics=tuple(diagnostics) + (Diagnostic("INVALID_BASELINE", "Baseline must be finite and the same length as actual observations."),))
        baseline_errors = [float(p) - float(a) for a, p in zip(actual, baseline)]
        result["baseline_mae"] = sum(abs(error) for error in baseline_errors) / len(baseline_errors)
        result["baseline_rmse"] = math.sqrt(sum(error * error for error in baseline_errors) / len(baseline_errors))
        result["baseline_r2"] = None if total == 0 else 1 - (sum(error * error for error in baseline_errors) / total)

    return EvaluationResult(**result)


def inspect_model_artifact(path: str | Path, *, load_artifact: bool = False) -> dict[str, Any]:
    """Return read-only file metadata; loading is opt-in because pickle is executable."""
    artifact = Path(path)
    if not artifact.is_file():
        return {"available": False, "status": "EVIDENCE UNAVAILABLE", "path": str(artifact)}
    digest = hashlib.sha256(artifact.read_bytes()).hexdigest()
    result: dict[str, Any] = {"available": True, "status": "METADATA AVAILABLE", "path": str(artifact), "size_bytes": artifact.stat().st_size, "sha256": digest}
    if load_artifact:
        import joblib

        model = joblib.load(artifact)
        result["type"] = type(model).__name__
        result["feature_names"] = list(getattr(getattr(model, "named_steps", {}).get("preprocessor"), "feature_names_in_", []))
        result["has_predict"] = callable(getattr(model, "predict", None))
    return result


def build_model_metadata(
    *,
    model_name: str,
    version: str,
    schema_version: str,
    dataset_sha256: str | None,
    feature_names: Sequence[str],
    status: str = "candidate",
    metrics: Mapping[str, Any] | None = None,
) -> dict[str, Any]:
    """Build a version record in memory; callers must decide where to persist it."""
    return {
        "model_name": model_name,
        "version": version,
        "status": status,
        "schema_version": schema_version,
        "dataset_sha256": dataset_sha256,
        "feature_names": list(feature_names),
        "metrics": dict(metrics or {}),
        "activation_authorized": False,
    }


def _as_date(value: str | date | datetime) -> date:
    if isinstance(value, datetime):
        return value.date()
    if isinstance(value, date):
        return value
    return date.fromisoformat(str(value)[:10])


def _shift_month(value: date, offset: int) -> date:
    month_index = value.year * 12 + value.month - 1 + offset
    year, month_index = divmod(month_index, 12)
    return date(year, month_index + 1, 1)
