"""Score saved research pipelines. This module does not fit models."""

from __future__ import annotations

import logging
import threading
from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError

import pandas as pd

from api.boundaries import blocked_keys_in
from api.errors import InferenceError
from api.registry import Registry
from api.schemas import DropoutRequest, DropoutResponse, EmployabilityRequest, EmployabilityResponse
from api.settings import Settings
from src.common import readiness_index
from src.data.dropout import NOT_DROPOUT_NOTE

logger = logging.getLogger("edupredict.ml")

DISCLAIMER = "SOURCE-DATA RESEARCH DEMO. This is not a prediction for a UCC student."
READINESS_NOTE = "The display readiness index rescales a 1–5 mean. It is not a probability of employment."
READINESS_UNDEFINED = (
    "The display readiness index is undefined because the prediction is outside the verified 1–5 range. "
    "It is not a probability of employment."
)
DECISION_NOTE = (
    "The predicted class comes from the estimator's predict method. "
    "For these models that is a 0.5 cut on the positive probability. "
    "It is not a Low, Moderate, or High threshold."
)
CLASS_NAMES = {0: "Not Dropout", 1: "Dropout"}


class Runtime:
    def __init__(self, settings: Settings):
        self.settings = settings
        self._executor = ThreadPoolExecutor(max_workers=1, thread_name_prefix="ml-inference")
        self._lock = threading.Lock()

    def call(self, function):
        def guarded():
            with self._lock:
                return function()

        future = self._executor.submit(guarded)
        try:
            return future.result(timeout=self.settings.inference_timeout_seconds)
        except InferenceError:
            raise
        except FuturesTimeoutError as exc:
            raise InferenceError(
                "inference_timeout",
                "Research inference exceeded the time limit.",
                504,
            ) from exc
        except Exception as exc:
            logger.error("inference failed type=%s", type(exc).__name__)
            raise InferenceError(
                "inference_failed",
                "The research service could not complete the request.",
                500,
            ) from exc

    def shutdown(self) -> None:
        self._executor.shutdown(wait=False, cancel_futures=True)


class InferenceService:
    def __init__(self, registry: Registry, runtime: Runtime):
        self.registry = registry
        self.runtime = runtime

    def score_employability(self, body: EmployabilityRequest) -> dict:
        self._require_ready()
        loaded = self.registry.employability
        if loaded is None:
            raise InferenceError("models_unavailable", "Research models are not ready.", 503)
        competence = float(body.CompetenceMean)
        psycap = float(body.PsyCapMean)

        def run() -> float:
            frame = pd.DataFrame([{"CompetenceMean": competence, "PsyCapMean": psycap}])
            try:
                return float(loaded.pipeline.predict(frame)[0])
            except InferenceError:
                raise
            except ValueError as exc:
                raise InferenceError(
                    "schema_mismatch",
                    "The research payload does not match the source schema.",
                    422,
                ) from exc

        predicted = float(self.runtime.call(run))
        try:
            readiness = float(readiness_index(predicted))
            note = READINESS_NOTE
        except ValueError:
            readiness = None
            note = READINESS_UNDEFINED
        return EmployabilityResponse(
            schema_id="pianda-v1",
            source_dataset=str(loaded.metadata.get("dataset_version")),
            model_version=loaded.version,
            algorithm="Linear Regression",
            trained_at=str(loaded.metadata.get("trained_at")),
            inputs={"CompetenceMean": competence, "PsyCapMean": psycap},
            predicted_employability_mean=predicted,
            display_readiness_index=readiness,
            readiness_note=note,
            disclaimer=DISCLAIMER,
        ).model_dump()

    def score_dropout(self, body: DropoutRequest) -> dict:
        self._require_ready()
        loaded = self.registry.dropout.get(body.feature_set)
        if loaded is None:
            raise InferenceError("schema_mismatch", "Unknown UCI research feature set.", 422)
        if blocked_keys_in(body.features):
            raise InferenceError(
                "ucc_input_rejected",
                "UCC questionnaire scores, grades, and student fields are not inputs to the UCI research models.",
                422,
            )
        columns = loaded.columns
        missing = [column for column in columns if column not in body.features]
        extra = [column for column in body.features if column not in columns]
        if missing or extra:
            raise InferenceError(
                "schema_mismatch",
                "Predictor schema mismatch for the requested UCI feature set.",
                422,
            )
        cleaned = {
            column: self.registry.support.coerce(column, body.features[column])
            for column in columns
        }

        def run() -> tuple[float, int]:
            frame = pd.DataFrame([cleaned], columns=columns)
            try:
                probability = _positive_probability(loaded.pipeline, frame)
                predicted = int(loaded.pipeline.predict(frame)[0])
            except InferenceError:
                raise
            except ValueError as exc:
                raise InferenceError(
                    "schema_mismatch",
                    "The research payload does not match the source schema.",
                    422,
                ) from exc
            return probability, predicted

        probability, predicted = self.runtime.call(run)
        if predicted not in CLASS_NAMES:
            raise InferenceError(
                "inference_failed",
                "The research service could not complete the request.",
                500,
            )
        return DropoutResponse(
            weak_compatibility_baseline=body.feature_set == "scholarship_only",
            schema_id="uci-697",
            source_dataset=str(loaded.metadata.get("dataset_version")),
            model_version=loaded.version,
            algorithm="Logistic Regression",
            feature_set=body.feature_set,
            role=str(loaded.metadata.get("role")),
            trained_at=str(loaded.metadata.get("trained_at")),
            predicted_dropout_probability=float(probability),
            predicted_class=predicted,
            predicted_class_name=CLASS_NAMES[predicted],
            decision_note=f"{DECISION_NOTE} {NOT_DROPOUT_NOTE}",
            timing_note=str(loaded.metadata.get("timing_note")),
            disclaimer=DISCLAIMER,
        ).model_dump()

    def _require_ready(self) -> None:
        if not self.registry.ready:
            raise InferenceError("models_unavailable", "Research models are not ready.", 503)


def _positive_probability(pipeline, frame) -> float:
    model = pipeline.named_steps["model"]
    classes = [int(label) for label in model.classes_]
    if classes != [0, 1]:
        raise InferenceError(
            "inference_failed",
            "The research service could not complete the request.",
            500,
        )
    probabilities = pipeline.predict_proba(frame)
    return float(probabilities[0, 1])
