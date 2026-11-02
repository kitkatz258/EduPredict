"""Strict source-native input checks for M42 research scoring."""

from __future__ import annotations

import math
from typing import Any, Mapping

from api.boundaries import blocked_keys_in, is_blocked_key, schema_is_ucc
from api.errors import InferenceError
from api.support import SourceSupport, load_support
from src.common import LIKERT_MAX, LIKERT_MIN
from src.m42.compatibility import GRADE_COLUMNS, UCC_BLOCKED_KEYS
from src.m43.catalog import ArtifactSpec
from src.m43.errors import ResearchInferenceError

_UCC_MESSAGE = (
    "UCC questionnaire scores, grades, student identifiers, and local snapshot "
    "fields are not inputs to M42 research models."
)
_SCHEMA_MESSAGE = "The research payload does not match the source-native schema for this artifact."
_TYPE_MESSAGE = "Research inputs must be finite JSON numbers. Booleans and strings are rejected."
_RANGE_MESSAGE = "A Likert mean must stay inside [1, 5]."
_UNSUPPORTED_MESSAGE = "This field is not part of the M42 source-native contract."


def validate_features(
    spec: ArtifactSpec,
    features: Mapping[str, Any],
    support: SourceSupport | None = None,
) -> dict[str, int | float]:
    if not isinstance(features, dict):
        raise ResearchInferenceError("invalid_input", "Features must be an object of source columns.")
    _reject_ucc(features)
    missing = [column for column in spec.columns if column not in features]
    extra = [key for key in features if key not in spec.columns]
    grade_extra = [key for key in extra if key in GRADE_COLUMNS]
    if grade_extra:
        raise ResearchInferenceError(
            "unsupported_schema",
            "UCI grade columns are excluded from M42 reduced contracts. "
            "Philippine GWA is not converted to the Portuguese 0-20 scale.",
        )
    if missing or extra:
        raise ResearchInferenceError("schema_mismatch", _SCHEMA_MESSAGE)
    helper = support if support is not None else load_support()
    cleaned: dict[str, int | float] = {}
    for column in spec.columns:
        cleaned[column] = _coerce_value(spec, helper, column, features[column])
    return cleaned


def _reject_ucc(payload: Mapping[str, Any]) -> None:
    blocked = blocked_keys_in(payload)
    for key in payload:
        if str(key) in UCC_BLOCKED_KEYS or is_blocked_key(key):
            blocked.append(str(key))
    if blocked:
        raise ResearchInferenceError("ucc_input_rejected", _UCC_MESSAGE)
    schema_id = payload.get("schema_id")
    if schema_is_ucc(schema_id):
        raise ResearchInferenceError("ucc_input_rejected", _UCC_MESSAGE)
    origin = str(payload.get("input_origin", "")).casefold()
    if origin.startswith("ucc") or "questionnaire" in origin:
        raise ResearchInferenceError("ucc_input_rejected", _UCC_MESSAGE)


def _coerce_value(
    spec: ArtifactSpec,
    support: SourceSupport,
    column: str,
    value: object,
) -> int | float:
    if spec.value_rule == "likert_mean":
        return _likert_mean(value)
    try:
        return support.coerce(column, value)
    except InferenceError as exc:
        code = exc.code if exc.code in {"invalid_input", "out_of_source_range", "unsupported_source_code"} else "invalid_input"
        raise ResearchInferenceError(code, exc.message) from exc


def _likert_mean(value: object) -> float:
    number = _finite_number(value)
    if number < LIKERT_MIN or number > LIKERT_MAX:
        raise ResearchInferenceError("invalid_input", _RANGE_MESSAGE)
    return number


def _finite_number(value: object) -> float:
    if isinstance(value, bool) or not isinstance(value, (int, float)):
        raise ResearchInferenceError("invalid_input", _TYPE_MESSAGE)
    number = float(value)
    if not math.isfinite(number):
        raise ResearchInferenceError("invalid_input", _TYPE_MESSAGE)
    return number
