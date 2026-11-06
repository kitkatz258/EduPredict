"""Load M42 joblib files from an explicit allowlist. This is not the M33 registry."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import joblib

from src.common import sha256_file
from src.m43.catalog import (
    ABSENT_OPTIONAL_FIELDS,
    ALLOWLIST,
    ArtifactSpec,
    M42_RESEARCH_DIR,
    MODEL_IDS,
    resolve_spec,
)
from src.m43.errors import ResearchInferenceError

_ID_PATTERN_MESSAGE = (
    "Unknown M42 research model id. Only the explicit allowlist is accepted. "
    "Client-supplied paths are rejected."
)
_NO_FALLBACK = "M42 research loader does not fall back to heuristic, M25, or M27 artifacts."


@dataclass
class LoadedResearchModel:
    spec: ArtifactSpec
    path: Path
    sha256: str
    pipeline: object
    metadata: dict
    classes: tuple[str, ...] | None
    absent_optional_fields: tuple[str, ...]

    @property
    def model_id(self) -> str:
        return self.spec.model_id

    @property
    def version(self) -> str:
        metadata_version = self.metadata.get("model_version")
        if metadata_version:
            return str(metadata_version)
        return self.spec.expected_name


class M42ResearchRegistry:
    """Separate from api.registry.Registry. Reads ml/artifacts/m42 only."""

    def __init__(self, artifacts_dir: Path | None = None):
        self.artifacts_dir = Path(artifacts_dir) if artifacts_dir is not None else M42_RESEARCH_DIR
        self.models: dict[str, LoadedResearchModel] = {}
        self.problems: list[str] = []

    def load(self) -> None:
        self.models = {}
        self.problems = []
        if self.artifacts_dir.resolve() != M42_RESEARCH_DIR.resolve():
            raise ResearchInferenceError(
                "artifact_contract",
                "M42 research artifacts must load from ml/artifacts/m42. "
                "ML_MODELS_DIR and client paths are not used.",
            )
        for model_id in MODEL_IDS:
            try:
                self.models[model_id] = self._load_one(ALLOWLIST[model_id])
            except ResearchInferenceError as exc:
                self.problems.append(f"{model_id}:{exc.code}")
                raise

    @property
    def ready(self) -> bool:
        return set(self.models) == set(MODEL_IDS) and not self.problems

    def get(self, model_id: object) -> LoadedResearchModel:
        spec = self._spec(model_id)
        loaded = self.models.get(spec.model_id)
        if loaded is None:
            raise ResearchInferenceError("models_unavailable", f"M42 research model {spec.model_id} is not loaded. {_NO_FALLBACK}")
        current = sha256_file(loaded.path)
        if current != spec.sha256 or current != loaded.sha256:
            raise ResearchInferenceError(
                "artifact_checksum_mismatch",
                f"Checksum changed for {spec.filename}. Original artifacts must remain byte-for-byte.",
            )
        return loaded

    def checksums(self) -> dict[str, dict[str, str | int | bool]]:
        rows = {}
        for model_id, spec in ALLOWLIST.items():
            path = spec.path
            digest = sha256_file(path) if path.is_file() else ""
            rows[model_id] = {
                "filename": spec.filename,
                "expected_sha256": spec.sha256,
                "actual_sha256": digest,
                "bytes": path.stat().st_size if path.is_file() else 0,
                "matches": digest == spec.sha256,
            }
        return rows

    def _spec(self, model_id: object) -> ArtifactSpec:
        try:
            return resolve_spec(model_id)
        except KeyError as exc:
            raise ResearchInferenceError("unknown_model", _ID_PATTERN_MESSAGE) from exc

    def _load_one(self, spec: ArtifactSpec) -> LoadedResearchModel:
        path = spec.path
        try:
            resolved = path.resolve()
            allowed_root = M42_RESEARCH_DIR.resolve()
        except OSError as exc:
            raise ResearchInferenceError("models_unavailable", f"M42 artifact path is unreadable: {spec.filename}. {_NO_FALLBACK}") from exc
        if resolved.parent != allowed_root or resolved.name != spec.filename:
            raise ResearchInferenceError("artifact_contract", "Artifact path escaped the M42 allowlist directory.")
        if not path.is_file():
            raise ResearchInferenceError("models_unavailable", f"Missing M42 artifact {spec.filename}. {_NO_FALLBACK}")
        digest = sha256_file(path)
        if digest != spec.sha256:
            raise ResearchInferenceError(
                "artifact_checksum_mismatch",
                f"Checksum mismatch for {spec.filename}. The original M42 file was not loaded.",
            )
        try:
            artifact = joblib.load(path)
        except Exception as exc:
            raise ResearchInferenceError("models_unavailable", f"M42 artifact {spec.filename} is unreadable. {_NO_FALLBACK}") from exc
        if not isinstance(artifact, dict) or "pipeline" not in artifact or "metadata" not in artifact:
            raise ResearchInferenceError("artifact_contract", f"M42 artifact {spec.filename} is not a pipeline/metadata pair.")
        metadata = artifact["metadata"]
        pipeline = artifact["pipeline"]
        self._validate_metadata(spec, metadata, pipeline)
        model = pipeline.named_steps.get("model")
        classes = None
        if spec.class_labels is not None:
            classes = tuple(str(label) for label in getattr(model, "classes_", []))
            if set(classes) != set(spec.class_labels):
                raise ResearchInferenceError(
                    "artifact_contract",
                    f"Class labels for {spec.model_id} are {list(classes)}, expected {list(spec.class_labels)}.",
                )
        absent = tuple(field for field in ABSENT_OPTIONAL_FIELDS if field not in metadata or metadata.get(field) is None)
        return LoadedResearchModel(
            spec=spec,
            path=path,
            sha256=digest,
            pipeline=pipeline,
            metadata=metadata,
            classes=classes,
            absent_optional_fields=absent,
        )

    def _validate_metadata(self, spec: ArtifactSpec, metadata: dict, pipeline) -> None:
        model = pipeline.named_steps.get("model")
        try:
            columns = tuple(str(column) for column in metadata.get("input_columns") or [])
            contract_ok = (
                metadata.get("name") == spec.expected_name
                and metadata.get("algorithm") == spec.expected_algorithm
                and metadata.get("milestone") == "M42"
                and metadata.get("production_validated") is False
                and metadata.get("research_candidate") is True
                and metadata.get("deployment_candidate") is False
                and metadata.get("ucc_inference") == "blocked"
                and metadata.get("risk_thresholds") is None
                and metadata.get("dataset_version") == spec.source_dataset
                and columns == spec.columns
                and type(model).__name__ == spec.expected_model_type
            )
            if spec.expected_contract_id is not None:
                contract_ok = contract_ok and metadata.get("contract_id") == spec.expected_contract_id
            if spec.comparison_only:
                contract_ok = contract_ok and metadata.get("comparison_only") is True
            if spec.mapping_status is not None and "mapping_status" in metadata:
                contract_ok = contract_ok and metadata.get("mapping_status") == spec.mapping_status
            if spec.deployment_status is not None:
                contract_ok = contract_ok and metadata.get("deployment_status") == spec.deployment_status
            if spec.prediction_timing in {"after_first_semester", "after_second_semester", "after_published_profile"}:
                contract_ok = contract_ok and metadata.get("timing") == spec.prediction_timing
            if spec.class_labels is not None and metadata.get("target_labels"):
                contract_ok = contract_ok and tuple(metadata.get("target_labels")) == spec.class_labels
        except Exception as exc:
            raise ResearchInferenceError("artifact_contract", f"M42 artifact {spec.filename} failed the research contract.") from exc
        if not contract_ok:
            raise ResearchInferenceError("artifact_contract", f"M42 artifact {spec.filename} failed the research contract.")


def load_registry(artifacts_dir: Path | None = None) -> M42ResearchRegistry:
    registry = M42ResearchRegistry(artifacts_dir=artifacts_dir)
    registry.load()
    return registry
