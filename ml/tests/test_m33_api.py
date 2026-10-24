"""M33 research inference: saved artifacts, auth, schemas, and safe failures."""

from __future__ import annotations

import hashlib
import json
import logging
import time
from pathlib import Path

import joblib
import pandas as pd
import pytest
from fastapi.testclient import TestClient

from api.boundaries import BLOCKED_KEYS
from api.main import create_app
from api.settings import ML_ROOT, Settings
from src.common import readiness_index
from src.training.binary_dropout import positive_dropout_probability

TOKEN = "test-token-value"
FIXTURE_PATH = ML_ROOT.parent / "src" / "tests" / "Fixtures" / "research" / "research-inference.json"
SCHEMA_PATH = ML_ROOT.parent / "src" / "app" / "Services" / "Prediction" / "research-schemas.json"
EMPLOYABILITY_FILE = ML_ROOT / "models" / "employability_candidate_v1.joblib"


def _settings(**overrides) -> Settings:
    values = {
        "token": TOKEN,
        "host": "127.0.0.1",
        "port": 8000,
        "models_dir": ML_ROOT / "models",
        "inference_timeout_seconds": 2.0,
        "max_body_bytes": 65536,
        "log_level": "INFO",
        "environment": "local",
    }
    values.update(overrides)
    return Settings(**values)


def _auth() -> dict[str, str]:
    return {"Authorization": f"Bearer {TOKEN}"}


@pytest.fixture(scope="module")
def client():
    app = create_app(_settings())
    with TestClient(app) as test_client:
        yield test_client


@pytest.fixture(scope="module")
def fixture():
    return json.loads(FIXTURE_PATH.read_text(encoding="utf-8"))


def test_laravel_schema_matches_saved_artifacts_and_blocked_keys():
    schema = json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))
    assert schema["blocked_keys"] == list(BLOCKED_KEYS)
    employability = joblib.load(EMPLOYABILITY_FILE)["metadata"]
    assert schema["employability"]["columns"] == list(employability["input_columns"])
    assert employability["algorithm"] == "Linear Regression"
    for feature_set, spec in schema["dropout"]["feature_sets"].items():
        metadata = joblib.load(ML_ROOT / "models" / {
            "full": "dropout_binary_full_exploratory_m27.joblib",
            "after_first_semester": "dropout_binary_after_first_semester_m27.joblib",
            "at_enrollment": "dropout_binary_at_enrollment_m27.joblib",
            "scholarship_only": "dropout_binary_scholarship_baseline_m27.joblib",
        }[feature_set])["metadata"]
        assert spec["columns"] == list(metadata["input_columns"])
        assert spec["suitable_for_student_advice"] is False
        assert metadata["algorithm"] == "Logistic Regression"
        assert "dropout_candidate_v1" not in metadata["model_version"]


def test_health_and_metadata_identify_research_models(client):
    live = client.get("/health/live")
    ready = client.get("/health/ready")
    assert live.status_code == 200
    assert live.json() == {"status": "live"}
    assert "model_version" not in live.text
    assert ready.status_code == 200
    assert ready.json()["status"] == "ready"
    assert ready.json()["auth_configured"] is True
    versions = {row["model_version"] for row in ready.json()["models"]}
    assert "employability_candidate_v1" in versions
    assert any(version.startswith("m27-binary-dropout-full-logistic_regression-") for version in versions)

    missing = client.get("/v1/research/models")
    assert missing.status_code == 401
    assert "predicted_" not in missing.text
    wrong = client.get("/v1/research/models", headers={"Authorization": "Bearer wrong-token"})
    assert wrong.status_code == 401

    metadata = client.get("/v1/research/models", headers=_auth())
    assert metadata.status_code == 200
    body = metadata.json()
    encoded = json.dumps(body)
    assert "coef" not in encoded
    assert "dropout_candidate_v1" not in encoded
    employability = next(row for row in body["models"] if row["id"] == "employability")
    assert employability["algorithm"] == "Linear Regression"
    assert employability["input_columns"] == ["CompetenceMean", "PsyCapMean"]
    assert employability["production_validated"] is False
    assert employability["risk_thresholds"] is None
    assert employability["research_only"] is True
    assert any("not validated substitutes" in line for line in employability["limitations"])
    dropout = [row for row in body["models"] if row["id"] == "dropout"]
    assert {row["feature_set"] for row in dropout} == {
        "full",
        "after_first_semester",
        "at_enrollment",
        "scholarship_only",
    }
    assert all(row["algorithm"] == "Logistic Regression" for row in dropout)
    assert all(row["suitable_for_student_advice"] is False for row in dropout)
    assert all(row["positive_label"] == "Dropout" and row["negative_label"] == "Not Dropout" for row in dropout)
    scholarship = next(row for row in dropout if row["feature_set"] == "scholarship_only")
    assert scholarship["weak_compatibility_baseline"] is True
    assert scholarship["role"] == "weak_compatibility_baseline"


