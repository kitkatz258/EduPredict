"""Load the saved research artifacts. Startup never fits or rewrites them."""

from __future__ import annotations

import logging

import joblib

from api.errors import InferenceError
from api.settings import Settings
from api.support import SourceSupport, load_support

logger = logging.getLogger("edupredict.ml")

EMPLOYABILITY_FILE = "employability_candidate_v1.joblib"
DROPOUT_FILES = {
    "full": "dropout_binary_full_exploratory_m27.joblib",
    "after_first_semester": "dropout_binary_after_first_semester_m27.joblib",
    "at_enrollment": "dropout_binary_at_enrollment_m27.joblib",
    "scholarship_only": "dropout_binary_scholarship_baseline_m27.joblib",
}
EXPECTED_ROLES = {
    "full": "exploratory_reference",
    "after_first_semester": "timing_sensitivity",
    "at_enrollment": "timing_sensitivity",
    "scholarship_only": "weak_compatibility_baseline",
}
PIANDA_COLUMNS = ["CompetenceMean", "PsyCapMean"]


class LoadedModel:
    def __init__(self, key: str, path_name: str, pipeline, metadata: dict):
        self.key = key
        self.path_name = path_name
        self.pipeline = pipeline
        self.metadata = metadata
        self.columns = [str(column) for column in metadata["input_columns"]]

    @property
    def version(self) -> str:
        return str(self.metadata.get("model_version") or self.metadata["name"])


class Registry:
    def __init__(self, settings: Settings, support: SourceSupport | None = None):
        self.settings = settings
        self.support = support if support is not None else load_support()
        self.employability: LoadedModel | None = None
        self.dropout: dict[str, LoadedModel] = {}
        self.problems: list[str] = []

    def load(self) -> None:
        self.employability = None
        self.dropout = {}
        self.problems = []
        self._load_employability()
        for feature_set, filename in DROPOUT_FILES.items():
            self._load_dropout(feature_set, filename)

    @property
    def ready(self) -> bool:
        return (
            self.settings.auth_configured
            and self.employability is not None
            and set(self.dropout) == set(DROPOUT_FILES)
            and not self.problems
        )

    def _load_employability(self) -> None:
        path = self.settings.models_dir / EMPLOYABILITY_FILE
        artifact = _read_artifact(path, "employability_unreadable")
        if artifact is None:
            self.problems.append("employability_unreadable")
            return
        try:
            metadata = artifact["metadata"]
            pipeline = artifact["pipeline"]
            model = pipeline.named_steps.get("model")
            contract_ok = (
                metadata.get("algorithm") == "Linear Regression"
                and metadata.get("name") == "employability_candidate_v1"
                and list(metadata.get("input_columns") or []) == PIANDA_COLUMNS
                and metadata.get("production_validated") is False
                and metadata.get("risk_thresholds") is None
                and type(model).__name__ == "LinearRegression"
            )
        except Exception:
            contract_ok = False
        if not contract_ok:
            self.problems.append("employability_contract")
            logger.error("employability artifact failed the research contract")
            return
        self.employability = LoadedModel("employability", EMPLOYABILITY_FILE, pipeline, metadata)

    def _load_dropout(self, feature_set: str, filename: str) -> None:
        path = self.settings.models_dir / filename
        artifact = _read_artifact(path, "dropout_unreadable")
        if artifact is None:
            self.problems.append(f"dropout_unreadable:{feature_set}")
            return
        try:
            metadata = artifact["metadata"]
            pipeline = artifact["pipeline"]
            model = pipeline.named_steps.get("model")
            classes = [int(label) for label in getattr(model, "classes_", [])]
            target = metadata.get("target_definition") or {}
            columns = [str(column) for column in metadata.get("input_columns") or []]
            contract_ok = (
                metadata.get("algorithm") == "Logistic Regression"
                and metadata.get("feature_set") == feature_set
                and metadata.get("role") == EXPECTED_ROLES[feature_set]
                and metadata.get("production_validated") is False
                and metadata.get("deployment_candidate") is False
                and metadata.get("research_candidate") is True
                and metadata.get("risk_thresholds") is None
                and target.get("positive_class") == 1
                and target.get("positive_label") == "Dropout"
                and target.get("negative_label") == "Not Dropout"
                and type(model).__name__ == "LogisticRegression"
                and classes == [0, 1]
                and len(columns) > 0
            )
        except Exception:
            contract_ok = False
            columns = []
        if not contract_ok:
            self.problems.append(f"dropout_contract:{feature_set}")
            logger.error("dropout artifact failed the research contract feature_set=%s", feature_set)
            return
        try:
            for column in columns:
                self.support.classify(column)
        except InferenceError:
            self.problems.append(f"dropout_support:{feature_set}")
            logger.error("dropout artifact column has no source support feature_set=%s", feature_set)
            return
        self.dropout[feature_set] = LoadedModel(feature_set, filename, pipeline, metadata)


def _read_artifact(path, problem: str):
    if not path.is_file():
        logger.error("research artifact missing problem=%s", problem)
        return None
    try:
        artifact = joblib.load(path)
    except Exception:
        logger.error("research artifact unreadable problem=%s", problem)
        return None
    if not isinstance(artifact, dict) or "pipeline" not in artifact or "metadata" not in artifact:
        logger.error("research artifact shape rejected problem=%s", problem)
        return None
    return artifact
