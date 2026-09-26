"""Model-info and safe model switching for the rice-yield service."""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from fastapi.testclient import TestClient

import main
import rice_yield_service

client = TestClient(main.app)
TOKEN = {"X-Model-Admin-Token": "secret"}
CANDIDATE = "storage/models/rice_yield_model_candidate.pkl"


def test_model_info_reports_loaded_rice_model_without_paths():
    body = client.get("/model-info").json()
    assert body["loaded"] is True
    assert body["filename"].endswith(".pkl")
    assert body["sklearn_version"] and body["loaded_at"] and body["sha256"]
    assert "path" not in body
    assert "/" not in body["filename"] and "\\" not in body["filename"]


def test_switch_requires_the_admin_token(monkeypatch):
    monkeypatch.setenv("ML_ADMIN_TOKEN", "secret")
    assert client.post("/model/switch", json={"path": CANDIDATE}).status_code == 403
    assert client.post("/model/switch", json={"path": CANDIDATE}, headers={"X-Model-Admin-Token": "wrong"}).status_code == 403


def test_switch_is_disabled_when_no_token_is_configured(monkeypatch):
    monkeypatch.delenv("ML_ADMIN_TOKEN", raising=False)
    assert client.post("/model/switch", json={"path": CANDIDATE}, headers={"X-Model-Admin-Token": ""}).status_code == 403


def test_switch_rejects_bad_paths_and_keeps_current_model(monkeypatch):
    monkeypatch.setenv("ML_ADMIN_TOKEN", "secret")
    before = client.get("/model-info").json()

    for bad in ["python_scripts/main.py", "../../etc/passwd", "storage/models/missing.pkl"]:
        assert client.post("/model/switch", json={"path": bad}, headers=TOKEN).status_code == 422

    assert client.get("/model-info").json()["sha256"] == before["sha256"]


def test_switch_swaps_model_and_rejects_wrong_checksum(monkeypatch):
    monkeypatch.setenv("ML_ADMIN_TOKEN", "secret")
    original = client.get("/model-info").json()
    original_path = rice_yield_service._rice_state["path"]

    try:
        wrong = client.post("/model/switch", json={"path": CANDIDATE, "sha256": "0" * 64}, headers=TOKEN)
        assert wrong.status_code == 422
        assert client.get("/model-info").json()["sha256"] == original["sha256"]

        ok = client.post("/model/switch", json={"path": CANDIDATE, "version": "1.1.0"}, headers=TOKEN)
        assert ok.status_code == 200
        assert ok.json()["filename"] == "rice_yield_model_candidate.pkl"
        assert ok.json()["version"] == "1.1.0"
    finally:
        rice_yield_service.switch_rice_model(original_path, original.get("version"), original["sha256"])

    assert client.get("/model-info").json()["sha256"] == original["sha256"]
