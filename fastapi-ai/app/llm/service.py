from __future__ import annotations

import logging
from dataclasses import dataclass

from app.core.config import Settings
from app.llm.clients.ollama import OllamaClient
from app.llm.fallbacks import build_numeric_safe_fallback
from app.llm.output import (
    ExplanationOutputError,
    build_explanation_response,
    parse_guarded_output,
)
from app.llm.prompts import GuardedExplanationPrompt, build_guarded_prompt
from app.schemas.explanation import (
    ExplanationContractRequest,
    ExplanationContractResponse,
)

logger = logging.getLogger("smartfactory.explanations")


@dataclass(slots=True)
class ExplanationRequestTooLargeError(ValueError):
    message: str = "The explanation request exceeded the configured size limit."


@dataclass(slots=True)
class ExplanationOutputRejectedError(ValueError):
    reason_code: str = "strict_output_validation_failed"
    message: str = "The local model output was rejected by the explanation safety checks."


class ExplanationGenerationService:
    """Orchestrates guarded prompts, private Ollama, and strict output validation."""

    def __init__(self, client: OllamaClient, settings: Settings) -> None:
        self._client = client
        self._maximum_payload_bytes = settings.explanation_max_payload_bytes
        self._maximum_output_bytes = settings.explanation_max_output_bytes
        self._attempts = settings.explanation_generation_attempts

    async def generate(
        self,
        request: ExplanationContractRequest,
        *,
        request_id: str,
    ) -> ExplanationContractResponse:
        request_bytes = request.model_dump_json().encode("utf-8")
        if len(request_bytes) > self._maximum_payload_bytes:
            raise ExplanationRequestTooLargeError()

        prompt = build_guarded_prompt(request)
        last_output_error: ExplanationOutputError | None = None

        for attempt in range(self._attempts):
            raw_output = await self._client.generate(
                self._messages_for_attempt(prompt, attempt, last_output_error),
                prompt.response_schema,
            )
            try:
                narrative = parse_guarded_output(
                    request,
                    raw_output,
                    maximum_bytes=self._maximum_output_bytes,
                    enforce_server_metadata=True,
                )
            except ExplanationOutputError as exception:
                last_output_error = exception
                continue

            return build_explanation_response(
                request,
                narrative,
                request_id=request_id,
            )

        reason_code = (
            last_output_error.code
            if last_output_error is not None
            else "strict_output_validation_failed"
        )

        if reason_code == "unsupported_numeric_value":
            fallback = build_numeric_safe_fallback(request)
            logger.warning(
                "explanation_numeric_fallback_used",
                extra={
                    "request_id": request_id,
                    "explanation_type": request.facts.explanation_type.value,
                    "role": request.role.value,
                    "reason_code": reason_code,
                    "attempts": self._attempts,
                },
            )
            return build_explanation_response(
                request,
                fallback,
                request_id=request_id,
            )

        logger.warning(
            "explanation_output_rejected",
            extra={
                "request_id": request_id,
                "explanation_type": request.facts.explanation_type.value,
                "role": request.role.value,
                "reason_code": reason_code,
                "attempts": self._attempts,
            },
        )
        raise ExplanationOutputRejectedError(reason_code=reason_code) from last_output_error

    @staticmethod
    def _messages_for_attempt(
        prompt: GuardedExplanationPrompt,
        attempt: int,
        previous_error: ExplanationOutputError | None,
    ) -> list[dict[str, str]]:
        messages = prompt.to_ollama_messages()
        if attempt == 0:
            return messages

        safe_code = (
            previous_error.code if previous_error is not None else "strict_output_validation_failed"
        )
        numeric_correction = (
            " Do not include any numeric token in summary, observations, or human checks; "
            "the verified values remain visible separately."
            if safe_code == "unsupported_numeric_value"
            else ""
        )
        messages.append(
            {
                "role": "user",
                "content": (
                    "STRICT CORRECTION: the previous response was rejected by the local "
                    f"validator with code '{safe_code}'. Re-read the original verified input. "
                    "Return exactly one plain JSON object that satisfies every required field, "
                    "required limitation, required fact reference, and grounding rule."
                    f"{numeric_correction} Do not repeat or discuss the rejected response."
                ),
            }
        )
        return messages
