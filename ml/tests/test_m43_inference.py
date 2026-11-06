"""Isolated M42 research inference. Does not score UCC students or change M33."""

from __future__ import annotations

import inspect
import json
import math
from pathlib import Path

import joblib
import pandas as pd
import pytest

from src.common import MODELS_DIR, sha256_file
from src.m42.common import M42_ARTIFACTS
from src.m42.compatibility import FIRST_SEM_UNITS
from src.m43 import __main__ as m43_cli
from src.m43.catalog import ALLOWLIST, EXPECTED_SHA256, MODEL_IDS, ROLE_SOURCE_EXPERIMENT, ROLE_SOURCE_ONLY
from src.m43.errors import ResearchInferenceError
from src.m43.inference import list_models, score
from src.m43.registry import M42ResearchRegistry, load_registry

PIANDA_AUDIT_FIRST8 = [3.640902, 3.819946, 3.666118, 3.948558, 4.07548, 4.668954, 4.43948, 3.459325]
M33_SHA256 = {
    "employability_candidate_v1.joblib": "e8e30ce6ccc5785abc96b6bb896533a1fefcc31cdd4818558908a6d9370fe705",
    "dropout_candidate_v1.joblib": "24a9505b241fa6587a44c2a572f64470aee00f3d6dd2cecd0168fe940250c95f",
    "dropout_binary_full_exploratory_m27.joblib": "cc897a5876ab08b2cdc69a7ed3f624e71ee9c004aea6b5e3d3c7b54adf27d46e",
    "dropout_binary_after_first_semester_m27.joblib": "e09cff1a5829f3eeb992ebaacc985dd5da2daa75239a077dcb169e352fea3545",
    "dropout_binary_at_enrollment_m27.joblib": "b07197532e97634e2b181d38d6a4c31a59d185e828dd3252a3c313765d93f835",
    "dropout_binary_scholarship_baseline_m27.joblib": "1d3b26619864d58f7753dda418e7983899d301a167edf9b9bf6c1b4ceb820dd6",
}

LABELED_PIANDA = {"CompetenceMean": 3.5, "PsyCapMean": 4.0}
LABELED_FIRST_SEM = {
    "Curricular units 1st sem (enrolled)": 6,
    "Curricular units 1st sem (approved)": 5,
}
LABELED_TWO_SEM = {
    **LABELED_FIRST_SEM,
    "Curricular units 2nd sem (enrolled)": 6,
    "Curricular units 2nd sem (approved)": 5,
}
LABELED_SCHOLARSHIP = {"Scholarship holder": 1}
LABELED_INTERNSHIP = {"CompetenceMean": 3.5, "PsyCapMean": 4.0, "InternshipMean": 4.0}


@pytest.fixture(scope="module")
def registry():
    if not (M42_ARTIFACTS / "employability_cp_reduced_m42.joblib").is_file():
        pytest.skip("M42 artifacts are written by python -m src.run_m42")
    return load_registry()


def _pipeline_predict(filename: str, payload: dict):
    artifact = joblib.load(M42_ARTIFACTS / filename)
    frame = pd.DataFrame([payload], columns=list(payload))
    pipeline = artifact["pipeline"]
    predicted = pipeline.predict(frame)[0]
    probabilities = None
    if hasattr(pipeline, "predict_proba"):
        probabilities = pipeline.predict_proba(frame)[0]
    return artifact, predicted, probabilities


def test_allowlist_is_explicit_and_rejects_paths(registry):
    assert set(MODEL_IDS) == set(ALLOWLIST)
    for model_id, spec in ALLOWLIST.items():
        assert spec.filename == f"{model_id}.joblib"
        assert spec.path.parent == M42_ARTIFACTS
        assert ".." not in spec.filename
        assert spec.research_role in {ROLE_SOURCE_EXPERIMENT, ROLE_SOURCE_ONLY}
    with pytest.raises(ResearchInferenceError) as exc:
        score("../models/employability_candidate_v1.joblib", LABELED_PIANDA, registry=registry)
    assert exc.value.code == "unknown_model"
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_candidate_v1", LABELED_PIANDA, registry=registry)
    assert exc.value.code == "unknown_model"
    with pytest.raises(ResearchInferenceError) as exc:
        score(str(M42_ARTIFACTS / "employability_cp_reduced_m42.joblib"), LABELED_PIANDA, registry=registry)
    assert exc.value.code == "unknown_model"


