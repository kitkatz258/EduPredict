"""Markdown and JSON for the M27 binary dropout comparison."""

from __future__ import annotations

import math

import numpy as np

from src.data.dropout import BINARY_TARGET_RULE, NOT_DROPOUT_NOTE
from src.training.binary_dropout import DEFAULT_DECISION_NOTE, SOURCE_DATA_DISCLAIMER

M27_PROTOCOL_TEXT = f"""{BINARY_TARGET_RULE}

{NOT_DROPOUT_NOTE} Calling the negative class Not Dropout does not show that those students remain enrolled in a later term, and it is not a UCC retention label. Enrolled and Graduate stay different source labels; they are grouped only for this binary experiment.

The outer row partition is the M25 split: seed 42, 20% holdout, stratified on the original three-class Target. Those holdout rows were already reviewed in the three-class experiment. Scores on that partition are an exploratory reuse. They are not an independent final validation, and they do not choose the algorithm. Selection uses repeated stratified cross-validation on the training partition only.

Preprocessing sits inside each pipeline. One-hot encoding and, for logistic regression, scaling are fit on the training rows of each fold. The holdout partition is not used to fit preprocessing or to select an algorithm.

The selection metric is mean cross-validated F1 for the Dropout class. The margin is 0.01. Within that margin the earlier algorithm wins: Logistic Regression, then Random Forest Classifier, then Gradient Boosting Classifier. The majority baseline is not selectable. Accuracy is reported and is not the selection metric. Precision, recall, balanced accuracy, ROC-AUC, PR-AUC, Brier score, confusion counts, and calibration are reported with it. A constant score has undefined ROC-AUC in this report; scikit-learn's 0.5 placeholder is not stored.

{DEFAULT_DECISION_NOTE} Logistic regression and random forest use class_weight="balanced". Gradient boosting uses balanced sample weights. The majority baseline does not. Balanced weights move the 0.5 decision, so the false-negative and false-positive counts are not the rates of an unweighted fit.

False negatives are observed Dropout rows predicted as Not Dropout. False positives are observed Enrolled or Graduate rows predicted as Dropout. Those two source labels are counted separately. No Low, Moderate, or High threshold is defined. No UCC mapping and no grade conversion are invented. None of the feature sets is production-validated.

{SOURCE_DATA_DISCLAIMER}. A scored UCI row is not a prediction for a UCC student.
"""


def _num(value, digits=4) -> str:
    if value is None:
        return "n/a"
    return f"{float(value):.{digits}f}"


def _pair(mean, std, digits=4) -> str:
    if mean is None:
        return "n/a"
    if std is None:
        return _num(mean, digits)
    return f"{_num(mean, digits)} ± {_num(std, digits)}"


def _int(value) -> str:
    if value is None:
        return "n/a"
    return str(int(value))


def _table(headers: list[str], rows: list[list[str]]) -> str:
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join("---" for _ in headers) + " |",
    ]
    for row in rows:
        lines.append("| " + " | ".join(row) + " |")
    return "\n".join(lines)


def _selected_row(feature_set: dict) -> dict:
    name = feature_set["decision"]["algorithm"]
    return next(row for row in feature_set["rows"] if row["algorithm"] == name)


def _leader_sentence(label: str, leaders: list[str], selected: str) -> str:
    shown = ", ".join(leaders) if leaders else "n/a"
    if selected in leaders and len(leaders) == 1:
        return f"Highest {label}: {shown}. That match does not make {label} the selection metric."
    if selected in leaders:
        return (
            f"Highest {label}: {shown}. {selected} is in that tie. "
            "The selection metric remains Dropout F1."
        )
    return f"Highest {label}: {shown}. The saved algorithm was not chosen by {label}."


