"""M25 checks for scoring, leakage, schema, reload, and reproducibility."""

from pathlib import Path

import joblib
import pandas as pd
import pytest

from src.common import (
    DROPOUT_CANDIDATE,
    EMPLOYABILITY_CANDIDATE,
    LIKERT_MAX,
    LIKERT_MIN,
    readiness_index,
)
from src.data.archive import audit_employability_archive
from src.data.dropout import REDUCED_PREDICTORS, load_dropout
from src.data.employability import (
    EMPLOYABILITY_ITEMS,
    V1_PREDICTORS,
    load_employability,
    verify_construct_means,
)
from src.features.catalog import dropout_manifest, employability_manifest
from src.features.ucc import CONTEXT_FIELDS_FORBIDDEN_IN_MODELS, approved_pdf_status
from src.training.dropout import _evaluate_feature_set, candidate_pipelines
from src.training.employability import train_employability

ML_ROOT = Path(__file__).resolve().parents[1]


def test_employability_means_match_codebook_and_exclude_target_items():
    verified = verify_construct_means(load_employability())
    assert verified["rows"] == 266
    assert verified["missing_cells"] == 0
    assert verified["range_allows_readiness_index"] is True
    assert list(verified["predictors"].columns) == list(V1_PREDICTORS)
    for item in EMPLOYABILITY_ITEMS:
        assert item not in verified["predictors"].columns
    assert float(verified["target"].min()) >= LIKERT_MIN
    assert float(verified["target"].max()) <= LIKERT_MAX


def test_readiness_index_is_a_rescale_not_a_threshold():
    assert readiness_index(1) == 0
    assert readiness_index(3) == 50
    assert readiness_index(5) == 100
    with pytest.raises(ValueError):
        readiness_index(0)
    with pytest.raises(ValueError):
        readiness_index(5.1)


def test_employability_schema_rejects_target_and_context_columns():
    verified = verify_construct_means(load_employability())
    leaked = verified["predictors"].copy()
    leaked["EVS1"] = 3
    with pytest.raises(ValueError, match="CompetenceMean and PsyCapMean only"):
        train_employability(leaked, verified["target"], n_repeats=1)
    context = verified["predictors"].copy()
    context["E1"] = 3
    with pytest.raises(ValueError, match="CompetenceMean and PsyCapMean only"):
        train_employability(context, verified["target"], n_repeats=1)


def test_invalid_likert_mean_is_rejected():
    verified = verify_construct_means(load_employability())
    broken = verified["predictors"].copy()
    broken.loc[broken.index[0], "CompetenceMean"] = 6
    with pytest.raises(ValueError):
        train_employability(broken, verified["target"], n_repeats=1)


def test_employability_training_is_reproducible_on_a_short_cv():
    verified = verify_construct_means(load_employability())
    first = train_employability(verified["predictors"], verified["target"], n_repeats=1)
    second = train_employability(verified["predictors"], verified["target"], n_repeats=1)
    assert [row["cv_rmse_mean"] for row in first["rows"]] == [row["cv_rmse_mean"] for row in second["rows"]]
    assert first["decision"]["algorithm"] == second["decision"]["algorithm"]
    sample = verified["predictors"].head(5)
    predictions = first["pipeline"].predict(sample)
    assert predictions.shape == (5,)
    assert first["pipeline"].predict(sample).tolist() == second["pipeline"].predict(sample).tolist()
    leaked = sample.copy()
    leaked["EVS1"] = 3
    with pytest.raises(ValueError, match="Extra"):
        first["pipeline"].predict(leaked)


def test_dropout_file_contract_and_reduced_schema():
    frame, quirks = load_dropout()
    assert len(frame) == 4424
    assert set(frame["Target"].unique()) == {"Dropout", "Enrolled", "Graduate"}
    assert quirks["utf8_bom_on_first_column"] is True
    kept = [row["name"] for row in dropout_manifest() if row["decision"] == "KEEP"]
    assert kept == list(REDUCED_PREDICTORS)
    assert "Curricular units 1st sem (grade)" not in kept
    assert set(kept).isdisjoint(CONTEXT_FIELDS_FORBIDDEN_IN_MODELS)
    pipe = candidate_pipelines(REDUCED_PREDICTORS, 42)["Logistic Regression"]
    predictors = frame.loc[:, list(REDUCED_PREDICTORS)].head(40)
    pipe.fit(predictors, frame["Target"].head(40))
    extra = predictors.copy()
    extra["E1"] = 1
    with pytest.raises(ValueError, match="Extra"):
        pipe.predict(extra)
    leaked = predictors.copy()
    leaked["Target"] = frame["Target"].head(40).to_numpy()
    with pytest.raises(ValueError, match="Extra"):
        pipe.predict(leaked)


