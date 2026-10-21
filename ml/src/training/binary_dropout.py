"""Binary UCI dropout experiment. Research comparison only; not a UCC model."""

from __future__ import annotations

import hashlib

import numpy as np
import pandas as pd
from sklearn.base import clone
from sklearn.calibration import calibration_curve
from sklearn.metrics import (
    accuracy_score,
    average_precision_score,
    balanced_accuracy_score,
    brier_score_loss,
    classification_report,
    confusion_matrix,
    f1_score,
    make_scorer,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import RepeatedStratifiedKFold, StratifiedKFold, cross_validate

from src.common import F1_SELECTION_MARGIN, RANDOM_SEED, TEST_SIZE
from src.data.dropout import (
    BINARY_TARGET_RULE,
    NOT_DROPOUT_NOTE,
    PREDICTOR_COLUMNS,
    REDUCED_PREDICTORS,
    SCHOLARSHIP_COLUMN,
    TARGET_COLUMN,
    binary_dropout_indicator,
)
from src.data.partitions import dropout_train_test_split
from src.features.ucc import CONTEXT_FIELDS_FORBIDDEN_IN_MODELS
from src.training.dropout import PREFERENCE, candidate_pipelines, timing_columns
from src.training.selection import select_candidate

SOURCE_DATA_DISCLAIMER = "SOURCE-DATA RESEARCH DEMO"
NOT_A_UCC_STUDENT = "This is not a prediction for a UCC student."
DEFAULT_DECISION_NOTE = (
    "The predicted class comes from the estimator's predict method. "
    "For these models that is a 0.5 cut on the positive probability. "
    "It is not a Low, Moderate, or High threshold."
)
SELECTION_METRIC = "cv_f1_dropout_mean"
CV_FIELDS = (
    "algorithm",
    "cv_precision_mean",
    "cv_recall_mean",
    "cv_f1_dropout_mean",
    "cv_balanced_accuracy_mean",
    "cv_roc_auc_mean",
    "cv_pr_auc_mean",
    "cv_brier_mean",
    "cv_accuracy_mean",
    "cv_train_f1_dropout_mean",
    "f1_generalization_gap",
)

TIMING_NOTES = {
    "full": (
        "Uses all 36 UCI predictors, including second-semester curricular units and grades. "
        "Those fields are late academic results. This set is an exploratory reference, "
        "not an enrollment-time model, and it has no verified UCC mapping."
    ),
    "after_first_semester": (
        "Drops the six second-semester curricular columns and keeps first-semester curricular results. "
        "The columns are appropriate only after those first-semester results exist. "
        "Grades stay on the UCI scale. No UCC conversion is applied."
    ),
    "at_enrollment": (
        "Drops all twelve curricular-unit columns. Debtor and tuition fees up to date stay in this cut, "
        "matching the M25 timing definition, but their snapshot date is unverified and they may not be known at enrollment. "
        "Macroeconomic fields and demographic codes remain. They are UCI source fields. No UCC mapping was added."
    ),
    "scholarship_only": (
        "Uses only the UCI Scholarship holder column coded 0 or 1 in the source file. "
        "This is a weak compatibility baseline. It is not a model of the five EduPredict scholarship statuses, "
        "and a missing UCC profile is not recoded to 0."
    ),
}


def _roc_auc(y_true, y_score) -> float:
    """Undefined when the score does not vary. A constant 0.5 from sklearn is not reported."""
    y_score = np.asarray(y_score, dtype=float)
    y_true = np.asarray(y_true)
    if np.unique(y_true).size < 2 or np.unique(np.round(y_score, 8)).size < 2:
        return np.nan
    return float(roc_auc_score(y_true, y_score))


def _scorers() -> dict:
    return {
        "precision": make_scorer(precision_score, pos_label=1, zero_division=0),
        "recall": make_scorer(recall_score, pos_label=1, zero_division=0),
        "f1": make_scorer(f1_score, pos_label=1, zero_division=0),
        "balanced_accuracy": "balanced_accuracy",
        "roc_auc": make_scorer(_roc_auc, response_method="predict_proba"),
        "pr_auc": make_scorer(average_precision_score, response_method="predict_proba", pos_label=1),
        "brier": make_scorer(brier_score_loss, response_method="predict_proba", greater_is_better=False),
        "accuracy": "accuracy",
    }


def _py_float(value) -> float | None:
    if value is None:
        return None
    number = float(value)
    if np.isnan(number):
        return None
    return number


def _mean_std(values) -> tuple[float | None, float | None]:
    array = np.asarray(values, dtype=float)
    if array.size == 0 or np.all(np.isnan(array)):
        return None, None
    mean = float(np.nanmean(array))
    if int(np.sum(~np.isnan(array))) < 2:
        return mean, None
    return mean, float(np.nanstd(array, ddof=1))


def _fingerprint(index) -> str:
    payload = ",".join(str(int(value)) for value in index)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def _label_counts(labels: pd.Series) -> dict[str, int]:
    return {str(key): int(value) for key, value in labels.value_counts().items()}


def _binary_counts(labels: pd.Series) -> dict[str, int]:
    coded = binary_dropout_indicator(labels)
    return {
        "Dropout": int((coded == 1).sum()),
        "Not Dropout": int((coded == 0).sum()),
    }


def feature_specifications() -> list[dict]:
    """M25 timing cuts plus the scholarship baseline. No new UCC column is added."""
    full = timing_columns("full")
    after_first = timing_columns("after_first_semester")
    enrollment = timing_columns("at_enrollment")
    expected = {
        "full": 36,
        "after_first_semester": 30,
        "at_enrollment": 24,
        "scholarship_only": 1,
    }
    specs = [
        {
            "name": "full",
            "role": "exploratory_reference",
            "columns": full,
            "removed_columns": [],
            "timing_note": TIMING_NOTES["full"],
        },
        {
            "name": "after_first_semester",
            "role": "timing_sensitivity",
            "columns": after_first,
            "removed_columns": [column for column in full if column not in after_first],
            "timing_note": TIMING_NOTES["after_first_semester"],
        },
        {
            "name": "at_enrollment",
            "role": "timing_sensitivity",
            "columns": enrollment,
            "removed_columns": [column for column in full if column not in enrollment],
            "timing_note": TIMING_NOTES["at_enrollment"],
        },
        {
            "name": "scholarship_only",
            "role": "weak_compatibility_baseline",
            "columns": REDUCED_PREDICTORS,
            "removed_columns": [],
            "timing_note": TIMING_NOTES["scholarship_only"],
        },
    ]
    for spec in specs:
        if len(spec["columns"]) != expected[spec["name"]]:
            raise ValueError(f"{spec['name']} no longer matches the M25 column count.")
        _reject_unmapped_columns(spec["columns"])
    if "Debtor" not in enrollment or "Tuition fees up to date" not in enrollment:
        raise ValueError("Enrollment cut drifted from the M25 definition.")
    if any(column.startswith("Curricular units") for column in enrollment):
        raise ValueError("Enrollment cut contains curricular units.")
    if any(column.startswith("Curricular units 2nd sem") for column in after_first):
        raise ValueError("After-first-semester cut still contains second-semester columns.")
    if "Curricular units 1st sem (grade)" not in after_first:
        raise ValueError("After-first-semester cut lost first-semester grades.")
    if specs[3]["columns"] != (SCHOLARSHIP_COLUMN,):
        raise ValueError("Scholarship baseline is no longer the single UCI flag.")
    return specs


def _reject_unmapped_columns(columns) -> None:
    unknown = [column for column in columns if column not in PREDICTOR_COLUMNS]
    if unknown:
        raise ValueError(f"Not a UCI predictor: {unknown}.")
    forbidden = [column for column in columns if column in CONTEXT_FIELDS_FORBIDDEN_IN_MODELS]
    if forbidden:
        raise ValueError(f"Questionnaire fields are not dropout predictors: {forbidden}.")


def m25_dropout_partition(frame: pd.DataFrame, *, seed: int = RANDOM_SEED, test_size: float = TEST_SIZE) -> dict:
    """Reuse the M25 row partition by stratifying on the original three-class target."""
    if not frame.index.is_unique:
        raise ValueError("Dropout split requires a unique row index.")
    _x_train, _x_test, y_train, y_test = dropout_train_test_split(
        frame.loc[:, [SCHOLARSHIP_COLUMN]],
        frame[TARGET_COLUMN],
        seed=seed,
        test_size=test_size,
    )
    return {
        "train_index": _x_train.index,
        "test_index": _x_test.index,
        "record": {
            "seed": seed,
            "test_size": test_size,
            "outer_split": (
                "M25 train_test_split, stratify=three-class Target, shuffle=True. "
                "The binary label is applied after the split."
            ),
            "inner_cv": (
                "RepeatedStratifiedKFold on the training partition, stratified on the binary indicator. "
                "Folds do not preserve the Enrolled versus Graduate mix inside Not Dropout."
            ),
            "oof": "One StratifiedKFold pass on the training partition, stratified on the binary indicator.",
            "train_rows": int(len(_x_train)),
            "test_rows": int(len(_x_test)),
            "train_source_counts": _label_counts(y_train),
            "test_source_counts": _label_counts(y_test),
            "train_binary_counts": _binary_counts(y_train),
            "test_binary_counts": _binary_counts(y_test),
            "train_index_sha256": _fingerprint(_x_train.index),
            "test_index_sha256": _fingerprint(_x_test.index),
            "holdout_role": "exploratory_reuse_of_m25_partition",
            "holdout_used_for_selection": False,
            "holdout_used_for_fitting": False,
            "target_rule": BINARY_TARGET_RULE,
            "not_dropout_note": NOT_DROPOUT_NOTE,
        },
    }


def _slice(frame: pd.DataFrame, columns, index) -> tuple[pd.DataFrame, pd.Series, pd.Series]:
    raw = frame.loc[list(index), TARGET_COLUMN]
    predictors = frame.loc[list(index), list(columns)]
    target = binary_dropout_indicator(raw)
    return predictors, target, raw


def positive_dropout_probability(pipeline, frame) -> np.ndarray:
    model = pipeline.named_steps["model"]
    classes = [int(label) for label in model.classes_]
    if classes != [0, 1]:
        raise ValueError(f"Binary dropout model classes must be [0, 1], found {classes}.")
    probabilities = pipeline.predict_proba(frame)
    return np.asarray(probabilities[:, 1], dtype=float)


def _calibration(y_true, probabilities) -> dict:
    try:
        fraction, mean_predicted = calibration_curve(
            y_true,
            probabilities,
            n_bins=8,
            strategy="quantile",
        )
    except ValueError:
        try:
            fraction, mean_predicted = calibration_curve(
                y_true,
                probabilities,
                n_bins=2,
                strategy="uniform",
            )
        except ValueError:
            fraction = np.array([])
            mean_predicted = np.array([])
    return {
        "fraction_positive": [float(value) for value in fraction],
        "mean_predicted": [float(value) for value in mean_predicted],
    }


def _binary_block(y_true, y_pred, probabilities, y_raw=None) -> dict:
    y_true = np.asarray(y_true).astype(int)
    y_pred = np.asarray(y_pred).astype(int)
    probabilities = np.asarray(probabilities, dtype=float)
    matrix = confusion_matrix(y_true, y_pred, labels=[0, 1])
    tn, fp, fn, tp = (int(matrix[0, 0]), int(matrix[0, 1]), int(matrix[1, 0]), int(matrix[1, 1]))
    report = classification_report(
        y_true,
        y_pred,
        labels=[0, 1],
        output_dict=True,
        zero_division=0,
    )
    positive = int((y_true == 1).sum())
    negative = int((y_true == 0).sum())
    roc = _py_float(_roc_auc(y_true, probabilities))
    block = {
        "precision_dropout": float(report["1"]["precision"]),
        "recall_dropout": float(report["1"]["recall"]),
        "f1_dropout": float(report["1"]["f1-score"]),
        "precision_not_dropout": float(report["0"]["precision"]),
        "recall_not_dropout": float(report["0"]["recall"]),
        "f1_not_dropout": float(report["0"]["f1-score"]),
        "f1_macro": float(f1_score(y_true, y_pred, average="macro", labels=[0, 1], zero_division=0)),
        "balanced_accuracy": float(balanced_accuracy_score(y_true, y_pred)),
        "accuracy": float(accuracy_score(y_true, y_pred)),
        "roc_auc": roc,
        "pr_auc": float(average_precision_score(y_true, probabilities)),
        "brier": float(brier_score_loss(y_true, probabilities)),
        "confusion_matrix": [[int(value) for value in row] for row in matrix.tolist()],
        "labels": ["Not Dropout", "Dropout"],
        "true_negative": tn,
        "false_positive": fp,
        "false_negative": fn,
        "true_positive": tp,
        "false_negative_rate": float(fn / positive) if positive else None,
        "false_positive_rate": float(fp / negative) if negative else None,
        "support_dropout": positive,
        "support_not_dropout": negative,
        "calibration_quantile": _calibration(y_true, probabilities),
        "decision_rule": DEFAULT_DECISION_NOTE,
    }
    if y_raw is not None:
        raw = np.asarray(y_raw).astype(str)
        if len(raw) != len(y_true):
            raise ValueError("Original labels are not aligned with the binary target.")
        if not np.all(raw[y_true == 1] == "Dropout"):
            raise ValueError("Binary 1 is not aligned with UCI Dropout.")
        negative_labels = set(np.unique(raw[y_true == 0]).tolist())
        if not negative_labels <= {"Enrolled", "Graduate"}:
            raise ValueError("Binary 0 is not aligned with Enrolled and Graduate.")
        fp_mask = (y_pred == 1) & (y_true == 0)
        counts = {
            label: int((raw[fp_mask] == label).sum()) for label in ("Enrolled", "Graduate")
        }
        if sum(counts.values()) != fp:
            raise ValueError("False-positive source counts do not match the confusion matrix.")
        block["false_positive_source_counts"] = counts
    return block


def _summarize(name: str, selectable: bool, result: dict) -> dict:
    precision_mean, precision_std = _mean_std(result["test_precision"])
    recall_mean, recall_std = _mean_std(result["test_recall"])
    f1_mean, f1_std = _mean_std(result["test_f1"])
    balanced_mean, balanced_std = _mean_std(result["test_balanced_accuracy"])
    roc_mean, roc_std = _mean_std(result["test_roc_auc"])
    pr_mean, pr_std = _mean_std(result["test_pr_auc"])
    brier_mean, brier_std = _mean_std(-np.asarray(result["test_brier"], dtype=float))
    accuracy_mean, accuracy_std = _mean_std(result["test_accuracy"])
    train_f1, _train_std = _mean_std(result["train_f1"])
    gap = None if train_f1 is None or f1_mean is None else train_f1 - f1_mean
    if selectable and f1_mean is None:
        raise ValueError(f"{name} produced no dropout F1.")
    return {
        "algorithm": name,
        "selectable": selectable,
        "cv_precision_mean": precision_mean,
        "cv_precision_std": precision_std,
        "cv_recall_mean": recall_mean,
        "cv_recall_std": recall_std,
        "cv_f1_dropout_mean": f1_mean,
        "cv_f1_dropout_std": f1_std,
        "cv_balanced_accuracy_mean": balanced_mean,
        "cv_balanced_accuracy_std": balanced_std,
        "cv_roc_auc_mean": roc_mean,
        "cv_roc_auc_std": roc_std,
        "cv_pr_auc_mean": pr_mean,
        "cv_pr_auc_std": pr_std,
        "cv_brier_mean": brier_mean,
        "cv_brier_std": brier_std,
        "cv_accuracy_mean": accuracy_mean,
        "cv_accuracy_std": accuracy_std,
        "cv_train_f1_dropout_mean": train_f1,
        "f1_generalization_gap": gap,
        "n_folds": int(len(result["test_f1"])),
    }


def _cv_one(pipeline, name: str, selectable: bool, x_train, y_train, cv) -> dict:
    result = cross_validate(
        clone(pipeline),
        x_train,
        y_train,
        cv=cv,
        scoring=_scorers(),
        n_jobs=1,
        return_train_score=True,
        error_score="raise",
    )
    return _summarize(name, selectable, result)


def _oof(pipeline, x_train, y_train, *, n_splits: int, seed: int):
    y_array = np.asarray(y_train)
    predicted = np.empty(len(y_array), dtype=int)
    probabilities = np.empty(len(y_array), dtype=float)
    splitter = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=seed)
    for train_pos, test_pos in splitter.split(x_train, y_array):
        model = clone(pipeline)
        model.fit(x_train.iloc[train_pos], y_train.iloc[train_pos])
        fold_x = x_train.iloc[test_pos]
        predicted[test_pos] = np.asarray(model.predict(fold_x)).astype(int)
        probabilities[test_pos] = positive_dropout_probability(model, fold_x)
    return predicted, probabilities