def _cv_table(rows: list[dict]) -> str:
    rendered = []
    for row in rows:
        rendered.append(
            [
                row["algorithm"],
                _pair(row["cv_precision_mean"], row["cv_precision_std"]),
                _pair(row["cv_recall_mean"], row["cv_recall_std"]),
                _pair(row["cv_f1_dropout_mean"], row["cv_f1_dropout_std"]),
                _pair(row["cv_balanced_accuracy_mean"], row["cv_balanced_accuracy_std"]),
                _pair(row["cv_roc_auc_mean"], row["cv_roc_auc_std"]),
                _pair(row["cv_pr_auc_mean"], row["cv_pr_auc_std"]),
                _pair(row["cv_brier_mean"], row["cv_brier_std"]),
                _pair(row["cv_accuracy_mean"], row["cv_accuracy_std"]),
                _num(row["cv_train_f1_dropout_mean"]),
                _num(row["f1_generalization_gap"]),
            ]
        )
    return _table(
        [
            "Algorithm",
            "Precision",
            "Recall",
            "Dropout F1",
            "Balanced accuracy",
            "ROC-AUC",
            "PR-AUC",
            "Brier",
            "Accuracy",
            "Train Dropout F1",
            "F1 gap",
        ],
        rendered,
    )


def _error_table(rows: list[dict], block_name: str) -> str:
    rendered = []
    for row in rows:
        block = row[block_name]
        sources = block.get("false_positive_source_counts") or {}
        rendered.append(
            [
                row["algorithm"],
                _num(block["precision_dropout"]),
                _num(block["recall_dropout"]),
                _num(block["f1_dropout"]),
                _num(block["balanced_accuracy"]),
                _int(block["false_negative"]),
                _num(block["false_negative_rate"]),
                _int(block["false_positive"]),
                _int(sources.get("Enrolled")),
                _int(sources.get("Graduate")),
                _num(block["false_positive_rate"]),
                _num(block["roc_auc"]),
                _num(block["pr_auc"]),
                _num(block["brier"]),
                _num(block["accuracy"]),
            ]
        )
    return _table(
        [
            "Algorithm",
            "Precision",
            "Recall",
            "Dropout F1",
            "Balanced accuracy",
            "FN",
            "FNR",
            "FP",
            "FP Enrolled",
            "FP Graduate",
            "FPR",
            "ROC-AUC",
            "PR-AUC",
            "Brier",
            "Accuracy",
        ],
        rendered,
    )


def _confusion(block: dict) -> str:
    matrix = block["confusion_matrix"]
    return _table(
        ["Observed \\ Predicted", "Not Dropout", "Dropout"],
        [
            ["Not Dropout", _int(matrix[0][0]), _int(matrix[0][1])],
            ["Dropout", _int(matrix[1][0]), _int(matrix[1][1])],
        ],
    )


def _calibration_table(block: dict) -> str:
    curve = block["calibration_quantile"]
    rows = []
    for predicted, observed in zip(curve["mean_predicted"], curve["fraction_positive"], strict=True):
        rows.append([_num(predicted), _num(observed)])
    if not rows:
        return "Calibration bins were not available for this score."
    return _table(["Mean predicted P(Dropout)", "Observed Dropout fraction"], rows)


def comparison_table(feature_sets: list[dict]) -> str:
    rows = []
    for feature_set in feature_sets:
        selected = _selected_row(feature_set)
        oof = selected["training_oof"]
        rows.append(
            [
                feature_set["name"],
                feature_set["role"],
                _int(feature_set["n_columns"]),
                selected["algorithm"],
                _pair(selected["cv_f1_dropout_mean"], selected["cv_f1_dropout_std"]),
                _pair(selected["cv_roc_auc_mean"], selected["cv_roc_auc_std"]),
                _pair(selected["cv_pr_auc_mean"], selected["cv_pr_auc_std"]),
                _pair(selected["cv_brier_mean"], selected["cv_brier_std"]),
                _pair(selected["cv_accuracy_mean"], selected["cv_accuracy_std"]),
                _int(oof["false_negative"]),
                _int(oof["false_positive"]),
            ]
        )
    return _table(
        [
            "Feature set",
            "Role",
            "Columns",
            "Selected by Dropout F1",
            "CV Dropout F1",
            "CV ROC-AUC",
            "CV PR-AUC",
            "CV Brier",
            "CV accuracy",
            "OOF FN",
            "OOF FP",
        ],
        rows,
    )


