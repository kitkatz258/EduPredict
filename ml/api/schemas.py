"""Request and response contracts for source-schema research inference."""

from __future__ import annotations

import math
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, field_validator

from api.boundaries import blocked_keys_in


class EmployabilityRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    schema_id: Literal["pianda-v1"]
    CompetenceMean: float
    PsyCapMean: float

    @field_validator("CompetenceMean", "PsyCapMean", mode="before")
    @classmethod
    def likert_mean(cls, value: object) -> float:
        if isinstance(value, bool) or not isinstance(value, (int, float)):
            raise ValueError("Likert means must be numeric.")
        number = float(value)
        if not math.isfinite(number) or number < 1 or number > 5:
            raise ValueError("Likert means must stay inside [1, 5].")
        return number

class DropoutRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    schema_id: Literal["uci-697"]
    feature_set: Literal["full", "after_first_semester", "at_enrollment", "scholarship_only"]
    features: dict[str, Any]

    @field_validator("features", mode="before")
    @classmethod
    def features_are_an_object(cls, value: object) -> dict:
        if not isinstance(value, dict):
            raise ValueError("features must be an object of UCI source columns.")
        if blocked_keys_in(value):
            raise ValueError("ucc_input_rejected")
        return value


class EmployabilityResponse(BaseModel):
    model_config = ConfigDict(extra="forbid")

    research_only: Literal[True] = True
    production_validated: Literal[False] = False
    suitable_for_student_advice: Literal[False] = False
    student_workflow: Literal[False] = False
    schema_id: Literal["pianda-v1"]
    source_dataset: str
    model_version: str
    algorithm: Literal["Linear Regression"]
    trained_at: str
    inputs: dict[str, float]
    predicted_employability_mean: float
    display_readiness_index: float | None
    readiness_note: str
    risk_level: None = None
    risk_thresholds: None = None
    disclaimer: str


class DropoutResponse(BaseModel):
    model_config = ConfigDict(extra="forbid")

    research_only: Literal[True] = True
    production_validated: Literal[False] = False
    suitable_for_student_advice: Literal[False] = False
    student_workflow: Literal[False] = False
    weak_compatibility_baseline: bool
    schema_id: Literal["uci-697"]
    source_dataset: str
    model_version: str
    algorithm: Literal["Logistic Regression"]
    feature_set: Literal["full", "after_first_semester", "at_enrollment", "scholarship_only"]
    role: str
    trained_at: str
    predicted_dropout_probability: float
    predicted_class: Literal[0, 1]
    predicted_class_name: Literal["Dropout", "Not Dropout"]
    positive_label: Literal["Dropout"] = "Dropout"
    negative_label: Literal["Not Dropout"] = "Not Dropout"
    risk_level: None = None
    risk_thresholds: None = None
    decision_note: str
    timing_note: str
    disclaimer: str
