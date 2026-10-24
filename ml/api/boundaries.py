"""Fields that must not be sent to the Pianda or UCI research models."""

from __future__ import annotations

import re

# Student assessment names and UCC item codes. UCI source columns are not in this list.
BLOCKED_KEYS = (
    "gwa",
    "major_gwa",
    "other_gwa",
    "failed_subjects",
    "major_failed_subjects",
    "other_failed_subjects",
    "major_units",
    "other_units",
    "semesters_completed",
    "limited_history",
    "scholarship_status",
    "has_scholarship",
    "employment_status",
    "income_bracket",
    "household_size",
    "living_arrangement",
    "internet_access",
    "device_access",
    "study_space",
    "technical_skill_count",
    "certification_count",
    "internship_count",
    "project_count",
    "work_experience_count",
    "construct_scores",
    "study_habits",
    "time_management",
    "motivation",
    "procrastination",
    "engagement",
    "academic",
    "socioeconomic",
    "behavioral",
    "competence",
    "psychological_capital",
    "self_perceived_employability",
    "student_number",
    "email",
    "birthdate",
    "first_name",
    "last_name",
    "name",
    "questionnaire",
    "section_mean",
    "competence_section_mean",
    "psycap_section_mean",
    "ucc_competence_mean",
    "ucc_psycap_mean",
    "final_grade",
    "philippine_grade",
    "grade_scale",
    "input_origin",
    "password",
    "token",
    "api_key",
)

ALLOWED_SCHEMA_IDS = frozenset({"pianda-v1", "uci-697"})

_ITEM_CODE = re.compile(r"^(?:a|s|b|c|p|e)\d{1,2}$", re.IGNORECASE)
_EVS_ITEM = re.compile(r"^evs\d+$", re.IGNORECASE)
_PSYCAP_ITEM = re.compile(r"^psycap\d+$", re.IGNORECASE)
_SIE_ITEM = re.compile(r"^sie\d+$", re.IGNORECASE)


def is_blocked_key(key: object) -> bool:
    if not isinstance(key, str):
        return False
    normalized = key.strip().casefold()
    if normalized in BLOCKED_KEYS:
        return True
    return bool(
        _ITEM_CODE.match(normalized)
        or _EVS_ITEM.match(normalized)
        or _PSYCAP_ITEM.match(normalized)
        or _SIE_ITEM.match(normalized)
    )


def blocked_keys_in(payload: object) -> list[str]:
    found: list[str] = []
    if isinstance(payload, dict):
        for key, value in payload.items():
            if is_blocked_key(key):
                found.append(str(key))
            found.extend(blocked_keys_in(value))
    elif isinstance(payload, list):
        for item in payload:
            found.extend(blocked_keys_in(item))
    return found


def schema_is_ucc(schema_id: object) -> bool:
    if not isinstance(schema_id, str):
        return False
    normalized = schema_id.strip().casefold()
    if normalized in ALLOWED_SCHEMA_IDS:
        return False
    return normalized.startswith("ucc") or "questionnaire" in normalized