def _decide(rows: list[dict]) -> dict:
    decision = select_candidate(
        rows,
        SELECTION_METRIC,
        higher=True,
        margin=F1_SELECTION_MARGIN,
        preference=PREFERENCE,
    )
    decision["best_metric_value"] = float(decision["best_metric_value"])
    decision["accuracy_not_used"] = True
    decision["holdout_not_used"] = True
    return decision


def _best_algorithms(rows: list[dict], metric: str) -> list[str]:
    values = [(row["algorithm"], row[metric]) for row in rows if row.get(metric) is not None]
    if not values:
        return []
    best = max(value for _name, value in values)
    return [name for name, value in values if value == best]


def cv_signature(rows: list[dict]) -> list[tuple]:
    return [tuple(row[field] for field in CV_FIELDS) for row in rows]


def cross_validate_feature_set(
    frame: pd.DataFrame,
    columns,
    train_index,
    *,
    seed: int,
    n_splits: int,
    n_repeats: int,
    echo=None,
    feature_name: str = "",
) -> list[dict]:
    _reject_unmapped_columns(columns)
    log = echo or (lambda *_args, **_kwargs: None)
    x_train, y_train, _raw = _slice(frame, columns, train_index)
    cv = RepeatedStratifiedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed)
    rows = []
    for name, pipeline in candidate_pipelines(columns, seed).items():
        log(f"CV {feature_name} / {name}")
        rows.append(_cv_one(pipeline, name, name != "Majority baseline", x_train, y_train, cv))
    return rows


