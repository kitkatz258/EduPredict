"""Structural UCC questionnaire boundaries. Item wording is not invented."""

from __future__ import annotations

from src.common import APPROVED_QUESTIONNAIRE_DIR

# Current locked PDFs for M42. Historic 36-item filenames are retained as
# aliases so older reports stay readable; they are no longer on disk.
EXPECTED_PDFS = (
    "SystemQuestions.pdf",
    "EduPredict_Scoring_and_Research_Basis_Updated.pdf",
)
LEGACY_PDFS = (
    "EduPredict_Questionnaire.pdf",
    "EduPredict_Scoring_and_Research_Basis.pdf",
)

# Section inventory for the current 60-item instrument. PDF presence does not
# authorize item pairing or use of these sections as UCI predictors.
SPEC_SECTIONS = (
    {
        "codes": "A1–A10",
        "section": "Academic Factors",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "S1–S10",
        "section": "Socioeconomic Factors",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "B1–B10",
        "section": "Behavioral/Engagement",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "C1–C10",
        "section": "Competence",
        "role": "Intended future employability predictor only after scoring and equivalence are verified.",
    },
    {
        "codes": "P1–P10",
        "section": "Psychological Capital",
        "role": "Intended future employability predictor only after scoring and equivalence are verified.",
    },
    {
        "codes": "E1–E10",
        "section": "Self-Perceived Employability",
        "role": "Descriptive comparison. Never a predictor.",
    },
)

CONTEXT_FIELDS_FORBIDDEN_IN_MODELS = tuple(
    [f"A{index}" for index in range(1, 11)]
    + [f"S{index}" for index in range(1, 11)]
    + [f"B{index}" for index in range(1, 11)]
    + [f"E{index}" for index in range(1, 11)]
    + [f"EVS{index}" for index in range(1, 13)]
    + ["AVERAGE (EVS)", "EmployabilityMean"]
)


def approved_pdf_status() -> dict:
    present = []
    missing = []
    repo_root = APPROVED_QUESTIONNAIRE_DIR.parents[3]
    for name in EXPECTED_PDFS:
        path = APPROVED_QUESTIONNAIRE_DIR / name
        relative = str(path.relative_to(repo_root))
        if path.is_file():
            present.append(relative)
        else:
            missing.append(relative)
    return {
        "directory": str(APPROVED_QUESTIONNAIRE_DIR),
        "directory_exists": APPROVED_QUESTIONNAIRE_DIR.is_dir(),
        "present": present,
        "missing": missing,
        "item_mapping_blocked": bool(missing),
        "section_means_blocked": bool(missing),
    }


def source_to_ucc_mapping() -> list[dict]:
    """Count-level discrepancies only. No item-to-item crosswalk is claimed."""
    status = approved_pdf_status()
    rows = [
        {
            "source": "Pianda C1–C10",
            "ucc": "Specified C1–C10",
            "status": "UNMATCHED",
            "evidence": "Item counts now both equal 10. No English sentence is identical. Measurement equivalence is not claimed.",
        },
        {
            "source": "Pianda PsyCap1–PsyCap14",
            "ucc": "Specified P1–P10",
            "status": "UNMATCHED",
            "evidence": "Item counts differ (14 vs 10). The current PDFs are on disk. No item was paired, and measurement equivalence is not claimed.",
        },
        {
            "source": "Pianda EVS1–EVS12",
            "ucc": "Specified E1–E10",
            "status": "UNMATCHED",
            "evidence": "Item counts differ (12 vs 10). E is not a predictor, and no crosswalk is claimed.",
        },
        {
            "source": "Pianda SIE1–SIE14",
            "ucc": "No SIE section in the six specified UCC sections",
            "status": "UNMATCHED",
            "evidence": "The M25 section list has no internship-experience block.",
        },
        {
            "source": "No Pianda employability-sheet counterpart",
            "ucc": "A1–A10, S1–S10, B1–B10",
            "status": "UNMATCHED",
            "evidence": "These sections are contextual. They are not inserted into UCI fields.",
        },
    ]
    for row in rows:
        row["pdfs_available"] = not status["item_mapping_blocked"]
    return rows
