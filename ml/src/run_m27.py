"""Run the M27 binary dropout comparison and write research artifacts."""

from __future__ import annotations

import os

os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import json
import warnings
from collections import Counter

import joblib

from src.common import (
    BINARY_DROPOUT_METRICS,
    BINARY_DROPOUT_MODELS,
    BINARY_DROPOUT_REPORT,
    DROPOUT_CANDIDATE,
    EMPLOYABILITY_CANDIDATE,
    FIGURES_DIR,
    M27_SUMMARY,
    N_REPEATS,
    N_SPLITS,
    RANDOM_SEED,
    RAW_DROPOUT,
    TEST_SIZE,
    file_record,
    library_versions,
    manila_now,
    sha256_file,
)
from src.data.dropout import NOT_DROPOUT_NOTE, TARGET_COLUMN, binary_dropout_indicator, load_dropout
from src.evaluation.binary_report import json_ready, render_binary_report, render_m27_summary
from src.evaluation.plots import binary_f1_bars, calibration, confusion
from src.training.binary_dropout import (
    DEFAULT_DECISION_NOTE,
    NOT_A_UCC_STUDENT,
    SELECTION_METRIC,
    SOURCE_DATA_DISCLAIMER,
    cv_signature,
    cross_validate_algorithm,
    cross_validate_feature_set,
    positive_dropout_probability,
    preprocessor_rows_seen,
    run_binary_experiment,
)


def _warning_rows(caught) -> list[dict]:
    counts = Counter(str(item.message).strip().replace("\n", " ")[:300] for item in caught)
    return [{"count": count, "message": message} for message, count in sorted(counts.items())]


def _slug(algorithm: str) -> str:
    return algorithm.lower().replace(" ", "_")


def _limitations(timing_note: str) -> list[str]:
    return [
        "Research artifact only. Not production-validated.",
        "This is not a UCC student model and it was not trained on UCC records.",
        NOT_DROPOUT_NOTE,
        "No Low, Moderate, or High threshold is part of this artifact.",
        DEFAULT_DECISION_NOTE,
        "No UCC-to-UCI grade conversion and no questionnaire mapping was applied.",
        "A, S, B, C, P, and E are not inputs.",
        "The pipeline was fit on the M25 training partition only.",
        "Scores on the M25 holdout are an exploratory reuse, not independent final validation.",
        "Live EduPredict records were not used.",
        timing_note,
    ]


def _public_feature_set(feature_set: dict) -> dict:
    return {key: value for key, value in feature_set.items() if key != "pipeline"}