def test_pianda_employability_matches_the_saved_linear_regression(client, fixture):
    before = hashlib.sha256(EMPLOYABILITY_FILE.read_bytes()).hexdigest()
    response = client.post("/v1/research/employability", headers=_auth(), json=fixture["employability"])
    assert response.status_code == 200
    body = response.json()
    pipeline = joblib.load(EMPLOYABILITY_FILE)["pipeline"]
    frame = pd.DataFrame([{
        "CompetenceMean": fixture["employability"]["CompetenceMean"],
        "PsyCapMean": fixture["employability"]["PsyCapMean"],
    }])
    predicted = float(pipeline.predict(frame)[0])
    assert body["predicted_employability_mean"] == pytest.approx(predicted)
    assert body["display_readiness_index"] == pytest.approx(readiness_index(predicted))
    assert body["model_version"] == "employability_candidate_v1"
    assert body["algorithm"] == "Linear Regression"
    assert body["schema_id"] == "pianda-v1"
    assert body["research_only"] is True
    assert body["production_validated"] is False
    assert body["suitable_for_student_advice"] is False
    assert body["student_workflow"] is False
    assert body["risk_level"] is None
    assert body["risk_thresholds"] is None
    assert "probability of employment" in body["readiness_note"]
    assert "not a prediction for a UCC student" in body["disclaimer"]
    assert hashlib.sha256(EMPLOYABILITY_FILE.read_bytes()).hexdigest() == before
    model = client.app.state.registry.employability.pipeline.named_steps["model"]

    def refused_fit(*_args, **_kwargs):
        raise AssertionError("research inference retrained a model")

    model.fit = refused_fit
    again = client.post("/v1/research/employability", headers=_auth(), json=fixture["employability"])
    assert again.status_code == 200


def test_uci_dropout_feature_sets_match_the_saved_logistic_models(client, fixture):
    schema = json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))
    full = fixture["dropout_full"]["features"]
    cases = {
        "full": full,
        "after_first_semester": {column: full[column] for column in schema["dropout"]["feature_sets"]["after_first_semester"]["columns"]},
        "at_enrollment": {column: full[column] for column in schema["dropout"]["feature_sets"]["at_enrollment"]["columns"]},
        "scholarship_only": fixture["dropout_scholarship_0"]["features"],
    }
    for feature_set, features in cases.items():
        response = client.post(
            "/v1/research/dropout",
            headers=_auth(),
            json={"schema_id": "uci-697", "feature_set": feature_set, "features": features},
        )
        assert response.status_code == 200, response.text
        body = response.json()
        loaded = client.app.state.registry.dropout[feature_set]
        frame = pd.DataFrame([{column: features[column] for column in loaded.columns}], columns=loaded.columns)
        probability = float(positive_dropout_probability(loaded.pipeline, frame)[0])
        predicted = int(loaded.pipeline.predict(frame)[0])
        assert body["predicted_dropout_probability"] == pytest.approx(probability)
        assert body["predicted_class"] == predicted
        assert body["predicted_class_name"] in {"Dropout", "Not Dropout"}
        assert body["risk_level"] is None
        assert body["risk_thresholds"] is None
        assert body["research_only"] is True
        assert body["production_validated"] is False
        assert body["suitable_for_student_advice"] is False
        assert body["student_workflow"] is False
        assert "Low, Moderate, or High" in body["decision_note"]
        assert body["weak_compatibility_baseline"] is (feature_set == "scholarship_only")
        assert "Enrolled" not in body["predicted_class_name"]
        assert "Graduate" not in body["predicted_class_name"]


