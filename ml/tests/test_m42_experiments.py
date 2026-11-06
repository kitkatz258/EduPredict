"""M42 training guards, short reproducibility, and saved-artifact limits."""

from __future__ import annotations

import joblib
import pytest

from src.common import LIKERT_MAX, LIKERT_MIN
from src.data.dropout import load_dropout
from src.data.employability import (
    EMPLOYABILITY_ITEMS,
    V1_PREDICTORS,
    load_employability,
    verify_construct_means,
)
from src.m42.common import M42_ARTIFACTS, M42_REPORTS
from src.m42.compatibility import FIRST_SEM_UNITS
from src.m42.dropout import FEATURE_SETS
from src.m42.employability import CP_COLUMNS, train_regression
from src.questionnaire.v2_scorer import score_v2
from src.training.dropout import _evaluate_feature_set

REQUIRED_REPORTS = (
    "data_audit.md",
    "questionnaire_feature_mapping.md",
    "employability_experiments.md",
    "dropout_experiments.md",
    "model_comparison.md",
    "deployment_feature_compatibility.md",
    "research_limitations.md",
)


def test_employability_training_rejects_target_and_e_items():
    verified = verify_construct_means(load_employability())
    leaked = verified["predictors"].copy()
    leaked["EVS1"] = 3
    with pytest.raises(ValueError, match="leaked"):
        train_regression(leaked, verified["target"], list(leaked.columns), n_repeats=1)
    for item in EMPLOYABILITY_ITEMS:
        assert item not in verified["predictors"].columns


def test_employability_short_cv_is_reproducible_and_beats_nothing_invented():
    verified = verify_construct_means(load_employability())
    first = train_regression(
        verified["predictors"], verified["target"], CP_COLUMNS, n_repeats=1
    )
    second = train_regression(
        verified["predictors"], verified["target"], CP_COLUMNS, n_repeats=1
    )
    assert [row["cv_rmse_mean"] for row in first["rows"]] == [
        row["cv_rmse_mean"] for row in second["rows"]
    ]
    dummy = next(row for row in first["rows"] if row["algorithm"] == "DummyRegressor")
    chosen = next(row for row in first["rows"] if row["algorithm"] == first["decision"]["algorithm"])
    assert chosen["cv_rmse_mean"] < dummy["cv_rmse_mean"]
    sample = verified["predictors"].head(4)
    pred = first["pipeline"].predict(sample)
    assert pred.shape == (4,)
    assert ((pred >= LIKERT_MIN - 1) & (pred <= LIKERT_MAX + 1)).all()


def test_dropout_feature_sets_exclude_grades_and_questionnaire():
    for spec in FEATURE_SETS:
        joined = " ".join(spec["columns"]).lower()
        assert "grade" not in joined
        assert "a1" not in joined
        assert spec["deployment_status"] != "allowed"


def test_dropout_short_cv_preserves_three_classes():
    frame, _quirks = load_dropout()
    first = _evaluate_feature_set(
        frame,
        FIRST_SEM_UNITS,
        seed=42,
        n_splits=2,
        n_repeats=1,
        test_size=0.2,
        collect_details=True,
    )
    second = _evaluate_feature_set(
        frame,
        FIRST_SEM_UNITS,
        seed=42,
        n_splits=2,
        n_repeats=1,
        test_size=0.2,
        collect_details=True,
    )
    assert [row["cv_f1_macro_mean"] for row in first["rows"]] == [
        row["cv_f1_macro_mean"] for row in second["rows"]
    ]
    classes = set(first["pipeline"].named_steps["model"].classes_)
    assert classes == {"Dropout", "Enrolled", "Graduate"}
    holdout = next(
        row["holdout"] for row in first["rows"] if row["algorithm"] == first["decision"]["algorithm"]
    )
    assert set(holdout["per_class"]) == classes


def test_scorer_example_and_reports_exist_after_training():
    answers = {f"C{i}": 4 for i in range(1, 11)}
    answers.update({f"P{i}": 4 for i in range(1, 11)})
    answers.update({f"A{i}": 4 for i in range(1, 11)})
    answers.update({f"S{i}": 4 for i in range(1, 11)})
    answers.update({f"B{i}": 4 for i in range(1, 11)})
    answers.update({f"E{i}": 4 for i in range(1, 11)})
    result = score_v2(answers)
    assert result["aggregate_total"] is None
    assert result["sections"]["competence"]["mean"] == 4.0
    for name in REQUIRED_REPORTS:
        path = M42_REPORTS / name
        if not path.is_file():
            pytest.skip(f"{name} is written by python -m src.run_m42")
        text = path.read_text(encoding="utf-8")
        lowered = text.lower()
        assert "m42" in lowered
        assert any(token in lowered for token in ("blocked", "pending", "not ucc", "not a ucc", "research"))


def test_saved_m42_artifacts_declare_blocked_ucc_inference():
    expected = [
        "employability_cp_reduced_m42.joblib",
        "employability_cp_internship_expanded_m42.joblib",
        "uci_scholarship_only_m42.joblib",
        "uci_first_sem_units_m42.joblib",
        "uci_two_sem_units_m42.joblib",
    ]
    if not M42_ARTIFACTS.is_dir():
        pytest.skip("M42 artifacts are written by python -m src.run_m42")
    present = list(M42_ARTIFACTS.glob("*.joblib"))
    if not present:
        pytest.skip("M42 artifacts are written by python -m src.run_m42")
    for name in expected:
        path = M42_ARTIFACTS / name
        assert path.is_file(), name
        artifact = joblib.load(path)
        metadata = artifact["metadata"]
        assert metadata["production_validated"] is False
        assert metadata["ucc_inference"] == "blocked"
        assert metadata["risk_thresholds"] is None
        assert path.with_suffix(".modelcard.json").is_file()
    verified = verify_construct_means(load_employability())
    emp = joblib.load(M42_ARTIFACTS / "employability_cp_reduced_m42.joblib")
    pred = emp["pipeline"].predict(verified["predictors"].head(3))
    assert pred.shape == (3,)
    extra = verified["predictors"].head(3).copy()
    extra["E1"] = 4
    with pytest.raises(ValueError, match="Extra"):
        emp["pipeline"].predict(extra)
    frame, _quirks = load_dropout()
    dropout = joblib.load(M42_ARTIFACTS / "uci_first_sem_units_m42.joblib")
    labels = dropout["pipeline"].predict(frame.loc[:, list(FIRST_SEM_UNITS)].head(5))
    assert labels.shape == (5,)
    assert set(labels).issubset({"Dropout", "Enrolled", "Graduate"})