def _removed_text(feature_set: dict) -> str:
    if feature_set["name"] == "scholarship_only":
        return "Every UCI predictor except Scholarship holder."
    if not feature_set["removed_columns"]:
        return "None. The set keeps all 36 UCI predictors."
    return ", ".join(feature_set["removed_columns"]) + "."


def render_binary_report(bundle: dict) -> str:
    split = bundle["split"]
    parts = [
        "# M27 binary dropout model comparison",
        "",
        f"Generated: {bundle['generated_at']}",
        "",
        "These models are research artifacts. They are not production-validated. "
        "No model in this file is a UCC student predictor, and no Low, Moderate, or High band is defined.",
        "",
        "## Target and partition",
        "",
        M27_PROTOCOL_TEXT.strip(),
        "",
        f"Training rows: {split['train_rows']}. Holdout rows: {split['test_rows']}.",
        f"Training source counts: {split['train_source_counts']}.",
        f"Holdout source counts: {split['test_source_counts']}.",
        f"Training binary counts: {split['train_binary_counts']}.",
        f"Holdout binary counts: {split['test_binary_counts']}.",
        f"Full-file source counts: {bundle['source_counts']}.",
        f"Full-file binary counts: {bundle['binary_counts']}.",
        f"Holdout index SHA-256: `{split['test_index_sha256']}`.",
        "",
        "Repeated CV uses "
        f"{split['n_splits']} splits × {split['n_repeats']} repeats on the training partition. "
        f"The out-of-fold tables use one stratified {split['n_splits']}-fold pass, "
        "separate from the repeated folds used for selection.",
        "",
        "## Selected comparison",
        "",
        "Each row is the algorithm chosen inside that feature set by training-partition Dropout F1. "
        "The scholarship row is a weak compatibility baseline, not a promotion to deployment. "
        "The full and timing rows still use UCI fields that have no verified UCC equivalent.",
        "",
        comparison_table(bundle["feature_sets"]),
        "",
        "## Feature timing",
        "",
    ]
    for feature_set in bundle["feature_sets"]:
        parts.extend(
            [
                f"### {feature_set['name']}",
                "",
                feature_set["timing_note"],
                "",
                f"Columns kept: {feature_set['n_columns']}. Removed: {_removed_text(feature_set)}",
                "",
            ]
        )
    parts.extend(["## Results by feature set", ""])
    for feature_set in bundle["feature_sets"]:
        selected = feature_set["decision"]["algorithm"]
        parts.extend(
            [
                f"### {feature_set['name']}",
                "",
                f"Role: {feature_set['role']}. {feature_set['timing_note']}",
                "",
                "Training-partition repeated CV. Accuracy is the last metric and is not the selection rule.",
                "",
                _cv_table(feature_set["rows"]),
                "",
                f"Selected by Dropout F1: **{selected}**. "
                f"Contenders within {feature_set['decision']['margin']}: "
                + ", ".join(feature_set["decision"]["contenders"])
                + ".",
                "",
                _leader_sentence("accuracy", feature_set["accuracy_leaders"], selected),
                "",
                _leader_sentence(
                    "balanced accuracy",
                    feature_set["balanced_accuracy_leaders"],
                    selected,
                ),
                "",
                _leader_sentence("PR-AUC", feature_set["pr_auc_leaders"], selected),
                "",
                "Training-partition out-of-fold scores for every algorithm. "
                "This is the error analysis used to inspect false negatives and false positives. "
                "It is not the reused holdout.",
                "",
                _error_table(feature_set["rows"], "training_oof"),
                "",
                f"Out-of-fold confusion for the selected algorithm ({selected}). "
                "Rows are observed labels and columns are predicted labels.",
                "",
                _confusion(_selected_row(feature_set)["training_oof"]),
                "",
                "Out-of-fold calibration for that selected algorithm, quantile bins of predicted P(Dropout).",
                "",
                _calibration_table(_selected_row(feature_set)["training_oof"]),
                "",
            ]
        )
    parts.extend(
        [
            "## Exploratory reuse of the M25 holdout",
            "",
            "The holdout rows below are the same M25 20% partition. "
            "They were already reviewed in the three-class experiment. "
            "They were not used to fit these pipelines or to choose the algorithm. "
            "They are not an independent final validation.",
            "",
        ]
    )
    holdout_rows = []
    for feature_set in bundle["feature_sets"]:
        selected = _selected_row(feature_set)
        block = selected["exploratory_holdout"]
        sources = block.get("false_positive_source_counts") or {}
        holdout_rows.append(
            [
                feature_set["name"],
                selected["algorithm"],
                _num(block["precision_dropout"]),
                _num(block["recall_dropout"]),
                _num(block["f1_dropout"]),
                _int(block["false_negative"]),
                _int(block["false_positive"]),
                _int(sources.get("Enrolled")),
                _int(sources.get("Graduate")),
                _num(block["roc_auc"]),
                _num(block["pr_auc"]),
                _num(block["brier"]),
                _num(block["accuracy"]),
            ]
        )
    parts.extend(
        [
            _table(
                [
                    "Feature set",
                    "Algorithm",
                    "Precision",
                    "Recall",
                    "Dropout F1",
                    "FN",
                    "FP",
                    "FP Enrolled",
                    "FP Graduate",
                    "ROC-AUC",
                    "PR-AUC",
                    "Brier",
                    "Accuracy",
                ],
                holdout_rows,
            ),
            "",
        ]
    )
    for feature_set in bundle["feature_sets"]:
        selected = _selected_row(feature_set)
        parts.extend(
            [
                f"Exploratory holdout confusion, {feature_set['name']}, {selected['algorithm']}.",
                "",
                _confusion(selected["exploratory_holdout"]),
                "",
            ]
        )
    warning_lines = ["No library warnings were recorded."]
    if bundle.get("warnings"):
        warning_lines = [
            f"- {item['count']} × {item['message']}" for item in bundle["warnings"]
        ]
    artifact_rows = [
        [
            item["feature_set"],
            item["algorithm"],
            item["model_version"],
            item["path"],
        ]
        for item in bundle["artifacts"]
    ]
    benchmark = bundle["employability_benchmark"]
    parts.extend(
        [
            "## Artifacts",
            "",
            "Saved pipelines were fit on the training partition only. "
            "`production_validated` is false and `risk_thresholds` is null. "
            "The M25 three-class scholarship candidate and the employability linear regression were not overwritten.",
            "",
            _table(
                ["Feature set", "Algorithm", "Model version", "Path"],
                artifact_rows,
            ),
            "",
            f"M25 dropout candidate SHA-256 unchanged: `{bundle['unchanged_m25']['dropout_candidate_sha256']}`.",
            f"M25 employability candidate SHA-256 unchanged: `{bundle['unchanged_m25']['employability_candidate_sha256']}`.",
            "",
            "## Employability benchmark",
            "",
            "The existing research benchmark remains linear regression on Pianda `CompetenceMean` and `PsyCapMean`. "
            "M27 does not refit it and does not pass UCC questionnaire scores through it.",
            "",
            f"Artifact: `{benchmark['name']}`. Algorithm: {benchmark['algorithm']}. "
            f"Trained at: {benchmark['trained_at']}. "
            f"Stored CV RMSE: {_num(benchmark['cv_rmse_mean'])}. "
            f"production_validated: {benchmark['production_validated']}. "
            "Refit in M27: false.",
            "",
            "The source-data demo scores one held-out Pianda row with that saved pipeline:",
            "",
            "```bash",
            "python -m src.demo_m27 employability --offset 0",
            "```",
            "",
            "## Dropout source-data demo",
            "",
            "The dropout demo loads a saved M27 pipeline and one held-out UCI row. "
            "The default feature set is the full exploratory model, so the printed probability uses late curricular fields. "
            "The command prints the source-data banner, the observed UCI label, the model version, and P(Dropout). "
            "It does not present the row as a UCC student.",
            "",
            "```bash",
            "python -m src.demo_m27 dropout --offset 0",
            "python -m src.demo_m27 dropout --offset 0 --feature-set scholarship_only",
            "```",
            "",
            "## Reproducibility",
            "",
            f"Scholarship-only CV, all algorithms, matched a second run: {bundle['reproducibility']['scholarship_all_algorithms_cv']}.",
            f"Full-set selected algorithm ({bundle['reproducibility']['full_selected_algorithm']}) "
            f"CV matched a second run: {bundle['reproducibility']['full_selected_algorithm_cv']}.",
            "",
            f"Library versions: {bundle['versions']}.",
            f"Dataset: {bundle['dataset']}.",
            "",
            "## Warnings",
            "",
            *warning_lines,
            "",
            "## Boundaries",
            "",
            "Laravel, the database, the questionnaire, FeatureBuilder, HeuristicPredictor, "
            "and the previous prediction artifacts were not modified. "
            "No FastAPI service was added. No risk band was chosen.",
            "",
        ]
    )
    return "\n".join(parts)


