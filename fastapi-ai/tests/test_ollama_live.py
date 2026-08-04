from __future__ import annotations

import asyncio
import os

import pytest

from app.core.config import get_settings
from app.llm.clients.ollama import OllamaHttpClient
from app.llm.models import OllamaHealthStatus


@pytest.mark.live_ollama
@pytest.mark.skipif(
    os.getenv("AI_RUN_LIVE_OLLAMA_TEST", "").lower() != "true",
    reason="Set AI_RUN_LIVE_OLLAMA_TEST=true for the optional live check.",
)
def test_local_ollama_contains_the_configured_exact_model() -> None:
    settings = get_settings()
    snapshot = asyncio.run(OllamaHttpClient(settings).health())

    assert snapshot.status is OllamaHealthStatus.AVAILABLE
    assert snapshot.model == settings.ollama_model