def test_checksums_match_catalog_and_stay_unchanged(registry):
    before = {name: sha256_file(M42_ARTIFACTS / name) for name in EXPECTED_SHA256}
    assert before == EXPECTED_SHA256
    recorded = registry.checksums()
    for model_id, spec in ALLOWLIST.items():
        assert recorded[model_id]["matches"] is True
        assert recorded[model_id]["expected_sha256"] == spec.sha256
        assert recorded[model_id]["actual_sha256"] == before[spec.filename]
    score("employability_cp_reduced_m42", LABELED_PIANDA, registry=registry)
    score("uci_first_sem_units_m42", LABELED_FIRST_SEM, registry=registry)
    after = {name: sha256_file(M42_ARTIFACTS / name) for name in EXPECTED_SHA256}
    assert after == before


def test_checksum_mismatch_is_visible(registry, monkeypatch):
    monkeypatch.setattr("src.m43.registry.sha256_file", lambda _path: "0" * 64)
    with pytest.raises(ResearchInferenceError) as exc:
        registry.get("employability_cp_reduced_m42")
    assert exc.value.code == "artifact_checksum_mismatch"


def test_alternate_artifact_directory_is_rejected():
    with pytest.raises(ResearchInferenceError) as exc:
        load_registry(artifacts_dir=MODELS_DIR)
    assert exc.value.code == "artifact_contract"
    assert "ML_MODELS_DIR" in exc.value.message


def test_metadata_and_absent_optional_fields(registry):
    cp = registry.get("employability_cp_reduced_m42")
    assert cp.metadata["production_validated"] is False
    assert cp.metadata["ucc_inference"] == "blocked"
    assert cp.metadata["risk_thresholds"] is None
    assert cp.spec.research_role == ROLE_SOURCE_EXPERIMENT
    assert "model_version" in cp.absent_optional_fields
    first = registry.get("uci_first_sem_units_m42")
    assert first.classes == ("Dropout", "Enrolled", "Graduate")
    assert "feature_set" in first.absent_optional_fields
    assert "role" in first.absent_optional_fields
    assert "contract_id" in first.absent_optional_fields
    assert first.metadata.get("feature_set") is None
    assert first.metadata.get("role") is None
    two = registry.get("uci_two_sem_units_m42")
    assert two.spec.research_role == ROLE_SOURCE_ONLY
    assert two.spec.prediction_timing == "after_second_semester"
    intern = registry.get("employability_cp_internship_expanded_m42")
    assert intern.spec.research_role == ROLE_SOURCE_ONLY
    assert intern.metadata["comparison_only"] is True
    scholarship = registry.get("uci_scholarship_only_m42")
    assert scholarship.spec.research_role == ROLE_SOURCE_ONLY


def test_pianda_cp_matches_pipeline_and_audit_reload(registry):
    body = score("employability_cp_reduced_m42", LABELED_PIANDA, registry=registry)
    _artifact, expected, _proba = _pipeline_predict("employability_cp_reduced_m42.joblib", LABELED_PIANDA)
    assert body["predicted_employability_mean"] == pytest.approx(float(expected))
    assert body["task"] == "employability_regression"
    assert body["source_schema"] == "pianda-cp-reduced-m42"
    assert body["research_only"] is True
    assert body["ucc_inference"] == "blocked"
    assert body["suitable_for_student_advice"] is False
    assert body["risk_level"] is None
    assert body["display_readiness_index"] == pytest.approx(((float(expected) - 1) / 4) * 100)
    assert "not a probability of employment" in body["readiness_note"].casefold()
    assert "not prove" in body["provenance_note"].casefold()
    again = score("employability_cp_reduced_m42", LABELED_PIANDA, registry=registry)
    assert again["predicted_employability_mean"] == body["predicted_employability_mean"]

    from src.data.employability import load_employability, verify_construct_means

    verified = verify_construct_means(load_employability())
    sample = verified["predictors"].head(8)
    pipeline = registry.get("employability_cp_reduced_m42").pipeline
    predicted = [round(float(value), 6) for value in pipeline.predict(sample)]
    assert predicted == PIANDA_AUDIT_FIRST8


