"""FastAPI research inference. Private, authenticated, and separate from student scoring."""

from __future__ import annotations

import hashlib
import json
import logging
import secrets
import time
from contextlib import asynccontextmanager

from fastapi import FastAPI, Header, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from starlette.exceptions import HTTPException as StarletteHTTPException

from api.boundaries import blocked_keys_in, schema_is_ucc
from api.errors import InferenceError, error_body
from api.inference import InferenceService, Runtime
from api.registry import Registry
from api.schemas import DropoutRequest, EmployabilityRequest
from api.settings import Settings, load_settings

logger = logging.getLogger("edupredict.ml")


class _RedactSecrets(logging.Filter):
    def filter(self, record: logging.LogRecord) -> bool:
        message = record.getMessage()
        if "Bearer " in message or "ML_API_TOKEN" in message or "local-dev-ml-token" in message:
            record.msg = "redacted log record"
            record.args = ()
        return True


def _configure_logging(level: str) -> None:
    logging.basicConfig(level=level, format="%(levelname)s %(name)s %(message)s")
    logger.setLevel(level)
    logger.addFilter(_RedactSecrets())


def create_app(settings: Settings | None = None) -> FastAPI:
    settings = settings or load_settings()
    _configure_logging(settings.log_level)
    registry = Registry(settings)
    runtime = Runtime(settings)

    @asynccontextmanager
    async def lifespan(app: FastAPI):
        registry.load()
        yield
        runtime.shutdown()

    app = FastAPI(
        title="EduPredict research inference",
        docs_url=None,
        redoc_url=None,
        openapi_url=None,
        lifespan=lifespan,
    )
    app.state.settings = settings
    app.state.registry = registry
    app.state.runtime = runtime
    app.state.service = InferenceService(registry, runtime)

    @app.middleware("http")
    async def limit_and_log(request: Request, call_next):
        if request.method in {"POST", "PUT", "PATCH"}:
            declared = request.headers.get("content-length")
            if declared is not None:
                try:
                    size = int(declared)
                except ValueError:
                    return JSONResponse(
                        status_code=400,
                        content=error_body("invalid_input", "Request body size is invalid."),
                    )
                if size > settings.max_body_bytes:
                    return JSONResponse(
                        status_code=413,
                        content=error_body("payload_too_large", "Request body is too large."),
                    )
        started = time.perf_counter()
        response = await call_next(request)
        elapsed_ms = (time.perf_counter() - started) * 1000
        logger.info(
            "request method=%s path=%s status=%s duration_ms=%.1f",
            request.method,
            request.url.path,
            response.status_code,
            elapsed_ms,
        )
        response.headers["X-Research-Only"] = "true"
        return response

    @app.exception_handler(RequestValidationError)
    async def invalid_input(_request: Request, exc: RequestValidationError):
        payload = _validation_payload(exc)
        if schema_is_ucc(_schema_id(payload)) or blocked_keys_in(payload):
            return JSONResponse(
                status_code=422,
                content=error_body(
                    "ucc_input_rejected",
                    "UCC questionnaire scores, grades, and student fields are not inputs to the research models.",
                ),
            )
        messages = " ".join(str(item.get("msg", "")) for item in exc.errors())
        if "ucc_input_rejected" in messages:
            return JSONResponse(
                status_code=422,
                content=error_body(
                    "ucc_input_rejected",
                    "UCC questionnaire scores, grades, and student fields are not inputs to the research models.",
                ),
            )
        return JSONResponse(
            status_code=422,
            content=error_body("invalid_input", "The research payload does not match the source schema."),
        )

    @app.exception_handler(StarletteHTTPException)
    async def http_error(_request: Request, exc: StarletteHTTPException):
        if isinstance(exc.detail, dict) and "error" in exc.detail:
            return JSONResponse(status_code=exc.status_code, content=exc.detail)
        if exc.status_code == 404:
            return JSONResponse(
                status_code=404,
                content=error_body("not_found", "Unknown research endpoint."),
            )
        return JSONResponse(
            status_code=exc.status_code,
            content=error_body("request_rejected", "Request rejected."),
        )

    @app.exception_handler(Exception)
    async def unhandled(request: Request, exc: Exception):
        logger.error("unhandled type=%s path=%s", type(exc).__name__, request.url.path)
        return JSONResponse(
            status_code=500,
            content=error_body("inference_failed", "The research service could not complete the request."),
        )

    @app.get("/health/live")
    def live():
        return {"status": "live"}

    @app.get("/health/ready")
    def ready():
        body = {
            "status": "ready" if registry.ready else "not_ready",
            "research_only": True,
            "auth_configured": settings.auth_configured,
            "models": _model_index(registry),
            "unavailable": list(registry.problems),
        }
        return JSONResponse(status_code=200 if registry.ready else 503, content=body)

    @app.get("/v1/research/models")
    def models(authorization: str | None = Header(default=None)):
        _require_token(settings, authorization)
        if not registry.ready:
            return JSONResponse(
                status_code=503,
                content=error_body("models_unavailable", "Research models are not ready."),
            )
        return {"research_only": True, "models": _public_models(registry)}

    @app.post("/v1/research/employability")
    async def employability(request: Request, authorization: str | None = Header(default=None)):
        _require_token(settings, authorization)
        payload = await _json_payload(request, settings)
        rejected = _reject_incompatible_payload(payload)
        if rejected is not None:
            return rejected
        try:
            body = EmployabilityRequest.model_validate(payload)
        except ValidationError:
            return JSONResponse(
                status_code=422,
                content=error_body("invalid_input", "The research payload does not match the source schema."),
            )
        return _inference_response(app.state.service.score_employability, body)

    @app.post("/v1/research/dropout")
    async def dropout(request: Request, authorization: str | None = Header(default=None)):
        _require_token(settings, authorization)
        payload = await _json_payload(request, settings)
        rejected = _reject_incompatible_payload(payload)
        if rejected is not None:
            return rejected
        try:
            body = DropoutRequest.model_validate(payload)
        except ValidationError as exc:
            messages = " ".join(str(item.get("msg", "")) for item in exc.errors())
            if "ucc_input_rejected" in messages:
                return JSONResponse(
                    status_code=422,
                    content=error_body(
                        "ucc_input_rejected",
                        "UCC questionnaire scores, grades, and student fields are not inputs to the UCI research models.",
                    ),
                )
            return JSONResponse(
                status_code=422,
                content=error_body("invalid_input", "The research payload does not match the source schema."),
            )
        return _inference_response(app.state.service.score_dropout, body)

    return app


