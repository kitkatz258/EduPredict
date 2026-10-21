"""Three-class dropout experiments: full exploratory reference and reduced candidate."""

from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.base import clone
from sklearn.calibration import calibration_curve
from sklearn.compose import ColumnTransformer
from sklearn.dummy import DummyClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    brier_score_loss,
    classification_report,
    confusion_matrix,
    f1_score,
    roc_auc_score,
)
from sklearn.model_selection import (
    RepeatedStratifiedKFold,
    StratifiedKFold,
    cross_val_predict,
    cross_validate,
    train_test_split,
)
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

from src.common import (
    F1_SELECTION_MARGIN,
    N_REPEATS,
    N_SPLITS,
    RANDOM_SEED,
    TARGET_LABELS,
    TEST_SIZE,
)
from src.data.dropout import (
    FIRST_SEMESTER_COLUMNS,
    PREDICTOR_COLUMNS,
    REDUCED_PREDICTORS,
    SECOND_SEMESTER_COLUMNS,
    TARGET_COLUMN,
)
from src.features.catalog import (
    BINARY_DROPOUT_COLUMNS,
    NOMINAL_DROPOUT_COLUMNS,
    NUMERIC_DROPOUT_COLUMNS,
)
from src.features.schemas import FrameSchema
from src.training.estimators import BalancedGradientBoostingClassifier
from src.training.selection import select_candidate

PREFERENCE = [
    "Logistic Regression",
    "Random Forest Classifier",
    "Gradient Boosting Classifier",
]
LABELS = list(TARGET_LABELS)


def _preprocessor(columns: tuple[str, ...], scale_numeric: bool):
    nominal = [column for column in NOMINAL_DROPOUT_COLUMNS if column in columns]
    numeric = [column for column in (*NUMERIC_DROPOUT_COLUMNS, *BINARY_DROPOUT_COLUMNS) if column in columns]
    numeric_step = StandardScaler() if scale_numeric else "passthrough"
    return ColumnTransformer(
        transformers=[
            (
                "nominal",
                OneHotEncoder(handle_unknown="ignore", sparse_output=False, dtype=np.float64),
                nominal,
            ),
            ("numeric", numeric_step, numeric),
        ],
        remainder="drop",
    )


def _pipeline(columns: tuple[str, ...], estimator, *, scale_numeric: bool, value_rule: str) -> Pipeline:
    steps = [("schema", FrameSchema(columns, value_rule=value_rule))]
    if len(columns) > 1 or value_rule != "binary01":
        steps.append(("preprocess", _preprocessor(columns, scale_numeric)))
    elif scale_numeric:
        steps.append(("scale", StandardScaler()))
    steps.append(("model", estimator))
    return Pipeline(steps)


def _estimators(seed: int) -> dict:
    return {
        "Logistic Regression": LogisticRegression(
            C=1.0,
            solver="lbfgs",
            max_iter=2000,
            class_weight="balanced",
            random_state=seed,
        ),
        "Random Forest Classifier": RandomForestClassifier(
            random_state=seed,
            n_jobs=1,
            class_weight="balanced",
        ),
        "Gradient Boosting Classifier": BalancedGradientBoostingClassifier(random_state=seed),
    }


def candidate_pipelines(columns: tuple[str, ...], seed: int = RANDOM_SEED) -> dict[str, Pipeline]:
    value_rule = "binary01" if columns == REDUCED_PREDICTORS else "any"
    pipelines = {}
    for name, estimator in _estimators(seed).items():
        scale = name == "Logistic Regression"
        pipelines[name] = _pipeline(columns, estimator, scale_numeric=scale, value_rule=value_rule)
    pipelines["Majority baseline"] = _pipeline(
        columns,
        DummyClassifier(strategy="most_frequent"),
        scale_numeric=False,
        value_rule=value_rule,
    )
    return pipelines