def test_uci_first_sem_class_order_and_probability_alignment(registry):
    body = score("uci_first_sem_units_m42", LABELED_FIRST_SEM, registry=registry)
    artifact, expected, raw_proba = _pipeline_predict("uci_first_sem_units_m42.joblib", LABELED_FIRST_SEM)
    classes = [str(label) for label in artifact["pipeline"].named_steps["model"].classes_]
    assert classes == ["Dropout", "Enrolled", "Graduate"]
    assert body["predicted_class"] == str(expected)
    assert body["class_order"] == classes
    assert body["prediction_timing"] == "after_first_semester"
    assert body["research_role"] == ROLE_SOURCE_EXPERIMENT
    assert body["predicted_class"] not in {"Low", "Moderate", "High"}
    assert body["risk_level"] is None
    aligned = body["source_model_uncalibrated_probabilities"]
    assert list(aligned) == classes
    for index, label in enumerate(classes):
        assert aligned[label] == pytest.approx(float(raw_proba[index]))
    assert sum(aligned.values()) == pytest.approx(1.0)
    again = score("uci_first_sem_units_m42", LABELED_FIRST_SEM, registry=registry)
    assert again == body


def test_source_only_two_sem_and_exploratory_models(registry):
    two = score("uci_two_sem_units_m42", LABELED_TWO_SEM, registry=registry)
    assert two["prediction_timing"] == "after_second_semester"
    assert two["source_only_exploratory"] is True
    assert two["research_role"] == ROLE_SOURCE_ONLY
    intern = score("employability_cp_internship_expanded_m42", LABELED_INTERNSHIP, registry=registry)
    assert intern["source_only_exploratory"] is True
    scholarship = score("uci_scholarship_only_m42", LABELED_SCHOLARSHIP, registry=registry)
    assert scholarship["predicted_class"] in {"Dropout", "Enrolled", "Graduate"}
    assert scholarship["source_only_exploratory"] is True
    with pytest.raises(ResearchInferenceError) as extra:
        score("uci_first_sem_units_m42", LABELED_TWO_SEM, registry=registry)
    assert extra.value.code == "schema_mismatch"


def test_schema_rejection_missing_extra_and_unsupported(registry):
    missing = {"CompetenceMean": 3.5}
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", missing, registry=registry)
    assert exc.value.code == "schema_mismatch"

    extra = dict(LABELED_PIANDA)
    extra["InternshipMean"] = 4.0
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", extra, registry=registry)
    assert exc.value.code == "schema_mismatch"

    leaked = dict(LABELED_PIANDA)
    leaked["EVS1"] = 4
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", leaked, registry=registry)
    assert exc.value.code == "ucc_input_rejected"

    grade = dict(LABELED_FIRST_SEM)
    grade["Curricular units 1st sem (grade)"] = 12.0
    with pytest.raises(ResearchInferenceError) as exc:
        score("uci_first_sem_units_m42", grade, registry=registry)
    assert exc.value.code == "unsupported_schema"

    wrapped = {"schema_id": "pianda-v1", **LABELED_PIANDA}
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", wrapped, registry=registry)
    assert exc.value.code == "schema_mismatch"


def test_ucc_keys_are_refused(registry):
    cases = [
        {**LABELED_PIANDA, "C1": 4},
        {**LABELED_PIANDA, "A1": 4},
        {**LABELED_PIANDA, "E1": 4},
        {**LABELED_PIANDA, "gwa": 1.75},
        {**LABELED_PIANDA, "student_number": "SYN-0001"},
        {**LABELED_PIANDA, "email": "student@ucc.example"},
        {**LABELED_PIANDA, "name": "Sam Student"},
        {**LABELED_PIANDA, "scholarship_status": "full"},
        {**LABELED_PIANDA, "input_origin": "ucc-panel-revised-60-v2"},
        {**LABELED_FIRST_SEM, "major_units": 18},
        {**LABELED_SCHOLARSHIP, "has_scholarship": True},
    ]
    models = [
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "employability_cp_reduced_m42",
        "uci_first_sem_units_m42",
        "uci_scholarship_only_m42",
    ]
    for model_id, payload in zip(models, cases):
        with pytest.raises(ResearchInferenceError) as exc:
            score(model_id, payload, registry=registry)
        assert exc.value.code == "ucc_input_rejected"
        assert "predicted_class" not in exc.value.message
        assert "SYN-0001" not in exc.value.message


def test_numeric_source_keys_are_not_treated_as_proven_origin(registry):
    body = score("employability_cp_reduced_m42", LABELED_PIANDA, registry=registry)
    assert body["input_provenance"] == "unverified"
    assert "does not prove" in body["provenance_note"]


