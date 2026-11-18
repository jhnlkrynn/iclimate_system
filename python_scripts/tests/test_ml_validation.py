import sys
from datetime import date
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from ml_validation import (  # noqa: E402
    DEPLOYED_RICE_FEATURES,
    DEPLOYED_INPUTS,
    Diagnostic,
    classify_records,
    compare_sensitivity,
    detect_split_leakage,
    diagnose_freshness,
    evaluate_predictions,
    inspect_model_artifact,
    build_model_metadata,
    validate_feature_units,
    validate_feature_mapping,
    validate_feature_values,
    validate_input_provenance,
    validate_evaluation_split,
    validate_research_dataset,
    validate_evidence_manifest,
    compare_feature_definitions,
)


def complete_features():
    return {key: ("Wet" if key == "season" else 1.0) for key in DEPLOYED_INPUTS}


def test_deployed_feature_contract_is_exact_and_ordered():
    assert validate_feature_mapping(DEPLOYED_RICE_FEATURES) == []
    assert validate_feature_mapping(DEPLOYED_RICE_FEATURES[:-1])
    assert validate_feature_mapping(tuple(reversed(DEPLOYED_RICE_FEATURES)))


def test_missing_non_numeric_and_unsupported_values_are_reported_without_defaults():
    features = complete_features()
    features.pop("rainfall_6m")
    features["temp_avg"] = "not-a-number"
    features["season"] = "Unknown"
    codes = {item.code for item in validate_feature_values(features)}
    assert codes == {"MISSING_FEATURE", "NON_NUMERIC_FEATURE", "UNSUPPORTED_SEASON"}


def test_optional_ranges_are_diagnostics_not_transformations():
    features = complete_features()
    diagnostics = validate_feature_values(features, approved_ranges={"area": (12.0, 258.0)})
    assert diagnostics == [
        Diagnostic("OUT_OF_APPROVED_RANGE", "area=1.0 is outside the approved range [12.0, 258.0].", "area", "warning")
    ]
    assert features["area"] == 1.0


def test_unit_diagnostics_report_mismatch_without_converting_values():
    diagnostics = validate_feature_units(
        {"rainfall_6m": "mm_sum", "area": "ha"},
        {"rainfall_6m": "mm_mean", "area": "ha", "temp_avg": "degC"},
    )
    assert {item.code for item in diagnostics} == {"UNIT_MISMATCH", "MISSING_UNIT"}


def test_model_metadata_is_explicitly_non_activating():
    metadata = build_model_metadata(
        model_name="rice-yield-candidate",
        version="0.1.0",
        schema_version="deployed-12-v1",
        dataset_sha256=None,
        feature_names=DEPLOYED_RICE_FEATURES,
    )
    assert metadata["status"] == "candidate"
    assert metadata["activation_authorized"] is False


def test_freshness_reports_missing_previous_months_and_does_not_fill_them():
    records = [{"record_date": "2026-01-15"}, {"record_date": "2026-02-15"}]
    diagnostics = diagnose_freshness(records, date(2026, 7, 1), required_months=3)
    assert {item.code for item in diagnostics} == {"MISSING_HISTORY_MONTHS", "STALE_HISTORY"}


def test_provenance_classifier_separates_demo_derived_and_unverified_rows():
    result = classify_records([
        {"source": "MAO", "validation_status": "verified", "observation_id": "a"},
        {"source": "demo_seed", "observation_id": "b"},
        {"source": "derived_allocation", "observation_id": "c"},
        {"source": "unknown", "observation_id": "a"},
    ])
    assert result["counts"] == {
        "verified_observation": 1,
        "unverified_source": 1,
        "derived_allocation": 1,
        "demo_seed": 1,
        "model_output": 0,
    }
    assert result["duplicates"] == {"a": [0, 3]}


def test_split_leakage_detects_same_season_and_observation_in_both_splits():
    diagnostics = detect_split_leakage([
        {"group_id": "Wet-2024", "observation_id": "row-1", "split": "train"},
        {"group_id": "Wet-2024", "observation_id": "row-1", "split": "test"},
    ])
    assert {item.code for item in diagnostics} == {"GROUP_SPLIT_LEAKAGE", "OBSERVATION_SPLIT_LEAKAGE"}


def test_evaluation_split_supports_chronological_and_season_grouped_evidence():
    assert validate_evaluation_split([
        {"record_date": "2024-01-01", "group_id": "2023", "split": "train"},
        {"record_date": "2024-02-01", "group_id": "2024", "split": "test"},
    ], strategy="chronological") == []
    assert validate_evaluation_split([
        {"record_date": "2024-01-01", "group_id": "2024", "split": "train"},
        {"record_date": "2025-01-01", "group_id": "2025", "split": "test"},
    ], strategy="season_grouped") == []


