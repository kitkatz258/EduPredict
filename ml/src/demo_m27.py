"""Score one held-out source row. This is not a UCC student prediction."""

from __future__ import annotations

import os

os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import argparse
import sys

import joblib

from src.common import (
    BINARY_DROPOUT_MODELS,
    EMPLOYABILITY_CANDIDATE,
    RANDOM_SEED,
    TEST_SIZE,
    readiness_index,
)
from src.data.dropout import NOT_DROPOUT_NOTE, TARGET_COLUMN, binary_dropout_indicator, load_dropout
from src.data.employability import V1_PREDICTORS, load_employability, verify_construct_means
from src.data.partitions import employability_train_test_split
from src.features.ucc import CONTEXT_FIELDS_FORBIDDEN_IN_MODELS
from src.training.binary_dropout import (
    DEFAULT_DECISION_NOTE,
    NOT_A_UCC_STUDENT,
    SOURCE_DATA_DISCLAIMER,
    m25_dropout_partition,
    positive_dropout_probability,
)

EMPLOYABILITY_DEMO_NOTE = (
    "Inputs are Pianda CompetenceMean and PsyCapMean from the source workbook. "
    "UCC questionnaire scores were not substituted."
)
EMPLOYABILITY_READINESS_NOTE = (
    "The display readiness index rescales a 1–5 mean. It is not a probability of employment."
)
PROBABILITY_NOTE = (
    "predicted_dropout_probability is a research score for this UCI source row. "
    "It is not a UCC retention probability."
)
CLASS_NAMES = {0: "Not Dropout", 1: "Dropout"}


def _fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(2)


def _format_value(value) -> str:
    number = float(value)
    if number.is_integer():
        return str(int(number))
    return f"{number:.6g}"


def _held_out_row(frame, offset: int, label: str):
    if offset < 0 or offset >= len(frame):
        _fail(f"offset {offset} is outside the held-out partition of {len(frame)} rows.")
    return frame.iloc[[offset]]


def render_dropout_demo(offset: int = 0, feature_set: str = "full") -> str:
    if feature_set not in BINARY_DROPOUT_MODELS:
        _fail(f"Unknown feature set {feature_set}.")
    path = BINARY_DROPOUT_MODELS[feature_set]
    if not path.is_file():
        _fail(f"Missing research artifact {path}. Run python -m src.run_m27 from ml/.")
    artifact = joblib.load(path)
    metadata = artifact["metadata"]
    if metadata.get("production_validated") is not False:
        _fail("Refusing to demo an artifact marked production-validated.")
    if metadata.get("risk_thresholds") is not None:
        _fail("Refusing to demo an artifact that carries risk thresholds.")
    if metadata.get("target_definition", {}).get("positive_class") != 1:
        _fail("Artifact target is not Dropout=1.")
    columns = list(metadata["input_columns"])
    from src.data.dropout import PREDICTOR_COLUMNS

    if any(column not in PREDICTOR_COLUMNS for column in columns):
        _fail("Artifact inputs are not UCI source predictors. Refusing to score.")
    if set(columns) & set(CONTEXT_FIELDS_FORBIDDEN_IN_MODELS):
        _fail("Artifact inputs include questionnaire fields. Refusing to score.")
    frame, _quirks = load_dropout()
    partition = m25_dropout_partition(frame)
    holdout = frame.loc[list(partition["test_index"])]
    row = _held_out_row(holdout, offset, "dropout")
    observed = str(row.iloc[0][TARGET_COLUMN])
    binary = int(binary_dropout_indicator(row[TARGET_COLUMN]).iloc[0])
    features = row.loc[:, columns]
    probability = float(positive_dropout_probability(artifact["pipeline"], features)[0])
    predicted = int(artifact["pipeline"].predict(features)[0])
    lines = [
        SOURCE_DATA_DISCLAIMER,
        NOT_A_UCC_STUDENT,
        NOT_DROPOUT_NOTE,
        PROBABILITY_NOTE,
        metadata["timing_note"],
        DEFAULT_DECISION_NOTE,
        "",
        f"model_version: {metadata['model_version']}",
        f"algorithm: {metadata['algorithm']}",
        f"feature_set: {metadata['feature_set']}",
        f"trained_at: {metadata['trained_at']}",
        "production_validated: false",
        "risk_thresholds: none",
        f"dataset: {metadata['dataset_version']}",
        f"source_row_index: {int(row.index[0])}",
        f"holdout_offset: {offset}",
        "partition: M25 holdout (exploratory reuse, not an independent final validation)",
        f"observed_uci_target: {observed}",
        f"observed_binary: {binary}",
        f"observed_binary_name: {CLASS_NAMES[binary]}",
        f"predicted_dropout_probability: {probability:.6f}",
        f"predicted_class: {predicted}",
        f"predicted_class_name: {CLASS_NAMES[predicted]}",
        "uci_source_features:",
    ]
    for column in columns:
        lines.append(f"  {column}: {_format_value(features.iloc[0][column])}")
    return "\n".join(lines) + "\n"


