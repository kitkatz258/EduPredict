"""Load the UCI dropout CSV without changing the raw file."""

from __future__ import annotations

import pandas as pd

from src.common import RAW_DROPOUT, TARGET_LABELS

TARGET_COLUMN = "Target"
SCHOLARSHIP_COLUMN = "Scholarship holder"

EXPECTED_COLUMNS = (
    "Marital status",
    "Application mode",
    "Application order",
    "Course",
    "Daytime/evening attendance",
    "Previous qualification",
    "Previous qualification (grade)",
    "Nacionality",
    "Mother's qualification",
    "Father's qualification",
    "Mother's occupation",
    "Father's occupation",
    "Admission grade",
    "Displaced",
    "Educational special needs",
    "Debtor",
    "Tuition fees up to date",
    "Gender",
    "Scholarship holder",
    "Age at enrollment",
    "International",
    "Curricular units 1st sem (credited)",
    "Curricular units 1st sem (enrolled)",
    "Curricular units 1st sem (evaluations)",
    "Curricular units 1st sem (approved)",
    "Curricular units 1st sem (grade)",
    "Curricular units 1st sem (without evaluations)",
    "Curricular units 2nd sem (credited)",
    "Curricular units 2nd sem (enrolled)",
    "Curricular units 2nd sem (evaluations)",
    "Curricular units 2nd sem (approved)",
    "Curricular units 2nd sem (grade)",
    "Curricular units 2nd sem (without evaluations)",
    "Unemployment rate",
    "Inflation rate",
    "GDP",
    TARGET_COLUMN,
)

PREDICTOR_COLUMNS = tuple(column for column in EXPECTED_COLUMNS if column != TARGET_COLUMN)
FIRST_SEMESTER_COLUMNS = tuple(
    column for column in PREDICTOR_COLUMNS if column.startswith("Curricular units 1st sem")
)
SECOND_SEMESTER_COLUMNS = tuple(
    column for column in PREDICTOR_COLUMNS if column.startswith("Curricular units 2nd sem")
)
REDUCED_PREDICTORS = (SCHOLARSHIP_COLUMN,)


def load_dropout(path=None) -> tuple[pd.DataFrame, dict]:
    csv_path = path or (RAW_DROPOUT / "data.csv")
    raw_prefix = csv_path.read_bytes()[:3]
    raw = pd.read_csv(csv_path, sep=";", encoding="utf-8-sig", dtype=str)
    original_columns = list(raw.columns)
    quirks = {
        "utf8_bom_on_first_column": raw_prefix == b"\xef\xbb\xbf",
        "trailing_tab_columns": [column for column in original_columns if column.endswith("\t") or column.endswith(" ")],
    }
    raw.columns = [column.replace("\ufeff", "").strip() for column in original_columns]
    if tuple(raw.columns) != EXPECTED_COLUMNS:
        raise ValueError("Dropout columns do not match the audited UCI header after BOM/whitespace cleanup.")
    if len(raw) != 4424:
        raise ValueError(f"Expected 4424 dropout rows, found {len(raw)}.")
    if raw.isna().any().any() or raw.eq("").any().any():
        raise ValueError("Dropout file contains empty cells; no imputation rule is defined.")

    frame = raw.copy()
    for column in PREDICTOR_COLUMNS:
        frame[column] = pd.to_numeric(frame[column], errors="raise")
    labels = set(frame[TARGET_COLUMN].unique())
    if labels != set(TARGET_LABELS):
        raise ValueError(f"Unexpected target labels: {sorted(labels)}.")
    if int(frame.duplicated().sum()) != 0:
        raise ValueError("Dropout file contains duplicate rows.")
    scholarship = set(frame[SCHOLARSHIP_COLUMN].unique().tolist())
    if scholarship != {0, 1}:
        raise ValueError(f"Scholarship holder is not a 0/1 field in this file: {sorted(scholarship)}.")
    return frame, quirks
