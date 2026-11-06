"""Isolated M42 research inference. Not a UCC student predictor and not the M33 service."""

from src.m43.errors import ResearchInferenceError
from src.m43.inference import list_models, score
from src.m43.registry import M42ResearchRegistry, load_registry

__all__ = [
    "M42ResearchRegistry",
    "ResearchInferenceError",
    "list_models",
    "load_registry",
    "score",
]
