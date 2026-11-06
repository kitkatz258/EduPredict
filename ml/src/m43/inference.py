"""Score saved M42 pipelines in an isolated research context. No student FeatureSet."""

from __future__ import annotations

from typing import Any, Mapping

import pandas as pd

from src.common import readiness_index
from src.m43.catalog import (
    ALLOWLIST,
    DISCLAIMER,
    MODEL_IDS,
    PROBABILITY_NOTE,
    PROVENANCE_NOTE,
    READINESS_NOTE,
    ROLE_SOURCE_ONLY,
)
from src.m43.errors import ResearchInferenceError
from src.m43.registry import M42ResearchRegistry, load_registry
from src.m43.validation import validate_features

_NO_FALLBACK = "M42 research loader does not fall back to heuristic, M25, or M27 artifacts."


def list_models(registry: M42ResearchRegistry | None = None) -> list[dict[str, Any]]:
    loaded = registry if registry is not None else load_registry()
    rows = []
    for model_id in MODEL_IDS:
        model = loaded.get(model_id)
        spec = model.spec
        rows.append(
            {
                "model_id": spec.model_id,
                "research_only": True,
                "production_validated": False,
                "suitable_for_student_advice": False,
                "student_workflow": False,
                "ucc_inference": "blocked",
                "source_dataset": spec.source_dataset,
                "source_schema": spec.source_schema,
                "task": spec.task,
                "algorithm": spec.expected_algorithm,
                "research_role": spec.research_role,
                "prediction_timing": spec.prediction_timing,
                "input_columns": list(spec.columns),
                "class_labels": list(spec.class_labels) if spec.class_labels else None,
                "target_scale": spec.target_scale,
                "trained_at": model.metadata.get("trained_at"),
                "absent_optional_fields": list(model.absent_optional_fields),
                "limitations": list(model.metadata.get("limitations") or []),
                "sha256": model.sha256,
            }
        )
    return rows


def score(
    model_id: str,
    features: Mapping[str, Any],
    registry: M42ResearchRegistry | None = None,
) -> dict[str, Any]:
    loaded_registry = registry if registry is not None else load_registry()
    model = loaded_registry.get(model_id)
    cleaned = validate_features(model.spec, features)
    frame = pd.DataFrame([cleaned], columns=list(model.spec.columns))
    try:
        predicted = model.pipeline.predict(frame)
    except ValueError as exc:
        raise ResearchInferenceError("schema_mismatch", "The research payload does not match the source schema.") from exc
    except Exception as exc:
        raise ResearchInferenceError("models_unavailable", f"M42 research scoring failed. {_NO_FALLBACK}") from exc
    body = _base_response(model)
    if model.spec.task == "employability_regression":
        native = float(predicted[0])
        try:
            display = float(readiness_index(native))
            note = READINESS_NOTE
        except ValueError:
            display = None
            note = (
                "The display readiness index is undefined because the prediction is outside "
                "the verified 1-5 range. It is not a probability of employment."
            )
        body.update(
            {
                "predicted_employability_mean": native,
                "display_readiness_index": display,
                "readiness_note": note,
                "risk_level": None,
                "risk_thresholds": None,
            }
        )
        return body
    labels = _class_labels(model)
    predicted_class = str(predicted[0])
    if predicted_class not in labels:
        raise ResearchInferenceError("artifact_contract", "Predicted class is not a source UCI label.")
    probabilities = _aligned_probabilities(model, frame, labels)
    body.update(
        {
            "predicted_class": predicted_class,
            "class_order": list(labels),
            "source_model_uncalibrated_probabilities": probabilities,
            "probability_note": PROBABILITY_NOTE,
            "risk_level": None,
            "risk_thresholds": None,
        }
    )
    return body


def _base_response(model) -> dict[str, Any]:
    spec = model.spec
    return {
        "research_only": True,
        "production_validated": False,
        "suitable_for_student_advice": False,
        "student_workflow": False,
        "ucc_inference": "blocked",
        "artifact_id": spec.model_id,
        "model_version": model.version,
        "source_dataset": spec.source_dataset,
        "source_schema": spec.source_schema,
        "task": spec.task,
        "algorithm": spec.expected_algorithm,
        "research_role": spec.research_role,
        "source_only_exploratory": spec.research_role == ROLE_SOURCE_ONLY,
        "prediction_timing": spec.prediction_timing,
        "target_scale": spec.target_scale,
        "trained_at": model.metadata.get("trained_at"),
        "input_columns": list(spec.columns),
        "absent_optional_fields": list(model.absent_optional_fields),
        "sha256": model.sha256,
        "input_provenance": "unverified",
        "provenance_note": PROVENANCE_NOTE,
        "limitations": list(model.metadata.get("limitations") or []),
        "disclaimer": DISCLAIMER,
    }


def _class_labels(model) -> tuple[str, ...]:
    if model.classes:
        return model.classes
    if model.spec.class_labels:
        return model.spec.class_labels
    raise ResearchInferenceError("artifact_contract", "Classifier is missing source class labels.")


def _aligned_probabilities(model, frame, labels: tuple[str, ...]) -> dict[str, float]:
    if not hasattr(model.pipeline, "predict_proba"):
        raise ResearchInferenceError("artifact_contract", "Classifier has no predict_proba method.")
    raw = model.pipeline.predict_proba(frame)[0]
    estimator = model.pipeline.named_steps["model"]
    actual = [str(label) for label in estimator.classes_]
    by_label = {label: float(raw[index]) for index, label in enumerate(actual)}
    return {label: by_label[label] for label in labels}


def public_allowlist() -> list[str]:
    return list(ALLOWLIST)
