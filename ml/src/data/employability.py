"""Load and verify the Pianda employability workbook."""

from __future__ import annotations

import pandas as pd

from src.common import LIKERT_MAX, LIKERT_MIN, RAW_EMPLOYABILITY

COMPETENCE_ITEMS = tuple(f"C{index}" for index in range(1, 11))
PSYCAP_ITEMS = tuple(f"PsyCap{index}" for index in range(1, 15))
EMPLOYABILITY_ITEMS = tuple(f"EVS{index}" for index in range(1, 13))
INTERNSHIP_ITEMS = tuple(f"SIE{index}" for index in range(1, 15))

AVERAGE_COMPETENCE = "AVERAGE (C)"
AVERAGE_PSYCAP = "AVERAGE (PsyCap)"
AVERAGE_EMPLOYABILITY = "AVERAGE (EVS)"
AVERAGE_INTERNSHIP = "AVERAGE (SIE)"

COMPETENCE_MEAN = "CompetenceMean"
PSYCAP_MEAN = "PsyCapMean"
EMPLOYABILITY_TARGET = "EmployabilityMean"

V1_PREDICTORS = (COMPETENCE_MEAN, PSYCAP_MEAN)

EXPECTED_COLUMNS = (
    "Gender",
    "Age",
    "school status",
    "school accreditation",
    *INTERNSHIP_ITEMS,
    AVERAGE_INTERNSHIP,
    *COMPETENCE_ITEMS,
    AVERAGE_COMPETENCE,
    *PSYCAP_ITEMS,
    AVERAGE_PSYCAP,
    *EMPLOYABILITY_ITEMS,
    AVERAGE_EMPLOYABILITY,
)

MEAN_CHECKS = (
    (COMPETENCE_ITEMS, AVERAGE_COMPETENCE, COMPETENCE_MEAN),
    (PSYCAP_ITEMS, AVERAGE_PSYCAP, PSYCAP_MEAN),
    (EMPLOYABILITY_ITEMS, AVERAGE_EMPLOYABILITY, EMPLOYABILITY_TARGET),
    (INTERNSHIP_ITEMS, AVERAGE_INTERNSHIP, "InternshipMean"),
)

# Codebook layout groups. These are not model inputs.
SUBSCALE_GROUPS = {
    "SIE": {
        "Clear internship objectives": INTERNSHIP_ITEMS[0:4],
        "School Support": INTERNSHIP_ITEMS[4:7],
        "comfortable environment": INTERNSHIP_ITEMS[7:11],
        "job prospects": INTERNSHIP_ITEMS[11:14],
    },
    "PsyCap": {
        "Hope": PSYCAP_ITEMS[0:3],
        "Optimism": PSYCAP_ITEMS[3:7],
        "Resiliency": PSYCAP_ITEMS[7:11],
        "Self-Efficacy": PSYCAP_ITEMS[11:14],
    },
    "Competence": {
        "Life and Career Competencies": COMPETENCE_ITEMS[0:4],
        "Learning and Innovation Competencies": COMPETENCE_ITEMS[4:7],
        "Information Technology and Media Competencies": COMPETENCE_ITEMS[7:10],
    },
    "EVS": {
        "Human Capital and Professional Development": EMPLOYABILITY_ITEMS[0:3],
        "Sosial Capital and Networks": EMPLOYABILITY_ITEMS[3:5],
        "Career Identity and Self-Management": EMPLOYABILITY_ITEMS[5:9],
        "Envirionmental Monitoring": EMPLOYABILITY_ITEMS[9:10],
        "Balance": EMPLOYABILITY_ITEMS[10:12],
    },
}


def load_employability(path=None) -> pd.DataFrame:
    workbook = path or (RAW_EMPLOYABILITY / "Raw Dataset.xlsx")
    frame = pd.read_excel(workbook, sheet_name="Dataset")
    if tuple(frame.columns) != EXPECTED_COLUMNS:
        raise ValueError(
            "Employability workbook columns do not match the audited Dataset sheet."
        )
    if len(frame) != 266:
        raise ValueError(f"Expected 266 employability rows, found {len(frame)}.")
    return frame


def verify_construct_means(frame: pd.DataFrame) -> dict:
    """Confirm published AVERAGE columns are unweighted item means. Stop on disagreement."""
    if frame.isna().any().any():
        raise ValueError("Employability sheet contains missing values; no imputation rule is defined.")
    if int(frame.duplicated().sum()) != 0:
        raise ValueError("Employability sheet contains duplicate rows.")

    item_columns = COMPETENCE_ITEMS + PSYCAP_ITEMS + EMPLOYABILITY_ITEMS + INTERNSHIP_ITEMS
    item_values = pd.unique(frame.loc[:, item_columns].to_numpy().ravel())
    if set(item_values.tolist()) - {1, 2, 3, 4, 5}:
        raise ValueError(f"Likert items leave the documented 1–5 set: {sorted(item_values.tolist())}.")

    checks = []
    derived = pd.DataFrame(index=frame.index)
    for items, stored_column, derived_name in MEAN_CHECKS:
        calculated = frame.loc[:, items].mean(axis=1)
        difference = (calculated - frame[stored_column]).abs()
        max_difference = float(difference.max())
        if max_difference > 1e-9:
            raise ValueError(
                f"{stored_column} does not match the unweighted mean of {items[0]}–{items[-1]} "
                f"(max absolute difference {max_difference})."
            )
        derived[derived_name] = calculated
        checks.append(
            {
                "items": list(items),
                "stored_column": stored_column,
                "derived_name": derived_name,
                "max_absolute_difference": max_difference,
                "min": float(calculated.min()),
                "max": float(calculated.max()),
            }
        )

    target = derived[EMPLOYABILITY_TARGET]
    if float(target.min()) < LIKERT_MIN or float(target.max()) > LIKERT_MAX:
        raise ValueError("Employability target leaves the verified 1–5 scoring range.")

    return {
        "rows": int(len(frame)),
        "missing_cells": 0,
        "duplicate_rows": 0,
        "checks": checks,
        "target_min": float(target.min()),
        "target_max": float(target.max()),
        "target_mean": float(target.mean()),
        "target_std": float(target.std(ddof=1)),
        "predictors": derived.loc[:, list(V1_PREDICTORS)].copy(),
        "target": target.copy(),
        "range_allows_readiness_index": True,
    }