def test_evaluation_split_rejects_group_or_chronology_leakage():
    diagnostics = validate_evaluation_split([
        {"record_date": "2024-02-01", "group_id": "2024", "split": "train"},
        {"record_date": "2024-01-01", "group_id": "2024", "split": "test"},
    ], strategy="chronological")
    assert {item.code for item in diagnostics} == {"GROUP_SPLIT_LEAKAGE", "CHRONOLOGICAL_SPLIT_OVERLAP"}


def test_input_provenance_requires_a_source_and_rejects_unverified_status():
    diagnostics = validate_input_provenance({"rainfall": {"source": "fixture", "status": "unverified"}} , required_inputs=["rainfall", "season"])
    assert {item.code for item in diagnostics} == {"UNSUPPORTED_INPUT_PROVENANCE", "MISSING_INPUT_PROVENANCE"}


def test_research_intake_rejects_demo_derived_duplicate_and_unlabelled_proxy_rows():
    result = validate_research_dataset([
        {
            "observation_id": "obs-1", "record_date": "2025-06-01", "group_id": "season-2025",
            "season": "Wet", "source_type": "Ambulong", "source": "Ambulong Lian", "provenance": "verified",
            "unit": "t/ha",
        },
        {
            "observation_id": "obs-1", "record_date": "bad-date", "group_id": "season-2025",
            "season": "Unknown", "source_type": "demo_seed", "provenance": "derived", "unit": "kg/ha",
        },
    ])
    codes = {item["code"] for item in result["diagnostics"]}
    assert {"DUPLICATE_OBSERVATION_ID", "INVALID_INTAKE_DATE", "UNSUPPORTED_INTAKE_SEASON", "UNIT_MISMATCH", "UNLABELLED_CLIMATE_PROXY"} <= codes
    assert result["valid"] is False


def test_research_intake_can_pass_structure_without_authorizing_accuracy():
    result = validate_research_dataset([
        {
            "observation_id": "obs-1", "record_date": "2025-06-01", "group_id": "season-2025",
            "season": "Wet", "source_type": "MAO", "provenance": "approved", "unit": "t/ha",
        },
    ])
    assert result["valid"] is True
    assert result["independent_groups"] == 1


def test_evidence_manifest_requires_roles_and_sha256_without_opening_artifacts():
    result = validate_evidence_manifest({
        "package_id": "phase-11-example",
        "provider_role": "original_model_developer",
        "approval_status": "pending",
        "artifacts": [{
            "artifact_id": "training-data",
            "role": "training_dataset",
            "filename": "training.csv",
            "sha256": "a" * 64,
            "format": "csv",
        }],
    })
    assert result["valid"] is True
    assert result["artifact_count"] == 1


def test_feature_definition_comparison_reports_units_and_transform_mismatches():
    diagnostics = compare_feature_definitions(
        {"RAINFALL": {"unit": "mm", "transformation": "sum", "window": "6M", "spatial_unit": "station"}},
        {"RAINFALL": {"unit": "mm", "transformation": "mean", "window": "6M", "spatial_unit": "station"}},
    )
    assert {item.code for item in diagnostics} == {"FEATURE_DEFINITION_MISMATCH"}


def test_sensitivity_diagnostic_never_runs_a_prediction():
    assert compare_sensitivity(None, None)[0].code == "SENSITIVITY_NOT_VERIFIED"
    assert compare_sensitivity(1.0, 1.0)[0].code == "NO_OBSERVED_SENSITIVITY"
    assert compare_sensitivity(1.0, 1.1) == []


def test_metrics_include_baseline_and_never_claim_accuracy_for_empty_evidence():
    result = evaluate_predictions([3.0, 4.0], [3.5, 3.5], [3.2, 3.2], n_groups=2)
    assert result.ready is True
    assert result.n_rows == 2
    assert result.n_groups == 2
    assert result.mae == 0.5
    assert result.baseline_mae == 0.5
    assert evaluate_predictions([], []).status == "ACCURACY NOT VERIFIED"


def test_model_metadata_is_read_only_and_reports_checksum_without_loading():
    path = Path(__file__).resolve().parents[2] / "storage" / "models" / "rice_yield_model_final.pkl"
    result = inspect_model_artifact(path)
    assert result["available"] is True
    assert len(result["sha256"]) == 64
    assert "feature_names" not in result