def test_dropout_reduced_training_shapes_and_reproducibility():
    frame, _quirks = load_dropout()
    first = _evaluate_feature_set(
        frame,
        REDUCED_PREDICTORS,
        seed=42,
        n_splits=2,
        n_repeats=1,
        test_size=0.2,
        collect_details=True,
    )
    second = _evaluate_feature_set(
        frame,
        REDUCED_PREDICTORS,
        seed=42,
        n_splits=2,
        n_repeats=1,
        test_size=0.2,
        collect_details=True,
    )
    assert [row["cv_f1_macro_mean"] for row in first["rows"]] == [
        row["cv_f1_macro_mean"] for row in second["rows"]
    ]
    sample = frame.loc[:, list(REDUCED_PREDICTORS)].head(6)
    labels = first["pipeline"].predict(sample)
    probabilities = first["pipeline"].predict_proba(sample)
    assert labels.shape == (6,)
    assert probabilities.shape == (6, 3)
    assert set(first["pipeline"].named_steps["model"].classes_) == {"Dropout", "Enrolled", "Graduate"}
    invalid = sample.copy()
    invalid.iloc[0, 0] = 2
    with pytest.raises(ValueError):
        first["pipeline"].predict(invalid)
    missing = sample.copy()
    missing.iloc[0, 0] = pd.NA
    with pytest.raises(ValueError):
        first["pipeline"].predict(missing)


def test_saved_artifacts_reload_and_declare_limits():
    employability = joblib.load(EMPLOYABILITY_CANDIDATE)
    dropout = joblib.load(DROPOUT_CANDIDATE)
    for artifact, columns in (
        (employability, list(V1_PREDICTORS)),
        (dropout, list(REDUCED_PREDICTORS)),
    ):
        assert artifact["metadata"]["production_validated"] is False
        assert artifact["metadata"]["risk_thresholds"] is None
        assert artifact["metadata"]["input_columns"] == columns
        assert "Not production-validated." in " ".join(artifact["metadata"]["limitations"])
    verified = verify_construct_means(load_employability())
    sample = verified["predictors"].head(4)
    again = joblib.load(EMPLOYABILITY_CANDIDATE)
    assert employability["pipeline"].predict(sample).tolist() == again["pipeline"].predict(sample).tolist()
    frame, _quirks = load_dropout()
    dropout_sample = frame.loc[:, list(REDUCED_PREDICTORS)].head(4)
    assert dropout["pipeline"].predict(dropout_sample).shape == (4,)


def test_manifests_keep_context_out_of_predictors():
    employability_kept = {row["name"] for row in employability_manifest() if row["decision"] == "KEEP"}
    assert employability_kept == set(V1_PREDICTORS)
    forbidden = set(CONTEXT_FIELDS_FORBIDDEN_IN_MODELS)
    assert forbidden.isdisjoint(employability_kept)
    dropout_kept = {row["name"] for row in dropout_manifest() if row["decision"] == "KEEP"}
    assert dropout_kept.isdisjoint(forbidden)
    status = approved_pdf_status()
    assert status["directory_exists"] is True
    assert status["item_mapping_blocked"] is True


def test_archive_questionnaire_is_duplicate_and_workbook_is_not_merged():
    audit = audit_employability_archive()
    assert audit["combined_into_training"] is False
    by_loose = {
        entry["loose_comparison"]["loose_name"]: entry["loose_comparison"]
        for entry in audit["files"]
        if entry.get("loose_comparison")
    }
    assert by_loose["Survey questionnaire.docx"]["same_crc32"] is True
    assert by_loose["Raw Dataset.xlsx"]["same_size"] is True
    assert by_loose["Raw Dataset.xlsx"]["same_crc32"] is False


def test_report_files_exist():
    reports = ML_ROOT / "reports"
    expected = [
        "employability_data_audit.md",
        "dropout_data_audit.md",
        "employability_feature_manifest.md",
        "dropout_feature_manifest.md",
        "employability_model_comparison.md",
        "dropout_model_comparison.md",
        "training_summary.md",
        "metrics.json",
    ]
    for name in expected:
        assert (reports / name).is_file(), name
