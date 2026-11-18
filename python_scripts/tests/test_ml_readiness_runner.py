import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from run_ml_readiness import _climate_rows_with_dates, build_evidence_inventory, build_readiness_report  # noqa: E402


def test_readiness_runner_is_read_only_and_does_not_claim_accuracy():
    report = build_readiness_report(target_date="2026-11-13")
    assert report["read_only"] is True
    assert report["production_mutation"] is False
    assert report["accuracy_status"] == "ACCURACY NOT VERIFIED"
    statuses = {gate["status"] for gate in report["gates"]}
    assert "BLOCKED" in statuses
    assert "NOT VERIFIED" in statuses


def test_evidence_inventory_distinguishes_candidate_proxy_and_missing_original_artifacts():
    inventory = {item["role"]: item for item in build_evidence_inventory()}
    assert inventory["deployed_model"]["status"] == "present_deployed_artifact"
    assert inventory["candidate_dataset"]["status"] == "present_candidate_only"
    assert inventory["climate_proxy"]["status"] == "present_proxy_unapproved"
    assert inventory["original_training_dataset"]["status"] == "missing_required"


def test_runner_reports_the_deployed_feature_schema_pass_without_changing_it():
    report = build_readiness_report(target_date="2025-12-01")
    schema_gate = next(gate for gate in report["gates"] if gate["name"] == "deployed_feature_schema")
    assert schema_gate["status"] == "PASS"
    assert len(schema_gate["evidence"]["expected_features"]) == 12


def test_runner_normalizes_repository_year_month_climate_rows():
    rows = _climate_rows_with_dates([{"YEAR": "2026", "MONTH": "7", "RAINFALL": "10"}])
    assert rows[0]["record_date"] == "2026-07-01"


def test_optional_research_snapshot_is_intake_checked_but_accuracy_stays_blocked(tmp_path):
    snapshot = tmp_path / "approved_snapshot.csv"
    snapshot.write_text(
        "observation_id,record_date,group_id,season,source_type,provenance,unit\n"
        "obs-1,2025-06-01,season-2025,Wet,MAO,approved,t/ha\n",
        encoding="utf-8",
    )
    report = build_readiness_report(evaluation_path=snapshot, target_date="2025-12-01")
    gate = next(gate for gate in report["gates"] if gate["name"] == "research_dataset_intake")
    assert gate["status"] == "PASS"
    assert report["accuracy_status"] == "ACCURACY NOT VERIFIED"


def test_optional_evaluation_snapshot_cannot_pass_with_overlapping_season_groups(tmp_path):
    snapshot = tmp_path / "leaky_snapshot.csv"
    snapshot.write_text(
        "observation_id,record_date,group_id,season,source_type,provenance,unit,split\n"
        "obs-1,2025-06-01,season-2025,Wet,MAO,approved,t/ha,train\n"
        "obs-2,2025-07-01,season-2025,Wet,MAO,approved,t/ha,test\n",
        encoding="utf-8",
    )
    report = build_readiness_report(evaluation_path=snapshot, evaluation_strategy="season_grouped", target_date="2025-12-01")
    gate = next(gate for gate in report["gates"] if gate["name"] == "leakage_and_independent_evidence")
    assert gate["status"] == "FAIL"


def test_optional_manifest_is_checked_without_authorizing_accuracy(tmp_path):
    manifest = tmp_path / "evidence.json"
    manifest.write_text(
        '{"package_id":"phase-11-example","provider_role":"researcher","approval_status":"pending",'
        '"artifacts":[{"artifact_id":"training-data","role":"training_dataset",'
        '"filename":"training.csv","sha256":"' + 'a' * 64 + '","format":"csv"}]}',
        encoding="utf-8",
    )
    report = build_readiness_report(manifest_path=manifest, target_date="2025-12-01")
    gate = next(gate for gate in report["gates"] if gate["name"] == "evidence_manifest")
    assert gate["status"] == "PASS"
    assert report["accuracy_status"] == "ACCURACY NOT VERIFIED"
