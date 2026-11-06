"""Run M42 source audits, mapping, training, artifact save, and reports.

Does not change Laravel predictors, M33 FastAPI, M25/M27 artifacts, or databases.
"""

from __future__ import annotations

import os

os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import json
import joblib
import numpy as np

from src.common import (
    RAW_DROPOUT,
    RAW_EMPLOYABILITY,
    file_record,
    library_versions,
    manila_now,
)
from src.data.archive import audit_employability_archive
from src.data.dropout import load_dropout
from src.data.employability import (
    COMPETENCE_MEAN,
    PSYCAP_MEAN,
    load_employability,
    verify_construct_means,
)
from src.m42.common import (
    M42_ARTIFACTS,
    M42_METRICS,
    M42_REPORTS,
    M42_SEED,
    m42_artifact,
)
from src.m42.compatibility import CONTRACTS, FIRST_SEM_UNITS
from src.m42.dropout import FEATURE_SETS, evaluate_dropout_sets
from src.training.dropout import _evaluate_feature_set
from src.m42.employability import (
    CP_COLUMNS,
    CP_INTERNSHIP_COLUMNS,
    internship_mean_from_source,
    train_regression,
)
from src.m42.mapping import assert_mapping_complete, questionnaire_rows
from src.m42.reports import (
    write_compatibility,
    write_data_audit,
    write_dropout_experiments,
    write_employability_experiments,
    write_limitations,
    write_model_comparison,
    write_questionnaire_mapping,
)
from src.questionnaire.v2_instrument import ALL_ITEM_CODES, VERSION


def _jsonable(value):
    if isinstance(value, dict):
        return {str(key): _jsonable(item) for key, item in value.items() if key != "pipeline"}
    if isinstance(value, (list, tuple)):
        return [_jsonable(item) for item in value]
    if isinstance(value, np.ndarray):
        return value.tolist()
    if isinstance(value, (np.floating,)):
        return float(value)
    if isinstance(value, (np.integer,)):
        return int(value)
    if isinstance(value, (np.bool_,)):
        return bool(value)
    return value


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
        "Panel revision of the 60-item questionnaire is not psychometric validation or UCC validity.",
        "No Low, Moderate, or High threshold is part of this artifact.",
        "Live EduPredict records were not used for training.",
        "UCC inference is blocked.",
    ]
    if kind == "employability":
        return shared + [
            "Target is the Pianda EVS mean, not employment or a UCC outcome.",
            "Predictors are Pianda construct means. UCC C1–C10 and P1–P10 are not validated substitutes.",
            "Sample is 266 vocational secondary students in Aceh, with same-occasion self-report.",
            "The pipeline was fit on the training split only.",
        ]
    return shared + [
        "Three-class UCI labels are preserved. P(Dropout) is source-trained, not UCC-calibrated.",
        "Grade columns are excluded because no Philippine-to-Portuguese conversion is documented.",
        "Unit-count and scholarship mappings remain pending. Do not score UCC fields with this pipeline.",
        "The pipeline was fit on the training split only.",
    ]