def _positive_dropout(probabilities: np.ndarray, classes: list[str]) -> np.ndarray:
    index = list(classes).index("Dropout")
    return probabilities[:, index]


def _classification_block(y_true, y_pred, y_proba, classes) -> dict:
    matrix = confusion_matrix(y_true, y_pred, labels=LABELS)
    report = classification_report(
        y_true,
        y_pred,
        labels=LABELS,
        output_dict=True,
        zero_division=0,
    )
    dropout_index = LABELS.index("Dropout")
    dropout_support = int(matrix[dropout_index].sum())
    false_negatives = int(dropout_support - matrix[dropout_index, dropout_index])
    dropout_probability = _positive_dropout(y_proba, classes)
    y_dropout = (np.asarray(y_true) == "Dropout").astype(int)
    try:
        auc = float(roc_auc_score(y_dropout, dropout_probability))
    except ValueError:
        auc = None
    try:
        auc_ovr = float(roc_auc_score(y_true, y_proba, multi_class="ovr", average="macro", labels=classes))
    except ValueError:
        auc_ovr = None
    try:
        fraction_positive, mean_predicted = calibration_curve(
            y_dropout,
            dropout_probability,
            n_bins=8,
            strategy="quantile",
        )
    except ValueError:
        try:
            fraction_positive, mean_predicted = calibration_curve(
                y_dropout,
                dropout_probability,
                n_bins=2,
                strategy="uniform",
            )
        except ValueError:
            fraction_positive = np.array([])
            mean_predicted = np.array([])
    return {
        "accuracy": float(accuracy_score(y_true, y_pred)),
        "f1_macro": float(f1_score(y_true, y_pred, average="macro", labels=LABELS, zero_division=0)),
        "f1_weighted": float(f1_score(y_true, y_pred, average="weighted", labels=LABELS, zero_division=0)),
        "per_class": {
            label: {
                "precision": float(report[label]["precision"]),
                "recall": float(report[label]["recall"]),
                "f1": float(report[label]["f1-score"]),
                "support": int(report[label]["support"]),
            }
            for label in LABELS
        },
        "confusion_matrix": matrix.tolist(),
        "labels": LABELS,
        "dropout_false_negatives": false_negatives,
        "dropout_false_negative_rate": float(false_negatives / dropout_support) if dropout_support else None,
        "roc_auc_dropout_vs_rest": auc,
        "roc_auc_ovr_macro": auc_ovr,
        "brier_dropout": float(brier_score_loss(y_dropout, dropout_probability)),
        "calibration_quantile": {
            "fraction_positive": fraction_positive.astype(float).tolist(),
            "mean_predicted": mean_predicted.astype(float).tolist(),
        },
    }


def _cv_summary(name: str, result: dict, selectable: bool) -> dict:
    return {
        "algorithm": name,
        "selectable": selectable,
        "cv_f1_macro_mean": float(result["test_f1_macro"].mean()),
        "cv_f1_macro_std": float(result["test_f1_macro"].std(ddof=1)),
        "cv_f1_weighted_mean": float(result["test_f1_weighted"].mean()),
        "cv_f1_weighted_std": float(result["test_f1_weighted"].std(ddof=1)),
        "cv_accuracy_mean": float(result["test_accuracy"].mean()),
        "cv_accuracy_std": float(result["test_accuracy"].std(ddof=1)),
        "cv_roc_auc_ovr_mean": float(result["test_roc_auc_ovr"].mean()),
        "cv_roc_auc_ovr_std": float(result["test_roc_auc_ovr"].std(ddof=1)),
        "cv_train_f1_macro_mean": float(result["train_f1_macro"].mean()),
        "f1_generalization_gap": float(result["train_f1_macro"].mean() - result["test_f1_macro"].mean()),
        "n_folds": int(len(result["test_f1_macro"])),
    }


