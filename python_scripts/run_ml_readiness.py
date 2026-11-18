"""Read-only ML readiness gate runner.

The command emits JSON to stdout and never writes reports, databases, model
files, caches, or predictions. It intentionally reports blocked evidence as
BLOCKED/NOT VERIFIED rather than manufacturing a passing accuracy result.
"""

from __future__ import annotations

import argparse
import csv
import json
from datetime import date
from pathlib import Path
from typing import Any

from ml_validation import (
    DEPLOYED_RICE_FEATURES,
    Diagnostic,
    classify_records,
    compare_sensitivity,
    detect_split_leakage,
    diagnose_freshness,
    inspect_model_artifact,
    validate_research_dataset,
    validate_evaluation_split,
    validate_evidence_manifest,
    validate_feature_mapping,
    validate_input_provenance,
)

BASE_DIR = Path(__file__).resolve().parent.parent


def _sha256(path: Path) -> str:
    import hashlib

    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_evidence_inventory(root: Path = BASE_DIR) -> list[dict[str, Any]]:
    """Inventory known evidence locations without treating presence as approval."""
    entries = [
        ("deployed_model", "storage/models/rice_yield_model_final.pkl", "present_deployed_artifact"),
        ("candidate_dataset", "storage/datasets/iClimate_Modeling_Ready_TimeAware.csv", "present_candidate_only"),
        ("candidate_metadata", "storage/models/rice_yield_model_candidate_metadata.json", "present_candidate_only"),
        ("candidate_evaluation", "storage/models/rice_yield_season_retrain_report.json", "present_candidate_only"),
        ("climate_proxy", "database/data/Ambulong Monthly Data.csv", "present_proxy_unapproved"),
        ("agricultural_source_shaped", "database/seeders/data/mao-planting-summary-2020-2025.csv", "present_unverified"),
        ("original_training_dataset", "research/original_training_dataset.csv", "missing_required"),
        ("original_training_code", "research/train_deployed_model.py", "missing_required"),
        ("original_feature_dictionary", "research/feature_dictionary.csv", "missing_required"),
        ("original_evaluation_protocol", "research/evaluation_protocol.json", "missing_required"),
        ("independent_yield_observations", "research/independent_yield_observations.csv", "missing_required"),
    ]
    inventory: list[dict[str, Any]] = []
    for role, relative, status_if_present in entries:
        path = root / relative
        item: dict[str, Any] = {"role": role, "path": relative, "status": status_if_present if path.is_file() else "missing_required"}
        if path.is_file():
            item["bytes"] = path.stat().st_size
            item["sha256"] = _sha256(path)
        inventory.append(item)
    return inventory


def _gate(name: str, status: str, message: str, *, evidence: Any = None, diagnostics: list[Diagnostic] | None = None) -> dict[str, Any]:
    return {
        "name": name,
        "status": status,
        "message": message,
        "evidence": evidence,
        "diagnostics": [item.__dict__ for item in (diagnostics or [])],
    }


def _read_csv(path: Path) -> list[dict[str, str]]:
    if not path.is_file():
        return []
    with path.open(newline="", encoding="utf-8-sig") as handle:
        return list(csv.DictReader(handle))


def _climate_rows_with_dates(rows: list[dict[str, str]]) -> list[dict[str, str]]:
    """Normalize the repository's YEAR/MONTH climate file for freshness checks."""
    normalized: list[dict[str, str]] = []
    for row in rows:
        copy = dict(row)
        if not copy.get("record_date") and copy.get("YEAR") and copy.get("MONTH"):
            copy["record_date"] = f"{copy['YEAR']}-{int(copy['MONTH']):02d}-01"
        normalized.append(copy)
    return normalized


