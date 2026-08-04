from __future__ import annotations

from fastapi import APIRouter, Depends, Request, status

from app.core.errors import ApiError
from app.core.request_context import current_request_id
from app.core.security import require_internal_token
from app.llm.errors import (
    OllamaClientError,
    OllamaModelMissingError,
    OllamaProtocolError,
    OllamaRequestTooLargeError,
    OllamaResponseTooLargeError,
    OllamaTimeoutError,
    OllamaUnavailableError,
)
from app.llm.policies import ExplanationPolicyError
from app.llm.prompts import PromptConstructionError
from app.llm.rate_limit import ExplanationRateLimitError
from app.llm.service import (
    ExplanationOutputRejectedError,
    ExplanationRequestTooLargeError,
)
from app.schemas.explanation import (
    ExplanationContractRequest,
    ExplanationContractResponse,
)

router = APIRouter(
    prefix="/internal/v1/explanations",
    tags=["Internal guarded explanations"],
    dependencies=[Depends(require_internal_token)],
)


@router.post(
    "/generate",
    response_model=ExplanationContractResponse,
    status_code=status.HTTP_200_OK,
    summary="Generate one guarded explanation from verified structured facts",
)
async def generate_explanation(
    payload: ExplanationContractRequest,
    request: Request,
) -> ExplanationContractResponse:
    request_id = current_request_id()

    try:
        async with request.app.state.explanation_rate_limiter.admit():
            return await request.app.state.explanation_service.generate(
                payload,
                request_id=request_id,
            )
    except ExplanationRateLimitError as exception:
        raise ApiError(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            code="explanation_rate_limited",
            message="Explanation generation is temporarily rate limited.",
            request_id=request_id,
            headers={"Retry-After": str(exception.retry_after_seconds)},
        ) from exception
    except ExplanationRequestTooLargeError as exception:
        raise ApiError(
            status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
            code="explanation_request_too_large",
            message=exception.message,
            request_id=request_id,
        ) from exception
    except OllamaRequestTooLargeError as exception:
        raise ApiError(
            status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
            code=exception.code,
            message=exception.message,
            request_id=request_id,
        ) from exception
    except (OllamaTimeoutError, OllamaUnavailableError, OllamaModelMissingError) as exception:
        raise ApiError(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            code=exception.code,
            message=exception.message,
            request_id=request_id,
            headers={"Retry-After": "5"},
        ) from exception
    except (OllamaProtocolError, OllamaResponseTooLargeError) as exception:
        raise ApiError(
            status_code=status.HTTP_502_BAD_GATEWAY,
            code=exception.code,
            message=exception.message,
            request_id=request_id,
        ) from exception
    except ExplanationOutputRejectedError as exception:
        raise ApiError(
            status_code=status.HTTP_502_BAD_GATEWAY,
            code="explanation_output_rejected",
            message=exception.message,
            request_id=request_id,
            details=[
                {
                    "field": "model_output",
                    "type": exception.reason_code,
                    "message": (
                        "The final local-model response did not pass the guarded contract."
                    ),
                }
            ],
        ) from exception
    except (PromptConstructionError, ExplanationPolicyError) as exception:
        raise ApiError(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            code="explanation_source_rejected",
            message="The verified explanation facts could not be processed safely.",
            request_id=request_id,
        ) from exception
    except OllamaClientError as exception:
        raise ApiError(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            code=exception.code,
            message=exception.message,
            request_id=request_id,
        ) from exception
