"""Six-section v2 scorer. Isolated from Laravel QuestionnaireScorer.

Rules (updated scoring PDF, section 3):
- Each section mean needs all ten integer answers in 1–5. Missing answers
  are not imputed as Neutral.
- Items are positively worded. No UCC reverse scoring is applied.
- Classification uses the unrounded mean against 1.80, 2.60, 3.40, 4.20.
- There is no all-60 total.
- An A/S/B mean at or below 2.60 is a questionnaire-context support flag,
  not a trained dropout prediction.
- E is a descriptive self-assessment and is never an employability predictor.
"""

from __future__ import annotations

from typing import Any, Mapping

from src.questionnaire.v2_instrument import (
    ALL_ITEM_CODES,
    BAND_CUTS,
    CONTEXT_FLAG_AT_OR_BELOW,
    CONTEXT_SECTIONS,
    ITEM_BY_CODE,
    ITEMS_PER_SECTION,
    LIKERT_MAX,
    LIKERT_MIN,
    REVERSE_SCORED_CODES,
    SECTIONS,
    VERSION,
)


class QuestionnaireScoringError(ValueError):
    """Invalid v2 questionnaire payload."""


def _is_likert_integer(value: Any) -> bool:
    if isinstance(value, bool) or value is None:
        return False
    if isinstance(value, int):
        return LIKERT_MIN <= value <= LIKERT_MAX
    return False


def _classify(mean: float) -> str:
    for cutoff, label in BAND_CUTS:
        if mean <= cutoff:
            return label
    raise QuestionnaireScoringError(f"Mean {mean} is outside the 1–5 scale.")


def _readiness_index(mean: float) -> float:
    return ((mean - LIKERT_MIN) / (LIKERT_MAX - LIKERT_MIN)) * 100.0


def _as_answer_map(answers: Mapping[str, Any] | list[tuple[str, Any]]) -> dict[str, Any]:
    if isinstance(answers, Mapping):
        payload = dict(answers)
    else:
        payload = {}
        seen = []
        for item in answers:
            if not isinstance(item, (tuple, list)) or len(item) != 2:
                raise QuestionnaireScoringError("Answer entries must be (code, value) pairs.")
            code, value = item
            if code in payload:
                seen.append(code)
            payload[code] = value
        if seen:
            raise QuestionnaireScoringError(f"Duplicate item codes: {sorted(set(seen))}.")
    unknown = sorted(str(code) for code in payload if code not in ITEM_BY_CODE)
    if unknown:
        raise QuestionnaireScoringError(f"Unknown item codes: {unknown}.")
    return payload


def _section_result(section: dict, payload: Mapping[str, Any]) -> dict[str, Any]:
    codes = list(section["item_codes"])
    present = []
    missing = []
    invalid = []
    for code in codes:
        if code not in payload:
            missing.append(code)
            continue
        value = payload[code]
        if not _is_likert_integer(value):
            invalid.append({"code": code, "value": value})
            continue
        present.append((code, int(value)))

    complete = (
        len(present) == ITEMS_PER_SECTION
        and not missing
        and not invalid
        and len({code for code, _value in present}) == ITEMS_PER_SECTION
    )
    result: dict[str, Any] = {
        "section": section["key"],
        "code": section["code"],
        "label": section["label"],
        "mean_name": section["mean_name"],
        "role": section["role"],
        "construct": section["construct"],
        "required_items": ITEMS_PER_SECTION,
        "complete": complete,
        "missing_codes": missing,
        "invalid_responses": invalid,
        "reverse_scored": False,
        "imputed": False,
        "mean": None,
        "sum": None,
        "level": None,
        "readiness_index": None,
        "context_flag": False,
        "context_flag_note": None,
    }
    if not complete:
        result["status"] = "incomplete"
        return result

    values = [value for _code, value in present]
    total = int(sum(values))
    mean = total / ITEMS_PER_SECTION
    level = _classify(mean)
    context_section = section["key"] in CONTEXT_SECTIONS
    context_flag = context_section and mean <= CONTEXT_FLAG_AT_OR_BELOW
    result.update(
        {
            "status": "complete",
            "mean": mean,
            "sum": total,
            "level": level,
            "readiness_index": _readiness_index(mean),
            "context_flag": context_flag,
            "context_flag_note": (
                "Provisional questionnaire-context support concern. "
                "Not a trained dropout prediction or calibrated probability."
                if context_flag
                else None
            ),
        }
    )
    if section["code"] == "B" and mean > CONTEXT_FLAG_AT_OR_BELOW:
        result["engagement_note"] = (
            "A higher Behavioral mean indicates higher self-reported engagement, not higher risk."
        )
    if section["code"] == "E":
        result["predictor_note"] = (
            "Self-perceived employability is a descriptive comparison. "
            "It is never a predictor of the Pianda employability outcome."
        )
    return result


def score_v2(answers: Mapping[str, Any] | list[tuple[str, Any]]) -> dict[str, Any]:
    """Score six independent 10-item means. Does not return an all-60 total."""
    if REVERSE_SCORED_CODES:
        raise QuestionnaireScoringError("v2 items are positively worded; reverse scoring is not defined.")
    payload = _as_answer_map(answers)
    sections = [_section_result(section, payload) for section in SECTIONS]
    completed = [row for row in sections if row["complete"]]
    return {
        "instrument_version": VERSION,
        "psychometrically_validated": False,
        "panel_reapproved": False,
        "reverse_scoring_applied": False,
        "aggregate_total": None,
        "aggregate_total_note": (
            "No all-60 total is computed. Section means are not interchangeable "
            "with Pianda or UCI source features."
        ),
        "answered_item_count": sum(
            ITEMS_PER_SECTION if row["complete"] else ITEMS_PER_SECTION - len(row["missing_codes"]) - len(row["invalid_responses"])
            for row in sections
        ),
        "complete_section_count": len(completed),
        "sections": {row["section"]: row for row in sections},
        "means": {row["mean_name"]: row["mean"] for row in sections},
        "levels": {row["mean_name"]: row["level"] for row in sections},
        "unused_item_codes": sorted(code for code in payload if code not in ALL_ITEM_CODES),
    }


def section_mean(answers: Mapping[str, Any], section_key: str) -> float | None:
    result = score_v2(answers)
    row = result["sections"][section_key]
    return row["mean"]
