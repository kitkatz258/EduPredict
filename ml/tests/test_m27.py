"""M27 checks for the binary target, reused split, leakage boundary, and demos."""

import json
from pathlib import Path

import joblib
import pandas as pd
import pytest

from src.common import (
    BINARY_DROPOUT_METRICS,
    BINARY_DROPOUT_MODELS,
    BINARY_DROPOUT_REPORT,
    DROPOUT_CANDIDATE,
    EMPLOYABILITY_CANDIDATE,
    FIGURES_DIR,
    M27_SUMMARY,
    RANDOM_SEED,
    TEST_SIZE,
)
from src.data.dropout import (
    BINARY_TARGET_RULE,
    NOT_DROPOUT_NOTE,
    REDUCED_PREDICTORS,
    binary_dropout_indicator,
    load_dropout,
)
from src.data.employability import V1_PREDICTORS, load_employability, verify_construct_means
from src.data.partitions import dropout_train_test_split, employability_train_test_split
from src.demo_m27 import (
    EMPLOYABILITY_DEMO_NOTE,
    EMPLOYABILITY_READINESS_NOTE,
    PROBABILITY_NOTE,
    main,
)
from src.evaluation.binary_report import M27_PROTOCOL_TEXT
from src.features.catalog import dropout_manifest
from src.features.ucc import CONTEXT_FIELDS_FORBIDDEN_IN_MODELS, source_to_ucc_mapping
from src.training.binary_dropout import (
    DEFAULT_DECISION_NOTE,
    SELECTION_METRIC,
    cross_validate_feature_set,
    cv_signature,
    evaluate_feature_set,
    feature_specifications,
    m25_dropout_partition,
    positive_dropout_probability,
)
from src.training.dropout import PREFERENCE
from src.training.selection import select_candidate

ML_ROOT = Path(__file__).resolve().parents[1]


def test_binary_indicator_groups_enrolled_and_graduate_without_calling_it_retention():
    frame, _quirks = load_dropout()
    coded = binary_dropout_indicator(frame["Target"])
    assert BINARY_TARGET_RULE.startswith("Dropout is coded 1.")
    assert "not proof of future retention" in NOT_DROPOUT_NOTE
    assert int((coded == 1).sum()) == int((frame["Target"] == "Dropout").sum())
    assert int((coded == 0).sum()) == int(frame["Target"].isin(["Enrolled", "Graduate"]).sum())
    assert int((coded == 1).sum()) == 1421
    with pytest.raises(ValueError, match="Unexpected target labels"):
        binary_dropout_indicator(pd.Series(["Dropout", "Retained"]))


def test_m25_holdout_partition_is_reused():
    frame, _quirks = load_dropout()
    partition = m25_dropout_partition(frame)
    _x_train, x_test, _y_train, y_test = dropout_train_test_split(
        frame,
        frame["Target"],
        seed=RANDOM_SEED,
        test_size=TEST_SIZE,
    )
    assert list(x_test.index) == list(partition["test_index"])
    assert list(y_test.index) == list(frame.loc[partition["test_index"], "Target"].index)
    counts = y_test.value_counts().to_dict()
    assert counts == {"Graduate": 442, "Dropout": 284, "Enrolled": 159}
    assert partition["record"]["train_rows"] == 3539
    assert partition["record"]["test_rows"] == 885
    assert partition["record"]["holdout_used_for_selection"] is False
    assert partition["record"]["holdout_role"] == "exploratory_reuse_of_m25_partition"
    scholarship = dropout_train_test_split(
        frame.loc[:, list(REDUCED_PREDICTORS)],
        frame["Target"],
        seed=RANDOM_SEED,
        test_size=TEST_SIZE,
    )
    assert list(scholarship[1].index) == list(x_test.index)


def test_binary_selection_uses_dropout_f1_not_accuracy_or_holdout():
    rows = [
        {
            "algorithm": "Logistic Regression",
            "selectable": True,
            "cv_f1_dropout_mean": 0.50,
            "cv_accuracy_mean": 0.90,
        },
        {
            "algorithm": "Random Forest Classifier",
            "selectable": True,
            "cv_f1_dropout_mean": 0.70,
            "cv_accuracy_mean": 0.40,
        },
        {
            "algorithm": "Gradient Boosting Classifier",
            "selectable": True,
            "cv_f1_dropout_mean": 0.69,
            "cv_accuracy_mean": 0.99,
        },
        {
            "algorithm": "Majority baseline",
            "selectable": False,
            "cv_f1_dropout_mean": 0.0,
            "cv_accuracy_mean": 0.95,
        },
    ]
    decision = select_candidate(
        rows,
        SELECTION_METRIC,
        higher=True,
        margin=0.01,
        preference=PREFERENCE,
    )
    assert decision["algorithm"] == "Random Forest Classifier"
    assert decision["metric"] == "cv_f1_dropout_mean"
    assert "holdout" not in decision


