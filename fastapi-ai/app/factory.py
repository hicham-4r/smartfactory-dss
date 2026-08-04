from __future__ import annotations

from fastapi import FastAPI

from app.api.router import api_router
from app.core.config import Settings, get_settings
from app.core.errors import install_exception_handlers
from app.core.logging import configure_logging
from app.inference.registry import ModelRegistryLoader
from app.inference.service import InferenceService
from app.llm.clients.ollama import DisabledOllamaClient, OllamaHttpClient
from app.llm.rate_limit import ExplanationRateLimiter
from app.llm.service import ExplanationGenerationService
from app.middleware.request_context import RequestContextMiddleware


def create_app(settings: Settings | None = None) -> FastAPI:
    resolved = settings or get_settings()
    configure_logging(resolved.log_level)

    app = FastAPI(
        title=resolved.app_name,
        summary="Internal AI-service boundary for the SmartFactory DSS prototype.",
        description=(
            "This API validates contracts, serves verified model inference, and produces "
            "guarded explanations through a private local Ollama dependency. It loads only "
            "verified model artifacts, has no database access, and never exposes Ollama "
            "directly to browsers."
        ),
        version=resolved.app_version,
        docs_url=resolved.docs_url,
        redoc_url=resolved.redoc_url,
        openapi_url=resolved.openapi_url,
        contact={"name": "SmartFactory DSS project"},
        license_info={"name": "Internal prototype"},
    )
    app.state.settings = resolved
    app.state.inference_service = InferenceService(ModelRegistryLoader(resolved.model_root))

    ollama_client = (
        OllamaHttpClient(resolved) if resolved.ollama_enabled else DisabledOllamaClient()
    )
    app.state.ollama_client = ollama_client
    app.state.explanation_service = ExplanationGenerationService(
        ollama_client,
        resolved,
    )
    app.state.explanation_rate_limiter = ExplanationRateLimiter(
        resolved.explanation_rate_limit_per_minute,
        resolved.explanation_max_concurrent_requests,
    )

    install_exception_handlers(app)
    app.include_router(api_router)
    app.add_middleware(RequestContextMiddleware, settings=resolved)

    return app
