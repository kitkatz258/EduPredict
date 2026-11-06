"""Controlled failures for isolated M42 research inference. No payload logging."""

from __future__ import annotations


class ResearchInferenceError(Exception):
    """Visible research-loader failure. Never a silent fallback to heuristic or M25/M27."""

    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code
        self.message = message
