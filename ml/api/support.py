"""Observed UCI source support. This is not a UCC grade conversion."""

from __future__ import annotations

import json
import math
from pathlib import Path

from api.errors import InferenceError

SUPPORT_PATH = Path(__file__).resolve().parent / "uci_source_support.json"
_OUT_OF_RANGE = "A research input is outside the audited UCI source range."
_GRADE_RANGE = (
    "A grade column is outside the audited UCI source range. Philippine grades are not converted."
)


class SourceSupport:
    def __init__(self, payload: dict):
        self.dataset_version = str(payload["dataset_version"])
        self.nominal = {name: set(int(code) for code in codes) for name, codes in payload["nominal"].items()}
        self.binary = set(payload["binary"])
        self.numeric = payload["numeric"]

    def classify(self, column: str) -> str:
        if column in self.nominal:
            return "nominal"
        if column in self.binary:
            return "binary"
        if column in self.numeric:
            return "numeric"
        raise InferenceError(
            "models_unavailable",
            "A research model column has no audited source support.",
            503,
        )

    def coerce(self, column: str, value: object) -> int | float:
        number = _finite_number(value)
        kind = self.classify(column)
        if kind == "binary":
            if number not in (0.0, 1.0):
                raise InferenceError("invalid_input", "Binary UCI fields must be 0 or 1.", 422)
            return int(number)
        if kind == "nominal":
            if not number.is_integer():
                raise InferenceError("invalid_input", "Coded UCI fields must be whole numbers.", 422)
            code = int(number)
            if code not in self.nominal[column]:
                raise InferenceError(
                    "unsupported_source_code",
                    "A coded column is outside the UCI codes observed in the research file.",
                    422,
                )
            return code
        spec = self.numeric[column]
        if spec["integral"] and not number.is_integer():
            raise InferenceError("invalid_input", "This UCI field must be a whole number.", 422)
        low = float(spec["min"])
        high = float(spec["max"])
        if number < low - 1e-9 or number > high + 1e-9:
            message = _GRADE_RANGE if "grade" in column.casefold() else _OUT_OF_RANGE
            raise InferenceError("out_of_source_range", message, 422)
        if spec["integral"]:
            return int(number)
        return float(number)


def load_support(path: Path = SUPPORT_PATH) -> SourceSupport:
    payload = json.loads(path.read_text(encoding="utf-8"))
    return SourceSupport(payload)


def _finite_number(value: object) -> float:
    if isinstance(value, bool) or not isinstance(value, (int, float)):
        raise InferenceError("invalid_input", "Research inputs must be JSON numbers.", 422)
    number = float(value)
    if not math.isfinite(number):
        raise InferenceError("invalid_input", "Research inputs must be finite numbers.", 422)
    return number