def test_feature_sets_keep_m25_timing_and_do_not_promote_unmapped_columns():
    specs = {spec["name"]: spec for spec in feature_specifications()}
    decisions = {row["name"]: row["decision"] for row in dropout_manifest()}
    assert [len(specs[name]["columns"]) for name in ("full", "after_first_semester", "at_enrollment", "scholarship_only")] == [
        36,
        30,
        24,
        1,
    ]
    for spec in specs.values():
        assert set(spec["columns"]).isdisjoint(CONTEXT_FIELDS_FORBIDDEN_IN_MODELS)
        promoted = [
            column
            for column in spec["columns"]
            if decisions[column] == "KEEP" and column != "Scholarship holder"
        ]
        assert promoted == []
    assert "Debtor" in specs["at_enrollment"]["columns"]
    assert "Curricular units 1st sem (grade)" not in specs["at_enrollment"]["columns"]
    assert "Curricular units 2nd sem (grade)" not in specs["after_first_semester"]["columns"]
    frame, _quirks = load_dropout()
    with pytest.raises(ValueError, match="Not a UCI predictor"):
        cross_validate_feature_set(
            frame,
            ("E1",),
            frame.index[:10],
            seed=42,
            n_splits=2,
            n_repeats=1,
        )
    for row in source_to_ucc_mapping():
        assert row["status"] == "UNMATCHED"


def test_short_scholarship_cv_selects_on_dropout_f1_and_records_errors():
    frame, _quirks = load_dropout()
    partition = m25_dropout_partition(frame)
    spec = next(item for item in feature_specifications() if item["name"] == "scholarship_only")
    first = evaluate_feature_set(frame, spec, partition, seed=42, n_splits=2, n_repeats=1)
    second = cross_validate_feature_set(
        frame,
        spec["columns"],
        partition["train_index"],
        seed=42,
        n_splits=2,
        n_repeats=1,
    )
    assert cv_signature(first["rows"]) == cv_signature(second)
    assert first["decision"]["metric"] == SELECTION_METRIC
    assert first["decision"]["holdout_not_used"] is True
    assert first["decision"]["accuracy_not_used"] is True
    assert list(first["pipeline"].named_steps["model"].classes_) == [0, 1]
    for row in first["rows"]:
        assert 0.0 <= row["cv_brier_mean"] <= 1.0
        assert 0.0 <= row["cv_f1_dropout_mean"] <= 1.0
        if row["cv_roc_auc_mean"] is not None:
            assert 0.0 <= row["cv_roc_auc_mean"] <= 1.0
        for block_name in ("training_oof", "exploratory_holdout"):
            block = row[block_name]
            assert block["false_negative"] + block["true_positive"] == block["support_dropout"]
            assert sum(block["false_positive_source_counts"].values()) == block["false_positive"]
        assert row["exploratory_holdout"]["independent_final_validation"] is False
    sample = frame.loc[:, list(REDUCED_PREDICTORS)].head(4)
    assert first["pipeline"].predict_proba(sample).shape == (4, 2)
    leaked = sample.copy()
    leaked["E1"] = 1
    with pytest.raises(ValueError, match="Extra"):
        first["pipeline"].predict(leaked)


def test_protocol_text_states_the_research_limits():
    assert "not proof of future retention" in M27_PROTOCOL_TEXT
    assert "not an independent final validation" in M27_PROTOCOL_TEXT
    assert "not the selection metric" in M27_PROTOCOL_TEXT
    assert "No Low, Moderate, or High threshold is defined." in M27_PROTOCOL_TEXT
    assert "SOURCE-DATA RESEARCH DEMO" in M27_PROTOCOL_TEXT


def test_employability_demo_uses_pianda_features_only(capsys):
    code = main(["employability", "--offset", "0"])
    text = capsys.readouterr().out
    assert code == 0
    assert "SOURCE-DATA RESEARCH DEMO" in text
    assert "This is not a prediction for a UCC student." in text
    assert EMPLOYABILITY_DEMO_NOTE in text
    assert EMPLOYABILITY_READINESS_NOTE in text
    assert "CompetenceMean:" in text
    assert "PsyCapMean:" in text
    assert "Gender" not in text
    verified = verify_construct_means(load_employability())
    _x_train, x_test, _y_train, y_test = employability_train_test_split(
        verified["predictors"],
        verified["target"],
        seed=RANDOM_SEED,
        test_size=TEST_SIZE,
    )
    artifact = joblib.load(EMPLOYABILITY_CANDIDATE)
    row = x_test.iloc[[0]]
    predicted = float(artifact["pipeline"].predict(row)[0])
    assert f"{predicted:.4f}" in text
    assert f"{float(y_test.iloc[0]):.4f}" in text
    assert list(row.columns) == list(V1_PREDICTORS)
    with pytest.raises(SystemExit):
        main(["employability", "--offset", "-1"])