def _save_artifact(path, pipeline, metadata: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {"pipeline": pipeline, "metadata": metadata}
    joblib.dump(payload, path)
    card_path = path.with_suffix(".modelcard.json")
    card_path.write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")
    reloaded = joblib.load(path)
    if reloaded["metadata"]["production_validated"] is not False:
        raise SystemExit("Saved artifact must declare production_validated false.")


def _employability_meta(name, result, dataset_sha, versions, trained_at, extra) -> dict:
    selected = next(row for row in result["rows"] if row["algorithm"] == result["decision"]["algorithm"])
    return {
        "name": name,
        "milestone": "M42",
        "production_validated": False,
        "research_candidate": True,
        "ucc_inference": "blocked",
        "algorithm": result["decision"]["algorithm"],
        "input_columns": result["columns"],
        "feature_order": result["columns"],
        "value_rule": "likert_mean",
        "target": "EmployabilityMean, unweighted mean of EVS1-EVS12",
        "target_range": [1, 5],
        "display_readiness_index": "((native_score - 1) / 4) * 100 inside [1, 5]; not a probability",
        "risk_thresholds": None,
        "dataset_sha256": dataset_sha,
        "dataset_version": "Mendeley 10.17632/hmn3b4c6c4.4",
        "source_codebook": "Raw Dataset.xlsx sheet Codebook",
        "random_seed": M42_SEED,
        "split": result["split"],
        "selection": result["decision"],
        "cv_rmse_mean": selected["cv_rmse_mean"],
        "cv_rmse_std": selected["cv_rmse_std"],
        "cv_mae_mean": selected["cv_mae_mean"],
        "cv_r2_mean": selected["cv_r2_mean"],
        "holdout": selected["holdout"],
        "library_versions": versions,
        "trained_at": trained_at,
        "fitted_on": "training split only",
        "limitations": _limitations("employability"),
        **extra,
    }


def _dropout_meta(name, result, dataset_sha, versions, trained_at) -> dict:
    selected = next(row for row in result["rows"] if row["algorithm"] == result["decision"]["algorithm"])
    return {
        "name": name,
        "milestone": "M42",
        "production_validated": False,
        "research_candidate": True,
        "deployment_candidate": False,
        "ucc_inference": "blocked",
        "algorithm": result["decision"]["algorithm"],
        "input_columns": result["columns"],
        "feature_order": result["columns"],
        "timing": result["timing"],
        "deployment_status": result["deployment_status"],
        "target_labels": ["Dropout", "Enrolled", "Graduate"],
        "probability": "P(Dropout) from predict_proba; source-trained, not UCC-calibrated",
        "risk_thresholds": None,
        "dataset_sha256": dataset_sha,
        "dataset_version": "UCI id 697, DOI 10.24432/C5MC89",
        "random_seed": M42_SEED,
        "split": result["split"],
        "selection": result["decision"],
        "cv_f1_macro_mean": selected["cv_f1_macro_mean"],
        "cv_f1_macro_std": selected["cv_f1_macro_std"],
        "holdout": {
            key: selected["holdout"][key]
            for key in (
                "accuracy",
                "f1_macro",
                "f1_weighted",
                "per_class",
                "confusion_matrix",
                "labels",
                "dropout_false_negatives",
                "dropout_false_negative_rate",
                "roc_auc_dropout_vs_rest",
                "roc_auc_ovr_macro",
                "brier_dropout",
            )
        },
        "library_versions": versions,
        "trained_at": trained_at,
        "fitted_on": "training split only",
        "limitations": _limitations("dropout"),
        "note": result["note"],
    }


def main() -> None:
    assert_mapping_complete()
    mapped = questionnaire_rows()
    if len(mapped) != 60 or set(row["code"] for row in mapped) != set(ALL_ITEM_CODES):
        raise SystemExit("M42 mapping does not cover all 60 v2 items.")
    if any(row["matching_status"] == "exact" for row in mapped):
        raise SystemExit("Exact item matches were claimed without verbatim identity.")

    print("Auditing Pianda workbook...")
    employability_frame = load_employability()
    verified = verify_construct_means(employability_frame)
    verified = dict(verified)
    verified["internship"] = internship_mean_from_source(employability_frame)
    print("Auditing UCI file...")
    dropout_frame, quirks = load_dropout()
    archive = audit_employability_archive()
    employability_files = [
        file_record(RAW_EMPLOYABILITY / name)
        for name in ("Raw Dataset.xlsx", "Survey questionnaire.docx", "Dataset.rar")
    ]
    dropout_file = file_record(RAW_DROPOUT / "data.csv")

    internship = verified["internship"]

    print("Training employability C/P reduced...")
    cp_predictors = verified["predictors"].loc[:, CP_COLUMNS]
    employability_cp = train_regression(cp_predictors, verified["target"], CP_COLUMNS)
    print("Selected C/P:", employability_cp["decision"]["algorithm"])
    employability_cp_again = train_regression(cp_predictors, verified["target"], CP_COLUMNS)
    employability_match = _regression_signature(employability_cp) == _regression_signature(
        employability_cp_again
    )
    if not employability_match:
        raise SystemExit("Employability C/P comparison was not reproducible.")

    print("Training employability internship expanded comparison...")
    intern_frame = cp_predictors.copy()
    intern_frame["InternshipMean"] = internship
    intern_frame = intern_frame.loc[:, CP_INTERNSHIP_COLUMNS]
    employability_intern = train_regression(
        intern_frame, verified["target"], CP_INTERNSHIP_COLUMNS
    )
    print("Selected internship expanded:", employability_intern["decision"]["algorithm"])

    print("Training reduced three-class dropout sets...")
    dropout_results = evaluate_dropout_sets(dropout_frame)
    first_sem = dropout_results["uci-first-sem-units-m42"]
    first_sem_again = _evaluate_feature_set(
        dropout_frame,
        FIRST_SEM_UNITS,
        seed=M42_SEED,
        n_splits=first_sem["split"]["n_splits"],
        n_repeats=first_sem["split"]["n_repeats"],
        test_size=first_sem["split"]["test_size"],
        collect_details=True,
    )
    dropout_match = _classification_signature(first_sem) == _classification_signature(first_sem_again)
    if not dropout_match:
        raise SystemExit("First-semester unit dropout comparison was not reproducible.")

    versions = library_versions()
    trained_at = manila_now()
    M42_ARTIFACTS.mkdir(parents=True, exist_ok=True)

    emp_meta = _employability_meta(
        "employability_cp_reduced_m42",
        employability_cp,
        employability_files[0]["sha256"],
        versions,
        trained_at,
        {
            "deployment_candidate": False,
            "mapping_status": "pending",
            "contract_id": "pianda-cp-reduced-m42",
        },
    )
    intern_meta = _employability_meta(
        "employability_cp_internship_expanded_m42",
        employability_intern,
        employability_files[0]["sha256"],
        versions,
        trained_at,
        {
            "deployment_candidate": False,
            "mapping_status": "blocked",
            "contract_id": "pianda-cp-internship-expanded-m42",
            "comparison_only": True,
        },
    )
    _save_artifact(
        m42_artifact("employability_cp_reduced_m42.joblib"),
        employability_cp["pipeline"],
        emp_meta,
    )
    _save_artifact(
        m42_artifact("employability_cp_internship_expanded_m42.joblib"),
        employability_intern["pipeline"],
        intern_meta,
    )
    sample = cp_predictors.head(8)
    saved_emp = joblib.load(m42_artifact("employability_cp_reduced_m42.joblib"))
    if not (employability_cp["pipeline"].predict(sample) == saved_emp["pipeline"].predict(sample)).all():
        raise SystemExit("Reloaded employability predictions changed.")

    for spec in FEATURE_SETS:
        result = dropout_results[spec["id"]]
        meta = _dropout_meta(
            spec["id"].replace("-", "_"),
            result,
            dropout_file["sha256"],
            versions,
            trained_at,
        )
        path = m42_artifact(f"{meta['name']}.joblib")
        _save_artifact(path, result["pipeline"], meta)
        cols = list(spec["columns"])
        probe = dropout_frame.loc[:, cols].head(8)
        reloaded = joblib.load(path)
        if not (result["pipeline"].predict(probe) == reloaded["pipeline"].predict(probe)).all():
            raise SystemExit(f"Reloaded dropout predictions changed for {spec['id']}.")

    emp_checks = {
        key: value
        for key, value in verified.items()
        if key not in {"predictors", "target", "internship"}
    }
    competence_check = next(
        check for check in verified["checks"] if check["derived_name"] == COMPETENCE_MEAN
    )
    psycap_check = next(
        check for check in verified["checks"] if check["derived_name"] == PSYCAP_MEAN
    )
    internship_check = {
        "min": float(internship.min()),
        "max": float(internship.max()),
        "mean": float(internship.mean()),
    }

    bundle = {
        "generated_at": trained_at,
        "seed": M42_SEED,
        "instrument_version": VERSION,
        "versions": versions,
        "files": {"employability": employability_files, "dropout": dropout_file},
        "archive": archive,
        "contracts": CONTRACTS,
        "employability_audit": {
            "rows": verified["rows"],
            "missing_cells": verified["missing_cells"],
            "duplicate_rows": 0,
            "competence_min": competence_check["min"],
            "competence_max": competence_check["max"],
            "psycap_min": psycap_check["min"],
            "psycap_max": psycap_check["max"],
            "target_min": verified["target_min"],
            "target_max": verified["target_max"],
            "target_mean": verified["target_mean"],
            "target_std": verified["target_std"],
            "internship_min": internship_check["min"],
            "internship_max": internship_check["max"],
            "mean_checks": emp_checks,
        },
        "dropout_audit": {
            "rows": int(len(dropout_frame)),
            "counts": dropout_frame["Target"].value_counts().to_dict(),
            "quirks": quirks,
        },
        "employability": {
            "cp_reduced": employability_cp,
            "internship_expanded": employability_intern,
        },
        "dropout": dropout_results,
        "reproducibility": {
            "employability_cp": employability_match,
            "dropout_first_sem": dropout_match,
        },
    }
    M42_REPORTS.mkdir(parents=True, exist_ok=True)
    reports = {
        "data_audit.md": write_data_audit(bundle),
        "questionnaire_feature_mapping.md": write_questionnaire_mapping(),
        "employability_experiments.md": write_employability_experiments(bundle),
        "dropout_experiments.md": write_dropout_experiments(bundle),
        "model_comparison.md": write_model_comparison(bundle),
        "deployment_feature_compatibility.md": write_compatibility(bundle),
        "research_limitations.md": write_limitations(),
    }
    for name, text in reports.items():
        (M42_REPORTS / name).write_text(text, encoding="utf-8")
    M42_METRICS.write_text(json.dumps(_jsonable(bundle), indent=2) + "\n", encoding="utf-8")
    print(
        json.dumps(
            {
                "instrument": VERSION,
                "employability_cp": employability_cp["decision"]["algorithm"],
                "employability_internship": employability_intern["decision"]["algorithm"],
                "dropout": {
                    spec_id: result["decision"]["algorithm"]
                    for spec_id, result in dropout_results.items()
                },
                "reproducibility": bundle["reproducibility"],
                "reports": list(reports),
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
