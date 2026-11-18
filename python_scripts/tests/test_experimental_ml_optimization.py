import json
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from experimental_ml_optimization import (  # noqa: E402
    DEPLOYED_INPUTS,
    DEPLOYED_RICE_FEATURES,
    blocked_report,
    model_search_spaces,
    validate_approved_dataset,
)


def test_blocked_without_approved_evidence():
    report = blocked_report()
    assert report["status"] == "BLOCKED"
    assert report["accuracy_status"] == "ACCURACY NOT VERIFIED"
    assert report["artifacts_written"] is False


def test_search_supports_requested_models():
    assert set(model_search_spaces()) == {"Random Forest", "Gradient Boosting", "Extra Trees", "HistGradientBoosting"}


def test_schema_and_approval_gate_reject_incomplete_package(tmp_path):
    csv_path = tmp_path / "data.csv"
    pd.DataFrame({"observation_id": ["x"]}).to_csv(csv_path, index=False)
    metadata_path = tmp_path / "metadata.json"
    metadata_path.write_text(json.dumps({"approval_status": "pending"}), encoding="utf-8")
    frame, diagnostics = validate_approved_dataset(csv_path, metadata_path)
    codes = {item.code for item in diagnostics}
    assert frame is None
    assert {"DATA_NOT_APPROVED", "MISSING_METADATA", "MISSING_DATASET_FIELD"} <= codes


def test_metadata_requires_exact_deployed_schema(tmp_path):
    csv_path = tmp_path / "data.csv"
    row = {key: ("Wet" if key == "season" else 1.0) for key in DEPLOYED_INPUTS}
    row.update({"observation_id": "x", "group_id": "g", "record_date": "2025-01-01", "split": "train", "provenance_status": "verified", "approval_ref": "a", "actual_yield_t_ha": 4.0})
    pd.DataFrame([row]).to_csv(csv_path, index=False)
    metadata_path = tmp_path / "metadata.json"
    metadata_path.write_text(json.dumps({
        "approval_status": "approved",
        "target": "actual_yield_t_ha",
        "model_feature_names": list(DEPLOYED_RICE_FEATURES[:-1]),
        "feature_definitions": {},
    }), encoding="utf-8")
    frame, diagnostics = validate_approved_dataset(csv_path, metadata_path)
    assert frame is None
    assert "FEATURE_SCHEMA_MISMATCH" in {item.code for item in diagnostics}