def _evaluate_feature_set(
    frame: pd.DataFrame,
    columns: tuple[str, ...],
    *,
    seed: int,
    n_splits: int,
    n_repeats: int,
    test_size: float,
    collect_details: bool,
) -> dict:
    predictors = frame.loc[:, list(columns)]
    target = frame[TARGET_COLUMN]
    x_train, x_test, y_train, y_test = train_test_split(
        predictors,
        target,
        test_size=test_size,
        random_state=seed,
        shuffle=True,
        stratify=target,
    )
    cv = RepeatedStratifiedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed)
    scoring = {
        "f1_macro": "f1_macro",
        "f1_weighted": "f1_weighted",
        "accuracy": "accuracy",
        "roc_auc_ovr": "roc_auc_ovr",
    }
    rows = []
    fitted = {}
    for name, pipeline in candidate_pipelines(columns, seed).items():
        result = cross_validate(
            pipeline,
            x_train,
            y_train,
            cv=cv,
            scoring=scoring,
            n_jobs=1,
            return_train_score=True,
            error_score="raise",
        )
        row = _cv_summary(name, result, selectable=name != "Majority baseline")
        estimator = clone(pipeline)
        estimator.fit(x_train, y_train)
        fitted[name] = estimator
        if collect_details:
            classes = list(estimator.named_steps["model"].classes_)
            row["holdout"] = _classification_block(
                y_test,
                estimator.predict(x_test),
                estimator.predict_proba(x_test),
                classes,
            )
        rows.append(row)
    decision = select_candidate(
        rows,
        "cv_f1_macro_mean",
        higher=True,
        margin=F1_SELECTION_MARGIN,
        preference=PREFERENCE,
    )
    selected_name = decision["algorithm"]
    selected = fitted[selected_name]
    details = {}
    if collect_details:
        oof_cv = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=seed)
        classes = list(selected.named_steps["model"].classes_)
        oof_proba = cross_val_predict(
            clone(candidate_pipelines(columns, seed)[selected_name]),
            x_train,
            y_train,
            cv=oof_cv,
            method="predict_proba",
            n_jobs=1,
        )
        oof_pred = np.asarray(classes)[np.argmax(oof_proba, axis=1)]
        details = {
            "oof_training_partition": _classification_block(y_train, oof_pred, oof_proba, classes),
            "holdout_predictions": {
                "y_true": y_test.astype(str).tolist(),
                "y_pred": selected.predict(x_test).astype(str).tolist(),
                "p_dropout": _positive_dropout(selected.predict_proba(x_test), classes).astype(float).tolist(),
            },
            "interpretation": _interpret(selected, columns),
        }
    return {
        "columns": list(columns),
        "rows": rows,
        "decision": decision,
        "pipeline": selected,
        "details": details,
        "split": {
            "seed": seed,
            "test_size": test_size,
            "n_splits": n_splits,
            "n_repeats": n_repeats,
            "train_rows": int(len(x_train)),
            "test_rows": int(len(x_test)),
            "class_counts": target.value_counts().to_dict(),
        },
    }


def _interpret(pipeline: Pipeline, columns: tuple[str, ...]) -> dict:
    model = pipeline.named_steps["model"]
    if hasattr(model, "feature_importances_") and "preprocess" not in pipeline.named_steps:
        return {
            "kind": "impurity_importance",
            "terms": [
                {"feature": column, "importance": float(value)}
                for column, value in zip(columns, model.feature_importances_, strict=True)
            ],
        }
    if hasattr(model, "coef_") and "preprocess" not in pipeline.named_steps:
        classes = list(model.classes_)
        dropout_row = model.coef_[classes.index("Dropout")]
        return {
            "kind": "dropout_class_coefficients",
            "intercept": float(model.intercept_[classes.index("Dropout")]),
            "scaled": "scale" in pipeline.named_steps,
            "terms": [
                {"feature": column, "coefficient": float(value)}
                for column, value in zip(columns, np.asarray(dropout_row).ravel(), strict=True)
            ],
        }
    return {
        "kind": "encoded_feature_space",
        "note": "Full-feature coefficients or importances are on one-hot and numeric columns. They are not a UCC deployment explanation.",
    }


