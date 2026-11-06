"""Isolated v2 60-item scorer tests. No Laravel, database, or FastAPI."""

from __future__ import annotations

import pytest

from src.questionnaire.v2_instrument import (
    ALL_ITEM_CODES,
    ITEMS,
    ITEMS_PER_SECTION,
    REVERSE_SCORED_CODES,
    SECTIONS,
    VERSION,
)
from src.questionnaire.v2_scorer import QuestionnaireScoringError, score_v2


def _complete_answers(fill: int = 4) -> dict[str, int]:
    return {code: fill for code in ALL_ITEM_CODES}


def _section_codes(key: str) -> tuple[str, ...]:
    return next(section["item_codes"] for section in SECTIONS if section["key"] == key)


def test_instrument_has_sixty_positively_worded_items():
    assert VERSION == "ucc-panel-revised-60-v2"
    assert len(ITEMS) == 60
    assert len(ALL_ITEM_CODES) == 60
    assert len(set(ALL_ITEM_CODES)) == 60
    assert REVERSE_SCORED_CODES == ()
    for section in SECTIONS:
        assert len(section["item_codes"]) == ITEMS_PER_SECTION
        assert section["item_codes"][0].endswith("1")
        assert section["item_codes"][-1].endswith("10")


def test_complete_uniform_answers_score_each_section_independently():
    result = score_v2(_complete_answers(4))
    assert result["aggregate_total"] is None
    assert result["complete_section_count"] == 6
    assert result["reverse_scoring_applied"] is False
    assert result["psychometrically_validated"] is False
    for mean_name, mean in result["means"].items():
        assert mean == 4.0
        assert result["levels"][mean_name] == "High"
    assert result["sections"]["behavioral"]["context_flag"] is False
    assert result["sections"]["self_perceived_employability"]["predictor_note"]


def test_worked_example_from_scoring_pdf():
    answers = _complete_answers(3)
    answers.update(
        {
            "C1": 4, "C2": 5, "C3": 4, "C4": 3, "C5": 4,
            "C6": 4, "C7": 5, "C8": 4, "C9": 4, "C10": 4,
            "P1": 4, "P2": 4, "P3": 5, "P4": 4, "P5": 3,
            "P6": 4, "P7": 4, "P8": 5, "P9": 4, "P10": 4,
            "E1": 4, "E2": 4, "E3": 3, "E4": 4, "E5": 4,
            "E6": 3, "E7": 4, "E8": 4, "E9": 4, "E10": 4,
            "B1": 2, "B2": 3, "B3": 2, "B4": 4, "B5": 2,
            "B6": 4, "B7": 3, "B8": 3, "B9": 4, "B10": 2,
        }
    )
    result = score_v2(answers)
    assert result["sections"]["competence"]["sum"] == 41
    assert result["sections"]["competence"]["mean"] == 4.1
    assert result["sections"]["competence"]["level"] == "High"
    assert result["sections"]["psychological_capital"]["sum"] == 41
    assert result["sections"]["psychological_capital"]["mean"] == 4.1
    assert result["sections"]["self_perceived_employability"]["sum"] == 38
    assert result["sections"]["self_perceived_employability"]["mean"] == pytest.approx(3.8)
    assert result["sections"]["self_perceived_employability"]["level"] == "High"
    assert result["sections"]["behavioral"]["sum"] == 29
    assert result["sections"]["behavioral"]["mean"] == pytest.approx(2.9)
    assert result["sections"]["behavioral"]["level"] == "Moderate"
    assert result["sections"]["behavioral"]["context_flag"] is False
    assert result["aggregate_total"] is None
    assert 4.1 + 4.1 + 3.8 + 2.9 != result.get("total")


@pytest.mark.parametrize(
    ("mean_fill", "expected"),
    [
        (1, "Very Low"),
        (2, "Low"),
        (3, "Moderate"),
        (4, "High"),
        (5, "Very High"),
    ],
)
def test_integer_means_map_to_descriptive_bands(mean_fill, expected):
    result = score_v2(_complete_answers(mean_fill))
    assert result["sections"]["academic"]["mean"] == float(mean_fill)
    assert result["sections"]["academic"]["level"] == expected


