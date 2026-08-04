from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Any

from pydantic import ValidationError

from app.llm.contracts import (
    ExplanationGroundingError,
    build_prompt_fact_bundle,
    validate_narrative_grounding,
)
from app.llm.policies import (
    ExplanationPolicyError,
    required_fact_keys,
    required_limitations,
    validate_narrative_policy,
)
from app.schemas.explanation import (
    ExplanationContractRequest,
    ExplanationContractResponse,
    ExplanationNarrative,
)

_MAX_OUTPUT_BYTES = 32_768


@dataclass(slots=True)
class ExplanationOutputError(ValueError):
    code: str
    message: str


class _DuplicateJsonKeyError(ValueError):
    pass


def parse_guarded_output(
    request: ExplanationContractRequest,
    raw_output: str | bytes,
    *,
    maximum_bytes: int = _MAX_OUTPUT_BYTES,
    enforce_server_metadata: bool = False,
) -> ExplanationNarrative:
    text = _bounded_text(raw_output, maximum_bytes=maximum_bytes)

    if "```" in text:
        raise ExplanationOutputError(
            code="markdown_not_allowed",
            message="The model output must be a plain JSON object without Markdown fences.",
        )

    try:
        payload = json.loads(text, object_pairs_hook=_unique_object)
    except _DuplicateJsonKeyError as exception:
        raise ExplanationOutputError(
            code="duplicate_json_key",
            message="The model output contains a duplicate JSON key.",
        ) from exception
    except json.JSONDecodeError as exception:
        raise ExplanationOutputError(
            code="invalid_json",
            message="The model output is not valid JSON.",
        ) from exception

    if not isinstance(payload, dict):
        raise ExplanationOutputError(
            code="invalid_json_shape",
            message="The model output must be exactly one JSON object.",
        )

    if enforce_server_metadata:
        payload = _with_server_owned_metadata(request, payload)

    try:
        narrative = ExplanationNarrative.model_validate(payload)
        validate_narrative_grounding(request, narrative)
        validate_narrative_policy(
            request,
            build_prompt_fact_bundle(request),
            narrative,
        )
    except ValidationError as exception:
        raise ExplanationOutputError(
            code="invalid_narrative_contract",
            message="The model output does not satisfy the strict narrative contract.",
        ) from exception
    except ExplanationGroundingError as exception:
        raise ExplanationOutputError(
            code="unsupported_fact_reference",
            message="The model output references a fact outside the strict allowlist.",
        ) from exception
    except ExplanationPolicyError as exception:
        raise ExplanationOutputError(
            code=exception.code,
            message=exception.message,
        ) from exception

    return narrative


def _with_server_owned_metadata(
    request: ExplanationContractRequest,
    payload: dict[str, Any],
) -> dict[str, Any]:
    """Restore deterministic metadata that must not depend on model copying."""

    normalized = dict(payload)
    normalized["limitations"] = list(required_limitations(request))

    references: list[str] = []
    raw_references = normalized.get("referenced_fact_keys")
    if isinstance(raw_references, list):
        for item in raw_references:
            if isinstance(item, str) and item not in references:
                references.append(item)

    for required_key in sorted(required_fact_keys(request.facts.explanation_type)):
        if required_key not in references:
            references.append(required_key)

    normalized["referenced_fact_keys"] = references
    return normalized


def build_explanation_response(
    request: ExplanationContractRequest,
    narrative: ExplanationNarrative,
    *,
    request_id: str,
) -> ExplanationContractResponse:
    validate_narrative_grounding(request, narrative)
    validate_narrative_policy(
        request,
        build_prompt_fact_bundle(request),
        narrative,
    )
    return ExplanationContractResponse(
        explanation_id=request.explanation_id,
        explanation_type=request.facts.explanation_type,
        role=request.role,
        language=request.language,
        narrative=narrative,
        request_id=request_id,
    )


def _bounded_text(raw_output: str | bytes, *, maximum_bytes: int) -> str:
    if maximum_bytes < 1_024 or maximum_bytes > 1_048_576:
        raise ExplanationOutputError(
            code="invalid_output_limit",
            message="The model-output size limit is outside the safe range.",
        )

    if isinstance(raw_output, bytes):
        encoded = raw_output
        try:
            text = raw_output.decode("utf-8")
        except UnicodeDecodeError as exception:
            raise ExplanationOutputError(
                code="invalid_utf8",
                message="The model output is not valid UTF-8 text.",
            ) from exception
    elif isinstance(raw_output, str):
        text = raw_output
        encoded = raw_output.encode("utf-8")
    else:
        raise ExplanationOutputError(
            code="invalid_output_type",
            message="The model output must be text or UTF-8 bytes.",
        )

    if len(encoded) > maximum_bytes:
        raise ExplanationOutputError(
            code="output_too_large",
            message="The model output exceeded the configured size limit.",
        )

    normalized = text.strip().lstrip("\ufeff").strip()
    if not normalized:
        raise ExplanationOutputError(
            code="empty_output",
            message="The model returned an empty explanation.",
        )
    return normalized


def _unique_object(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise _DuplicateJsonKeyError(key)
        result[key] = value
    return result
