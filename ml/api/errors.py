"""Safe research-service errors. Responses never include payloads or traces."""

from __future__ import annotations


class InferenceError(Exception):
    def __init__(self, code: str, message: str, status: int):
        super().__init__(message)
        self.code = code
        self.message = message
        self.status = status


def error_body(code: str, message: str) -> dict:
    return {
        "error": code,
        "message": message,
        "research_only": True,
    }