def main() -> None:
    dropout_hash = sha256_file(DROPOUT_CANDIDATE)
    employability_hash = sha256_file(EMPLOYABILITY_CANDIDATE)
    print("Loading UCI dropout file...", flush=True)
    frame, quirks = load_dropout()
    coded = binary_dropout_indicator(frame[TARGET_COLUMN])
    dataset = file_record(RAW_DROPOUT / "data.csv")
    versions = library_versions()
    trained_at = manila_now()

    with warnings.catch_warnings(record=True) as caught:
        warnings.simplefilter("always")
        experiment = run_binary_experiment(
            frame,
            seed=RANDOM_SEED,
            n_splits=N_SPLITS,
            n_repeats=N_REPEATS,
            test_size=TEST_SIZE,
            echo=lambda message: print(message, flush=True),
        )
        print("Re-running scholarship CV...", flush=True)
        scholarship = next(item for item in experiment["feature_sets"] if item["name"] == "scholarship_only")
        scholarship_again = cross_validate_feature_set(
            frame,
            scholarship["columns"],
            experiment["partition"]["train_index"],
            seed=RANDOM_SEED,
            n_splits=N_SPLITS,
            n_repeats=N_REPEATS,
            feature_name="scholarship_only_rerun",
            echo=lambda message: print(message, flush=True),
        )
        full = next(item for item in experiment["feature_sets"] if item["name"] == "full")
        full_algorithm = full["decision"]["algorithm"]
        print(f"Re-running full-set CV for {full_algorithm}...", flush=True)
        full_again = cross_validate_algorithm(
            frame,
            full["columns"],
            full_algorithm,
            experiment["partition"]["train_index"],
            seed=RANDOM_SEED,
            n_splits=N_SPLITS,
            n_repeats=N_REPEATS,
        )
    warning_rows = _warning_rows(caught)
    scholarship_match = cv_signature(scholarship["rows"]) == cv_signature(scholarship_again)
    full_row = next(row for row in full["rows"] if row["algorithm"] == full_algorithm)
    full_match = cv_signature([full_row]) == cv_signature([full_again])
    if not scholarship_match or not full_match:
        raise SystemExit(
            "Binary dropout CV was not reproducible: "
            f"scholarship={scholarship_match}, full={full_match}."
        )

    artifacts = []
    for feature_set in experiment["feature_sets"]:
        algorithm = feature_set["decision"]["algorithm"]
        path = BINARY_DROPOUT_MODELS[feature_set["name"]]
        version = f"m27-binary-dropout-{feature_set['name']}-{_slug(algorithm)}-{trained_at}"
        metadata = {
            "name": path.stem,
            "model_version": version,
            "production_validated": False,
            "research_candidate": True,
            "deployment_candidate": False,
            "feature_set": feature_set["name"],
            "role": feature_set["role"],
            "algorithm": algorithm,
            "input_columns": feature_set["columns"],
            "timing_note": feature_set["timing_note"],
            "target_definition": {
                "positive_class": 1,
                "positive_label": "Dropout",
                "negative_class": 0,
                "negative_label": "Not Dropout",
                "negative_source_labels": ["Enrolled", "Graduate"],
                "rule": experiment["split"]["target_rule"],
                "negative_meaning": NOT_DROPOUT_NOTE,
            },
            "selection_metric": SELECTION_METRIC,
            "selection": feature_set["decision"],
            "risk_thresholds": None,
            "disclaimer": SOURCE_DATA_DISCLAIMER,
            "not_a_ucc_student": NOT_A_UCC_STUDENT,
            "dataset_sha256": dataset["sha256"],
            "dataset_version": "UCI id 697, DOI 10.24432/C5MC89",
            "random_seed": RANDOM_SEED,
            "split": experiment["split"],
            "training_rows_fit": feature_set["training_rows_fit"],
            "preprocessor_rows_seen": preprocessor_rows_seen(feature_set["pipeline"]),
            "library_versions": versions,
            "trained_at": trained_at,
            "fitted_on": "M25 training partition only",
            "holdout_role": "exploratory_reuse_of_m25_partition",
            "serialization": "joblib compress=3",
            "limitations": _limitations(feature_set["timing_note"]),
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump(
            {"pipeline": feature_set["pipeline"], "metadata": metadata},
            path,
            compress=3,
        )
        reloaded = joblib.load(path)
        check = frame.loc[[experiment["partition"]["test_index"][0]], feature_set["columns"]]
        original = positive_dropout_probability(feature_set["pipeline"], check)
        again = positive_dropout_probability(reloaded["pipeline"], check)
        if not (original == again).all():
            raise SystemExit(f"Reloaded predictions changed for {feature_set['name']}.")
        artifacts.append(
            {
                "feature_set": feature_set["name"],
                "algorithm": algorithm,
                "model_version": version,
                "path": str(path.relative_to(path.parents[2])),
                "preprocessor_rows_seen": metadata["preprocessor_rows_seen"],
                "training_rows_fit": metadata["training_rows_fit"],
            }
        )

    if sha256_file(DROPOUT_CANDIDATE) != dropout_hash or sha256_file(EMPLOYABILITY_CANDIDATE) != employability_hash:
        raise SystemExit("M25 candidate files changed during the M27 run.")

    employability = joblib.load(EMPLOYABILITY_CANDIDATE)
    employability_meta = employability["metadata"]
    bundle = {
        "generated_at": trained_at,
        "versions": versions,
        "dataset": dataset,
        "dropout_quirks": quirks,
        "source_counts": {str(key): int(value) for key, value in frame[TARGET_COLUMN].value_counts().items()},
        "binary_counts": {
            "Dropout": int((coded == 1).sum()),
            "Not Dropout": int((coded == 0).sum()),
        },
        "split": experiment["split"],
        "feature_sets": [_public_feature_set(feature_set) for feature_set in experiment["feature_sets"]],
        "reproducibility": {
            "scholarship_all_algorithms_cv": scholarship_match,
            "full_selected_algorithm_cv": full_match,
            "full_selected_algorithm": full_algorithm,
        },
        "warnings": warning_rows,
        "artifacts": artifacts,
        "unchanged_m25": {
            "dropout_candidate_sha256": dropout_hash,
            "employability_candidate_sha256": employability_hash,
        },
        "employability_benchmark": {
            "name": employability_meta["name"],
            "algorithm": employability_meta["algorithm"],
            "trained_at": employability_meta["trained_at"],
            "cv_rmse_mean": employability_meta["cv_rmse_mean"],
            "production_validated": employability_meta["production_validated"],
            "input_columns": employability_meta["input_columns"],
            "refit_in_m27": False,
        },
        "report_path": str(BINARY_DROPOUT_REPORT.relative_to(BINARY_DROPOUT_REPORT.parents[2])),
        "metrics_path": str(BINARY_DROPOUT_METRICS.relative_to(BINARY_DROPOUT_METRICS.parents[2])),
        "selection_metric": SELECTION_METRIC,
        "production_validated": False,
        "holdout_role": "exploratory_reuse_of_m25_partition",
    }
    BINARY_DROPOUT_REPORT.write_text(render_binary_report(bundle), encoding="utf-8")
    M27_SUMMARY.write_text(render_m27_summary(bundle), encoding="utf-8")
    BINARY_DROPOUT_METRICS.write_text(
        json.dumps(json_ready(bundle), indent=2),
        encoding="utf-8",
    )
    FIGURES_DIR.mkdir(parents=True, exist_ok=True)
    binary_f1_bars(bundle["feature_sets"], FIGURES_DIR / "binary_dropout_f1.png")
    for feature_set in bundle["feature_sets"]:
        selected = next(
            row
            for row in feature_set["rows"]
            if row["algorithm"] == feature_set["decision"]["algorithm"]
        )
        stem = f"binary_dropout_{feature_set['name']}_oof"
        confusion(
            selected["training_oof"]["confusion_matrix"],
            selected["training_oof"]["labels"],
            f"{feature_set['name']} training out-of-fold",
            FIGURES_DIR / f"{stem}_confusion.png",
        )
        calibration(
            selected["training_oof"]["calibration_quantile"],
            f"{feature_set['name']} training out-of-fold P(Dropout)",
            FIGURES_DIR / f"{stem}_calibration.png",
        )
    print(
        json.dumps(
            {
                "selected": {
                    item["feature_set"]: item["algorithm"] for item in artifacts
                },
                "reproducibility": bundle["reproducibility"],
                "warnings": len(warning_rows),
            },
            indent=2,
        ),
        flush=True,
    )


if __name__ == "__main__":
    main()