def test_invalid_auth_does_not_score(client, fixture):
    response = client.post("/v1/research/employability", json=fixture["employability"])
    assert response.status_code == 401
    assert response.json()["error"] == "unauthorized"
    assert "predicted_employability_mean" not in response.text
    assert TOKEN not in response.text


def test_invalid_pianda_and_ucc_payloads_are_rejected(client, fixture):
    cases = [
        ({"schema_id": "pianda-v1", "CompetenceMean": 0, "PsyCapMean": 3}, "invalid_input"),
        ({"schema_id": "pianda-v1", "CompetenceMean": "4", "PsyCapMean": 3}, "invalid_input"),
        ({"schema_id": "pianda-v1", "CompetenceMean": 80, "PsyCapMean": 3}, "invalid_input"),
        ({"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3, "notes": "x"}, "invalid_input"),
        ({"schema_id": "ucc-panel-approved-v1", "CompetenceMean": 4, "PsyCapMean": 3}, "ucc_input_rejected"),
        ({"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3, "C1": 4, "gwa": 1.25}, "ucc_input_rejected"),
        ({"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3, "EVS1": 5}, "ucc_input_rejected"),
    ]
    for payload, code in cases:
        response = client.post("/v1/research/employability", headers=_auth(), json=payload)
        assert response.status_code == 422
        assert response.json()["error"] == code
        assert "predicted_employability_mean" not in response.text
        assert "1.25" not in response.text


def test_uci_schema_mismatches_and_incompatible_grades_are_rejected(client, fixture):
    features = dict(fixture["dropout_full"]["features"])
    missing = dict(features)
    missing.pop("GDP")
    extra = dict(features)
    extra["foo"] = 1
    ucc = dict(features)
    ucc["gwa"] = 1.25
    ucc["C1"] = 4
    philippine_admission = dict(features)
    philippine_admission["Admission grade"] = 1.25
    unknown_code = dict(features)
    unknown_code["Application mode"] = 3
    bad_scholarship = {"Scholarship holder": "none"}

    cases = [
        ({"schema_id": "uci-697", "feature_set": "full", "features": missing}, 422, "schema_mismatch"),
        ({"schema_id": "uci-697", "feature_set": "full", "features": extra}, 422, "schema_mismatch"),
        ({"schema_id": "uci-697", "feature_set": "full", "features": ucc}, 422, "ucc_input_rejected"),
        ({"schema_id": "ucc", "feature_set": "full", "features": features}, 422, "ucc_input_rejected"),
        ({"schema_id": "uci-697", "feature_set": "full", "features": philippine_admission}, 422, "out_of_source_range"),
        ({"schema_id": "uci-697", "feature_set": "full", "features": unknown_code}, 422, "unsupported_source_code"),
        ({"schema_id": "uci-697", "feature_set": "scholarship_only", "features": bad_scholarship}, 422, "invalid_input"),
        ({"schema_id": "uci-697", "feature_set": "not_a_model", "features": {"Scholarship holder": 0}}, 422, "invalid_input"),
    ]
    for payload, status, code in cases:
        response = client.post("/v1/research/dropout", headers=_auth(), json=payload)
        assert response.status_code == status
        body = response.json()
        assert body["error"] == code
        assert body["research_only"] is True
        assert "predicted_dropout_probability" not in response.text
        assert "Traceback" not in response.text
        assert "gwa" not in response.text or code == "ucc_input_rejected"


def test_service_errors_do_not_leak_payloads_or_traces(client, caplog):
    caplog.set_level(logging.INFO, logger="edupredict.ml")
    response = client.post("/v1/research/employability", headers=_auth(), content=b"not-json")
    assert response.status_code == 422
    assert "not-json" not in response.text
    assert TOKEN not in caplog.text
    assert "Bearer" not in caplog.text
    unknown = client.get("/v1/research/missing", headers=_auth())
    assert unknown.status_code == 404
    assert unknown.json()["error"] == "not_found"


def test_missing_models_and_empty_token_are_not_ready(tmp_path):
    app = create_app(_settings(models_dir=tmp_path))
    with TestClient(app) as test_client:
        assert test_client.get("/health/live").status_code == 200
        ready = test_client.get("/health/ready")
        assert ready.status_code == 503
        assert ready.json()["status"] == "not_ready"
        assert "employability_unreadable" in ready.json()["unavailable"]
        scored = test_client.post(
            "/v1/research/employability",
            headers=_auth(),
            json={"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3},
        )
        assert scored.status_code == 503
        assert scored.json()["error"] == "models_unavailable"
        assert "Traceback" not in scored.text

    closed = create_app(_settings(token=""))
    with TestClient(closed) as test_client:
        assert test_client.get("/health/ready").status_code == 503
        assert test_client.get("/v1/research/models", headers=_auth()).status_code == 401

    production = create_app(_settings(token="local-dev-ml-token", environment="production"))
    with TestClient(production) as test_client:
        assert test_client.get("/health/ready").json()["auth_configured"] is False
        refused = test_client.post(
            "/v1/research/employability",
            headers={"Authorization": "Bearer local-dev-ml-token"},
            json={"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3},
        )
        assert refused.status_code == 401


def test_inference_timeout_returns_a_safe_error():
    app = create_app(_settings(inference_timeout_seconds=0.05))
    with TestClient(app) as test_client:
        pipeline = app.state.registry.employability.pipeline
        original = pipeline.predict

        def slow(frame):
            time.sleep(0.4)
            return original(frame)

        pipeline.predict = slow
        response = test_client.post(
            "/v1/research/employability",
            headers=_auth(),
            json={"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3.7857},
        )
        assert response.status_code == 504
        assert response.json()["error"] == "inference_timeout"
        assert "3.7857" not in response.text
        time.sleep(0.5)


def test_oversized_body_is_rejected():
    app = create_app(_settings(max_body_bytes=32))
    with TestClient(app) as test_client:
        response = test_client.post(
            "/v1/research/employability",
            headers=_auth(),
            json={"schema_id": "pianda-v1", "CompetenceMean": 4, "PsyCapMean": 3},
        )
        assert response.status_code == 413
        assert response.json()["error"] == "payload_too_large"


@pytest.mark.skipif(
    not (ML_ROOT / "data" / "raw" / "dropout" / "data.csv").is_file(),
    reason="UCI source file is not in this workspace",
)
def test_documented_dropout_holdout_matches_the_pipeline(client):
    from src.data.dropout import TARGET_COLUMN, load_dropout
    from src.training.binary_dropout import m25_dropout_partition

    frame, _quirks = load_dropout()
    partition = m25_dropout_partition(frame)
    row = frame.loc[[partition["test_index"][0]]]
    loaded = client.app.state.registry.dropout["full"]
    features = {column: row.iloc[0][column].item() for column in loaded.columns}
    response = client.post(
        "/v1/research/dropout",
        headers=_auth(),
        json={"schema_id": "uci-697", "feature_set": "full", "features": features},
    )
    assert response.status_code == 200
    body = response.json()
    probability = float(positive_dropout_probability(loaded.pipeline, row.loc[:, loaded.columns])[0])
    assert body["predicted_dropout_probability"] == pytest.approx(probability)
    assert row.iloc[0][TARGET_COLUMN] == "Graduate"
    assert body["predicted_class_name"] == "Not Dropout"


@pytest.mark.skipif(
    not (ML_ROOT / "data" / "raw" / "employability" / "Raw Dataset.xlsx").is_file(),
    reason="Pianda workbook is not in this workspace",
)
def test_documented_pianda_holdout_matches_the_pipeline(client):
    from src.common import RANDOM_SEED, TEST_SIZE
    from src.data.employability import load_employability, verify_construct_means
    from src.data.partitions import employability_train_test_split

    verified = verify_construct_means(load_employability())
    _x_train, x_test, _y_train, _y_test = employability_train_test_split(
        verified["predictors"],
        verified["target"],
        seed=RANDOM_SEED,
        test_size=TEST_SIZE,
    )
    row = x_test.iloc[[0]]
    response = client.post(
        "/v1/research/employability",
        headers=_auth(),
        json={
            "schema_id": "pianda-v1",
            "CompetenceMean": float(row.iloc[0]["CompetenceMean"]),
            "PsyCapMean": float(row.iloc[0]["PsyCapMean"]),
        },
    )
    assert response.status_code == 200
    pipeline = client.app.state.registry.employability.pipeline
    assert response.json()["predicted_employability_mean"] == pytest.approx(float(pipeline.predict(row)[0]))
