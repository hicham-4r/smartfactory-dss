from __future__ import annotations

from datetime import UTC, datetime
from typing import Annotated
from uuid import UUID

from fastapi import APIRouter, Depends, Header, status

from app.core.errors import ApiError
from app.core.request_context import current_request_id
from app.core.security import require_internal_token
from app.schemas.analytics import (
    ANALYTICS_CONTRACT_NAME,
    ANALYTICS_CONTRACT_VERSION,
    AnalyticsContractAcceptedResponse,
    AnalyticsSnapshotContractRequest,
)

router = APIRouter(
    prefix="/internal/v1/contracts",
    tags=["Internal analytics contract"],
)


@router.post(
    "/analytics/validate",
    response_model=AnalyticsContractAcceptedResponse,
    dependencies=[Depends(require_internal_token)],
    status_code=status.HTTP_200_OK,
    summary="Validate the versioned Laravel analytics snapshot contract",
)
async def validate_analytics_contract(
    payload: AnalyticsSnapshotContractRequest,
    idempotency_key: Annotated[
        str,
        Header(
            alias="Idempotency-Key",
            min_length=36,
            max_length=36,
            description="Must equal metadata.snapshot_id.",
        ),
    ],
    contract_version: Annotated[
        str,
        Header(
            alias="X-Analytics-Contract-Version",
            min_length=2,
            max_length=20,
        ),
    ],
) -> AnalyticsContractAcceptedResponse:
    try:
        parsed_idempotency_key = UUID(idempotency_key)
    except ValueError as exception:
        raise ApiError(
            status_code=status.HTTP_409_CONFLICT,
            code="invalid_idempotency_key",
            message="The idempotency key is invalid.",
            request_id=current_request_id(),
        ) from exception

    if parsed_idempotency_key != payload.metadata.snapshot_id:
        raise ApiError(
            status_code=status.HTTP_409_CONFLICT,
            code="idempotency_key_mismatch",
            message="The idempotency key does not match the analytics snapshot.",
            request_id=current_request_id(),
        )

    if contract_version != payload.metadata.contract_version:
        raise ApiError(
            status_code=status.HTTP_409_CONFLICT,
            code="contract_version_mismatch",
            message="The analytics contract version header does not match the payload.",
            request_id=current_request_id(),
        )

    if (
        payload.metadata.contract_name != ANALYTICS_CONTRACT_NAME
        or payload.metadata.contract_version != ANALYTICS_CONTRACT_VERSION
    ):
        raise ApiError(
            status_code=status.HTTP_409_CONFLICT,
            code="unsupported_contract",
            message="The analytics contract is not supported by this service.",
            request_id=current_request_id(),
        )

    return AnalyticsContractAcceptedResponse(
        status="accepted",
        contract_name=ANALYTICS_CONTRACT_NAME,
        contract_version=ANALYTICS_CONTRACT_VERSION,
        snapshot_id=payload.metadata.snapshot_id,
        accepted_sections=payload.payload.section_names(),
        received_at=datetime.now(UTC),
        request_id=current_request_id(),
    )