def test_saved_binary_artifacts_declare_research_limits():
    m25_dropout = joblib.load(DROPOUT_CANDIDATE)
    assert set(m25_dropout["pipeline"].named_steps["model"].classes_) == {
        "Dropout",
        "Enrolled",
        "Graduate",
    }
    employability = joblib.load(EMPLOYABILITY_CANDIDATE)
    assert employability["metadata"]["algorithm"] == "Linear Regression"
    frame, _quirks = load_dropout()
    for name, path in BINARY_DROPOUT_MODELS.items():
        artifact = joblib.load(path)
        metadata = artifact["metadata"]
        assert metadata["production_validated"] is False
        assert metadata["deployment_candidate"] is False
        assert metadata["risk_thresholds"] is None
        assert metadata["selection_metric"] == SELECTION_METRIC
        assert metadata["target_definition"]["positive_class"] == 1
        assert "not proof of future retention" in metadata["target_definition"]["negative_meaning"]
        assert metadata["holdout_role"] == "exploratory_reuse_of_m25_partition"
        assert set(metadata["input_columns"]).isdisjoint(CONTEXT_FIELDS_FORBIDDEN_IN_MODELS)
        assert list(artifact["pipeline"].named_steps["model"].classes_) == [0, 1]
        if metadata["preprocessor_rows_seen"] is not None:
            assert metadata["preprocessor_rows_seen"] == metadata["training_rows_fit"]
            assert metadata["preprocessor_rows_seen"] != len(frame)
        assert name == metadata["feature_set"]
    metrics = json.loads(BINARY_DROPOUT_METRICS.read_text(encoding="utf-8"))
    assert metrics["selection_metric"] == SELECTION_METRIC
    assert metrics["holdout_role"] == "exploratory_reuse_of_m25_partition"
    assert metrics["production_validated"] is False
    report = BINARY_DROPOUT_REPORT.read_text(encoding="utf-8")
    summary = M27_SUMMARY.read_text(encoding="utf-8")
    for text in (report, summary):
        assert "not proof of future retention" in text
        assert "not an independent final validation" in text or "exploratory reuse" in text
    assert "No Low, Moderate, or High threshold is defined." in report
    for figure in (
        "binary_dropout_f1.png",
        "binary_dropout_full_oof_confusion.png",
        "binary_dropout_full_oof_calibration.png",
        "binary_dropout_after_first_semester_oof_confusion.png",
        "binary_dropout_at_enrollment_oof_confusion.png",
        "binary_dropout_scholarship_only_oof_confusion.png",
        "binary_dropout_scholarship_only_oof_calibration.png",
    ):
        assert (FIGURES_DIR / figure).is_file(), figure


def test_dropout_demo_scores_a_held_out_uci_row(capsys):
    code = main(["dropout", "--offset", "0", "--feature-set", "full"])
    text = capsys.readouterr().out
    assert code == 0
    assert "SOURCE-DATA RESEARCH DEMO" in text
    assert "This is not a prediction for a UCC student." in text
    assert "not proof of future retention" in text
    assert PROBABILITY_NOTE in text
    assert "second-semester" in text
    assert DEFAULT_DECISION_NOTE in text
    assert "production_validated: false" in text
    frame, _quirks = load_dropout()
    partition = m25_dropout_partition(frame)
    artifact = joblib.load(BINARY_DROPOUT_MODELS["full"])
    row = frame.loc[[partition["test_index"][0]], artifact["metadata"]["input_columns"]]
    probability = float(positive_dropout_probability(artifact["pipeline"], row)[0])
    observed = frame.loc[partition["test_index"][0], "Target"]
    assert f"{probability:.6f}" in text
    assert f"observed_uci_target: {observed}" in text
    assert f"source_row_index: {int(partition['test_index'][0])}" in text
    scholarship = main(["dropout", "--offset", "0", "--feature-set", "scholarship_only"])
    scholarship_text = capsys.readouterr().out
    assert scholarship == 0
    assert "Scholarship holder:" in scholarship_text
    assert "not a model of the five EduPredict scholarship statuses" in scholarship_text
    assert "This is not a prediction for a UCC student." in scholarship_text
    with pytest.raises(SystemExit) as caught:
        main(["dropout", "--offset", "10000"])
    assert caught.value.code == 2
    assert "held-out" in capsys.readouterr().err