def test_unrounded_cutoffs():
    answers = _complete_answers(2)
    # Nine 2s and one 1 → mean 1.9, just above 1.80 → Low
    for code in _section_codes("academic")[:9]:
        answers[code] = 2
    answers["A10"] = 1
    low = score_v2(answers)
    assert low["sections"]["academic"]["mean"] == pytest.approx(1.9)
    assert low["sections"]["academic"]["level"] == "Low"

    # Ten 1s and enough 5s to sit on 1.80: nine 1s + one 9 would be invalid.
    # Nine 2s and one -2 invalid; use nine 1s + one 9 no.
    # 8*1 + 2*5 = 18 → 1.80 Very Low.
    academic = list(_section_codes("academic"))
    for code in academic[:8]:
        answers[code] = 1
    answers[academic[8]] = 5
    answers[academic[9]] = 5
    on_cut = score_v2(answers)
    assert on_cut["sections"]["academic"]["mean"] == pytest.approx(1.8)
    assert on_cut["sections"]["academic"]["level"] == "Very Low"

    # 7*1 + 3*5 = 22 → 2.20 Low; 4*1 + 6*4 = 28 → 2.80 Moderate.
    for code in academic[:4]:
        answers[code] = 1
    for code in academic[4:]:
        answers[code] = 4
    moderate = score_v2(answers)
    assert moderate["sections"]["academic"]["mean"] == pytest.approx(2.8)
    assert moderate["sections"]["academic"]["level"] == "Moderate"


def test_exact_band_edges_use_unrounded_mean():
    answers = _complete_answers(3)
    academic = list(_section_codes("academic"))
    socioeconomic = list(_section_codes("socioeconomic"))
    behavioral = list(_section_codes("behavioral"))
    competence = list(_section_codes("competence"))

    # 1.80: 8 ones + 2 fives = 18
    for code in academic[:8]:
        answers[code] = 1
    answers[academic[8]] = 5
    answers[academic[9]] = 5
    # 2.60: 6 ones + 4 fives = 26
    for code in socioeconomic[:6]:
        answers[code] = 1
    for code in socioeconomic[6:]:
        answers[code] = 5
    # 3.40: 6 threes + 4 fours = 34
    for code in behavioral[:6]:
        answers[code] = 3
    for code in behavioral[6:]:
        answers[code] = 4
    # 4.20: 8 fours + 2 fives = 42
    for code in competence[:8]:
        answers[code] = 4
    answers[competence[8]] = 5
    answers[competence[9]] = 5

    result = score_v2(answers)
    assert result["sections"]["academic"]["mean"] == pytest.approx(1.8)
    assert result["sections"]["academic"]["level"] == "Very Low"
    assert result["sections"]["socioeconomic"]["mean"] == pytest.approx(2.6)
    assert result["sections"]["socioeconomic"]["level"] == "Low"
    assert result["sections"]["behavioral"]["mean"] == pytest.approx(3.4)
    assert result["sections"]["behavioral"]["level"] == "Moderate"
    assert result["sections"]["competence"]["mean"] == pytest.approx(4.2)
    assert result["sections"]["competence"]["level"] == "High"


def test_values_just_above_cutoffs_move_to_the_next_band():
    answers = _complete_answers(1)
    # 1.81: 9 ones + one 8 invalid. 9*1 + 1*10 no.
    # 7 ones + 3 fives = 22 → 2.2 already tested.
    # One 2 and nine 1s = 1.1 still Very Low.
    # 19/10 = 1.9: nine 1s + one 10 invalid. nine 2s + one 1 = 19.
    academic = list(_section_codes("academic"))
    for code in academic[:9]:
        answers[code] = 2
    answers[academic[9]] = 1
    assert score_v2(answers)["sections"]["academic"]["level"] == "Low"

    socioeconomic = list(_section_codes("socioeconomic"))
    # 2.70: 3 ones + 7 fours = 31? 3+28 = 31 → 3.1. Use 7 twos + 3 fives = 14+15 = 29 → 2.9.
    for code in socioeconomic[:7]:
        answers[code] = 2
    for code in socioeconomic[7:]:
        answers[code] = 5
    assert score_v2(answers)["sections"]["socioeconomic"]["level"] == "Moderate"

    competence = list(_section_codes("competence"))
    # 4.30: 7 fours + 3 fives = 43
    for code in competence[:7]:
        answers[code] = 4
    for code in competence[7:]:
        answers[code] = 5
    result = score_v2(answers)
    assert result["sections"]["competence"]["mean"] == pytest.approx(4.3)
    assert result["sections"]["competence"]["level"] == "Very High"