def render_m27_summary(bundle: dict) -> str:
    benchmark = bundle["employability_benchmark"]
    lines = [
        "# M27 summary",
        "",
        f"Generated: {bundle['generated_at']}",
        "",
        BINARY_TARGET_RULE,
        "",
        NOT_DROPOUT_NOTE,
        "",
        "Model selection used training-partition Dropout F1. "
        "The M25 holdout was scored only as an exploratory reuse and is not an independent final validation. "
        "No model is production-validated, and no Low, Moderate, or High threshold was set.",
        "",
        comparison_table(bundle["feature_sets"]),
        "",
        "Feature-timing limits:",
        "",
    ]
    for feature_set in bundle["feature_sets"]:
        selected = _selected_row(feature_set)
        lines.append(
            f"- {feature_set['name']}: {selected['algorithm']}. {feature_set['timing_note']}"
        )
    lines.extend(
        [
            "",
            "The full exploratory score uses second-semester curricular results. "
            "It is an upper reference on source fields EduPredict cannot supply as the same measurements. "
            "The enrollment cut still includes UCI fields with no verified local equivalent, "
            "and debtor and tuition timing remain unverified. "
            "Scholarship-only is the weak compatibility baseline. "
            "The five local scholarship statuses were not trained, and no grade conversion was invented.",
            "",
            "Employability benchmark, not refit: "
            f"{benchmark['algorithm']} in `{benchmark['name']}`, "
            f"inputs {benchmark['input_columns']}, stored CV RMSE {_num(benchmark['cv_rmse_mean'])}. "
            "UCC questionnaire scores were not substituted.",
            "",
            "Demo commands, from `ml/` with `PYTHONPATH=.`:",
            "",
            "```bash",
            "python -m src.demo_m27 dropout --offset 0",
            "python -m src.demo_m27 employability --offset 0",
            "```",
            "",
            f"Reproducibility: scholarship CV {bundle['reproducibility']['scholarship_all_algorithms_cv']}; "
            f"full selected algorithm CV {bundle['reproducibility']['full_selected_algorithm_cv']}.",
            "",
            "Artifacts:",
            "",
        ]
    )
    for item in bundle["artifacts"]:
        lines.append(f"- `{item['path']}`: {item['model_version']}")
    lines.extend(
        [
            f"- `{bundle['report_path']}`",
            f"- `{bundle['metrics_path']}`",
            "",
            "## Commands",
            "",
            "```bash",
            "export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1",
            "export PYTHONUNBUFFERED=1 PYTHONPATH=.",
            ".venv/bin/python -m src.run_m27",
            ".venv/bin/python -m pytest",
            ".venv/bin/python -m src.demo_m27 dropout --offset 0",
            ".venv/bin/python -m src.demo_m27 employability --offset 0",
            "```",
            "",
            "## Test results",
            "",
            "Pending the pytest run in this session.",
            "",
        ]
    )
    return "\n".join(lines)


def json_ready(value):
    if isinstance(value, dict):
        return {str(key): json_ready(item) for key, item in value.items()}
    if isinstance(value, (list, tuple)):
        return [json_ready(item) for item in value]
    if isinstance(value, np.ndarray):
        return json_ready(value.tolist())
    if isinstance(value, np.integer):
        return int(value)
    if isinstance(value, np.floating):
        number = float(value)
        if math.isnan(number):
            return None
        return number
    if isinstance(value, np.bool_):
        return bool(value)
    if isinstance(value, float) and math.isnan(value):
        return None
    return value
