# FastAPI internal contract

Base URL during native Windows development:

```text
http://127.0.0.1:8001
```

Laravel sends:

```http
Authorization: Bearer <AI_INTERNAL_TOKEN>
Accept: application/json
User-Agent: SmartFactory-DSS/1.0
X-Request-ID: <Laravel-generated correlation ID>
```

## GET `/health/live`

Minimal unauthenticated liveness endpoint.

## GET `/health/ready`

Authenticated readiness endpoint used by Laravel.

## GET `/version`

Authenticated service-version metadata.

## POST `/internal/v1/ping`

Authenticated strict-schema foundation contract.

## POST `/internal/v1/contracts/analytics/validate`

Authenticated, stateless validation of the versioned analytics snapshot contract.

Additional required headers:

```http
Idempotency-Key: <metadata.snapshot_id UUID>
X-Analytics-Contract-Version: v1
```

The endpoint:

- validates strict Pydantic schemas;
- rejects unknown fields;
- requires at least one supported analytics section;
- verifies that every section uses the metadata timezone;
- verifies the idempotency key and version header;
- returns only a compact receipt;
- does not calculate KPIs;
- does not store the payload;
- does not query a database;
- does not log the payload.

Successful response:

```json
{
  "status": "accepted",
  "contract_name": "smartfactory.analytics.snapshot",
  "contract_version": "v1",
  "snapshot_id": "11111111-1111-4111-8111-111111111111",
  "accepted_sections": [
    "production_kpis"
  ],
  "received_at": "2026-08-02T21:45:00Z",
  "request_id": "..."
}
```

See `ANALYTICS_CONTRACT_V1.md` for the complete contract design.

## Standard error

```json
{
  "error": {
    "code": "validation_error",
    "message": "The request did not satisfy the API contract.",
    "request_id": "...",
    "details": []
  }
}
```

Error responses never contain tokens, request bodies, stack traces, or raw internal exception messages.