async def _json_payload(request: Request, settings: Settings):
    raw = await request.body()
    if len(raw) > settings.max_body_bytes:
        raise StarletteHTTPException(
            status_code=413,
            detail=error_body("payload_too_large", "Request body is too large."),
        )
    try:
        return json.loads(raw.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError):
        raise StarletteHTTPException(
            status_code=422,
            detail=error_body("invalid_input", "The research payload does not match the source schema."),
        ) from None


def _reject_incompatible_payload(payload):
    if schema_is_ucc(_schema_id(payload)) or blocked_keys_in(payload):
        return JSONResponse(
            status_code=422,
            content=error_body(
                "ucc_input_rejected",
                "UCC questionnaire scores, grades, and student fields are not inputs to the research models.",
            ),
        )
    return None


def _inference_response(function, body):
    try:
        return function(body)
    except InferenceError as exc:
        return JSONResponse(status_code=exc.status, content=error_body(exc.code, exc.message))


def _require_token(settings: Settings, authorization: str | None) -> None:
    if not settings.auth_configured or authorization is None or not authorization.startswith("Bearer "):
        raise StarletteHTTPException(status_code=401, detail=error_body(
            "unauthorized",
            "Research inference requires authentication.",
        ))
    provided = authorization[7:].strip()
    expected_digest = hashlib.sha256(settings.token.encode("utf-8")).digest()
    provided_digest = hashlib.sha256(provided.encode("utf-8")).digest()
    if not secrets.compare_digest(expected_digest, provided_digest):
        raise StarletteHTTPException(status_code=401, detail=error_body(
            "unauthorized",
            "Research inference requires authentication.",
        ))


def _model_index(registry: Registry) -> list[dict]:
    rows = []
    if registry.employability is not None:
        rows.append({
            "id": "employability",
            "model_version": registry.employability.version,
            "algorithm": "Linear Regression",
            "loaded": True,
        })
    for feature_set, loaded in registry.dropout.items():
        rows.append({
            "id": "dropout",
            "feature_set": feature_set,
            "model_version": loaded.version,
            "algorithm": "Logistic Regression",
            "loaded": True,
        })
    return rows


def _public_models(registry: Registry) -> list[dict]:
    rows = []
    if registry.employability is not None:
        rows.append(_public_model(registry.employability, kind="employability"))
    for loaded in registry.dropout.values():
        rows.append(_public_model(loaded, kind="dropout"))
    return rows


def _public_model(loaded, kind: str) -> dict:
    metadata = loaded.metadata
    payload = {
        "id": kind if kind == "employability" else "dropout",
        "research_only": True,
        "production_validated": False,
        "suitable_for_student_advice": False,
        "student_workflow": False,
        "model_version": loaded.version,
        "algorithm": metadata.get("algorithm"),
        "trained_at": metadata.get("trained_at"),
        "source_dataset": metadata.get("dataset_version"),
        "input_columns": loaded.columns,
        "risk_thresholds": None,
        "limitations": list(metadata.get("limitations") or []),
    }
    if kind == "dropout":
        payload["feature_set"] = loaded.key
        payload["role"] = metadata.get("role")
        payload["weak_compatibility_baseline"] = loaded.key == "scholarship_only"
        payload["positive_label"] = "Dropout"
        payload["negative_label"] = "Not Dropout"
    else:
        payload["schema_id"] = "pianda-v1"
        payload["target"] = metadata.get("target")
    return payload


def _validation_payload(exc: RequestValidationError):
    body = exc.body
    if isinstance(body, (bytes, bytearray)):
        try:
            return json.loads(body.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError):
            return None
    return body


def _schema_id(payload) -> object:
    if isinstance(payload, dict):
        return payload.get("schema_id")
    return None


app = create_app()


if __name__ == "__main__":
    import uvicorn

    settings = app.state.settings
    uvicorn.run(
        app,
        host=settings.host,
        port=settings.port,
        access_log=False,
        log_level=settings.log_level.lower(),
        timeout_keep_alive=5,
        limit_concurrency=8,
    )