def cross_validate_algorithm(
    frame: pd.DataFrame,
    columns,
    algorithm: str,
    train_index,
    *,
    seed: int,
    n_splits: int,
    n_repeats: int,
) -> dict:
    x_train, y_train, _raw = _slice(frame, columns, train_index)
    pipeline = candidate_pipelines(columns, seed)[algorithm]
    cv = RepeatedStratifiedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed)
    return _cv_one(pipeline, algorithm, True, x_train, y_train, cv)


def evaluate_feature_set(
    frame: pd.DataFrame,
    spec: dict,
    partition: dict,
    *,
    seed: int,
    n_splits: int,
    n_repeats: int,
    echo=None,
) -> dict:
    columns = tuple(spec["columns"])
    rows = cross_validate_feature_set(
        frame,
        columns,
        partition["train_index"],
        seed=seed,
        n_splits=n_splits,
        n_repeats=n_repeats,
        echo=echo,
        feature_name=spec["name"],
    )
    decision = _decide(rows)
    x_train, y_train, y_train_raw = _slice(frame, columns, partition["train_index"])
    x_test, y_test, y_test_raw = _slice(frame, columns, partition["test_index"])
    fitted = {}
    pipelines = candidate_pipelines(columns, seed)
    for row, (name, pipeline) in zip(rows, pipelines.items(), strict=True):
        if row["algorithm"] != name:
            raise ValueError("Algorithm order drifted from the pipeline list.")
        echo_oof = echo or (lambda *_args, **_kwargs: None)
        echo_oof(f"OOF {spec['name']} / {name}")
        oof_pred, oof_proba = _oof(pipeline, x_train, y_train, n_splits=n_splits, seed=seed)
        row["training_oof"] = _binary_block(y_train, oof_pred, oof_proba, y_train_raw)
        model = clone(pipeline)
        model.fit(x_train, y_train)
        fitted[name] = model
        holdout_proba = positive_dropout_probability(model, x_test)
        row["exploratory_holdout"] = _binary_block(
            y_test,
            model.predict(x_test),
            holdout_proba,
            y_test_raw,
        )
        row["exploratory_holdout"]["role"] = "exploratory_reuse_of_m25_partition"
        row["exploratory_holdout"]["independent_final_validation"] = False
    selected_name = decision["algorithm"]
    return {
        "name": spec["name"],
        "role": spec["role"],
        "timing_note": spec["timing_note"],
        "columns": list(columns),
        "removed_columns": list(spec["removed_columns"]),
        "n_columns": len(columns),
        "rows": rows,
        "decision": decision,
        "accuracy_leaders": _best_algorithms(rows, "cv_accuracy_mean"),
        "balanced_accuracy_leaders": _best_algorithms(rows, "cv_balanced_accuracy_mean"),
        "pr_auc_leaders": _best_algorithms(rows, "cv_pr_auc_mean"),
        "pipeline": fitted[selected_name],
        "training_rows_fit": int(len(x_train)),
    }


