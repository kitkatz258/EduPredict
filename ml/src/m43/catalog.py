"""Explicit M42 research-artifact allowlist. Client paths are never accepted."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from src.m42.common import M42_ARTIFACTS
from src.m42.compatibility import (
    FIRST_SEM_UNITS,
    PIANDA_CP_COLUMNS,
    PIANDA_CP_INTERNSHIP_COLUMNS,
    SCHOLARSHIP_ONLY,
    TWO_SEM_UNITS,
)

M42_RESEARCH_DIR = M42_ARTIFACTS
SOURCE_CLASS_LABELS = ("Dropout", "Enrolled", "Graduate")
PIANDA_TARGET_SCALE = "1-5 Pianda EmployabilityMean (unweighted EVS1-EVS12 mean); not a hiring probability"
UCI_TARGET_SCALE = "UCI source labels Dropout / Enrolled / Graduate; not UCC dropout outcomes"

ROLE_SOURCE_EXPERIMENT = "deployment_feasible_source_experiment"
ROLE_SOURCE_ONLY = "source_only_exploratory"

ABSENT_OPTIONAL_FIELDS = (
    "model_version",
    "feature_set",
    "role",
    "contract_id",
    "value_rule",
    "timing",
    "comparison_only",
    "mapping_status",
    "deployment_status",
    "target_labels",
)

# Byte hashes recorded from the M42.1 artifacts. Tests re-check the files.
# Do not update these to make a rewritten joblib pass.
EXPECTED_SHA256 = {
    "employability_cp_reduced_m42.joblib": (
        "2b210d72cb271faa15539a229b30d5d0238b570878fcda4abdf11abf76c147be"
    ),
    "employability_cp_internship_expanded_m42.joblib": (
        "fa5f30191fe7bdbee7fe1e1db9429310010641aea27eb900c52588a95403df38"
    ),
    "uci_first_sem_units_m42.joblib": (
        "653c3a8ba1baed778ba383d8933d4bda057d897c775d4b1ca8060fc71769cf61"
    ),
    "uci_scholarship_only_m42.joblib": (
        "6e5e2fd7e2ac9b601fca2c827d27d00f4503b599c09d494968c31b8869535595"
    ),
    "uci_two_sem_units_m42.joblib": (
        "8566d0db02ef5337da1eb33a30d52f9f592f1137f72e3c02f831274a7a4ac2f5"
    ),
}

DISCLAIMER = (
    "SOURCE-DATA RESEARCH DEMO. This is not a prediction for a UCC student. "
    "M42 artifacts are source-trained research candidates. UCC inference is blocked."
)
PROVENANCE_NOTE = (
    "Accepting source-named numeric keys does not prove the values came from Pianda "
    "or UCI records. Rejecting UCC-named keys does not prove the origin of remaining numbers."
)
PROBABILITY_NOTE = (
    "These are source-model uncalibrated scores/probabilities for the UCI three-class "
    "target. They are not validated UCC dropout probabilities and are not Low/Moderate/High labels."
)
READINESS_NOTE = (
    "The display readiness index rescales a native 1-5 Pianda EVS-mean prediction. "
    "It is not a probability of employment or a UCC outcome."
)


@dataclass(frozen=True)
class ArtifactSpec:
    model_id: str
    filename: str
    sha256: str
    expected_name: str
    expected_algorithm: str
    expected_model_type: str
    task: str
    source_dataset: str
    source_schema: str
    columns: tuple[str, ...]
    value_rule: str
    research_role: str
    prediction_timing: str
    class_labels: tuple[str, ...] | None
    target_scale: str
    expected_contract_id: str | None = None
    comparison_only: bool = False
    mapping_status: str | None = None
    deployment_status: str | None = None

    @property
    def path(self) -> Path:
        return M42_RESEARCH_DIR / self.filename


ALLOWLIST: dict[str, ArtifactSpec] = {
    "employability_cp_reduced_m42": ArtifactSpec(
        model_id="employability_cp_reduced_m42",
        filename="employability_cp_reduced_m42.joblib",
        sha256=EXPECTED_SHA256["employability_cp_reduced_m42.joblib"],
        expected_name="employability_cp_reduced_m42",
        expected_algorithm="LinearRegression",
        expected_model_type="LinearRegression",
        task="employability_regression",
        source_dataset="Mendeley 10.17632/hmn3b4c6c4.4",
        source_schema="pianda-cp-reduced-m42",
        columns=tuple(PIANDA_CP_COLUMNS),
        value_rule="likert_mean",
        research_role=ROLE_SOURCE_EXPERIMENT,
        prediction_timing="same_occasion_source_survey",
        class_labels=None,
        target_scale=PIANDA_TARGET_SCALE,
        expected_contract_id="pianda-cp-reduced-m42",
        mapping_status="pending",
    ),
    "employability_cp_internship_expanded_m42": ArtifactSpec(
        model_id="employability_cp_internship_expanded_m42",
        filename="employability_cp_internship_expanded_m42.joblib",
        sha256=EXPECTED_SHA256["employability_cp_internship_expanded_m42.joblib"],
        expected_name="employability_cp_internship_expanded_m42",
        expected_algorithm="LinearRegression",
        expected_model_type="LinearRegression",
        task="employability_regression",
        source_dataset="Mendeley 10.17632/hmn3b4c6c4.4",
        source_schema="pianda-cp-internship-expanded-m42",
        columns=tuple(PIANDA_CP_INTERNSHIP_COLUMNS),
        value_rule="likert_mean",
        research_role=ROLE_SOURCE_ONLY,
        prediction_timing="same_occasion_source_survey",
        class_labels=None,
        target_scale=PIANDA_TARGET_SCALE,
        expected_contract_id="pianda-cp-internship-expanded-m42",
        comparison_only=True,
        mapping_status="blocked",
    ),
    "uci_first_sem_units_m42": ArtifactSpec(
        model_id="uci_first_sem_units_m42",
        filename="uci_first_sem_units_m42.joblib",
        sha256=EXPECTED_SHA256["uci_first_sem_units_m42.joblib"],
        expected_name="uci_first_sem_units_m42",
        expected_algorithm="Logistic Regression",
        expected_model_type="LogisticRegression",
        task="dropout_classification",
        source_dataset="UCI id 697, DOI 10.24432/C5MC89",
        source_schema="uci-first-sem-units-m42",
        columns=FIRST_SEM_UNITS,
        value_rule="uci_count",
        research_role=ROLE_SOURCE_EXPERIMENT,
        prediction_timing="after_first_semester",
        class_labels=SOURCE_CLASS_LABELS,
        target_scale=UCI_TARGET_SCALE,
        deployment_status="pending_unit_definition",
    ),
    "uci_two_sem_units_m42": ArtifactSpec(
        model_id="uci_two_sem_units_m42",
        filename="uci_two_sem_units_m42.joblib",
        sha256=EXPECTED_SHA256["uci_two_sem_units_m42.joblib"],
        expected_name="uci_two_sem_units_m42",
        expected_algorithm="Random Forest Classifier",
        expected_model_type="RandomForestClassifier",
        task="dropout_classification",
        source_dataset="UCI id 697, DOI 10.24432/C5MC89",
        source_schema="uci-two-sem-units-m42",
        columns=TWO_SEM_UNITS,
        value_rule="uci_count",
        research_role=ROLE_SOURCE_ONLY,
        prediction_timing="after_second_semester",
        class_labels=SOURCE_CLASS_LABELS,
        target_scale=UCI_TARGET_SCALE,
        deployment_status="pending_unit_definition",
    ),
    "uci_scholarship_only_m42": ArtifactSpec(
        model_id="uci_scholarship_only_m42",
        filename="uci_scholarship_only_m42.joblib",
        sha256=EXPECTED_SHA256["uci_scholarship_only_m42.joblib"],
        expected_name="uci_scholarship_only_m42",
        expected_algorithm="Logistic Regression",
        expected_model_type="LogisticRegression",
        task="dropout_classification",
        source_dataset="UCI id 697, DOI 10.24432/C5MC89",
        source_schema="uci-scholarship-only-m42",
        columns=SCHOLARSHIP_ONLY,
        value_rule="binary01",
        research_role=ROLE_SOURCE_ONLY,
        prediction_timing="after_published_profile",
        class_labels=SOURCE_CLASS_LABELS,
        target_scale=UCI_TARGET_SCALE,
        deployment_status="pending_mapping",
    ),
}

MODEL_IDS = tuple(ALLOWLIST)


def resolve_spec(model_id: object) -> ArtifactSpec:
    if not isinstance(model_id, str) or model_id not in ALLOWLIST:
        raise KeyError(str(model_id))
    return ALLOWLIST[model_id]