def _score_named_pipeline(
    frame: pd.DataFrame,
    columns: tuple[str, ...],
    algorithm: str,
    *,
    seed: int,
    n_splits: int,
    n_repeats: int,
    test_size: float,
) -> dict:
    """CV the already chosen exploratory algorithm on a timing-restricted column set."""
    predictors = frame.loc[:, list(columns)]
    target = frame[TARGET_COLUMN]
    x_train, _x_test, y_train, _y_test = train_test_split(
        predictors,
        target,
        test_size=test_size,
        random_state=seed,
        shuffle=True,
        stratify=target,
    )
    pipeline = candidate_pipelines(columns, seed)[algorithm]
    result = cross_validate(
        pipeline,
        x_train,
        y_train,
        cv=RepeatedStratifiedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed),
        scoring={"f1_macro": "f1_macro", "f1_weighted": "f1_weighted", "accuracy": "accuracy"},
        n_jobs=1,
        return_train_score=False,
        error_score="raise",
    )
    return {
        "algorithm": algorithm,
        "columns": list(columns),
        "n_columns": len(columns),
        "cv_f1_macro_mean": float(result["test_f1_macro"].mean()),
        "cv_f1_macro_std": float(result["test_f1_macro"].std(ddof=1)),
        "cv_f1_weighted_mean": float(result["test_f1_weighted"].mean()),
        "cv_accuracy_mean": float(result["test_accuracy"].mean()),
        "n_folds": int(len(result["test_f1_macro"])),
    }


def timing_columns(level: str) -> tuple[str, ...]:
    if level == "full":
        return PREDICTOR_COLUMNS
    if level == "after_first_semester":
        return tuple(column for column in PREDICTOR_COLUMNS if column not in SECOND_SEMESTER_COLUMNS)
    if level == "at_enrollment":
        blocked = set(FIRST_SEMESTER_COLUMNS) | set(SECOND_SEMESTER_COLUMNS)
        return tuple(column for column in PREDICTOR_COLUMNS if column not in blocked)
    raise ValueError(level)


def train_dropout(
    frame: pd.DataFrame,
    *,
    seed: int = RANDOM_SEED,
    n_splits: int = N_SPLITS,
    n_repeats: int = N_REPEATS,
    test_size: float = TEST_SIZE,
) -> dict:
    if set(frame[TARGET_COLUMN].unique()) != set(LABELS):
        raise ValueError("Dropout target labels are not the verified three-class set.")
    exploratory = _evaluate_feature_set(
        frame,
        PREDICTOR_COLUMNS,
        seed=seed,
        n_splits=n_splits,
        n_repeats=n_repeats,
        test_size=test_size,
        collect_details=True,
    )
    reduced = _evaluate_feature_set(
        frame,
        REDUCED_PREDICTORS,
        seed=seed,
        n_splits=n_splits,
        n_repeats=n_repeats,
        test_size=test_size,
        collect_details=True,
    )
    winner_name = exploratory["decision"]["algorithm"]
    timing = {
        level: _score_named_pipeline(
            frame,
            timing_columns(level),
            winner_name,
            seed=seed,
            n_splits=n_splits,
            n_repeats=n_repeats,
            test_size=test_size,
        )
        for level in ("after_first_semester", "at_enrollment")
    }
    exploratory_public = {key: value for key, value in exploratory.items() if key != "pipeline"}
    return {
        "exploratory": exploratory_public,
        "reduced": {key: value for key, value in reduced.items() if key != "pipeline"},
        "timing": timing,
        "pipeline": reduced["pipeline"],
        "input_columns": list(REDUCED_PREDICTORS),
        "target_labels": LABELS,
        "exploratory_pipeline_saved": False,
    }