def run_binary_experiment(
    frame: pd.DataFrame,
    *,
    seed: int = RANDOM_SEED,
    n_splits: int = 5,
    n_repeats: int = 5,
    test_size: float = TEST_SIZE,
    echo=None,
) -> dict:
    partition = m25_dropout_partition(frame, seed=seed, test_size=test_size)
    partition["record"]["n_splits"] = n_splits
    partition["record"]["n_repeats"] = n_repeats
    feature_sets = []
    for spec in feature_specifications():
        feature_sets.append(
            evaluate_feature_set(
                frame,
                spec,
                partition,
                seed=seed,
                n_splits=n_splits,
                n_repeats=n_repeats,
                echo=echo,
            )
        )
    return {"split": partition["record"], "partition": partition, "feature_sets": feature_sets}


def preprocessor_rows_seen(pipeline) -> int | None:
    if "scale" in pipeline.named_steps and hasattr(pipeline.named_steps["scale"], "n_samples_seen_"):
        return int(pipeline.named_steps["scale"].n_samples_seen_)
    preprocess = pipeline.named_steps.get("preprocess")
    if preprocess is None:
        return None
    numeric = preprocess.named_transformers_["numeric"]
    if hasattr(numeric, "n_samples_seen_"):
        return int(numeric.n_samples_seen_)
    return None
