"""D1 academic-definition helpers.

Pure checks for course counts, explicit degree-term identity, and scholarship
states. They do not read a database, do not score students, and do not emit
UCI columns.
"""

from src.d1.contract import (
    OPERATIONAL_LABEL,
    CountPolicy,
    OperationalCounts,
    ScholarshipClassification,
    SubjectRow,
    TermRecord,
    TermSelection,
    UnresolvedAcademicRule,
    assert_source_timing,
    classify_row,
    classify_scholarship,
    operational_counts,
    select_degree_term,
)

__all__ = [
    "OPERATIONAL_LABEL",
    "CountPolicy",
    "OperationalCounts",
    "ScholarshipClassification",
    "SubjectRow",
    "TermRecord",
    "TermSelection",
    "UnresolvedAcademicRule",
    "assert_source_timing",
    "classify_row",
    "classify_scholarship",
    "operational_counts",
    "select_degree_term",
]
