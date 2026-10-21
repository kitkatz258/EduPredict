"""Run the M25 audits, comparisons, artifact save, and report write."""

from __future__ import annotations

import os

os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import json

import joblib

from src.common import (
    EMPLOYABILITY_CANDIDATE,
    FIGURES_DIR,
    RANDOM_SEED,
    RAW_DROPOUT,
    RAW_EMPLOYABILITY,
    REPORTS_DIR,
    DROPOUT_CANDIDATE,
    file_record,
    library_versions,
    manila_now,
)
from src.data.archive import audit_employability_archive
from src.data.dropout import REDUCED_PREDICTORS, load_dropout
from src.data.employability import load_employability, verify_construct_means
from src.evaluation.plots import (
    calibration,
    classification_bars,
    confusion,
    regression_bars,
    regression_scatter,
)
from src.evaluation.render import write_reports
from src.features.ucc import approved_pdf_status
from src.training.dropout import _evaluate_feature_set, train_dropout
from src.training.employability import train_employability


def _regression_signature(result: dict) -> list:
    return [
        (
            row["algorithm"],
            row["cv_rmse_mean"],
            row["cv_mae_mean"],
            row["cv_r2_mean"],
            row["holdout"]["rmse"],
        )
        for row in result["rows"]
    ]


def _classification_signature(result: dict) -> list:
    return [
        (
            row["algorithm"],
            row["cv_f1_macro_mean"],
            row["cv_f1_weighted_mean"],
            row["cv_accuracy_mean"],
            row["holdout"]["f1_macro"],
        )
        for row in result["rows"]
    ]


def _limitations(kind: str) -> list[str]:
    shared = [
        "Research candidate only. Not production-validated.",
        "Panel approval of the UCC questionnaire is not psychometric validation or external validity.",
        "No Low, Moderate, or High threshold is part of this artifact.",
        "Live EduPredict records were not used for training.",
    ]
    if kind == "employability":
        return shared + [
            "Target is the Pianda self-perceived EVS mean, not employment or a UCC outcome.",
            "Predictors are Pianda construct means. UCC C1–C8 and P1–P6 are not validated substitutes.",
            "Sample is 266 vocational secondary students in Aceh, with same-occasion self-report.",
            "The pipeline was fit on the training split only.",
        ]
    return shared + [
        "Trained only on UCI Scholarship holder (1 yes, 0 no).",
        "Portuguese grades and curricular units are not included because no UCC conversion is verified.",
        "The exploratory full-feature model is not this artifact.",
        "Three-class labels are preserved. P(Dropout) is a class probability, not a risk band.",
    ]


