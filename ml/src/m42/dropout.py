"""Source-native UCI three-class dropout experiments for M42.

Grade columns are excluded. A, S, and B questionnaire means are not used.
UCC inference remains blocked while unit definitions are unverified.
"""

from __future__ import annotations

from src.data.dropout import REDUCED_PREDICTORS, TARGET_COLUMN
from src.features.catalog import GRADE_COLUMNS_BLOCKED
from src.m42.common import M42_N_REPEATS, M42_N_SPLITS, M42_SEED, M42_TEST_SIZE
from src.m42.compatibility import FIRST_SEM_UNITS, TWO_SEM_UNITS
from src.training.dropout import _evaluate_feature_set
from src.questionnaire.v2_instrument import ALL_ITEM_CODES

FEATURE_SETS = (
    {
        "id": "uci-scholarship-only-m42",
        "label": "Scholarship holder only",
        "columns": tuple(REDUCED_PREDICTORS),
        "timing": "after_published_profile",
        "deployment_status": "pending_mapping",
        "note": (
            "Source-native 0/1 scholarship flag. EduPredict category collapse and "
            "missing-profile coercion are not verified. Weak three-class signal."
        ),
    },
    {
        "id": "uci-first-sem-units-m42",
        "label": "First-semester enrolled and approved units (grades excluded)",
        "columns": FIRST_SEM_UNITS,
        "timing": "after_first_semester",
        "deployment_status": "pending_unit_definition",
        "note": (
            "Source-native unit counts only. UCC cumulative subject units have no "
            "verified first-semester index or approval definition."
        ),
    },
    {
        "id": "uci-two-sem-units-m42",
        "label": "First- and second-semester enrolled and approved units (grades excluded)",
        "columns": TWO_SEM_UNITS,
        "timing": "after_second_semester",
        "deployment_status": "pending_unit_definition",
        "note": (
            "Second-semester columns are unavailable at enrollment or after only the "
            "first semester. Using them earlier is temporal leakage."
        ),
    },
)


def _guard_columns(columns: tuple[str, ...]) -> None:
    blocked = [column for column in columns if column in GRADE_COLUMNS_BLOCKED]
    if blocked:
        raise ValueError(f"Grade columns are excluded from M42 reduced dropout sets: {blocked}.")
    questionnaire = [column for column in columns if column in ALL_ITEM_CODES or column in {"A", "S", "B"}]
    if questionnaire:
        raise ValueError(f"Questionnaire fields cannot enter UCI dropout models: {questionnaire}.")
    if TARGET_COLUMN in columns:
        raise ValueError("Target leaked into dropout predictors.")


def evaluate_dropout_sets(frame, *, seed: int = M42_SEED, n_repeats: int | None = None) -> dict:
    repeats = M42_N_REPEATS if n_repeats is None else n_repeats
    results = {}
    for spec in FEATURE_SETS:
        _guard_columns(spec["columns"])
        evaluated = _evaluate_feature_set(
            frame,
            spec["columns"],
            seed=seed,
            n_splits=M42_N_SPLITS,
            n_repeats=repeats,
            test_size=M42_TEST_SIZE,
            collect_details=True,
        )
        public = {key: value for key, value in evaluated.items() if key != "pipeline"}
        public.update(
            {
                "id": spec["id"],
                "label": spec["label"],
                "timing": spec["timing"],
                "deployment_status": spec["deployment_status"],
                "note": spec["note"],
                "pipeline": evaluated["pipeline"],
            }
        )
        results[spec["id"]] = public
    return results
