"""Structural UCC questionnaire boundaries. Item wording is not invented."""

from __future__ import annotations

from src.common import APPROVED_QUESTIONNAIRE_DIR

EXPECTED_PDFS = (
    "EduPredict_Questionnaire.pdf",
    "EduPredict_Scoring_and_Research_Basis.pdf",
)

# Section inventory stated in the M25 specification. Item text was not readable
# because the approved PDFs are not in the workspace.
SPEC_SECTIONS = (
    {
        "codes": "A1–A6",
        "section": "Academic Factors",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "S1–S6",
        "section": "Socioeconomic Factors",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "B1–B6",
        "section": "Behavioral/Engagement",
        "role": "Contextual measure. Not a UCI dropout predictor and not an employability model input.",
    },
    {
        "codes": "C1–C8",
        "section": "Competence",
        "role": "Intended future employability predictor only after scoring and equivalence are verified.",
    },
    {
        "codes": "P1–P6",
        "section": "Psychological Capital",
        "role": "Intended future employability predictor only after scoring and equivalence are verified.",
    },
    {
        "codes": "E1–E4",
        "section": "Self-Perceived Employability",
        "role": "Descriptive comparison. Never a predictor.",
    },
)

CONTEXT_FIELDS_FORBIDDEN_IN_MODELS = tuple(
    [f"A{index}" for index in range(1, 7)]
    + [f"S{index}" for index in range(1, 7)]
    + [f"B{index}" for index in range(1, 7)]
    + [f"E{index}" for index in range(1, 5)]
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
            "ucc": "Specified C1–C8",
            "status": "UNMATCHED",
            "evidence": "Item counts differ (10 vs 8). PDF item text is unavailable, so no item was paired.",
        },
        {
            "source": "Pianda PsyCap1–PsyCap14",
            "ucc": "Specified P1–P6",
            "status": "UNMATCHED",
            "evidence": "Item counts differ (14 vs 6). PDF item text is unavailable, so no item was paired.",
        },
        {
            "source": "Pianda EVS1–EVS12",
            "ucc": "Specified E1–E4",
            "status": "UNMATCHED",
            "evidence": "Item counts differ (12 vs 4). E is not a predictor even if a future crosswalk exists.",
        },
        {
            "source": "Pianda SIE1–SIE14",
            "ucc": "No SIE section in the six specified UCC sections",
            "status": "UNMATCHED",
            "evidence": "The M25 section list has no internship-experience block.",
        },
        {
            "source": "No Pianda employability-sheet counterpart",
            "ucc": "A1–A6, S1–S6, B1–B6",
            "status": "UNMATCHED",
            "evidence": "These sections are contextual. They are not inserted into UCI fields.",
        },
    ]
    for row in rows:
        row["pdfs_available"] = not status["item_mapping_blocked"]
    return rows
