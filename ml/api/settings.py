"""Host, port, token, and limits. Values come from the environment."""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

LOCAL_DEV_TOKEN = "local-dev-ml-token"
API_ROOT = Path(__file__).resolve().parent
ML_ROOT = API_ROOT.parent


@dataclass(frozen=True)
class Settings:
    token: str
    host: str
    port: int
    models_dir: Path
    inference_timeout_seconds: float
    max_body_bytes: int
    log_level: str
    environment: str

    @property
    def auth_configured(self) -> bool:
        if self.token == "":
            return False
        if self.environment == "production" and self.token == LOCAL_DEV_TOKEN:
            return False
        return True


def _port() -> int:
    raw = os.environ.get("PORT") or os.environ.get("ML_PORT") or "8000"
    try:
        port = int(raw)
    except ValueError:
        return 8000
    if port < 1 or port > 65535:
        return 8000
    return port


def _positive_float(name: str, default: float) -> float:
    raw = os.environ.get(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = float(raw)
    except ValueError:
        return default
    if value <= 0:
        return default
    return value


def load_settings() -> Settings:
    environment = os.environ.get("ML_ENV", "local").strip().lower() or "local"
    level = os.environ.get("ML_LOG_LEVEL", "INFO").strip().upper() or "INFO"
    if level not in {"DEBUG", "INFO", "WARNING", "ERROR"}:
        level = "INFO"
    if environment == "production" and level == "DEBUG":
        level = "INFO"
    try:
        max_body = int(os.environ.get("ML_MAX_BODY_BYTES", "65536"))
    except ValueError:
        max_body = 65536
    models_dir = Path(os.environ.get("ML_MODELS_DIR", str(ML_ROOT / "models"))).expanduser()
    return Settings(
        token=os.environ.get("ML_API_TOKEN", ""),
        host=os.environ.get("ML_HOST", "0.0.0.0").strip() or "0.0.0.0",
        port=_port(),
        models_dir=models_dir,
        inference_timeout_seconds=_positive_float("ML_INFERENCE_TIMEOUT_SECONDS", 2.0),
        max_body_bytes=max(1024, max_body),
        log_level=level,
        environment=environment,
    )