def main() -> None:
    print("Auditing employability workbook...")
    employability_frame = load_employability()
    verified = verify_construct_means(employability_frame)
    print("Auditing dropout file...")
    dropout_frame, quirks = load_dropout()
    ucc = approved_pdf_status()
    if ucc["item_mapping_blocked"]:
        print("UCC PDF mapping is blocked. Source-data experiments continue.")
    archive = audit_employability_archive()
    employability_files = [
        file_record(RAW_EMPLOYABILITY / name)
        for name in ("Raw Dataset.xlsx", "Survey questionnaire.docx", "Dataset.rar")
    ]
    dropout_file = file_record(RAW_DROPOUT / "data.csv")

    print("Training employability candidates...")
    employability = train_employability(verified["predictors"], verified["target"], seed=RANDOM_SEED)
    print("Selected employability:", employability["decision"]["algorithm"])
    print("Re-running employability comparison...")
    employability_again = train_employability(verified["predictors"], verified["target"], seed=RANDOM_SEED)
    employability_match = _regression_signature(employability) == _regression_signature(employability_again)
    if not employability_match:
        raise SystemExit("Employability comparison was not reproducible.")

    print("Training dropout experiments...")
    dropout = train_dropout(dropout_frame, seed=RANDOM_SEED)
    print(
        "Selected dropout candidate:",
        dropout["reduced"]["decision"]["algorithm"],
        "| exploratory reference:",
        dropout["exploratory"]["decision"]["algorithm"],
    )
    print("Re-running reduced dropout comparison...")
    reduced_again = _evaluate_feature_set(
        dropout_frame,
        REDUCED_PREDICTORS,
        seed=RANDOM_SEED,
        n_splits=dropout["reduced"]["split"]["n_splits"],
        n_repeats=dropout["reduced"]["split"]["n_repeats"],
        test_size=dropout["reduced"]["split"]["test_size"],
        collect_details=True,
    )
    dropout_match = _classification_signature(dropout["reduced"]) == _classification_signature(reduced_again)
    if not dropout_match:
        raise SystemExit("Reduced dropout comparison was not reproducible.")

    versions = library_versions()
    trained_at = manila_now()
    employability_meta = {
        "name": "employability_candidate_v1",
        "production_validated": False,
        "research_candidate": True,
        "algorithm": employability["decision"]["algorithm"],
        "input_columns": employability["input_columns"],
        "value_rule": "likert_mean",
        "target": "EmployabilityMean, unweighted mean of EVS1-EVS12",
        "target_range": [1, 5],
        "display_readiness_index": "((native_score - 1) / 4) * 100 inside [1, 5]; not a probability",
        "risk_thresholds": None,
        "dataset_sha256": employability_files[0]["sha256"],
        "dataset_version": "Mendeley 10.17632/hmn3b4c6c4.4",
        "random_seed": RANDOM_SEED,
        "split": employability["split"],
        "selection": employability["decision"],
        "cv_rmse_mean": next(
            row["cv_rmse_mean"]
            for row in employability["rows"]
            if row["algorithm"] == employability["decision"]["algorithm"]
        ),
        "library_versions": versions,
        "trained_at": trained_at,
        "fitted_on": "training split only",
        "limitations": _limitations("employability"),
    }
    dropout_meta = {
        "name": "dropout_candidate_v1",
        "production_validated": False,
        "research_candidate": True,
        "algorithm": dropout["reduced"]["decision"]["algorithm"],
        "input_columns": dropout["input_columns"],
        "value_rule": "binary01",
        "target_labels": dropout["target_labels"],
        "probability": "P(Dropout) from predict_proba; three-class predict is separate",
        "risk_thresholds": None,
        "dataset_sha256": dropout_file["sha256"],
        "dataset_version": "UCI id 697, DOI 10.24432/C5MC89",
        "random_seed": RANDOM_SEED,
        "split": dropout["reduced"]["split"],
        "selection": dropout["reduced"]["decision"],
        "cv_f1_macro_mean": next(
            row["cv_f1_macro_mean"]
            for row in dropout["reduced"]["rows"]
            if row["algorithm"] == dropout["reduced"]["decision"]["algorithm"]
        ),
        "library_versions": versions,
        "trained_at": trained_at,
        "fitted_on": "training split only",
        "exploratory_model_saved": False,
        "limitations": _limitations("dropout"),
    }
    EMPLOYABILITY_CANDIDATE.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(
        {"pipeline": employability["pipeline"], "metadata": employability_meta},
        EMPLOYABILITY_CANDIDATE,
    )
    joblib.dump({"pipeline": dropout["pipeline"], "metadata": dropout_meta}, DROPOUT_CANDIDATE)
    reloaded_employability = joblib.load(EMPLOYABILITY_CANDIDATE)
    reloaded_dropout = joblib.load(DROPOUT_CANDIDATE)
    sample = verified["predictors"].head(8)
    first = employability["pipeline"].predict(sample)
    second = reloaded_employability["pipeline"].predict(sample)
    if not (first == second).all():
        raise SystemExit("Reloaded employability predictions changed.")
    scholarship_sample = dropout_frame.loc[:, list(REDUCED_PREDICTORS)].head(8)
    if not (
        dropout["pipeline"].predict(scholarship_sample)
        == reloaded_dropout["pipeline"].predict(scholarship_sample)
    ).all():
        raise SystemExit("Reloaded dropout predictions changed.")

    FIGURES_DIR.mkdir(parents=True, exist_ok=True)
    regression_bars(employability["rows"], FIGURES_DIR / "employability_rmse.png")
    regression_scatter(
        employability["holdout_predictions"]["y_true"],
        employability["holdout_predictions"]["y_pred"],
        FIGURES_DIR / "employability_holdout_scatter.png",
    )
    classification_bars(
        dropout["exploratory"]["rows"],
        dropout["reduced"]["rows"],
        FIGURES_DIR / "dropout_macro_f1.png",
    )
    full_choice = next(
        row
        for row in dropout["exploratory"]["rows"]
        if row["algorithm"] == dropout["exploratory"]["decision"]["algorithm"]
    )
    reduced_choice = next(
        row
        for row in dropout["reduced"]["rows"]
        if row["algorithm"] == dropout["reduced"]["decision"]["algorithm"]
    )
    confusion(
        full_choice["holdout"]["confusion_matrix"],
        full_choice["holdout"]["labels"],
        "Exploratory holdout confusion",
        FIGURES_DIR / "dropout_full_confusion.png",
    )
    confusion(
        reduced_choice["holdout"]["confusion_matrix"],
        reduced_choice["holdout"]["labels"],
        "Reduced-candidate holdout confusion",
        FIGURES_DIR / "dropout_reduced_confusion.png",
    )
    calibration(
        full_choice["holdout"]["calibration_quantile"],
        "Exploratory holdout P(Dropout)",
        FIGURES_DIR / "dropout_full_calibration.png",
    )
    calibration(
        reduced_choice["holdout"]["calibration_quantile"],
        "Reduced-candidate holdout P(Dropout)",
        FIGURES_DIR / "dropout_reduced_calibration.png",
    )

    missing = ucc["missing"]
    ucc_text = (
        "The approved-questionnaire directory exists, but these required PDFs are absent: "
        + "; ".join(missing)
        + ". Item text, reverse-scoring exceptions, and consultation-draft bands were not read. "
        "Section codes used in the mapping note come from the M25 specification, not from a PDF transcription. "
        "UCC section means were not computed."
        if missing
        else "Both approved PDFs were present."
    )
    bundle = {
        "generated_at": trained_at,
        "seed": RANDOM_SEED,
        "versions": versions,
        "files": {"employability": employability_files, "dropout": dropout_file},
        "verification": {
            "employability": {
                key: value
                for key, value in verified.items()
                if key not in {"predictors", "target"}
            },
            "dropout_quirks": quirks,
            "dropout_counts": dropout_frame["Target"].value_counts().to_dict(),
        },
        "archive": archive,
        "ucc": ucc,
        "ucc_status_text": ucc_text,
        "employability": employability,
        "dropout": dropout,
        "reproducibility": {
            "employability": employability_match,
            "dropout_reduced": dropout_match,
        },
    }
    write_reports(bundle, REPORTS_DIR)
    print(json.dumps(
        {
            "employability": employability["decision"]["algorithm"],
            "dropout": dropout["reduced"]["decision"]["algorithm"],
            "exploratory": dropout["exploratory"]["decision"]["algorithm"],
            "reproducibility": bundle["reproducibility"],
        },
        indent=2,
    ))


if __name__ == "__main__":
    main()
