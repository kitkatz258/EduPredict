"""Fail-closed UCC academic contract for D1.

Application token rules below mirror ``GradeScale`` so a fixture can be checked.
They are not a registrar policy, and they are not UCI curricular-unit features.
UCI column names are never returned.
"""

from __future__ import annotations

from dataclasses import dataclass
from decimal import Decimal, InvalidOperation
from typing import Mapping

OPERATIONAL_LABEL = "local_operational_count"
UCI_EQUIVALENT = False

_NUMERIC_SCALE = (
    Decimal("1.00"),
    Decimal("1.25"),
    Decimal("1.50"),
    Decimal("1.75"),
    Decimal("2.00"),
    Decimal("2.25"),
    Decimal("2.50"),
    Decimal("2.75"),
    Decimal("3.00"),
    Decimal("5.00"),
)
_PASSING_MAX = Decimal("3.00")
_FAILING = Decimal("5.00")
_INCOMPLETE = frozenset({"INC", "INCOMPLETE"})
_UNOFFICIAL = frozenset({"UD", "UNOFFICIAL", "UNOFFICIALLYDROPPED"})
_DROPPED = frozenset({"DRP", "W", "WITHDRAWN", "DROPPED", "DROP"})
_AWARDED = frozenset({"partial", "full", "government", "private"})
_REPEAT_MODES = frozenset({"every_row", "distinct_code"})
_NON_UCI_TERMS = frozenset({"midyear", "summer"})


class UnresolvedAcademicRule(ValueError):
    """The mapping is not defined well enough to compute or to export."""


@dataclass(frozen=True)
class SubjectRow:
    subject_code: str
    units: Decimal | str | int | float | None
    final_grade: str
    remarks: str = ""


@dataclass(frozen=True)
class CountPolicy:
    """Inclusions that must be chosen explicitly. ``None`` fails closed."""

    include_incomplete: bool | None = None
    include_dropped: bool | None = None
    include_unofficially_dropped: bool | None = None
    include_failed: bool | None = None
    include_nstp: bool | None = None
    repeat_mode: str | None = None

    def require_resolved(self) -> None:
        missing = [
            name
            for name in (
                "include_incomplete",
                "include_dropped",
                "include_unofficially_dropped",
                "include_failed",
                "include_nstp",
            )
            if getattr(self, name) is None
        ]
        if missing or self.repeat_mode not in _REPEAT_MODES:
            raise UnresolvedAcademicRule(
                "Course-count policy is unresolved "
                f"({', '.join(missing) or 'repeat_mode'}). "
                "Refusing to guess inclusions or to emit UCI enrolled/approved columns."
            )


@dataclass(frozen=True)
class OperationalCounts:
    course_row_count: int
    credit_unit_sum: Decimal
    passed_course_count: int
    passed_credit_unit_sum: Decimal
    by_status: Mapping[str, int]
    label: str = OPERATIONAL_LABEL
    uci_equivalent: bool = UCI_EQUIVALENT

    def as_uci_columns(self) -> dict[str, int]:
        raise UnresolvedAcademicRule(
            "Local course counts and credit-unit sums are not UCI curricular units. "
            "Enrolled, approved, evaluations, credited, and grade columns stay blocked."
        )


@dataclass(frozen=True)
class TermRecord:
    degree_term_index: int | None
    semester: str | None = None
    school_year: str | None = None
    term_type: str | None = None
    year_level: int | None = None
    enrollment_year: int | None = None


@dataclass(frozen=True)
class TermSelection:
    index: int
    record: TermRecord | None
    status: str
    reason: str

    def as_uci_columns(self) -> dict[str, int]:
        raise UnresolvedAcademicRule(
            "An explicit degree-term index does not authorize UCI enrolled or approved values. "
            f"Selection status is {self.status}."
        )


@dataclass(frozen=True)
class ScholarshipClassification:
    state: str
    uci_value: None = None

    def as_uci_binary(self) -> int:
        raise UnresolvedAcademicRule(
            f"Scholarship state {self.state!r} is not UCI Scholarship holder. "
            "Missing, draft, blank, reported none, and reported award are not coerced to 0 or 1."
        )


def _compact(token: str | None) -> str:
    return "".join(str(token or "").upper().split())


def _numeric_grade(token: str) -> Decimal | None:
    try:
        value = Decimal(token)
    except InvalidOperation:
        return None
    quantized = value.quantize(Decimal("0.01"))
    if quantized in _NUMERIC_SCALE:
        return quantized
    return None


def classify_row(row: SubjectRow) -> str:
    """Classify one stored grade token the way the application already does.

    ``unofficially_dropped`` is separated from ``dropped`` so a later policy can
    choose. ``GwaCalculator`` currently counts both as dropped.
    """

    grade = _compact(row.final_grade)
    remarks = _compact(row.remarks)
    tokens = (grade, remarks)
    if any(token in _INCOMPLETE for token in tokens):
        return "incomplete"
    if any(token in _UNOFFICIAL for token in tokens):
        return "unofficially_dropped"
    if any(token in _DROPPED for token in tokens):
        return "dropped"
    if "FAILED" in tokens:
        return "failed"
    numeric = _numeric_grade(grade)
    if numeric == _FAILING:
        return "failed"
    if numeric is not None and numeric <= _PASSING_MAX:
        return "passed"
    return "unreadable"


def _units(row: SubjectRow) -> Decimal:
    if row.units is None or (isinstance(row.units, str) and row.units.strip() == ""):
        raise UnresolvedAcademicRule(
            f"Units are missing for {row.subject_code or '(blank code)'}. "
            "A missing credit value is not zero."
        )
    try:
        value = Decimal(str(row.units))
    except InvalidOperation as exc:
        raise UnresolvedAcademicRule(
            f"Units for {row.subject_code or '(blank code)'} are not numeric."
        ) from exc
    return value.quantize(Decimal("0.01"))


