"""M42 mapping completeness and leakage rules. No fabricated exact matches."""

from __future__ import annotations

from src.m42.mapping import (
    ITEM_MAP,
    assert_mapping_complete,
    construct_summary,
    questionnaire_rows,
)
from src.questionnaire.v2_instrument import ALL_ITEM_CODES, ITEMS, VERSION


def test_all_sixty_items_are_mapped_and_none_are_exact():
    assert_mapping_complete()
    rows = questionnaire_rows()
    assert VERSION == "ucc-panel-revised-60-v2"
    assert [row["code"] for row in rows] == list(ALL_ITEM_CODES)
    assert len(rows) == 60
    assert all(row["statement"] for row in rows)
    assert all(row["matching_status"] in {"conceptual", "none"} for row in rows)
    assert not any(row["matching_status"] == "exact" for row in rows)
    assert set(ITEM_MAP) == set(ALL_ITEM_CODES)


def test_statements_match_the_locked_v2_catalog():
    by_code = {item["code"]: item["text"] for item in ITEMS}
    for row in questionnaire_rows():
        assert row["statement"] == by_code[row["code"]]


def test_e_is_never_a_predictor_and_asb_cannot_enter_models():
    rows = {row["code"]: row for row in questionnaire_rows()}
    for code in ALL_ITEM_CODES:
        if code.startswith("E"):
            assert rows[code]["model_use"] == "no"
            assert rows[code]["leakage"] == "target_adjacent"
        if code[0] in {"A", "S", "B"}:
            assert rows[code]["model_use"] == "no"
            assert rows[code]["matching_status"] == "none"


def test_c_and_p_remain_pending_despite_ten_competence_items():
    summary = {row["group"]: row for row in construct_summary()}
    assert summary["C"]["items"] == 10
    assert summary["C"]["source_items"] == 10
    assert summary["C"]["mapping_validated"] is False
    assert summary["C"]["model_use"] == "pending"
    assert summary["P"]["items"] == 10
    assert summary["P"]["source_items"] == 14
    assert summary["P"]["model_use"] == "pending"
    assert summary["E"]["model_use"] == "no"