def build_readiness_report(
    *,
    model_path: Path | str = BASE_DIR / "storage/models/rice_yield_model_final.pkl",
    climate_path: Path | str = BASE_DIR / "database/data/Ambulong Monthly Data.csv",
    agricultural_path: Path | str = BASE_DIR / "database/seeders/data/mao-planting-summary-2020-2025.csv",
    evaluation_path: Path | str | None = None,
    evaluation_strategy: str | None = None,
    manifest_path: Path | str | None = None,
    target_date: str | date | None = None,
) -> dict[str, Any]:
    """Build a read-only report from repository artifacts and explicit evidence."""
    model_path = Path(model_path)
    climate_path = Path(climate_path)
    agricultural_path = Path(agricultural_path)
    evaluation_path = Path(evaluation_path) if evaluation_path else None
    manifest_path = Path(manifest_path) if manifest_path else None
    target = target_date or date.today().isoformat()
    model = inspect_model_artifact(model_path, load_artifact=True)
    model_diagnostics = validate_feature_mapping(model.get("feature_names", []), {key: value for key, value in zip(
        ("rainfall", "temp_avg", "temp_range", "area", "previous_rainfall", "previous_temp", "rainfall_6m", "temp_3m", "temp_6m", "seasonal_rainfall", "seasonal_temp", "season"),
        DEPLOYED_RICE_FEATURES,
    )})

    climate_rows = _climate_rows_with_dates(_read_csv(climate_path))
    agricultural_rows = _read_csv(agricultural_path)
    evaluation_rows = _read_csv(evaluation_path) if evaluation_path else []
    intake = validate_research_dataset(evaluation_rows) if evaluation_path else None
    manifest = None
    manifest_validation = None
    if manifest_path and manifest_path.is_file():
        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            manifest_validation = validate_evidence_manifest(manifest)
        except (OSError, json.JSONDecodeError):
            manifest_validation = {"valid": False, "diagnostics": [{"code": "INVALID_MANIFEST_JSON", "message": "Evidence manifest is not valid JSON.", "field": None, "severity": "error"}]}
    evaluation_diagnostics = []
    if evaluation_rows:
        evaluation_diagnostics = (
            validate_evaluation_split(evaluation_rows, strategy=evaluation_strategy)
            if evaluation_strategy
            else detect_split_leakage(evaluation_rows)
        )
    provenance = {key: {"source": "repository implementation", "status": "unverified"} for key in (
        "rainfall", "temp_avg", "temp_range", "area", "previous_rainfall", "previous_temp", "rainfall_6m", "temp_3m", "temp_6m", "seasonal_rainfall", "seasonal_temp", "season",
    )}
    provenance_diagnostics = validate_input_provenance(provenance)
    freshness_diagnostics = diagnose_freshness(climate_rows, target, required_months=6)
    record_summary = classify_records([{"source": "unverified MAO-shaped source", "observation_id": row.get("YEAR", "") + "-" + row.get("MONTH", "")} for row in agricultural_rows])

    gates = [
        _gate(
            "deployed_feature_schema",
            "PASS" if model.get("available") and not model_diagnostics else "FAIL",
            "Deployed artifact feature names and order match the repository contract." if not model_diagnostics else "The artifact does not match the deployed 12-feature contract.",
            evidence={"model": model, "expected_features": list(DEPLOYED_RICE_FEATURES)},
            diagnostics=model_diagnostics,
        ),
        _gate(
            "model_checksum_metadata",
            "PASS" if model.get("available") else "BLOCKED",
            "Model checksum and artifact metadata are available read-only." if model.get("available") else "Model artifact is unavailable.",
            evidence={key: model.get(key) for key in ("path", "size_bytes", "sha256", "type")},
        ),
        _gate(
            "input_provenance",
            "BLOCKED",
            "Input sources are present only as unverified repository wiring; approved training provenance is unavailable.",
            evidence={"inputs": provenance},
            diagnostics=provenance_diagnostics,
        ),
        _gate(
            "climate_freshness",
            "FAIL" if any(item.code in {"STALE_HISTORY", "MISSING_HISTORY_MONTHS"} for item in freshness_diagnostics) else "PASS",
            "Required historical climate months are missing or stale for the requested target date." if freshness_diagnostics else "Required historical months are present and current.",
            evidence={"path": str(climate_path), "rows": len(climate_rows), "target_date": str(target)},
            diagnostics=freshness_diagnostics,
        ),
        _gate(
            "agricultural_provenance",
            "BLOCKED",
            "Agricultural source rows are not approved independent observations; derived/demo status cannot be cleared by this runner.",
            evidence={"path": str(agricultural_path), "rows": len(agricultural_rows), "classification": record_summary},
        ),
        _gate(
            "leakage_and_independent_evidence",
            "FAIL" if evaluation_diagnostics else "BLOCKED",
            "The supplied evaluation snapshot contains split leakage or invalid evaluation evidence." if evaluation_diagnostics else (
                "A structurally valid snapshot was supplied, but independent approval and methodology evidence are still required." if evaluation_rows else "No approved independent evaluation snapshot was supplied to the read-only runner."
            ),
            evidence={"independent_observations": intake["rows"] if intake else 0, "approved_groups": intake["independent_groups"] if intake else 0, "strategy": evaluation_strategy},
            diagnostics=evaluation_diagnostics,
        ),
        _gate(
            "research_dataset_intake",
            "PASS" if intake and intake["valid"] else ("FAIL" if intake and intake["diagnostics"] else "BLOCKED"),
            "The supplied research snapshot passed structural intake checks; approval and evaluation remain separate gates." if intake and intake["valid"] else (
                "The supplied research snapshot failed structural intake checks." if intake and intake["diagnostics"] else "No researcher-approved evaluation snapshot was supplied."
            ),
            evidence={"path": str(evaluation_path), "intake": intake} if evaluation_path else None,
        ),
        _gate(
            "evidence_manifest",
            "PASS" if manifest_validation and manifest_validation.get("valid") else ("FAIL" if manifest_validation else "BLOCKED"),
            "Evidence manifest is structurally valid; approval and artifact review remain external." if manifest_validation and manifest_validation.get("valid") else (
                "Evidence manifest is invalid." if manifest_validation else "No evidence manifest was supplied."
            ),
            evidence={"path": str(manifest_path), "manifest": manifest_validation} if manifest_path else None,
            diagnostics=[Diagnostic(item["code"], item["message"], item.get("field"), item.get("severity", "error")) for item in (manifest_validation or {}).get("diagnostics", [])],
        ),
        _gate(
            "prediction_sensitivity",
            "NOT VERIFIED",
            "No approved baseline/perturbation experiment was executed; the runner never calls prediction code.",
            diagnostics=compare_sensitivity(None, None),
        ),
        _gate(
            "accuracy_claim",
            "BLOCKED",
            "ACCURACY NOT VERIFIED: approved independent observations and methodology are unavailable.",
            evidence={"metrics": None, "baseline": None},
        ),
    ]

    return {
        "report": "iclimate-ml-readiness",
        "schema_version": "phase-9-v1",
        "generated_at": date.today().isoformat(),
        "evidence_inventory": build_evidence_inventory(BASE_DIR),
        "read_only": True,
        "production_mutation": False,
        "accuracy_status": "ACCURACY NOT VERIFIED",
        "gates": gates,
        "research_approvals": {
            "training_artifacts": "BLOCKED",
            "feature_definitions": "BLOCKED",
            "observation_unit": "BLOCKED",
            "climate_window": "BLOCKED",
            "validation_method": "BLOCKED",
            "farm_level_scope": "NOT VERIFIED",
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Read-only iClimate ML readiness diagnostics")
    parser.add_argument("--model", type=Path, default=BASE_DIR / "storage/models/rice_yield_model_final.pkl")
    parser.add_argument("--climate", type=Path, default=BASE_DIR / "database/data/Ambulong Monthly Data.csv")
    parser.add_argument("--agricultural", type=Path, default=BASE_DIR / "database/seeders/data/mao-planting-summary-2020-2025.csv")
    parser.add_argument("--evaluation", type=Path, default=None, help="Optional researcher snapshot for structural intake checks")
    parser.add_argument("--evaluation-strategy", choices=("chronological", "season_grouped"), default=None)
    parser.add_argument("--manifest", type=Path, default=None, help="Optional metadata-only evidence manifest JSON")
    parser.add_argument("--target-date", default=date.today().isoformat())
    args = parser.parse_args()
    print(json.dumps(build_readiness_report(model_path=args.model, climate_path=args.climate, agricultural_path=args.agricultural, evaluation_path=args.evaluation, evaluation_strategy=args.evaluation_strategy, manifest_path=args.manifest, target_date=args.target_date), indent=2, sort_keys=True))


if __name__ == "__main__":
    main()