def render_employability_demo(offset: int = 0) -> str:
    if not EMPLOYABILITY_CANDIDATE.is_file():
        _fail(f"Missing employability benchmark {EMPLOYABILITY_CANDIDATE}.")
    artifact = joblib.load(EMPLOYABILITY_CANDIDATE)
    metadata = artifact["metadata"]
    if metadata.get("algorithm") != "Linear Regression":
        _fail("Employability demo requires the saved linear regression benchmark.")
    if metadata.get("production_validated") is not False:
        _fail("Refusing to demo an artifact marked production-validated.")
    if list(metadata.get("input_columns", [])) != list(V1_PREDICTORS):
        _fail("Employability demo accepts only Pianda CompetenceMean and PsyCapMean.")
    verified = verify_construct_means(load_employability())
    _x_train, x_test, _y_train, y_test = employability_train_test_split(
        verified["predictors"],
        verified["target"],
        seed=RANDOM_SEED,
        test_size=TEST_SIZE,
    )
    row = _held_out_row(x_test, offset, "employability")
    observed = float(y_test.loc[row.index[0]])
    predicted = float(artifact["pipeline"].predict(row)[0])
    try:
        readiness = f"{readiness_index(predicted):.4f}"
    except ValueError:
        readiness = "undefined because the prediction is outside the verified 1–5 range"
    lines = [
        SOURCE_DATA_DISCLAIMER,
        NOT_A_UCC_STUDENT,
        EMPLOYABILITY_DEMO_NOTE,
        "Benchmark: employability_candidate_v1 linear regression, fit during M25, not refit for this demo.",
        EMPLOYABILITY_READINESS_NOTE,
        "",
        f"model_version: {metadata['name']}",
        f"algorithm: {metadata['algorithm']}",
        f"trained_at: {metadata['trained_at']}",
        "production_validated: false",
        "risk_thresholds: none",
        f"dataset: {metadata.get('dataset_version', 'Pianda source workbook')}",
        f"source_row_index: {int(row.index[0])}",
        f"holdout_offset: {offset}",
        "partition: M25 employability holdout (seed 42, 20% shuffle, not stratified)",
        f"CompetenceMean: {float(row.iloc[0]['CompetenceMean']):.4f}",
        f"PsyCapMean: {float(row.iloc[0]['PsyCapMean']):.4f}",
        f"observed_employability_mean: {observed:.4f}",
        f"predicted_employability_mean: {predicted:.4f}",
        f"display_readiness_index: {readiness}",
    ]
    return "\n".join(lines) + "\n"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Source-data research demos. Not UCC predictions."
    )
    subparsers = parser.add_subparsers(dest="command", required=True)
    dropout = subparsers.add_parser("dropout")
    dropout.add_argument("--offset", type=int, default=0)
    dropout.add_argument(
        "--feature-set",
        default="full",
        choices=sorted(BINARY_DROPOUT_MODELS),
        help="Research feature set. The default full model uses second-semester UCI fields.",
    )
    employability = subparsers.add_parser("employability")
    employability.add_argument("--offset", type=int, default=0)
    args = parser.parse_args(argv)
    if args.command == "dropout":
        print(render_dropout_demo(args.offset, args.feature_set), end="")
    else:
        print(render_employability_demo(args.offset), end="")
    return 0


if __name__ == "__main__":
    main()