def test_missing_item_is_not_imputed_as_neutral():
    answers = _complete_answers(5)
    del answers["A5"]
    result = score_v2(answers)
    academic = result["sections"]["academic"]
    assert academic["complete"] is False
    assert academic["mean"] is None
    assert academic["level"] is None
    assert academic["imputed"] is False
    assert academic["missing_codes"] == ["A5"]
    assert academic["status"] == "incomplete"
    assert result["sections"]["competence"]["complete"] is True
    assert result["complete_section_count"] == 5
    assert result["aggregate_total"] is None


def test_out_of_range_and_non_integer_responses_invalidate_the_section():
    answers = _complete_answers(4)
    answers["S1"] = 0
    answers["S2"] = 6
    answers["B1"] = 3.0
    answers["B2"] = "4"
    answers["C1"] = True
    answers["P1"] = None
    result = score_v2(answers)
    assert result["sections"]["socioeconomic"]["complete"] is False
    assert result["sections"]["behavioral"]["complete"] is False
    assert result["sections"]["competence"]["complete"] is False
    assert result["sections"]["psychological_capital"]["complete"] is False
    assert result["sections"]["academic"]["complete"] is True


def test_duplicate_codes_in_pair_list_are_rejected():
    pairs = [(code, 4) for code in ALL_ITEM_CODES] + [("A1", 5)]
    with pytest.raises(QuestionnaireScoringError, match="Duplicate"):
        score_v2(pairs)


def test_unknown_item_codes_are_rejected():
    answers = _complete_answers(4)
    answers["Z1"] = 4
    with pytest.raises(QuestionnaireScoringError, match="Unknown"):
        score_v2(answers)


def test_section_boundaries_do_not_mix_constructs():
    answers = {code: 1 for code in ALL_ITEM_CODES}
    for code in _section_codes("competence"):
        answers[code] = 5
    result = score_v2(answers)
    assert result["sections"]["academic"]["mean"] == 1.0
    assert result["sections"]["competence"]["mean"] == 5.0
    assert result["sections"]["psychological_capital"]["mean"] == 1.0
    assert "total" not in result
    assert result["aggregate_total"] is None


def test_positive_orientation_does_not_reverse_any_item():
    answers = _complete_answers(1)
    answers["B10"] = 5
    result = score_v2(answers)
    behavioral = result["sections"]["behavioral"]
    assert behavioral["reverse_scored"] is False
    assert behavioral["sum"] == 14
    assert behavioral["mean"] == pytest.approx(1.4)


def test_asb_context_flag_at_or_below_2_60_and_not_a_dropout_score():
    answers = _complete_answers(4)
    for code in _section_codes("academic"):
        answers[code] = 2
    for code in _section_codes("socioeconomic"):
        answers[code] = 1
    result = score_v2(answers)
    assert result["sections"]["academic"]["context_flag"] is True
    assert result["sections"]["socioeconomic"]["context_flag"] is True
    assert "Not a trained dropout" in result["sections"]["academic"]["context_flag_note"]
    assert result["sections"]["competence"]["context_flag"] is False
    assert result["sections"]["behavioral"]["context_flag"] is False
    assert result["sections"]["behavioral"]["engagement_note"]


def test_readiness_index_is_display_only():
    result = score_v2(_complete_answers(3))
    assert result["sections"]["competence"]["readiness_index"] == 50.0
    result_high = score_v2(_complete_answers(5))
    assert result_high["sections"]["competence"]["readiness_index"] == 100.0
