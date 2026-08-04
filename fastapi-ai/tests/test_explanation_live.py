from __future__ import annotations

import asyncio
import json
import os

import pytest

from app.core.config import get_settings
from app.llm.clients.ollama import OllamaHttpClient


@pytest.mark.live_ollama
@pytest.mark.skipif(
    os.getenv("AI_RUN_LIVE_OLLAMA_GENERATION_TEST", "").lower() != "true",
    reason="Set AI_RUN_LIVE_OLLAMA_GENERATION_TEST=true for the optional live check.",
)
def test_private_local_ollama_can_generate_one_schema_bounded_json_object() -> None:
    settings = get_settings()
    schema = {
        "type": "object",
        "properties": {"status": {"type": "string", "enum": ["ok"]}},
        "required": ["status"],
        "additionalProperties": False,
    }
    raw = asyncio.run(
        OllamaHttpClient(settings).generate(
            [
                {
                    "role": "system",
                    "content": "Return exactly one JSON object matching the supplied schema.",
                },
                {
                    "role": "user",
                    "content": 'Return {"status":"ok"} and nothing else.',
                },
            ],
            schema,
        )
    )

    assert json.loads(raw) == {"status": "ok"}
