"""UCC-style fixtures must be rejected. Source-schema rows may pass."""

from __future__ import annotations

import pandas as pd
import pytest

from src.data.dropout import load_dropout
from src.data.employability import V1_PREDICTORS, load_employability, verify_construct_means
from src.m42.compatibility import (
    CompatibilityError,
    FIRST_SEM_UNITS,
    GRADE_COLUMNS,
    reject_ucc_probe,
    validate_source_frame,
)
from src.questionnaire.v2_instrument import ALL_ITEM_CODES


def test_source_pianda_means_pass_the_cp_contract():
    verified = verify_construct_means(load_employability())
    sample = verified["predictors"].head(5)
    out = validate_source_frame("pianda-cp-reduced-m42", sample)
    assert list(out.columns) == list(V1_PREDICTORS)


def test_ucc_item_codes_are_rejected_on_the_cp_contract():
    payload = {code: 4 for code in ALL_ITEM_CODES if code.startswith("C")}
    with pytest.raises(CompatibilityError, match="UCC inference is blocked"):
        reject_ucc_probe("pianda-cp-reduced-m42", payload)


def test_numeric_means_labeled_as_ucc_are_still_rejected():
    payload = {
        "CompetenceMean": 3.8,
        "PsyCapMean": 4.1,
        "input_origin": "ucc-panel-revised-60-v2",
    }
    with pytest.raises(CompatibilityError, match="UCC"):
        reject_ucc_probe("pianda-cp-reduced-m42", payload)


def test_e_and_evs_columns_cannot_be_added_to_cp_inputs():
    verified = verify_construct_means(load_employability())
    leaked = verified["predictors"].head(3).copy()
    leaked["E1"] = 4
    with pytest.raises(CompatibilityError):
        validate_source_frame("pianda-cp-reduced-m42", leaked)
    leaked2 = verified["predictors"].head(3).copy()
    leaked2["EVS1"] = 4
    with pytest.raises(ValueError, match="Extra"):
        validate_source_frame("pianda-cp-reduced-m42", leaked2)


def test_gwa_cannot_enter_a_uci_grade_column():
    frame = pd.DataFrame(
        {
            "Curricular units 1st sem (enrolled)": [6],
            "Curricular units 1st sem (approved)": [5],
            "Curricular units 1st sem (grade)": [1.75],
        }
    )
    with pytest.raises(CompatibilityError, match="grade"):
        validate_source_frame("uci-first-sem-units-m42", frame)
    assert "Curricular units 1st sem (grade)" in GRADE_COLUMNS


def test_second_semester_columns_are_extra_on_the_first_sem_contract():
    frame, _quirks = load_dropout()
    extra = frame.loc[
        :4,
        list(FIRST_SEM_UNITS) + ["Curricular units 2nd sem (enrolled)"],
    ]
    with pytest.raises(ValueError, match="Extra"):
        validate_source_frame("uci-first-sem-units-m42", extra)


def test_source_first_sem_units_pass():
    frame, _quirks = load_dropout()
    sample = frame.loc[:, list(FIRST_SEM_UNITS)].head(6)
    out = validate_source_frame("uci-first-sem-units-m42", sample)
    assert list(out.columns) == list(FIRST_SEM_UNITS)