def _is_nstp(code: str) -> bool:
    return code.upper().strip().startswith("NSTP")


def _included(status: str, code: str, policy: CountPolicy) -> bool:
    if status == "incomplete" and not policy.include_incomplete:
        return False
    if status == "dropped" and not policy.include_dropped:
        return False
    if status == "unofficially_dropped" and not policy.include_unofficially_dropped:
        return False
    if status == "failed" and not policy.include_failed:
        return False
    if status == "unreadable":
        raise UnresolvedAcademicRule(
            "A row has no readable final grade. It is not counted as enrolled or approved."
        )
    if _is_nstp(code) and not policy.include_nstp:
        return False
    return True


def operational_counts(rows: list[SubjectRow], policy: CountPolicy) -> OperationalCounts:
    """Count subject rows and sum credit units under an explicit policy.

    The two numbers are different measurements. Neither is written to a UCI column.
    """

    policy.require_resolved()
    included: list[tuple[SubjectRow, str, Decimal]] = []
    by_status = {
        "passed": 0,
        "failed": 0,
        "incomplete": 0,
        "dropped": 0,
        "unofficially_dropped": 0,
    }
    for row in rows:
        code = row.subject_code.strip()
        if code == "" and _compact(row.final_grade) == "":
            continue
        status = classify_row(row)
        if not _included(status, code, policy):
            continue
        by_status[status] = by_status.get(status, 0) + 1
        included.append((row, status, _units(row)))

    if policy.repeat_mode == "distinct_code":
        seen: dict[str, str] = {}
        for row, status, _units_value in included:
            code = row.subject_code.strip().upper()
            previous = seen.get(code)
            if previous is not None and previous != status:
                raise UnresolvedAcademicRule(
                    f"Repeat subject {code} has conflicting statuses ({previous}, {status}). "
                    "No attempt-selection rule is confirmed."
                )
            if previous is not None:
                raise UnresolvedAcademicRule(
                    f"Repeat subject {code} appears more than once. "
                    "Counting distinct codes would drop an attempt without a confirmed rule."
                )
            seen[code] = status

    credit = sum((units for _row, _status, units in included), Decimal("0.00"))
    passed = [(row, units) for row, status, units in included if status == "passed"]
    passed_credit = sum((units for _row, units in passed), Decimal("0.00"))
    return OperationalCounts(
        course_row_count=len(included),
        credit_unit_sum=credit.quantize(Decimal("0.01")),
        passed_course_count=len(passed),
        passed_credit_unit_sum=passed_credit.quantize(Decimal("0.01")),
        by_status=by_status,
    )


def select_degree_term(records: list[TermRecord], index: int) -> TermSelection:
    """Return a term only when ``degree_term_index`` is stored on the record.

    ``semester``, ``year_level``, ``enrollment_year``, and list order are ignored.
    A missing term stays missing. It is not filled with another report or with zero.
    """

    if index < 1:
        raise UnresolvedAcademicRule("Degree-term index must be a positive integer.")
    explicit = [record for record in records if record.degree_term_index == index]
    if not explicit:
        return TermSelection(
            index=index,
            record=None,
            status="missing",
            reason=(
                "No stored degree_term_index matches this term. "
                "semester, year_level, enrollment_year, and upload order were not used."
            ),
        )
    if len(explicit) > 1:
        return TermSelection(
            index=index,
            record=None,
            status="ambiguous",
            reason="More than one record claims this degree_term_index.",
        )
    record = explicit[0]
    term_type = (record.term_type or "").strip().lower()
    semester = (record.semester or "").strip().lower()
    if term_type in _NON_UCI_TERMS or semester in _NON_UCI_TERMS:
        return TermSelection(
            index=index,
            record=record,
            status="not_a_uci_semester",
            reason="Midyear and Summer are not UCI first or second semester columns.",
        )
    return TermSelection(
        index=index,
        record=record,
        status="explicit",
        reason="Matched an explicit degree_term_index. The semester label was not the index.",
    )


def assert_source_timing(model_timing: str, explicit_indexes: set[int]) -> None:
    """Reject a source timing when the required degree terms are not explicit.

    Passing this check still does not authorize UCI values.
    """

    if model_timing == "after_first_semester":
        required = {1}
    elif model_timing == "after_second_semester":
        required = {1, 2}
    else:
        raise UnresolvedAcademicRule(f"Unknown source timing {model_timing!r}.")
    missing = sorted(required - set(explicit_indexes))
    if missing:
        raise UnresolvedAcademicRule(
            "Source timing "
            f"{model_timing} needs explicit degree terms {sorted(required)}. "
            f"Missing {missing}. Second-semester columns are leakage before term 2."
        )


def classify_scholarship(
    *,
    profile_present: bool,
    is_draft: bool | None = None,
    status: str | None = None,
    has_scholarship: bool | None = None,
) -> ScholarshipClassification:
    """Classify a local scholarship record without coercing it to 0 or 1.

    ``has_scholarship`` is accepted and ignored. The feature builder's boolean
    false is not evidence of "no scholarship".
    """

    del has_scholarship
    if not profile_present:
        return ScholarshipClassification("missing")
    if is_draft is None:
        raise UnresolvedAcademicRule("Draft versus published is unknown. Refusing to read the status.")
    if is_draft:
        return ScholarshipClassification("draft")
    if status is None or str(status).strip() == "":
        return ScholarshipClassification("blank")
    token = str(status).strip()
    if token == "none":
        return ScholarshipClassification("reported_none")
    if token in _AWARDED:
        return ScholarshipClassification("reported_award")
    return ScholarshipClassification("unrecognized")
