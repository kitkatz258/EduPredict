"""D1 fixtures. Local counts are not UCI features, and missing scholarship is not zero."""

from decimal import Decimal

import pytest

from src.d1.contract import (
    CountPolicy,
    SubjectRow,
    TermRecord,
    UnresolvedAcademicRule,
    assert_source_timing,
    classify_row,
    classify_scholarship,
    operational_counts,
    select_degree_term,
)

# Illustrative only. Not a UCC-approved inclusion rule.
ALL_ROWS = CountPolicy(
    include_incomplete=True,
    include_dropped=True,
    include_unofficially_dropped=True,
    include_failed=True,
    include_nstp=True,
    repeat_mode="every_row",
)

PORTAL_ROWS = [
    SubjectRow("CCIS101", Decimal("3.00"), "1.50", "PASSED"),
    SubjectRow("CCIS102", Decimal("3.00"), "5.00", "FAILED"),
    SubjectRow("NSTP1", Decimal("3.00"), "1.75", "PASSED"),
    SubjectRow("PATHFIT1", Decimal("2.00"), "INC", "INCOMPLETE"),
    SubjectRow("CCIS103", Decimal("1.00"), "DRP", "DROPPED"),
]


def test_course_count_is_not_the_credit_unit_sum():
    counts = operational_counts(PORTAL_ROWS, ALL_ROWS)

    assert counts.course_row_count == 5
    assert counts.credit_unit_sum == Decimal("12.00")
    assert counts.passed_course_count == 2
    assert counts.passed_credit_unit_sum == Decimal("6.00")
    assert counts.uci_equivalent is False
    assert counts.label == "local_operational_count"
    with pytest.raises(UnresolvedAcademicRule, match="not UCI"):
        counts.as_uci_columns()


def test_lecture_plus_lab_is_two_courses_and_four_credit_units():
    rows = [
        SubjectRow("IT101", Decimal("3.00"), "1.75"),
        SubjectRow("IT101L", Decimal("1.00"), "2.00"),
    ]

    counts = operational_counts(rows, ALL_ROWS)

    assert counts.course_row_count == 2
    assert counts.credit_unit_sum == Decimal("4.00")
    assert counts.passed_course_count == 2
    assert counts.passed_credit_unit_sum == Decimal("4.00")


def test_unresolved_inclusion_policy_fails_closed():
    with pytest.raises(UnresolvedAcademicRule, match="unresolved"):
        operational_counts(PORTAL_ROWS, CountPolicy())


def test_excluding_nstp_changes_both_count_and_sum():
    policy = CountPolicy(
        include_incomplete=True,
        include_dropped=True,
        include_unofficially_dropped=True,
        include_failed=True,
        include_nstp=False,
        repeat_mode="every_row",
    )

    counts = operational_counts(PORTAL_ROWS, policy)

    assert counts.course_row_count == 4
    assert counts.credit_unit_sum == Decimal("9.00")
    assert counts.passed_course_count == 1
    assert counts.passed_credit_unit_sum == Decimal("3.00")


def test_repeat_codes_are_not_collapsed_without_a_rule():
    rows = [
        SubjectRow("CCIS101", Decimal("3.00"), "5.00"),
        SubjectRow("CCIS101", Decimal("3.00"), "2.00"),
    ]
    policy = CountPolicy(True, True, True, True, True, "distinct_code")

    with pytest.raises(UnresolvedAcademicRule, match="Repeat subject"):
        operational_counts(rows, policy)

    every_attempt = operational_counts(rows, ALL_ROWS)
    assert every_attempt.course_row_count == 2
    assert every_attempt.credit_unit_sum == Decimal("6.00")
    assert every_attempt.passed_course_count == 1


def test_missing_units_are_not_zero():
    rows = [SubjectRow("CCIS101", None, "1.50")]

    with pytest.raises(UnresolvedAcademicRule, match="not zero"):
        operational_counts(rows, ALL_ROWS)


def test_ud_is_not_a_failure_and_inc_is_not_approved():
    assert classify_row(SubjectRow("CCIS101", "3.00", "UD")) == "unofficially_dropped"
    assert classify_row(SubjectRow("CCIS101", "3.00", "W", "WITHDRAWN")) == "dropped"
    assert classify_row(SubjectRow("CCIS101", "3.00", "INC")) == "incomplete"
    assert classify_row(SubjectRow("NSTP1", "3.00", "5.00")) == "failed"


def test_semester_first_is_not_degree_term_one():
    records = [
        TermRecord(
            degree_term_index=None,
            semester="First",
            school_year="2025-2026",
            year_level=1,
            enrollment_year=2026,
        )
    ]

    selection = select_degree_term(records, 1)

    assert selection.status == "missing"
    assert selection.record is None
    with pytest.raises(UnresolvedAcademicRule, match="does not authorize"):
        selection.as_uci_columns()


def test_missing_earlier_term_is_not_backfilled():
    records = [
        TermRecord(degree_term_index=2, semester="Second", school_year="2025-2026", term_type="second")
    ]

    first = select_degree_term(records, 1)
    second = select_degree_term(records, 2)

    assert first.status == "missing"
    assert second.status == "explicit"
    assert second.record is not None
    assert second.record.degree_term_index == 2
    with pytest.raises(UnresolvedAcademicRule, match="leakage"):
        assert_source_timing("after_second_semester", {2})
    with pytest.raises(UnresolvedAcademicRule, match="does not authorize"):
        second.as_uci_columns()


def test_midyear_is_not_a_uci_semester_even_with_an_index():
    records = [TermRecord(degree_term_index=1, semester="Midyear", term_type="midyear")]

    selection = select_degree_term(records, 1)

    assert selection.status == "not_a_uci_semester"


def test_duplicate_term_index_is_ambiguous():
    records = [
        TermRecord(degree_term_index=1, semester="First", term_type="first"),
        TermRecord(degree_term_index=1, semester="First", term_type="first"),
    ]

    assert select_degree_term(records, 1).status == "ambiguous"


def test_explicit_timing_check_does_not_emit_uci_values():
    assert_source_timing("after_first_semester", {1})
    assert_source_timing("after_second_semester", {1, 2})
    with pytest.raises(UnresolvedAcademicRule, match="Missing"):
        assert_source_timing("after_first_semester", set())


@pytest.mark.parametrize(
    ("kwargs", "state"),
    [
        ({"profile_present": False, "has_scholarship": False}, "missing"),
        ({"profile_present": True, "is_draft": True, "status": "full"}, "draft"),
        ({"profile_present": True, "is_draft": False, "status": None}, "blank"),
        ({"profile_present": True, "is_draft": False, "status": "none"}, "reported_none"),
        ({"profile_present": True, "is_draft": False, "status": "government"}, "reported_award"),
        ({"profile_present": True, "is_draft": False, "status": "unknown"}, "unrecognized"),
        ({"profile_present": True, "is_draft": False, "status": "0"}, "unrecognized"),
        ({"profile_present": True, "is_draft": False, "status": "expired"}, "unrecognized"),
    ],
)
def test_scholarship_states_never_become_binary(kwargs, state):
    classified = classify_scholarship(**kwargs)

    assert classified.state == state
    assert classified.uci_value is None
    with pytest.raises(UnresolvedAcademicRule, match="not coerced"):
        classified.as_uci_binary()


def test_unknown_draft_flag_is_refused():
    with pytest.raises(UnresolvedAcademicRule, match="Draft versus published"):
        classify_scholarship(profile_present=True, is_draft=None, status="none")
