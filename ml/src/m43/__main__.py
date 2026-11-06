"""Offline M42 research CLI. Not a student scoring service and not the M33 API."""

from __future__ import annotations

import os

os.environ.setdefault("OMP_NUM_THREADS", "1")
os.environ.setdefault("OPENBLAS_NUM_THREADS", "1")
os.environ.setdefault("MKL_NUM_THREADS", "1")
os.environ.setdefault("NUMEXPR_NUM_THREADS", "1")

import argparse
import json
import sys

from src.m43.catalog import MODEL_IDS
from src.m43.errors import ResearchInferenceError
from src.m43.inference import list_models, score
from src.m43.registry import load_registry


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Score saved M42 research artifacts with source-native inputs. Not a UCC prediction."
    )
    subparsers = parser.add_subparsers(dest="command", required=True)
    subparsers.add_parser("list", help="Show allowlisted M42 research models.")
    subparsers.add_parser("checksums", help="Print expected and actual SHA-256 values.")
    score_parser = subparsers.add_parser("score", help="Score one source-native feature object.")
    score_parser.add_argument("--model", required=True, choices=MODEL_IDS)
    score_parser.add_argument(
        "--features",
        required=True,
        help="JSON object of source-native columns only. Paths and UCC keys are rejected.",
    )
    args = parser.parse_args(argv)
    try:
        registry = load_registry()
        if args.command == "list":
            _print(list_models(registry))
            return 0
        if args.command == "checksums":
            _print(registry.checksums())
            return 0
        features = _parse_features(args.features)
        _print(score(args.model, features, registry=registry))
        return 0
    except ResearchInferenceError as exc:
        _print({"error": exc.code, "message": exc.message, "research_only": True})
        return 2


def _parse_features(raw: str) -> dict:
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ResearchInferenceError("invalid_input", "Features must be a JSON object.") from exc
    if not isinstance(payload, dict):
        raise ResearchInferenceError("invalid_input", "Features must be a JSON object.")
    return payload


def _print(payload) -> None:
    json.dump(payload, sys.stdout, indent=2, sort_keys=True)
    sys.stdout.write("\n")


if __name__ == "__main__":
    raise SystemExit(main())
