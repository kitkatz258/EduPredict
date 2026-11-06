"""M42 feature-schema contracts. UCC inference is rejected until mapping is validated."""

from __future__ import annotations

from typing import Any, Mapping

import pandas as pd

from src.data.employability import COMPETENCE_MEAN, PSYCAP_MEAN, V1_PREDICTORS
from src.features.schemas import FrameSchema
from src.questionnaire.v2_instrument import ALL_ITEM_CODES

UCC_BLOCKED_KEYS = frozenset(
    list(ALL_ITEM_CODES)
    + [
        "AcademicMean",
        "SocioeconomicMean",
        "BehavioralMean",
        "SelfPerceivedEmployabilityMean",
        "ucc_competence_mean",
        "ucc_psycap_mean",
        "gwa",
        "major_gwa",
        "other_gwa",
        "philippine_gwa",
        "final_grade",
        "has_scholarship",
        "scholarship_status",
        "failed_subjects",
        "incomplete_subjects",
        "dropped_subjects",
        "semesters_completed",
        "major_units",
        "other_units",
        "internship_count",
        "technical_skill_count",
        "certification_count",
        "work_experience_count",
        "study_habits",
        "time_management",
        "motivation",
        "procrastination",
        "engagement",
        "A",
        "S",
        "B",
        "C",
        "P",
        "E",
    ]
)

PIANDA_CP_COLUMNS = list(V1_PREDICTORS)
PIANDA_CP_INTERNSHIP_COLUMNS = [COMPETENCE_MEAN, PSYCAP_MEAN, "InternshipMean"]

FIRST_SEM_UNITS = (
    "Curricular units 1st sem (enrolled)",
    "Curricular units 1st sem (approved)",
)
TWO_SEM_UNITS = FIRST_SEM_UNITS + (
    "Curricular units 2nd sem (enrolled)",
    "Curricular units 2nd sem (approved)",
)
SCHOLARSHIP_ONLY = ("Scholarship holder",)
GRADE_COLUMNS = (
    "Previous qualification (grade)",
    "Admission grade",
    "Curricular units 1st sem (grade)",
    "Curricular units 2nd sem (grade)",
)

CONTRACTS = {
    "pianda-cp-reduced-m42": {
        "task": "employability_regression",
        "columns": PIANDA_CP_COLUMNS,
        "value_rule": "likert_mean",
        "ucc_inference": "blocked",
        "reason": (
            "Source-native Pianda CompetenceMean (C1–C10) and PsyCapMean "
            "(PsyCap1–PsyCap14). UCC 10-item C/P means are not measurement-equivalent."
        ),
        "status": "allowed_on_source_schema_only",
    },
    "pianda-cp-internship-expanded-m42": {
        "task": "employability_regression",
        "columns": PIANDA_CP_INTERNSHIP_COLUMNS,
        "value_rule": "likert_mean",
        "ucc_inference": "blocked",
        "reason": (
            "Expanded source-native comparison. InternshipMean is the Pianda SIE1–SIE14 "
            "mean. EduPredict stores an internship count, not that 14-item scale."
        ),
        "status": "allowed_on_source_schema_only",
        "deployment_candidate": False,
    },
    "uci-scholarship-only-m42": {
        "task": "dropout_classification",
        "columns": list(SCHOLARSHIP_ONLY),
        "value_rule": "binary01",
        "ucc_inference": "blocked",
        "reason": (
            "UCI Scholarship holder is 0/1. EduPredict scholarship_status categories "
            "and missing-profile coercion are not verified equivalents."
        ),
        "status": "pending_mapping",
    },
    "uci-first-sem-units-m42": {
        "task": "dropout_classification",
        "columns": list(FIRST_SEM_UNITS),
        "value_rule": "any",
        "ucc_inference": "blocked",
        "reason": (
            "Source-native first-semester enrolled and approved unit counts. "
            "UCC subject-unit totals have no verified first-semester index or "
            "approval definition. Grade columns are excluded."
        ),
        "status": "pending_unit_definition",
        "timing": "after_first_semester",
    },
    "uci-two-sem-units-m42": {
        "task": "dropout_classification",
        "columns": list(TWO_SEM_UNITS),
        "value_rule": "any",
        "ucc_inference": "blocked",
        "reason": (
            "Source-native first- and second-semester enrolled and approved counts. "
            "Using second-semester results for an earlier prediction is leakage. "
            "UCC term identity is unverified. Grade columns are excluded."
        ),
        "status": "pending_unit_definition",
        "timing": "after_second_semester",
    },
}


class CompatibilityError(ValueError):
    """Payload is not a permitted source-schema research input."""


def _blocked_keys(payload: Mapping[str, Any]) -> list[str]:
    found = []
    for key in payload:
        if str(key) in UCC_BLOCKED_KEYS:
            found.append(str(key))
    return found


def validate_source_frame(contract_id: str, frame: pd.DataFrame) -> pd.DataFrame:
    if contract_id not in CONTRACTS:
        raise CompatibilityError(f"Unknown M42 contract: {contract_id}.")
    contract = CONTRACTS[contract_id]
    extra_blocked = [column for column in frame.columns if str(column) in UCC_BLOCKED_KEYS]
    if extra_blocked:
        raise CompatibilityError(
            "UCC or leakage fields are not accepted on this research contract: "
            + ", ".join(extra_blocked)
        )
    if any(column in GRADE_COLUMNS for column in frame.columns):
        raise CompatibilityError("UCI/UCC grade columns are excluded from M42 reduced contracts.")
    schema = FrameSchema(contract["columns"], value_rule=contract["value_rule"])
    return schema.fit_transform(frame)


def reject_ucc_probe(contract_id: str, payload: Mapping[str, Any]) -> None:
    """Explicitly refuse UCC-style fixtures. Synthetic tests must expect this failure."""
    blocked = _blocked_keys(payload)
    if blocked:
        raise CompatibilityError(
            "UCC inference is blocked. Matching 1–5 arithmetic does not establish "
            f"construct equivalence. Blocked keys: {blocked}."
        )
    origin = str(payload.get("input_origin", "")).casefold()
    if origin.startswith("ucc") or "questionnaire" in origin:
        raise CompatibilityError("UCC-origin payloads are rejected until mapping is validated.")
    frame = pd.DataFrame([ {key: value for key, value in payload.items() if key != "input_origin"} ])
    validate_source_frame(contract_id, frame)
    raise CompatibilityError(
        "Even a numeric payload labeled as UCC C/P cannot be scored: source-vs-UCC "
        "scale equivalence is pending. This probe refuses deployment inference."
    )
