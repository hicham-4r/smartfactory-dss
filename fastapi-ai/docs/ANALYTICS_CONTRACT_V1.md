# Analytics snapshot contract v1

## Purpose

`smartfactory.analytics.snapshot` is the explicit boundary between the Laravel DSS and the
FastAPI AI service.

Laravel remains responsible for:

- authorization;
- database access;
- KPI calculation;
- deterministic analytics;
- data sanitization;
- simulated-data labeling.

FastAPI performs only strict structural validation in Step 21B. It does not recalculate,
store, enrich, forecast, classify, or explain any value.

## Metadata

Every request contains:

```json
{
  "snapshot_id": "<UUID>",
  "contract_name": "smartfactory.analytics.snapshot",
  "contract_version": "v1",
  "source_application": "smartfactory-dss-laravel",
  "source_system": "simulated_sage",
  "data_classification": "simulated_prototype",
  "generated_at": "<timezone-aware ISO-8601 timestamp>",
  "timezone": "Africa/Casablanca"
}
```

The classification is deliberately mandatory. Data from the simulator must never be presented
as real company data.

## Supported sections

A snapshot contains at least one of:

- `production_kpis`;
- `production_breakdowns`;
- `maintenance_kpis`;
- `quality_kpis`.

The field names mirror the existing verified Laravel DTO `toArray()` outputs. Different
quantity units remain separated. Unknown fields are rejected.

## Transport and retry safety

The request uses:

```http
Idempotency-Key: <snapshot_id>
X-Analytics-Contract-Version: v1
```

The idempotency key must exactly match `metadata.snapshot_id`. Laravel may retry only the same
stateless validation request with the same snapshot identifier after transient 429, 502, 503,
or 504 responses.

## Privacy and logging

The contract contains aggregate analytics only. It does not contain:

- passwords or tokens;
- email addresses;
- user names;
- free-form operator comments;
- ERP raw payloads;
- database credentials;
- model artifacts.

FastAPI logs only request metadata such as request ID, method, path, status, and duration.
The analytics body is not logged or echoed in the response.

## Step 21B limitation

Acceptance means only that Laravel and FastAPI agree on the structure. It is not a forecast,
anomaly result, model evaluation, or industrial validation.