def test_missing_null_nan_inf_and_wrong_types(registry):
    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": None, "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": math.nan, "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": math.inf, "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": True, "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": "3.5", "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("employability_cp_reduced_m42", {"CompetenceMean": 0, "PsyCapMean": 4.0}, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("uci_first_sem_units_m42", {
            "Curricular units 1st sem (enrolled)": 6.5,
            "Curricular units 1st sem (approved)": 5,
        }, registry=registry)
    assert exc.value.code == "invalid_input"

    with pytest.raises(ResearchInferenceError) as exc:
        score("uci_first_sem_units_m42", {
            "Curricular units 1st sem (enrolled)": 27,
            "Curricular units 1st sem (approved)": 5,
        }, registry=registry)
    assert exc.value.code == "out_of_source_range"

    with pytest.raises(ResearchInferenceError) as exc:
        score("uci_scholarship_only_m42", {"Scholarship holder": 2}, registry=registry)
    assert exc.value.code == "invalid_input"


def test_no_predictor_fallback(registry):
    empty = M42ResearchRegistry()
    empty.models = {}
    with pytest.raises(ResearchInferenceError) as exc:
        empty.get("employability_cp_reduced_m42")
    assert exc.value.code == "models_unavailable"
    assert "does not fall back" in exc.value.message
    with pytest.raises(ResearchInferenceError) as exc:
        score("dropout_binary_scholarship_baseline_m27", LABELED_SCHOLARSHIP, registry=registry)
    assert exc.value.code == "unknown_model"


def test_source_native_uci_rows_when_dataset_present(registry):
    from src.data.dropout import load_dropout

    frame, _quirks = load_dropout()
    sample = frame.loc[:, list(FIRST_SEM_UNITS)].head(8)
    pipeline = registry.get("uci_first_sem_units_m42").pipeline
    labels = list(pipeline.predict(sample))
    assert labels == ["Enrolled", "Graduate", "Dropout", "Graduate", "Enrolled", "Graduate", "Graduate", "Dropout"]
    assert set(labels).issubset({"Dropout", "Enrolled", "Graduate"})
    first = score("uci_first_sem_units_m42", sample.iloc[0].to_dict(), registry=registry)
    assert first["predicted_class"] == "Enrolled"


def test_cli_list_and_score(registry, capsys):
    assert m43_cli.main(["list"]) == 0
    listed = json.loads(capsys.readouterr().out)
    assert {row["model_id"] for row in listed} == set(MODEL_IDS)
    assert all(row["ucc_inference"] == "blocked" for row in listed)
    assert m43_cli.main(["checksums"]) == 0
    checksums = json.loads(capsys.readouterr().out)
    assert checksums["uci_first_sem_units_m42"]["matches"] is True
    code = m43_cli.main([
        "score",
        "--model",
        "employability_cp_reduced_m42",
        "--features",
        json.dumps(LABELED_PIANDA),
    ])
    assert code == 0
    body = json.loads(capsys.readouterr().out)
    assert body["artifact_id"] == "employability_cp_reduced_m42"
    failed = m43_cli.main([
        "score",
        "--model",
        "employability_cp_reduced_m42",
        "--features",
        json.dumps({"CompetenceMean": 3.5, "C1": 4}),
    ])
    assert failed == 2
    error = json.loads(capsys.readouterr().out)
    assert error["error"] == "ucc_input_rejected"
    assert "C1" not in json.dumps(error)


def test_m33_contracts_and_artifacts_are_unchanged():
    from api.main import create_app
    from api.registry import DROPOUT_FILES, EMPLOYABILITY_FILE
    from api.settings import ML_ROOT, load_settings

    assert EMPLOYABILITY_FILE == "employability_candidate_v1.joblib"
    assert DROPOUT_FILES == {
        "full": "dropout_binary_full_exploratory_m27.joblib",
        "after_first_semester": "dropout_binary_after_first_semester_m27.joblib",
        "at_enrollment": "dropout_binary_at_enrollment_m27.joblib",
        "scholarship_only": "dropout_binary_scholarship_baseline_m27.joblib",
    }
    settings = load_settings()
    assert settings.models_dir == ML_ROOT / "models"
    source = inspect.getsource(create_app)
    assert "/v1/research/employability" in source
    assert "/v1/research/dropout" in source
    assert "/v1/research/m42" not in source
    for name, digest in M33_SHA256.items():
        path = MODELS_DIR / name
        if not path.is_file():
            pytest.skip(f"M33 artifact missing: {name}")
        assert sha256_file(path) == digest
    m43_root = Path(__file__).resolve().parents[1] / "src" / "m43"
    for path in m43_root.glob("*.py"):
        text = path.read_text(encoding="utf-8")
        assert "FeatureBuilder" not in text
        assert "PREDICTOR_DRIVER" not in text
        assert "migrate" not in text


def test_list_models_does_not_expose_coefficients(registry):
    payload = json.dumps(list_models(registry))
    assert "coef" not in payload
    assert "intercept" not in payload
    assert "estimators_" not in payload
