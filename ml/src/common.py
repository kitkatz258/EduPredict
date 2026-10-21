"""Shared constants for the M25 research experiments."""

from __future__ import annotations

import hashlib
from datetime import datetime
from importlib.metadata import version
from pathlib import Path
from zoneinfo import ZoneInfo

RANDOM_SEED = 42
TEST_SIZE = 0.20
N_SPLITS = 5
N_REPEATS = 5
RMSE_SELECTION_MARGIN = 0.01
F1_SELECTION_MARGIN = 0.01

ML_ROOT = Path(__file__).resolve().parents[1]
RAW_EMPLOYABILITY = ML_ROOT / "data" / "raw" / "employability"
RAW_DROPOUT = ML_ROOT / "data" / "raw" / "dropout"
APPROVED_QUESTIONNAIRE_DIR = (
    ML_ROOT.parent / "src" / "cursor" / "reference" / "approved-questionnaire"
)
REPORTS_DIR = ML_ROOT / "reports"
FIGURES_DIR = REPORTS_DIR / "figures"
MODELS_DIR = ML_ROOT / "models"

EMPLOYABILITY_CANDIDATE = MODELS_DIR / "employability_candidate_v1.joblib"
DROPOUT_CANDIDATE = MODELS_DIR / "dropout_candidate_v1.joblib"

LIKERT_MIN = 1
LIKERT_MAX = 5
TARGET_LABELS = ("Dropout", "Enrolled", "Graduate")

LIBRARY_PACKAGES = (
    "numpy",
    "pandas",
    "scipy",
    "scikit-learn",
    "matplotlib",
    "joblib",
    "openpyxl",
)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def file_record(path: Path) -> dict:
    return {
        "path": str(path.relative_to(ML_ROOT.parent)),
        "bytes": path.stat().st_size,
        "sha256": sha256_file(path),
    }


def library_versions() -> dict[str, str]:
    return {name: version(name) for name in LIBRARY_PACKAGES}


def manila_now() -> str:
    return datetime.now(ZoneInfo("Asia/Manila")).isoformat(timespec="seconds")


def readiness_index(score: float) -> float:
    """Display rescale of a verified 1–5 employability mean. Not an employment probability."""
    value = float(score)
    if value < LIKERT_MIN or value > LIKERT_MAX:
        raise ValueError(
            f"Readiness index requires a native score in [{LIKERT_MIN}, {LIKERT_MAX}], got {value}."
        )
    return ((value - LIKERT_MIN) / (LIKERT_MAX - LIKERT_MIN)) * 100.0
