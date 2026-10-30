"""M42 paths and protocol constants. Existing M25/M27/M33 artifacts are not replaced."""

from __future__ import annotations

from pathlib import Path

from src.common import ML_ROOT, RANDOM_SEED, REPORTS_DIR

M42_ARTIFACTS = ML_ROOT / "artifacts" / "m42"
M42_REPORTS = REPORTS_DIR
M42_METRICS = M42_REPORTS / "m42_metrics.json"
M42_MODEL_CARDS = M42_ARTIFACTS / "model_cards"

CURRENT_QUESTIONNAIRE_PDF = "SystemQuestions.pdf"
CURRENT_SCORING_PDF = "EduPredict_Scoring_and_Research_Basis_Updated.pdf"

M42_SEED = RANDOM_SEED
M42_TEST_SIZE = 0.20
M42_N_SPLITS = 5
M42_N_REPEATS = 5

REPO_ROOT = ML_ROOT.parent


def m42_artifact(name: str) -> Path:
    return M42_ARTIFACTS / name
